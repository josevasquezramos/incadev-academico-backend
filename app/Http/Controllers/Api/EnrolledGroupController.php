<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrolledGroupResource;
use App\Http\Resources\GroupDetailResource;
use IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus;
use IncadevUns\CoreDomain\Enums\GroupStatus;
use IncadevUns\CoreDomain\Enums\PaymentStatus;
use IncadevUns\CoreDomain\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use IncadevUns\CoreDomain\Models\Module;

class EnrolledGroupController extends Controller
{
    /**
     * Lista los grupos donde el usuario está matriculado con estado activo
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $user = $request->user();

        // Obtener grupos donde está matriculado con pago confirmado y estado académico activo
        $groups = Group::with([
                'courseVersion.course',
                'teachers'
            ])
            ->whereHas('enrollments', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('payment_status', PaymentStatus::Paid)
                    ->where('academic_status', EnrollmentAcademicStatus::Active);
            })
            ->where('status', GroupStatus::Active)
            ->orderBy('start_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => EnrolledGroupResource::collection($groups->items()),
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
     * Muestra el detalle completo de un grupo donde el usuario está matriculado
     * Incluye: módulos, clases, materiales, exámenes, asistencias y notas
     * 
     * @param Request $request
     * @param int $groupId
     * @return JsonResponse
     */
    public function show(Request $request, int $groupId): JsonResponse
    {
        $user = $request->user();

        // Verificar que el grupo existe y cargar las relaciones básicas
        $group = Group::with(['courseVersion.course', 'teachers'])->find($groupId);

        if (!$group) {
            return response()->json([
                'message' => 'Grupo no encontrado'
            ], 404);
        }

        // Verificar que el usuario tiene matrícula activa en este grupo
        $enrollment = $group->enrollments()
            ->where('user_id', $user->id)
            ->where('payment_status', PaymentStatus::Paid)
            ->where('academic_status', EnrollmentAcademicStatus::Active)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'message' => 'No tienes acceso a este grupo. Verifica que tu matrícula esté confirmada.'
            ], 403);
        }

        // Obtener el course_version_id del grupo
        $courseVersionId = $group->course_version_id;

        // Cargar módulos del course_version con todas sus relaciones
        $modules = Module::where('course_version_id', $courseVersionId)
            ->with([
                // Cargar clases del grupo actual con materiales
                'classSessions' => function ($query) use ($groupId) {
                    $query->where('group_id', $groupId)
                        ->orderBy('start_time', 'asc')
                        ->with(['materials']);
                },
                // Cargar asistencias del usuario actual
                'classSessions.attendances' => function ($query) use ($enrollment) {
                    $query->where('enrollment_id', $enrollment->id);
                },
                // Cargar exámenes del grupo actual
                'exams' => function ($query) use ($groupId) {
                    $query->where('group_id', $groupId)
                        ->orderBy('start_time', 'asc');
                },
                // Cargar notas del usuario actual
                'exams.grades' => function ($query) use ($enrollment) {
                    $query->where('enrollment_id', $enrollment->id);
                },
            ])
            ->orderBy('sort', 'asc')
            ->get();

        // Crear un array con todos los datos necesarios para el resource
        $groupData = [
            'group' => $group,
            'modules' => $modules,
            'enrollment' => $enrollment
        ];

        return response()->json([
            'data' => new GroupDetailResource($groupData)
        ]);
    }
}