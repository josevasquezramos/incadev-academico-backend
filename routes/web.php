<?php

use App\Http\Controllers\CertificateValidationController;
use App\Http\Controllers\SocialiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => ['Laravel' => app()->version()]);

Route::get('/certificates/verify/{uuid}', [CertificateValidationController::class, 'validateCertificate']);

Route::get('/auth/google/redirect', [SocialiteController::class, 'redirectToGoogle'])->name('google.redirect');
Route::get('/auth/google/callback', [SocialiteController::class, 'handleGoogleCallback'])->name('google.callback');