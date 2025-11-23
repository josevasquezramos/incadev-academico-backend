<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use IncadevUns\CoreDomain\Models\Course;
use IncadevUns\CoreDomain\Models\CourseVersion;

class MarketingController extends Controller
{
    /**
     * Obtener lista de estudiantes con su perfil, visible solo para marketing.
     */
    public function students(Request $request): JsonResponse
    {
        // 1. Verificación de Permisos (Solo rol 'marketing')
        // Aunque se puede hacer en middleware, aquí forzamos la verificación
        if (!$request->user()->hasRole('marketing')) {
            return response()->json(['message' => 'Forbidden. Marketing role required.'], 403);
        }

        // 2. Query: Usuarios con rol 'student' + relación 'studentProfile'
        $students = User::role('student')
            ->with('studentProfile') // Eager loading del perfil definido en tu trait
            ->paginate($request->get('per_page', 15)); // Por defecto 15, o lo que envíen por query string

        // 3. Construcción de la respuesta
        return response()->json([
            'data' => $students->items(),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    /**
     * Método para devolver la lista de cursos.
     */
    public function courses(Request $request): JsonResponse
    {
        // Verificación de Permisos
        if (!$request->user()->hasRole('marketing')) {
            return response()->json(['message' => 'Forbidden. Marketing role required.'], 403);
        }

        // Query: Listar cursos
        // Puedes agregar ->orderBy('created_at', 'desc') si deseas los más recientes primero
        $courses = Course::query()
            ->paginate($request->get('per_page', 15));

        return response()->json($courses);
    }

    /**
     * Método para devolver la lista de versiones, indicando el curso.
     */
    public function versions(Request $request): JsonResponse
    {
        // Verificación de Permisos
        if (!$request->user()->hasRole('marketing')) {
            return response()->json(['message' => 'Forbidden. Marketing role required.'], 403);
        }

        // Query: Listar versiones CON su curso padre
        // Asumimos que el modelo CourseVersion existe en el mismo namespace
        $versions = CourseVersion::query()
            ->with('course')
            ->paginate($request->get('per_page', 15));

        return response()->json($versions);
    }
}