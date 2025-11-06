<?php

namespace App\Http\Controllers;

use IncadevUns\CoreDomain\Models\Certificate;
use Illuminate\Http\Request;

class CertificateValidationController extends Controller
{
    /**
     * Validar certificado (página web pública)
     */
    public function validateCertificate(string $uuid)
    {
        $certificate = Certificate::with(['user', 'group.courseVersion.course'])
            ->where('uuid', $uuid)
            ->first();

        return view('certificates.validation', [
            'certificate' => $certificate,
            'isValid' => (bool) $certificate
        ]);
    }
}