<?php

use App\Http\Controllers\CertificateValidationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => ['Laravel' => app()->version()]);

Route::get('/certificates/verify/{uuid}', [CertificateValidationController::class, 'validateCertificate']);
