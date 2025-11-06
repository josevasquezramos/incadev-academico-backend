<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompletedGroupResource;
use Barryvdh\DomPDF\Facade\Pdf;
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
                'enrollments' => function($query) use ($user) {
                    $query->where('user_id', $user->id);
                },
                'certificates' => function($query) use ($user) {
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

        $certificate = Certificate::with(['user', 'group.courseVersion.course'])
            ->where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $qrData = [
            'verification_url' => url("/certificates/verify/{$certificate->uuid}"),
            'extra_data_json' => $certificate->extra_data_json
        ];

        $pdf = Pdf::loadView('certificates.pdf', [
            'fullname' => $certificate->user->fullname ?? $certificate->user->name,
            'qrData' => $qrData
        ]);

        $filename = "certificado-{$certificate->uuid}.pdf";

        return $pdf->download($filename);
    }
}