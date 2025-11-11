<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use IncadevUns\CoreDomain\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherStatisticsController extends Controller
{
    /**
     * Display a listing of the groups for a teacher.
     */
    public function index(Request $request, User $user): JsonResponse
    {
        // Verificar permisos
        $this->authorizeAccess();

        if (!$user->hasRole('teacher')) {
            return response()->json(['message' => 'The provided user is not a teacher'], 422);
        }

        $groups = $this->getTeacherGroups($user, $request);

        return response()->json([
            'data' => $this->formatGroupsData($groups->items()),
            'meta' => $this->getPaginationMeta($groups),
        ]);
    }

    /**
     * Display the specified group statistics.
     */
    public function show(Group $group): JsonResponse
    {
        // Verificar permisos
        $this->authorizeAccess();

        $group->load(['courseVersion.course', 'enrollments', 'classSessions.materials', 'exams']);

        return response()->json([
            'group' => $this->getBasicGroupInfo($group),
            'statistics' => $this->calculateGroupStatistics($group),
        ]);
    }

    /**
     * Authorize access for human resources role.
     */
    private function authorizeAccess(): void
    {
        if (!auth()->user()->hasRole('human_resources')) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Get teacher groups with filters.
     */
    private function getTeacherGroups(User $user, Request $request)
    {
        $query = $user->groupsAsTeacher()
            ->with(['courseVersion.course'])
            ->orderBy('start_date', 'desc');

        // Aplicar filtros de fecha
        $this->applyDateFilters($query, $request);

        return $query->paginate($request->input('per_page', 15));
    }

    /**
     * Apply date filters to query.
     */
    private function applyDateFilters($query, Request $request): void
    {
        $filters = [
            'start_date_from' => ['start_date', '>='],
            'start_date_to' => ['start_date', '<='],
            'end_date_from' => ['end_date', '>='],
            'end_date_to' => ['end_date', '<='],
        ];

        foreach ($filters as $param => [$field, $operator]) {
            if ($value = $request->input($param)) {
                $query->where($field, $operator, $value);
            }
        }
    }

    /**
     * Format groups data for response.
     */
    private function formatGroupsData(array $groups): array
    {
        return collect($groups)->map(function ($group) {
            return [
                'id' => (int) $group->id,
                'name' => $group->name,
                'start_date' => $group->start_date,
                'end_date' => $group->end_date,
                'status' => $group->status,
                'students_count' => (int) $group->enrollments()->count(),
                'course_version' => [
                    'id' => (int) $group->courseVersion->id,
                    'name' => $group->courseVersion->name,
                    'price' => (float) $group->courseVersion->price,
                    'course' => [
                        'id' => (int) $group->courseVersion->course->id,
                        'name' => $group->courseVersion->course->name,
                    ]
                ],
                'created_at' => $group->created_at,
                'updated_at' => $group->updated_at,
            ];
        })->toArray();
    }

    /**
     * Get pagination meta information.
     */
    private function getPaginationMeta($paginator): array
    {
        return [
            'current_page' => (int) $paginator->currentPage(),
            'last_page' => (int) $paginator->lastPage(),
            'per_page' => (int) $paginator->perPage(),
            'total' => (int) $paginator->total(),
            'from' => $paginator->firstItem() ? (int) $paginator->firstItem() : null,
            'to' => $paginator->lastItem() ? (int) $paginator->lastItem() : null,
        ];
    }

    /**
     * Get basic group information.
     */
    private function getBasicGroupInfo(Group $group): array
    {
        return [
            'id' => (int) $group->id,
            'name' => $group->name,
            'start_date' => $group->start_date,
            'end_date' => $group->end_date,
            'status' => $group->status,
            'course_version' => [
                'id' => (int) $group->courseVersion->id,
                'name' => $group->courseVersion->name,
                'price' => (float) $group->courseVersion->price,
                'course' => [
                    'id' => (int) $group->courseVersion->course->id,
                    'name' => $group->courseVersion->course->name,
                ]
            ],
        ];
    }

    /**
     * Calculate comprehensive group statistics.
     */
    private function calculateGroupStatistics(Group $group): array
    {
        return [
            'academic' => $this->getAcademicStatistics($group),
            'financial' => $this->getFinancialStatistics($group),
            'attendance' => $this->getAttendanceStatistics($group),
            'enrollments' => $this->getEnrollmentStatistics($group),
        ];
    }

    /**
     * Get academic statistics.
     */
    private function getAcademicStatistics(Group $group): array
    {
        $classSessionsCount = $group->classSessions->count();
        $totalMaterials = $group->classSessions->sum(fn($session) => $session->materials->count());
        $averageMaterialsPerClass = $classSessionsCount > 0 ? $totalMaterials / $classSessionsCount : 0;

        $gradesAverage = $group->exams()
            ->join('grades', 'exams.id', '=', 'grades.exam_id')
            ->avg('grades.grade');

        return [
            'class_sessions_count' => (int) $classSessionsCount,
            'average_materials_per_class' => (float) round($averageMaterialsPerClass, 2),
            'exams_count' => (int) $group->exams->count(),
            'grades_average' => $gradesAverage ? (float) round($gradesAverage, 2) : 0,
        ];
    }

    /**
     * Get financial statistics.
     */
    private function getFinancialStatistics(Group $group): array
    {
        $totalEnrollments = $group->enrollments->count();
        $coursePrice = (float) $group->courseVersion->price;
        $expectedMoney = $totalEnrollments * $coursePrice;

        $receivedMoney = DB::table('enrollment_payments')
            ->join('enrollments', 'enrollment_payments.enrollment_id', '=', 'enrollments.id')
            ->where('enrollments.group_id', $group->id)
            ->where('enrollment_payments.status', 'approved')
            ->sum('enrollment_payments.amount');

        return [
            'course_price' => $coursePrice,
            'expected_money' => (float) $expectedMoney,
            'received_money' => (float) $receivedMoney,
            'payment_completion_rate' => $expectedMoney > 0 ? (float) round(($receivedMoney / $expectedMoney) * 100, 2) : 0,
        ];
    }

    /**
     * Get attendance statistics.
     */
    private function getAttendanceStatistics(Group $group): array
    {
        $attendanceStats = DB::table('class_sessions')
            ->leftJoin('attendances', 'class_sessions.id', '=', 'attendances.class_session_id')
            ->where('class_sessions.group_id', $group->id)
            ->select(
                DB::raw('COUNT(DISTINCT class_sessions.id) as total_sessions'),
                DB::raw('COUNT(attendances.id) as total_attendance_records'),
                DB::raw('SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) as present_count')
            )
            ->first();

        $totalAttendanceRecords = (int) $attendanceStats->total_attendance_records;
        $presentCount = (int) $attendanceStats->present_count;
        $attendanceAverage = $totalAttendanceRecords > 0 ? ($presentCount / $totalAttendanceRecords) * 100 : 0;

        return [
            'total_sessions' => (int) $attendanceStats->total_sessions,
            'total_attendance_records' => $totalAttendanceRecords,
            'present_count' => $presentCount,
            'attendance_average' => (float) round($attendanceAverage, 2),
        ];
    }

    /**
     * Get enrollment statistics.
     */
    private function getEnrollmentStatistics(Group $group): array
    {
        $enrollmentStats = DB::table('enrollments')
            ->where('group_id', $group->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN payment_status = "paid" THEN 1 ELSE 0 END) as paid_count')
            ->first();

        // Contar estudiantes aprobados desde enrollment_results
        $approvedStudents = DB::table('enrollment_results')
            ->join('enrollments', 'enrollment_results.enrollment_id', '=', 'enrollments.id')
            ->where('enrollments.group_id', $group->id)
            ->where('enrollment_results.status', 'approved')
            ->count();

        return [
            'total_students' => (int) $enrollmentStats->total,
            'paid_students' => (int) $enrollmentStats->paid_count,
            'approved_students' => (int) $approvedStudents,
            'approval_rate' => $enrollmentStats->total > 0 ?
                (float) round(($approvedStudents / $enrollmentStats->total) * 100, 2) : 0,
        ];
    }
}