<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaterialResource;
use App\Http\Resources\TeachingClassSessionResource;
use App\Http\Resources\TeachingGroupResource;
use App\Http\Resources\TeachingGroupDetailResource;
use App\Services\GroupCompletionService;
use Illuminate\Support\Facades\Validator;
use IncadevUns\CoreDomain\Enums\GroupStatus;
use IncadevUns\CoreDomain\Enums\MediaType;
use IncadevUns\CoreDomain\Enums\PaymentStatus;
use IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus;
use IncadevUns\CoreDomain\Models\ClassSession;
use IncadevUns\CoreDomain\Models\ClassSessionMaterial;
use IncadevUns\CoreDomain\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use IncadevUns\CoreDomain\Models\Module;

class TeachingGroupController extends Controller
{
    protected $completionService;

    public function __construct(GroupCompletionService $completionService)
    {
        $this->completionService = $completionService;
    }

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
                    $query->where('payment_status', PaymentStatus::Paid)
                          ->where('academic_status', EnrollmentAcademicStatus::Active);
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
        $modules = Module::where('course_version_id', $courseVersionId)
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
            ->where('payment_status', PaymentStatus::Paid)
            ->where('academic_status', EnrollmentAcademicStatus::Active)
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
     * Verificar si un grupo puede ser completado
     * 
     * @param Request $request
     * @param int $group
     * @return JsonResponse
     */
    public function canCompleteGroup(Request $request, int $group): JsonResponse
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

        $validation = $this->completionService->canCompleteGroup($group);

        return response()->json([
            'data' => $validation
        ]);
    }

    /**
     * Marcar un grupo como completado y generar resultados/certificados
     * 
     * @param Request $request
     * @param int $group
     * @return JsonResponse
     */
    public function markAsCompleted(Request $request, int $group): JsonResponse
    {
        $user = $request->user();

        try {
            // Verificar que el grupo existe y que el usuario es profesor
            $group = Group::with(['courseVersion.course', 'classSessions', 'exams'])
                ->whereHas('teachers', function ($query) use ($user) {
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

            // Verificar requisitos previos
            $canComplete = $this->completionService->canCompleteGroup($group);
            if (!$canComplete['can_complete']) {
                return response()->json([
                    'message' => 'No se puede completar el grupo. Verifica que tenga estudiantes y clases.',
                    'validation' => $canComplete
                ], 422);
            }

            // Completar el grupo y generar resultados
            $completionResult = $this->completionService->completeGroup($group);

            return response()->json([
                'message' => 'Grupo marcado como completado exitosamente. Resultados y certificados generados.',
                'data' => [
                    'group' => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'status' => $group->status->value
                    ],
                    'completion_summary' => [
                        'total_students' => $completionResult['total_students'],
                        'certificates_generated' => $completionResult['certificates_generated'],
                        'academic_settings_used' => $completionResult['academic_settings_used'],
                    ],
                    'student_results' => $completionResult['results']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al completar el grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar todas las clases de un grupo específico
     * 
     * @param Request $request
     * @param int $group
     * @return JsonResponse
     */
    public function listClasses(Request $request, int $group): JsonResponse
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

        // Obtener clases del grupo con paginación
        $classes = ClassSession::with(['module', 'materials'])
            ->where('group_id', $group->id)
            ->orderBy('start_time', 'asc');

        return response()->json([
            'data' => TeachingClassSessionResource::collection($classes->get()),
            
        ]);
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

    /**
     * Listar todos los materiales de una clase específica
     * 
     * @param Request $request
     * @param int $class
     * @return JsonResponse
     */
    public function listMaterials(Request $request, int $class): JsonResponse
    {
        $user = $request->user();

        // Verificar que la clase existe y que el usuario es profesor del grupo
        $classSession = ClassSession::with(['group.teachers'])
            ->whereHas('group.teachers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->find($class);

        if (!$classSession) {
            return response()->json([
                'message' => 'Clase no encontrada o no tienes permisos para acceder a ella'
            ], 404);
        }

        // Obtener materiales de la clase con paginación
        $materials = ClassSessionMaterial::where('class_session_id', $classSession->id)
            ->orderBy('created_at', 'desc');

        return response()->json([
            'data' => MaterialResource::collection($materials->get()),
            'class_info' => [
                'id' => $classSession->id,
                'title' => $classSession->title,
                'start_time' => $classSession->start_time->toISOString(),
            ]
        ]);
    }

    /**
     * Crear un nuevo material para una clase
     * 
     * @param Request $request
     * @param int $class
     * @return JsonResponse
     */
    public function createMaterial(Request $request, int $class): JsonResponse
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
                    'message' => 'Clase no encontrada o no tienes permisos para acceder a ella'
                ], 404);
            }

            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'type' => 'required|string|in:' . implode(',', MediaType::values()),
                'material_url' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Crear el material
            $material = ClassSessionMaterial::create([
                'class_session_id' => $classSession->id,
                'type' => $request->type,
                'material_url' => $request->material_url,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Material creado exitosamente',
                'data' => new MaterialResource($material)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al crear el material: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un material existente
     * 
     * @param Request $request
     * @param int $material
     * @return JsonResponse
     */
    public function updateMaterial(Request $request, int $material): JsonResponse
    {
        $user = $request->user();

        DB::beginTransaction();

        try {
            // Verificar que el material existe y que el usuario es profesor del grupo
            $material = ClassSessionMaterial::with(['classSession.group.teachers'])
                ->whereHas('classSession.group.teachers', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->find($material);

            if (!$material) {
                return response()->json([
                    'message' => 'Material no encontrado o no tienes permisos para modificarlo'
                ], 404);
            }

            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'type' => 'sometimes|required|string|in:' . implode(',', MediaType::values()),
                'material_url' => 'sometimes|required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Actualizar el material
            $material->update($request->only(['type', 'material_url']));

            DB::commit();

            return response()->json([
                'message' => 'Material actualizado exitosamente',
                'data' => new MaterialResource($material)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al actualizar el material: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un material
     * 
     * @param Request $request
     * @param int $material
     * @return JsonResponse
     */
    public function deleteMaterial(Request $request, int $material): JsonResponse
    {
        $user = $request->user();

        DB::beginTransaction();

        try {
            // Verificar que el material existe y que el usuario es profesor del grupo
            $material = ClassSessionMaterial::with(['classSession.group.teachers'])
                ->whereHas('classSession.group.teachers', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->find($material);

            if (!$material) {
                return response()->json([
                    'message' => 'Material no encontrado o no tienes permisos para eliminarlo'
                ], 404);
            }

            // Verificar si la clase ya ha ocurrido (opcional - depende de tus reglas de negocio)
            if ($material->classSession->start_time->isPast()) {
                // Puedes decidir si permitir eliminar materiales de clases pasadas
                // return response()->json([
                //     'message' => 'No se puede eliminar material de una clase que ya ha ocurrido'
                // ], 422);
            }

            // Eliminar el material
            $material->delete();

            DB::commit();

            return response()->json([
                'message' => 'Material eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al eliminar el material: ' . $e->getMessage()
            ], 500);
        }
    }
}