<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeachingClassSessionResource;
use App\Http\Resources\TeachingGroupResource;
use App\Http\Resources\TeachingGroupDetailResource;
use Illuminate\Support\Facades\Validator;
use IncadevUns\CoreDomain\Enums\GroupStatus;
use IncadevUns\CoreDomain\Models\ClassSession;
use IncadevUns\CoreDomain\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use IncadevUns\CoreDomain\Models\Module;

class TeachingGroupController extends Controller
{
    /**
     * Lista los grupos donde el usuario es profesor
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $user = $request->user();

        // Obtener grupos donde el usuario es profesor
        $groups = Group::with([
                'courseVersion.course',
                'teachers',
                'enrollments' => function($query) {
                    $query->where('payment_status', \IncadevUns\CoreDomain\Enums\PaymentStatus::Paid)
                          ->where('academic_status', \IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus::Active);
                }
            ])
            ->whereHas('teachers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orderBy('start_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => TeachingGroupResource::collection($groups->items()),
            'meta' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
                'from' => $groups->firstItem(),
                'to' => $groups->lastItem(),
            ]
        ]);
    }

    /**
     * Muestra el detalle completo de un grupo que dicta el profesor
     * Incluye: módulos, clases, materiales, exámenes, estudiantes, etc.
     * 
     * @param Request $request
     * @param int $group
     * @return JsonResponse
     */
    public function show(Request $request, int $group): JsonResponse
    {
        $user = $request->user();

        // Verificar que el grupo existe y que el usuario es profesor
        $group = Group::with(['courseVersion.course', 'teachers'])
            ->whereHas('teachers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->find($group);

        if (!$group) {
            return response()->json([
                'message' => 'Grupo no encontrado o no tienes permisos para acceder a él'
            ], 404);
        }

        // Obtener el course_version_id del grupo
        $courseVersionId = $group->course_version_id;

        // Cargar módulos del course_version con todas sus relaciones
        $modules = \IncadevUns\CoreDomain\Models\Module::where('course_version_id', $courseVersionId)
            ->with([
                // Cargar clases del grupo actual con materiales
                'classSessions' => function ($query) use ($group) {
                    $query->where('group_id', $group->id)
                        ->orderBy('start_time', 'asc')
                        ->with(['materials']);
                },
                // Cargar exámenes del grupo actual
                'exams' => function ($query) use ($group) {
                    $query->where('group_id', $group->id)
                        ->orderBy('start_time', 'asc');
                },
            ])
            ->orderBy('sort', 'asc')
            ->get();

        // Cargar estudiantes matriculados activos
        $students = $group->enrollments()
            ->with(['user'])
            ->where('payment_status', \IncadevUns\CoreDomain\Enums\PaymentStatus::Paid)
            ->where('academic_status', \IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus::Active)
            ->get()
            ->map(function ($enrollment) {
                return [
                    'enrollment_id' => $enrollment->id,
                    'user_id' => $enrollment->user->id,
                    'name' => $enrollment->user->name,
                    'email' => $enrollment->user->email,
                    'avatar' => $enrollment->user->avatar,
                ];
            });

        return response()->json([
            'data' => new TeachingGroupDetailResource([
                'group' => $group,
                'modules' => $modules,
                'students' => $students
            ])
        ]);
    }

    /**
     * Marcar un grupo como completado
     * 
     * @param Request $request
     * @param int $group
     * @return JsonResponse
     */
    public function markAsCompleted(Request $request, int $group): JsonResponse
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

            // Validar que el grupo puede ser marcado como completado
            if ($group->status === GroupStatus::Completed) {
                return response()->json([
                    'message' => 'El grupo ya está marcado como completado'
                ], 422);
            }

            if ($group->status !== GroupStatus::Active) {
                return response()->json([
                    'message' => 'Solo los grupos activos pueden ser marcados como completados'
                ], 422);
            }

            // Actualizar el estado del grupo
            $group->update([
                'status' => GroupStatus::Completed
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Grupo marcado como completado exitosamente',
                'data' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'status' => $group->status->value
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al marcar el grupo como completado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva clase en un módulo específico
     * 
     * @param Request $request
     * @param int $group
     * @param int $module
     * @return JsonResponse
     */
    public function createClass(Request $request, int $group, int $module): JsonResponse
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
                'meet_url' => 'nullable|url|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Crear la clase
            $classSession = ClassSession::create([
                'group_id' => $group->id,
                'module_id' => $module->id,
                'title' => $request->title,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'meet_url' => $request->meet_url,
            ]);

            // Cargar relaciones para la respuesta
            $classSession->load(['materials']);

            DB::commit();

            return response()->json([
                'message' => 'Clase creada exitosamente',
                'data' => new TeachingClassSessionResource($classSession)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al crear la clase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una clase existente
     * 
     * @param Request $request
     * @param int $class
     * @return JsonResponse
     */
    public function updateClass(Request $request, int $class): JsonResponse
    {
        $user = $request->user();

        DB::beginTransaction();

        try {
            // Verificar que la clase existe y que el usuario es profesor del grupo
            $classSession = ClassSession::with(['group.teachers'])
                ->whereHas('group.teachers', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->find($class);

            if (!$classSession) {
                return response()->json([
                    'message' => 'Clase no encontrada o no tienes permisos para modificarla'
                ], 404);
            }

            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'start_time' => 'sometimes|required|date',
                'end_time' => 'sometimes|required|date|after:start_time',
                'meet_url' => 'nullable|url|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que no se esté actualizando una clase pasada
            if ($classSession->start_time->isPast() && $request->hasAny(['start_time', 'end_time'])) {
                return response()->json([
                    'message' => 'No se pueden modificar las fechas de clases que ya han ocurrido'
                ], 422);
            }

            // Actualizar la clase
            $classSession->update($request->only(['title', 'start_time', 'end_time', 'meet_url']));

            // Cargar relaciones para la respuesta
            $classSession->load(['materials']);

            DB::commit();

            return response()->json([
                'message' => 'Clase actualizada exitosamente',
                'data' => new TeachingClassSessionResource($classSession)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al actualizar la clase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una clase
     * 
     * @param Request $request
     * @param int $class
     * @return JsonResponse
     */
    public function deleteClass(Request $request, int $class): JsonResponse
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
                    'message' => 'Clase no encontrada o no tienes permisos para eliminarla'
                ], 404);
            }

            // Verificar que la clase no tenga asistencias registradas
            if ($classSession->attendances->count() > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar una clase que ya tiene asistencias registradas'
                ], 422);
            }

            // Verificar que la clase no haya ocurrido aún
            if ($classSession->start_time->isPast()) {
                return response()->json([
                    'message' => 'No se puede eliminar una clase que ya ha ocurrido'
                ], 422);
            }

            // Eliminar materiales primero (si existen)
            $classSession->materials()->delete();

            // Eliminar la clase
            $classSession->delete();

            DB::commit();

            return response()->json([
                'message' => 'Clase eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al eliminar la clase: ' . $e->getMessage()
            ], 500);
        }
    }
}