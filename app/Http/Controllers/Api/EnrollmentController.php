<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnrollmentRequest;
use IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus;
use IncadevUns\CoreDomain\Enums\GroupStatus;
use IncadevUns\CoreDomain\Enums\PaymentStatus;
use IncadevUns\CoreDomain\Enums\PaymentVerificationStatus;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Models\EnrollmentPayment;
use IncadevUns\CoreDomain\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    /**
     * Matricula al usuario en un grupo y registra el pago
     * 
     * @param EnrollmentRequest $request
     * @param int $groupId
     * @return JsonResponse
     */
    public function enroll(EnrollmentRequest $request, int $groupId): JsonResponse
    {
        $user = $request->user();

        // Verificar que el grupo existe y está en estado 'enrolling'
        $group = Group::find($groupId);

        if (!$group) {
            return response()->json([
                'message' => 'Grupo no encontrado'
            ], 404);
        }

        if ($group->status !== GroupStatus::Enrolling) {
            return response()->json([
                'message' => 'Este grupo no está disponible para matrícula'
            ], 400);
        }

        // Verificar si el usuario ya está matriculado en este grupo
        $existingEnrollment = Enrollment::where('group_id', $groupId)
            ->where('user_id', $user->id)
            ->first();

        if ($existingEnrollment) {
            return response()->json([
                'message' => 'Ya estás matriculado en este grupo'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Crear la matrícula
            $enrollment = Enrollment::create([
                'group_id' => $groupId,
                'user_id' => $user->id,
                'payment_status' => PaymentStatus::Pending,
                'academic_status' => EnrollmentAcademicStatus::Pending,
            ]);

            // Crear el pago
            $payment = EnrollmentPayment::create([
                'enrollment_id' => $enrollment->id,
                'operation_number' => $request->operation_number,
                'agency_number' => $request->agency_number,
                'operation_date' => $request->operation_date,
                'amount' => $request->amount,
                'evidence_path' => $request->evidence_path,
                'status' => PaymentVerificationStatus::Pending,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Matrícula registrada exitosamente. Tu pago está en revisión.',
                'data' => [
                    'enrollment_id' => $enrollment->id,
                    'payment_id' => $payment->id,
                    'payment_status' => $enrollment->payment_status->value,
                    'academic_status' => $enrollment->academic_status->value,
                    'group_id' => $groupId,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al procesar la matrícula',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}