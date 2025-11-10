<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompletedGroupResource;
use Barryvdh\DomPDF\Facade\Pdf;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Models\Group;
use IncadevUns\CoreDomain\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StudentGroupController extends Controller
{
    /**
     * Listar grupos completados del estudiante
     */
    public function completedGroups(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->input('per_page', 10);

        $groups = Group::with([
            'courseVersion.course',
            'teachers',
            'enrollments' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            },
            'certificates' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }
        ])
            ->whereHas('enrollments', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('payment_status', \IncadevUns\CoreDomain\Enums\PaymentStatus::Paid);
            })
            ->where('status', \IncadevUns\CoreDomain\Enums\GroupStatus::Completed)
            ->orderBy('end_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => CompletedGroupResource::collection($groups->items()),
            'meta' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
            ]
        ]);
    }

    /**
     * Descargar certificado en PDF
     */
    public function downloadCertificate(Request $request, string $uuid)
    {
        $user = $request->user();

        $certificate = Certificate::with([
            'user',
            'group.courseVersion.course',
            'group.courseVersion.modules'
        ])->where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Obtener enrollment para acceder a notas y asistencias
        $enrollment = Enrollment::with([
            'grades.exam.module',
            'attendances.classSession.module',
            'result'
        ])->where('group_id', $certificate->group_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Preparar datos de notas por módulo
        $moduleGrades = [];
        foreach ($enrollment->grades as $grade) {
            $moduleName = $grade->exam->module->title;
            if (!isset($moduleGrades[$moduleName])) {
                $moduleGrades[$moduleName] = [];
            }
            $moduleGrades[$moduleName][] = [
                'exam' => $grade->exam->title,
                'grade' => $grade->grade
            ];
        }

        // Preparar datos de asistencias por módulo
        $moduleAttendances = [];
        foreach ($enrollment->attendances as $attendance) {
            $moduleName = $attendance->classSession->module->title;
            if (!isset($moduleAttendances[$moduleName])) {
                $moduleAttendances[$moduleName] = [
                    'present' => 0,
                    'total' => 0
                ];
            }
            $moduleAttendances[$moduleName]['total']++;
            if ($attendance->status->value === 'present' || $attendance->status->value === 'late') {
                $moduleAttendances[$moduleName]['present']++;
            }
        }

        $qrUrl = url("/certificates/verify/{$certificate->uuid}");

        $pdf = Pdf::loadView('certificates.pdf', [
            'certificate' => $certificate,
            'fullname' => $certificate->user->fullname ?? $certificate->user->name,
            'courseName' => $certificate->group->courseVersion->course->name,
            'startDate' => $certificate->group->start_date->format('d/m/Y'),
            'endDate' => $certificate->group->end_date->format('d/m/Y'),
            'qrUrl' => $qrUrl,
            'uuid' => $certificate->uuid,
            'moduleGrades' => $moduleGrades,
            'moduleAttendances' => $moduleAttendances,
            'finalGrade' => $enrollment->result->final_grade ?? 0,
            'attendancePercentage' => $enrollment->result->attendance_percentage ?? 0,
        ]);

        $pdf->setPaper('a4', 'landscape');

        $filename = "certificado-{$certificate->uuid}.pdf";

        return $pdf->stream($filename);
    }
}