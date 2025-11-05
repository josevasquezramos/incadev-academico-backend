<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AvailableGroupResource;
use IncadevUns\CoreDomain\Enums\GroupStatus;
use IncadevUns\CoreDomain\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AvailableGroupController extends Controller
{
    /**
     * Lista grupos disponibles para matrícula (status: enrolling)
     * donde el usuario autenticado NO está matriculado
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $user = $request->user();

        // Obtener IDs de grupos donde ya está matriculado
        $enrolledGroupIds = $user->enrollments()
            ->pluck('group_id')
            ->toArray();

        // Obtener grupos en estado 'enrolling' donde no está matriculado
        $groups = Group::with([
                'courseVersion.course',
                'teachers'
            ])
            ->where('status', GroupStatus::Enrolling)
            ->whereNotIn('id', $enrolledGroupIds)
            ->orderBy('start_date', 'asc')
            ->paginate($perPage);

        return response()->json([
            'data' => AvailableGroupResource::collection($groups->items()),
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
}