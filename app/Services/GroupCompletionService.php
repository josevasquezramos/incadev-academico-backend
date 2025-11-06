<?php

namespace App\Services;

use IncadevUns\CoreDomain\Enums\AttendanceStatus;
use IncadevUns\CoreDomain\Enums\EnrollmentAcademicStatus;
use IncadevUns\CoreDomain\Enums\EnrollmentResultStatus;
use IncadevUns\CoreDomain\Enums\GroupStatus;
use IncadevUns\CoreDomain\Enums\PaymentStatus;
use IncadevUns\CoreDomain\Models\Group;
use IncadevUns\CoreDomain\Models\Enrollment;
use IncadevUns\CoreDomain\Models\EnrollmentResult;
use IncadevUns\CoreDomain\Models\Certificate;
use IncadevUns\CoreDomain\Models\AcademicSetting;
use Illuminate\Support\Facades\DB;

class GroupCompletionService
{
    /**
     * Completar un grupo y generar resultados y certificados
     */
    public function completeGroup(Group $group): array
    {
        DB::beginTransaction();

        try {
            // Obtener configuración académica
            $academicSettings = AcademicSetting::first();
            if (!$academicSettings) {
                throw new \Exception('No se encontró la configuración académica');
            }

            $minPassingGrade = $academicSettings->min_passing_grade;
            $maxAbsencePercentage = $academicSettings->absence_percentage;

            // Obtener todas las matrículas activas del grupo
            $enrollments = Enrollment::with([
                'user',
                'grades.exam',
                'attendances.classSession'
            ])
            ->where('group_id', $group->id)
            ->where('payment_status', PaymentStatus::Paid)
            ->where('academic_status', EnrollmentAcademicStatus::Active)
            ->get();

            $totalStudents = $enrollments->count();
            $results = [];
            $certificatesGenerated = 0;

            foreach ($enrollments as $enrollment) {
                // Calcular nota final (promedio de todas las notas)
                $finalGrade = $this->calculateFinalGrade($enrollment);
                
                // Calcular porcentaje de asistencia
                $attendancePercentage = $this->calculateAttendancePercentage($enrollment, $group);
                
                // Determinar estado del resultado
                $status = $this->determineResultStatus($finalGrade, $attendancePercentage, $minPassingGrade, $maxAbsencePercentage);
                
                // Crear o actualizar EnrollmentResult
                $enrollmentResult = EnrollmentResult::updateOrCreate(
                    ['enrollment_id' => $enrollment->id],
                    [
                        'final_grade' => $finalGrade,
                        'attendance_percentage' => $attendancePercentage,
                        'status' => $status,
                    ]
                );

                // Generar certificado si aprobó
                if ($status === EnrollmentResultStatus::Approved) {
                    $this->generateCertificate($enrollment, $group, $enrollmentResult);
                    $certificatesGenerated++;
                }

                $results[] = [
                    'enrollment_id' => $enrollment->id,
                    'user_name' => $enrollment->user->name,
                    'final_grade' => $finalGrade,
                    'attendance_percentage' => $attendancePercentage,
                    'status' => $status->value,
                    'certificate_generated' => $status === EnrollmentResultStatus::Approved,
                ];
            }

            // Actualizar estado del grupo
            $group->update([
                'status' => GroupStatus::Completed
            ]);

            DB::commit();

            return [
                'success' => true,
                'total_students' => $totalStudents,
                'certificates_generated' => $certificatesGenerated,
                'results' => $results,
                'academic_settings_used' => [
                    'min_passing_grade' => $minPassingGrade,
                    'max_absence_percentage' => $maxAbsencePercentage,
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calcular la nota final del estudiante
     */
    private function calculateFinalGrade(Enrollment $enrollment): float
    {
        $grades = $enrollment->grades;
        
        if ($grades->isEmpty()) {
            return 0.0;
        }

        $totalGrade = 0;
        $count = 0;

        foreach ($grades as $grade) {
            $totalGrade += $grade->grade;
            $count++;
        }

        return $count > 0 ? round($totalGrade / $count, 2) : 0.0;
    }

    /**
     * Calcular el porcentaje de asistencia
     */
    private function calculateAttendancePercentage(Enrollment $enrollment, Group $group): float
    {
        $totalClasses = $group->classSessions->count();
        
        if ($totalClasses === 0) {
            return 100.0; // Si no hay clases, se considera 100% de asistencia
        }

        // Contar solo las ausencias (absent)
        $absentCount = $enrollment->attendances
            ->where('status', AttendanceStatus::Absent)
            ->count();

        // Las clases sin registro de asistencia se consideran ausentes
        $classesWithoutAttendance = $totalClasses - $enrollment->attendances->count();
        $totalAbsences = $absentCount + $classesWithoutAttendance;

        $attendancePercentage = (($totalClasses - $totalAbsences) / $totalClasses) * 100;
        
        return max(0, round($attendancePercentage, 2));
    }

    /**
     * Determinar el estado del resultado
     */
    private function determineResultStatus(
        float $finalGrade, 
        float $attendancePercentage, 
        int $minPassingGrade, 
        float $maxAbsencePercentage
    ): EnrollmentResultStatus {
        $minAttendancePercentage = 100 - $maxAbsencePercentage;

        if ($finalGrade >= $minPassingGrade && $attendancePercentage >= $minAttendancePercentage) {
            return EnrollmentResultStatus::Approved;
        } elseif ($finalGrade < $minPassingGrade) {
            return EnrollmentResultStatus::Failed;
        } else {
            return EnrollmentResultStatus::Failed; // Falló por asistencia
        }
    }

    /**
     * Generar certificado para el estudiante
     */
    private function generateCertificate(Enrollment $enrollment, Group $group, EnrollmentResult $result): void
    {
        // Verificar si ya existe un certificado
        $existingCertificate = Certificate::where('user_id', $enrollment->user_id)
            ->where('group_id', $group->id)
            ->first();

        if ($existingCertificate) {
            return; // Ya existe, no generar duplicado
        }

        $extraData = [
            'course_name' => $group->courseVersion->course->name,
            'course_version' => $group->courseVersion->version,
            'group_name' => $group->name,
            'final_grade' => $result->final_grade,
            'attendance_percentage' => $result->attendance_percentage,
            'issue_date' => now()->toDateString(),
            'total_students_in_group' => $group->enrollments()
                ->where('payment_status', PaymentStatus::Paid)
                ->where('academic_status', EnrollmentAcademicStatus::Active)
                ->count(),
        ];

        // Crear el certificado sin asignar ID manualmente
        Certificate::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'user_id' => $enrollment->user_id,
            'group_id' => $group->id,
            'issue_date' => now(),
            'extra_data_json' => $extraData,
        ]);
    }

    /**
     * Verificar si un grupo puede ser completado
     */
    public function canCompleteGroup(Group $group): array
    {
        $enrollments = Enrollment::where('group_id', $group->id)
            ->where('payment_status', PaymentStatus::Paid)
            ->where('academic_status', EnrollmentAcademicStatus::Active)
            ->count();

        $hasClasses = $group->classSessions()->exists();
        $hasExams = $group->exams()->exists();

        return [
            'can_complete' => $enrollments > 0 && $hasClasses,
            'reasons' => [
                'has_students' => $enrollments > 0,
                'has_classes' => $hasClasses,
                'has_exams' => $hasExams,
                'total_students' => $enrollments,
                'total_classes' => $group->classSessions()->count(),
                'total_exams' => $group->exams()->count(),
            ]
        ];
    }
}