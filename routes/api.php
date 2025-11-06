<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailableGroupController;
use App\Http\Controllers\Api\EnrolledGroupController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\StudentCertificateController;
use App\Http\Controllers\Api\StudentGroupController;
use App\Http\Controllers\Api\TeachingGroupController;
use Illuminate\Support\Facades\Route;

// TODO: Borrar y usar el endpoint de Johan para el login
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::prefix('available-groups')->group(function () {
        Route::get('/', [AvailableGroupController::class, 'index'])
            ->name('api.available-groups.index');
        Route::post('{group}/enroll', [EnrollmentController::class, 'enroll'])
            ->name('api.groups.enroll');
    });

    Route::prefix('enrolled-groups')->group(function () {
        Route::get('/', [EnrolledGroupController::class, 'index'])
            ->name('api.enrolled-groups.index');
        Route::get('{group}', [EnrolledGroupController::class, 'show'])
            ->name('api.enrolled-groups.show');
    });

    Route::prefix('teaching-groups')->group(function () {

        Route::get('/', [TeachingGroupController::class, 'index']);
        Route::get('{group}', [TeachingGroupController::class, 'show']);

        Route::get('{group}/can-complete', [TeachingGroupController::class, 'canCompleteGroup']);
        Route::post('{group}/complete', [TeachingGroupController::class, 'markAsCompleted']);

        Route::get('{group}/classes', [TeachingGroupController::class, 'listClasses']);
        Route::post('{group}/modules/{module}/classes', [TeachingGroupController::class, 'createClass']);
        Route::put('classes/{class}', [TeachingGroupController::class, 'updateClass']);
        Route::delete('classes/{class}', [TeachingGroupController::class, 'deleteClass']);

        Route::get('classes/{class}/materials', [TeachingGroupController::class, 'listMaterials']);
        Route::post('classes/{class}/materials', [TeachingGroupController::class, 'createMaterial']);
        Route::put('materials/{material}', [TeachingGroupController::class, 'updateMaterial']);
        Route::delete('materials/{material}', [TeachingGroupController::class, 'deleteMaterial']);

        Route::get('{group}/exams', [ExamController::class, 'index']);
        Route::post('{group}/modules/{module}/exams', [ExamController::class, 'createExam']);
        Route::get('exams/{exam}', [ExamController::class, 'show']);
        Route::put('exams/{exam}', [ExamController::class, 'updateExam']);
        Route::delete('exams/{exam}', [ExamController::class, 'deleteExam']);
        Route::post('exams/{exam}/grades', [ExamController::class, 'recordGrades']);
        Route::put('grades/{grade}', [ExamController::class, 'updateGrade']);

        Route::get('{group}/attendances', [AttendanceController::class, 'index']);
        Route::get('classes/{class}/attendances', [AttendanceController::class, 'show']);
        Route::post('classes/{class}/attendances', [AttendanceController::class, 'recordAttendances']);
        Route::put('attendances/{attendance}', [AttendanceController::class, 'updateAttendance']);
        Route::get('{group}/attendance-statistics', [AttendanceController::class, 'getGroupStatistics']);
    });

    Route::prefix('student')->group(function () {
        Route::get('/completed-groups', [StudentGroupController::class, 'completedGroups']);
        Route::get('/certificates/{uuid}/download', [StudentGroupController::class, 'downloadCertificate']);
    });
});
