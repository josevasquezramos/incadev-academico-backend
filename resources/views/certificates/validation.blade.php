<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validación de Certificado - INCADEV</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #4c1d95 40%, #065f46 80%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            padding: 40px 40px 30px;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .status-icon {
            font-size: 64px;
            margin-bottom: 16px;
            animation: scaleIn 0.5s ease-out;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        .status-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .status-title.valid {
            color: #059669;
        }
        
        .status-title.invalid {
            color: #dc2626;
        }
        
        .status-subtitle {
            font-size: 16px;
            color: #6b7280;
        }
        
        .content {
            padding: 40px;
        }
        
        .info-section {
            margin-bottom: 32px;
        }
        
        .info-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 6px;
        }
        
        .info-value {
            font-size: 18px;
            font-weight: 500;
            color: #000;
            margin-bottom: 20px;
        }
        
        .info-value.large {
            font-size: 24px;
            font-weight: 700;
        }
        
        .divider {
            height: 1px;
            background: #e5e7eb;
            margin: 24px 0;
        }
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-top: 20px;
        }
        
        .grid-item {
            text-align: center;
            padding: 20px;
            background: #f9fafb;
            border-radius: 12px;
        }
        
        .grid-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .grid-value {
            font-size: 28px;
            font-weight: 700;
            color: #000;
        }
        
        .grid-value.medium {
            font-size: 18px;
        }
        
        .footer {
            padding: 24px 40px;
            background: #f9fafb;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        
        .footer-text {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
        }
        
        .uuid-box {
            background: #f3f4f6;
            padding: 12px 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #000;
            word-break: break-all;
            margin-top: 8px;
        }
        
        .error-message {
            padding: 16px;
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .error-message p {
            font-size: 14px;
            color: #991b1b;
            line-height: 1.5;
        }
        
        @media (max-width: 640px) {
            .container {
                margin: 0;
                border-radius: 0;
            }
            
            .header, .content, .footer {
                padding-left: 24px;
                padding-right: 24px;
            }
            
            .grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .status-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        @if($isValid)
            <!-- CERTIFICADO VÁLIDO -->
            <div class="header">
                <div class="status-icon">✓</div>
                <h1 class="status-title valid">Certificado Válido</h1>
                <p class="status-subtitle">Este certificado ha sido verificado exitosamente</p>
            </div>
            
            <div class="content">
                <!-- Nombre del Estudiante -->
                <div class="info-section">
                    <div class="info-label">Otorgado a</div>
                    <div class="info-value large">{{ $certificate->user->fullname ?? $certificate->user->name }}</div>
                </div>
                
                <div class="divider"></div>
                
                <!-- Información del Curso -->
                <div class="info-section">
                    <div class="info-label">Curso</div>
                    <div class="info-value">{{ $certificate->extra_data_json['course_name'] ?? $certificate->group->courseVersion->course->name }}</div>
                </div>
                
                <div class="info-section">
                    <div class="info-label">Versión</div>
                    <div class="info-value">{{ $certificate->extra_data_json['course_version'] ?? 'N/A' }}</div>
                </div>
                
                <div class="info-section">
                    <div class="info-label">Grupo</div>
                    <div class="info-value">{{ $certificate->extra_data_json['group_name'] ?? $certificate->group->name }}</div>
                </div>
                
                <div class="divider"></div>
                
                <!-- Métricas Académicas y Estudiantes -->
                <div class="grid">
                    <div class="grid-item">
                        <div class="grid-label">Nota Final</div>
                        <div class="grid-value">{{ number_format($certificate->extra_data_json['final_grade'] ?? 0, 2) }}</div>
                    </div>
                    <div class="grid-item">
                        <div class="grid-label">Asistencia</div>
                        <div class="grid-value">{{ number_format($certificate->extra_data_json['attendance_percentage'] ?? 0, 0) }}%</div>
                    </div>
                    <div class="grid-item">
                        <div class="grid-label">Total Estudiantes</div>
                        <div class="grid-value">{{ $certificate->extra_data_json['total_students_in_group'] ?? 0 }}</div>
                    </div>
                </div>
                
                <div class="divider"></div>
                
                <!-- Fechas del Curso -->
                <div class="grid">
                    <div class="grid-item">
                        <div class="grid-label">Inicio del Curso</div>
                        <div class="grid-value medium">
                            {{ \Carbon\Carbon::parse($certificate->group->start_date)->format('d/m/Y') }}
                        </div>
                    </div>
                    <div class="grid-item">
                        <div class="grid-label">Fin del Curso</div>
                        <div class="grid-value medium">
                            {{ \Carbon\Carbon::parse($certificate->group->end_date)->format('d/m/Y') }}
                        </div>
                    </div>
                    <div class="grid-item">
                        <div class="grid-label">Fecha de Emisión</div>
                        <div class="grid-value medium">
                            {{ \Carbon\Carbon::parse($certificate->extra_data_json['issue_date'] ?? $certificate->issue_date)->format('d/m/Y') }}
                        </div>
                    </div>
                </div>
                
                <div class="divider"></div>
                
                <!-- UUID -->
                <div class="info-section">
                    <div class="info-label">Código de Verificación</div>
                    <div class="uuid-box">{{ $certificate->uuid }}</div>
                </div>
            </div>
            
            <div class="footer">
                <p class="footer-text">
                    Este certificado fue emitido por el <strong>Instituto de Capacitación INCADEV</strong>
                    y puede ser verificado en cualquier momento mediante este código único.
                </p>
            </div>
        @else
            <!-- CERTIFICADO NO VÁLIDO -->
            <div class="header">
                <div class="status-icon">✕</div>
                <h1 class="status-title invalid">Certificado No Válido</h1>
                <p class="status-subtitle">No se pudo verificar este certificado</p>
            </div>
            
            <div class="content">
                <div class="error-message">
                    <p>
                        <strong>El certificado no se encuentra en nuestros registros.</strong><br><br>
                        Esto puede deberse a:
                    </p>
                    <ul style="margin-top: 12px; margin-left: 20px; color: #991b1b;">
                        <li>El código de verificación es incorrecto</li>
                        <li>El enlace de verificación está incompleto o dañado</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer">
                <p class="footer-text">
                    Instituto de Capacitación INCADEV<br>
                    Sistema de Verificación de Certificados
                </p>
            </div>
        @endif
    </div>
</body>
</html>