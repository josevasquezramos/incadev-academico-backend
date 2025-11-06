<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeachingExamResource;
use App\Http\Resources\ExamDetailResource;
use App\Http\Resources\GradeResource;
use IncadevUns\CoreDomain\Models\Group;
use IncadevUns\CoreDomain\Models\Module;
use IncadevUns\CoreDomain\Models\Exam;
use IncadevUns\CoreDomain\Models\Grade;
use IncadevUns\CoreDomain\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExamController extends Controller
{
    /**
     * Listar todos los exámenes de un grupo
     * 
     * @param Request $request
     * @param int $group
     * @return JsonResponse
     */
    public function index(Request $request, int $group): JsonResponse
    {
        $user = $request->user();

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

        // Obtener exámenes del grupo con paginación
        $exams = Exam::with(['module', 'grades.enrollment.user'])
            ->where('group_id', $group->id)
            ->orderBy('start_time', 'asc');

        return response()->json([
            'data' => TeachingExamResource::collection($exams->get()),
        ]);
    }

    /**
     * Crear un nuevo examen en un módulo específico
     * 
     * @param Request $request
     * @param int $group
     * @param int $module
     * @return JsonResponse
     */
    public function createExam(Request $request, int $group, int $module): JsonResponse
    {
        $user = $request->user();

        DB::beginTransaction();

        try {
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

            // Verificar que el módulo existe y pertenece al course_version del grupo
            $module = Module::where('course_version_id', $group->course_version_id)
                ->find($module);

            if (!$module) {
                return response()->json([
                    'message' => 'Módulo no encontrado o no pertenece a este grupo'
                ], 404);
            }

            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'start_time' => 'required|date|after:now',
                'end_time' => 'required|date|after:start_time',
                'exam_url' => 'nullable|url|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Crear el examen
            $exam = Exam::create([
                'group_id' => $group->id,
                'module_id' => $module->id,
                'title' => $request->title,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'exam_url' => $request->exam_url,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Examen creado exitosamente',
                'data' => new TeachingExamResource($exam)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al crear el examen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un examen existente
     * 
     * @param Request $request
     * @param int $exam
     * @return JsonResponse
     */
    public function updateExam(Request $request, int $exam): JsonResponse
    {
        $user = $request->user();

        DB::beginTransaction();

        try {
            // Verificar que el examen existe y que el usuario es profesor del grupo
            $exam = Exam::with(['group.teachers'])
                ->whereHas('group.teachers', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->find($exam);

            if (!$exam) {
                return response()->json([
                    'message' => 'Examen no encontrado o no tienes permisos para modificarlo'
                ], 404);
            }

            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'start_time' => 'sometimes|required|date',
                'end_time' => 'sometimes|required|date|after:start_time',
                'exam_url' => 'nullable|url|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que no se esté actualizando un examen pasado
            if ($exam->start_time->isPast() && $request->hasAny(['start_time', 'end_time'])) {
                return response()->json([
                    'message' => 'No se pueden modificar las fechas de exámenes que ya han ocurrido'
                ], 422);
            }

            // Actualizar el examen
            $exam->update($request->only(['title', 'start_time', 'end_time', 'exam_url']));

            DB::commit();

            return response()->json([
                'message' => 'Examen actualizado exitosamente',
                'data' => new TeachingExamResource($exam)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al actualizar el examen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un examen
     * 
     * @param Request $request
     * @param int $exam
     * @return JsonResponse
     */
    public function deleteExam(Request $request, int $exam): JsonResponse
    {
        $user = $request->user();

        DB::beginTransaction();

        try {
            // Verificar que el examen existe y que el usuario es profesor del grupo
            $exam = Exam::with(['group.teachers', 'grades'])
                ->whereHas('group.teachers', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->find($exam);

            if (!$exam) {
                return response()->json([
                    'message' => 'Examen no encontrado o no tienes permisos para eliminarlo'
                ], 404);
            }

            // Verificar que el examen no tenga notas registradas
            if ($exam->grades->count() > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar un examen que ya tiene notas registradas'
                ], 422);
            }

            // Verificar que el examen no haya ocurrido aún
            if ($exam->start_time->isPast()) {
                return response()->json([
                    'message' => 'No se puede eliminar un examen que ya ha ocurrido'
                ], 422);
            }

            // Eliminar el examen
            $exam->delete();

            DB::commit();

            return response()->json([
                'message' => 'Examen eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al eliminar el examen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el detalle completo de un examen con estudiantes y sus notas
     * 
     * @param Request $request
     * @param int $exam
     * @return JsonResponse
     */
    public function show(Request $request, int $exam): JsonResponse
    {
        $user = $request->user();

        // Verificar que el examen existe y que el usuario es profesor del grupo
        $exam = Exam::with([
                'group.teachers',
                'module',
                'grades.enrollment.user'
            ])
            ->whereHas('group.teachers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->find($exam);

        if (!$exam) {
            return response()->json([
                'message' => 'Examen no encontrado o no tienes permisos para acceder a él'
            ], 404);
        }

        // Obtener todos los estudiantes matriculados en el grupo
        $enrollments = Enrollment::with(['user'])
            ->where('group_id', $exam->group_id)
            ->where('payment_status', \IncadevUns\CoreDomain\Enums\PaymentStatus::Paid)
            ->where('academic_status', \IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus::Active)
            ->get();

        // Preparar datos de estudiantes con sus notas si existen
        $students = $enrollments->map(function ($enrollment) use ($exam) {
            $grade = $exam->grades->firstWhere('enrollment_id', $enrollment->id);
            
            return [
                'enrollment_id' => $enrollment->id,
                'user_id' => $enrollment->user->id,
                'name' => $enrollment->user->name,
                'email' => $enrollment->user->email,
                'avatar' => $enrollment->user->avatar,
                'grade' => $grade ? [
                    'id' => $grade->id,
                    'grade' => (float) $grade->grade,
                    'feedback' => $grade->feedback,
                    'created_at' => $grade->created_at->toISOString(),
                    'updated_at' => $grade->updated_at->toISOString(),
                ] : null
            ];
        });

        return response()->json([
            'data' => new ExamDetailResource([
                'exam' => $exam,
                'students' => $students
            ])
        ]);
    }

    /**
     * Registrar o actualizar notas para un examen (múltiples estudiantes)
     * 
     * @param Request $request
     * @param int $exam
     * @return JsonResponse
     */
    public function recordGrades(Request $request, int $exam): JsonResponse
    {
        $user = $request->user();

        DB::beginTransaction();

        try {
            // Verificar que el examen existe y que el usuario es profesor del grupo
            $exam = Exam::with(['group.teachers', 'grades'])
                ->whereHas('group.teachers', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->find($exam);

            if (!$exam) {
                return response()->json([
                    'message' => 'Examen no encontrado o no tienes permisos para acceder a él'
                ], 404);
            }

            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'grades' => 'required|array|min:1',
                'grades.*.enrollment_id' => 'required|exists:enrollments,id',
                'grades.*.grade' => 'required|numeric|min:0|max:20',
                'grades.*.feedback' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que todas las enrollment_ids pertenecen al grupo del examen
            $validEnrollmentIds = Enrollment::where('group_id', $exam->group_id)
                ->where('payment_status', \IncadevUns\CoreDomain\Enums\PaymentStatus::Paid)
                ->where('academic_status', \IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus::Active)
                ->pluck('id')
                ->toArray();

            $invalidEnrollments = array_diff(
                array_column($request->grades, 'enrollment_id'),
                $validEnrollmentIds
            );

            if (!empty($invalidEnrollments)) {
                return response()->json([
                    'message' => 'Algunas matrículas no pertenecen a este grupo o no están activas',
                    'invalid_enrollments' => array_values($invalidEnrollments)
                ], 422);
            }

            $processedGrades = [];

            // Procesar cada nota
            foreach ($request->grades as $gradeData) {
                $grade = Grade::updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'enrollment_id' => $gradeData['enrollment_id'],
                    ],
                    [
                        'grade' => $gradeData['grade'],
                        'feedback' => $gradeData['feedback'] ?? null,
                    ]
                );

                $processedGrades[] = $grade;
            }

            DB::commit();

            // Cargar relaciones para la respuesta
            $exam->load(['grades.enrollment.user']);

            return response()->json([
                'message' => 'Notas registradas exitosamente',
                'data' => [
                    'exam_id' => $exam->id,
                    'exam_title' => $exam->title,
                    'total_grades_processed' => count($processedGrades),
                    'grades' => GradeResource::collection($exam->grades)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al registrar las notas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una nota específica
     * 
     * @param Request $request
     * @param int $grade
     * @return JsonResponse
     */
    public function updateGrade(Request $request, int $grade): JsonResponse
    {
        $user = $request->user();

        DB::beginTransaction();

        try {
            // Verificar que la nota existe y que el usuario es profesor del grupo
            $grade = Grade::with(['exam.group.teachers'])
                ->whereHas('exam.group.teachers', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->find($grade);

            if (!$grade) {
                return response()->json([
                    'message' => 'Nota no encontrada o no tienes permisos para modificarla'
                ], 404);
            }

            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'grade' => 'required|numeric|min:0|max:20',
                'feedback' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Actualizar la nota
            $grade->update([
                'grade' => $request->grade,
                'feedback' => $request->feedback,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Nota actualizada exitosamente',
                'data' => new GradeResource($grade)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al actualizar la nota: ' . $e->getMessage()
            ], 500);
        }
    }
}