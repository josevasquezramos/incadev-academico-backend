<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassSessionResource;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\AttendanceDetailResource;
use IncadevUns\CoreDomain\Models\Group;
use IncadevUns\CoreDomain\Models\ClassSession;
use IncadevUns\CoreDomain\Models\Attendance;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Enums\AttendanceStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    /**
     * Listar todas las clases de un grupo (para seleccionar y tomar asistencia)
     * 
     * @param Request $request
     * @param int $group
     * @return JsonResponse
     */
    public function index(Request $request, int $group): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->input('per_page', 10);

        // Verificar que el grupo existe y que el usuario es profesor
        $group = Group::whereHas('teachers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->find($group);

        if (!$group) {
            return response()->json([
                'message' => 'Grupo no encontrado o no tienes permisos para acceder a él'
            ], 404);
        }

        // Obtener clases del grupo con paginación
        $classes = ClassSession::with(['module', 'attendances.enrollment.user'])
            ->where('group_id', $group->id)
            ->orderBy('start_time', 'desc') // Más recientes primero
            ->paginate($perPage);

        return response()->json([
            'data' => ClassSessionResource::collection($classes->items()),
            'meta' => [
                'current_page' => $classes->currentPage(),
                'last_page' => $classes->lastPage(),
                'per_page' => $classes->perPage(),
                'total' => $classes->total(),
                'from' => $classes->firstItem(),
                'to' => $classes->lastItem(),
            ]
        ]);
    }

    /**
     * Obtener el detalle completo de una clase con estudiantes y sus asistencias
     * 
     * @param Request $request
     * @param int $class
     * @return JsonResponse
     */
    public function show(Request $request, int $class): JsonResponse
    {
        $user = $request->user();

        // Verificar que la clase existe y que el usuario es profesor del grupo
        $classSession = ClassSession::with([
                'group.teachers',
                'module',
                'attendances.enrollment.user'
            ])
            ->whereHas('group.teachers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->find($class);

        if (!$classSession) {
            return response()->json([
                'message' => 'Clase no encontrada o no tienes permisos para acceder a ella'
            ], 404);
        }

        // Obtener todos los estudiantes matriculados en el grupo
        $enrollments = Enrollment::with(['user'])
            ->where('group_id', $classSession->group_id)
            ->where('payment_status', \IncadevUns\CoreDomain\Enums\PaymentStatus::Paid)
            ->where('academic_status', \IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus::Active)
            ->get();

        // Preparar datos de estudiantes con sus asistencias si existen
        $students = $enrollments->map(function ($enrollment) use ($classSession) {
            $attendance = $classSession->attendances->firstWhere('enrollment_id', $enrollment->id);
            
            return [
                'enrollment_id' => $enrollment->id,
                'user_id' => $enrollment->user->id,
                'name' => $enrollment->user->name,
                'email' => $enrollment->user->email,
                'avatar' => $enrollment->user->avatar,
                'attendance' => $attendance ? [
                    'id' => $attendance->id,
                    'status' => $attendance->status->value,
                    'created_at' => $attendance->created_at->toISOString(),
                    'updated_at' => $attendance->updated_at->toISOString(),
                ] : null
            ];
        });

        return response()->json([
            'data' => new AttendanceDetailResource([
                'class_session' => $classSession,
                'students' => $students
            ])
        ]);
    }

    /**
     * Registrar o actualizar asistencias para una clase (múltiples estudiantes)
     * 
     * @param Request $request
     * @param int $class
     * @return JsonResponse
     */
    public function recordAttendances(Request $request, int $class): JsonResponse
    {
        $user = $request->user();

        DB::beginTransaction();

        try {
            // Verificar que la clase existe y que el usuario es profesor del grupo
            $classSession = ClassSession::with(['group.teachers', 'attendances'])
                ->whereHas('group.teachers', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->find($class);

            if (!$classSession) {
                return response()->json([
                    'message' => 'Clase no encontrada o no tienes permisos para acceder a ella'
                ], 404);
            }

            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'attendances' => 'required|array|min:1',
                'attendances.*.enrollment_id' => 'required|exists:enrollments,id',
                'attendances.*.status' => 'required|string|in:' . implode(',', AttendanceStatus::values()),
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que todas las enrollment_ids pertenecen al grupo de la clase
            $validEnrollmentIds = Enrollment::where('group_id', $classSession->group_id)
                ->where('payment_status', \IncadevUns\CoreDomain\Enums\PaymentStatus::Paid)
                ->where('academic_status', \IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus::Active)
                ->pluck('id')
                ->toArray();

            $invalidEnrollments = array_diff(
                array_column($request->attendances, 'enrollment_id'),
                $validEnrollmentIds
            );

            if (!empty($invalidEnrollments)) {
                return response()->json([
                    'message' => 'Algunas matrículas no pertenecen a este grupo o no están activas',
                    'invalid_enrollments' => array_values($invalidEnrollments)
                ], 422);
            }

            $processedAttendances = [];

            // Procesar cada asistencia
            foreach ($request->attendances as $attendanceData) {
                $attendance = Attendance::updateOrCreate(
                    [
                        'class_session_id' => $classSession->id,
                        'enrollment_id' => $attendanceData['enrollment_id'],
                    ],
                    [
                        'status' => $attendanceData['status'],
                    ]
                );

                $processedAttendances[] = $attendance;
            }

            DB::commit();

            // Cargar relaciones para la respuesta
            $classSession->load(['attendances.enrollment.user']);

            return response()->json([
                'message' => 'Asistencias registradas exitosamente',
                'data' => [
                    'class_session_id' => $classSession->id,
                    'class_title' => $classSession->title,
                    'total_attendances_processed' => count($processedAttendances),
                    'attendances' => AttendanceResource::collection($classSession->attendances)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al registrar las asistencias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una asistencia específica
     * 
     * @param Request $request
     * @param int $attendance
     * @return JsonResponse
     */
    public function updateAttendance(Request $request, int $attendance): JsonResponse
    {
        $user = $request->user();

        DB::beginTransaction();

        try {
            // Verificar que la asistencia existe y que el usuario es profesor del grupo
            $attendance = Attendance::with(['classSession.group.teachers'])
                ->whereHas('classSession.group.teachers', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->find($attendance);

            if (!$attendance) {
                return response()->json([
                    'message' => 'Asistencia no encontrada o no tienes permisos para modificarla'
                ], 404);
            }

            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:' . implode(',', AttendanceStatus::values()),
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Actualizar la asistencia
            $attendance->update([
                'status' => $request->status,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Asistencia actualizada exitosamente',
                'data' => new AttendanceResource($attendance)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al actualizar la asistencia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de asistencia de un grupo
     * 
     * @param Request $request
     * @param int $group
     * @return JsonResponse
     */
    public function getGroupStatistics(Request $request, int $group): JsonResponse
    {
        $user = $request->user();

        // Verificar que el grupo existe y que el usuario es profesor
        $group = Group::whereHas('teachers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['enrollments.user', 'classSessions.attendances'])
            ->find($group);

        if (!$group) {
            return response()->json([
                'message' => 'Grupo no encontrado o no tienes permisos para acceder a él'
            ], 404);
        }

        $totalClasses = $group->classSessions->count();
        $students = $group->enrollments->where('payment_status', \IncadevUns\CoreDomain\Enums\PaymentStatus::Paid)
            ->where('academic_status', \IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus::Active);

        $statistics = $students->map(function ($enrollment) use ($totalClasses, $group) {
            $presentCount = 0;
            $absentCount = 0;
            $lateCount = 0;
            $excusedCount = 0;

            foreach ($group->classSessions as $class) {
                $attendance = $class->attendances->firstWhere('enrollment_id', $enrollment->id);
                
                if ($attendance) {
                    switch ($attendance->status) {
                        case AttendanceStatus::Present:
                            $presentCount++;
                            break;
                        case AttendanceStatus::Absent:
                            $absentCount++;
                            break;
                        case AttendanceStatus::Late:
                            $lateCount++;
                            break;
                        case AttendanceStatus::Excused:
                            $excusedCount++;
                            break;
                    }
                } else {
                    // Si no hay registro, contar como ausente
                    $absentCount++;
                }
            }

            $attendancePercentage = $totalClasses > 0 ? 
                (($presentCount + $lateCount + $excusedCount) / $totalClasses) * 100 : 0;

            return [
                'enrollment_id' => $enrollment->id,
                'user_id' => $enrollment->user->id,
                'name' => $enrollment->user->name,
                'email' => $enrollment->user->email,
                'statistics' => [
                    'total_classes' => $totalClasses,
                    'present' => $presentCount,
                    'absent' => $absentCount,
                    'late' => $lateCount,
                    'excused' => $excusedCount,
                    'attendance_percentage' => round($attendancePercentage, 2),
                ]
            ];
        });

        return response()->json([
            'data' => [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'total_classes' => $totalClasses,
                'students_statistics' => $statistics
            ]
        ]);
    }
}