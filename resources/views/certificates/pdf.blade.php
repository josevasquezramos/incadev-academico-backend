<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Certificado</title>
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .page-1 {
            page-break-after: always;
            position: relative;
        }

        .header-image {
            width: 100%;
            display: block;
            margin: 0;
            padding: 0;
        }

        .certificate-content {
            text-align: center;
            padding: 30px 60px;
        }

        .certificate-title {
            color: #08669cff;
            font-size: 48px;
            font-weight: bold;
            margin: 40px 0 30px 0;
            letter-spacing: 2px;
        }

        .student-name {
            font-size: 32px;
            font-weight: bold;
            margin: 30px 0;
            text-transform: uppercase;
        }

        .certificate-text {
            font-size: 16px;
            line-height: 1.8;
            margin: 30px auto;
            max-width: 700px;
        }

        .course-name {
            font-weight: bold;
        }

        .course-dates {
            font-weight: bold;
        }

        .signature-table {
            width: 100%;
            margin-top: 80px;
            border-collapse: collapse;
        }

        .signature-table td {
            vertical-align: top;
            text-align: center;
            padding: 0 20px;
        }

        .signature-column {
            width: 33.33%;
        }

        .signature-image {
            max-width: 200px;
            height: auto;
            margin-bottom: 10px;
        }

        .signature-line {
            border-top: 2px solid #000;
            width: 200px;
            margin: 10px auto 10px auto;
        }

        .signature-name {
            font-weight: bold;
            font-size: 14px;
            margin: 5px 0;
        }

        .signature-title {
            font-size: 13px;
        }

        .logo-image {
            max-width: 320px;
            height: auto;
            margin-top: 20px;
        }

        .qr-code {
            max-width: 100px;
            height: auto;
            margin: 10px auto;
        }

        .uuid-text {
            font-size: 10px;
            margin-top: 5px;
            word-break: break-all;
        }

        .uuid-link {
            color: black;
            text-decoration: none;
            font-size: 10px;
            margin-top: 5px;
            word-break: break-all;
        }

        .page-2 {
            padding: 40px 60px;
        }

        .page-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin: 25px 0 15px 0;
            border-bottom: 2px solid #08669cff;
            padding-bottom: 5px;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .details-table th {
            background-color: #08669cff;
            color: white;
            padding: 5px 10px;
            text-align: left;
            font-size: 13px;
        }

        .details-table td {
            padding: 5px 10px;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }

        .details-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .summary-box {
            background-color: #ecf0f1;
            padding: 7px 15px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .summary-item {
            font-size: 13px;
            margin: 8px 0;
        }

        .summary-label {
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="page-1">
        <img src="{{ public_path('images/certificate/header.png') }}" alt="Header" class="header-image">
        <div class="certificate-content">
            <div class="certificate-title">CERTIFICADO</div>
            <div class="student-name">{{ $fullname }}</div>
            <div class="certificate-text">
                Por haber completado exitosamente el curso de <span class="course-name">{{ $courseName }}</span>,
                realizado del <span class="course-dates">{{ $startDate }}</span> hasta el <span
                    class="course-dates">{{ $endDate }}</span> en el Instituto de Capacitación INCADEV.
            </div>
            <table class="signature-table">
                <tr>
                    <td class="signature-column">

                        {{-- TODO: Obtener firma desde la base de datos --}}

                        <img src="{{ public_path('images/certificate/signature.png') }}" alt="Firma"
                            class="signature-image">
                        <div class="signature-line"></div>
                        <div class="signature-name">GRADO NOMBRE PRESIDENTE</div>
                        <div class="signature-title">Director de INCADEV</div>
                    </td>
                    <td class="signature-column">
                        <img src="{{ public_path('images/certificate/isologotipo.png') }}" alt="Logo"
                            class="logo-image">
                    </td>
                    <td class="signature-column">
                        <img src="data:image/png;base64,{{ base64_encode(SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(200)->generate($qrUrl)) }}"
                            alt="QR Code" class="qr-code">
                        <div class="uuid-text">
                            <a href="{{ $qrUrl }}" target="_blank" class="uuid-link">{{ $uuid }}</a>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="page-2">
        <div class="page-title">Detalles Académicos</div>
        <div class="section-title">Calificaciones por Módulo</div>
        <table class="details-table">
            <thead>
                <tr>
                    <th>Módulo</th>
                    <th>Prueba</th>
                    <th>Nota</th>
                </tr>
            </thead>
            <tbody>
                @foreach($moduleGrades as $moduleName => $grades)
                    @foreach($grades as $index => $gradeData)
                        <tr>
                            @if($index === 0)
                                <td rowspan="{{ count($grades) }}">{{ $moduleName }}</td>
                            @endif
                            <td>{{ $gradeData['exam'] }}</td>
                            <td>{{ number_format($gradeData['grade'], 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
        <div class="section-title">Asistencias por Módulo</div>
        <table class="details-table">
            <thead>
                <tr>
                    <th>Módulo</th>
                    <th>Asistencias</th>
                    <th>Total de Sesiones</th>
                    <th>Porcentaje</th>
                </tr>
            </thead>
            <tbody>
                @foreach($moduleAttendances as $moduleName => $attendance)
                    <tr>
                        <td>{{ $moduleName }}</td>
                        <td>{{ $attendance['present'] }}</td>
                        <td>{{ $attendance['total'] }}</td>
                        <td>{{ $attendance['total'] > 0 ? number_format(($attendance['present'] / $attendance['total']) * 100, 2) : 0 }}%
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="section-title">Resumen Final</div>
        <div class="summary-box">
            <div class="summary-item">
                <span class="summary-label">Nota Final:</span> {{ number_format($finalGrade, 2) }}
            </div>
            <div class="summary-item">
                <span class="summary-label">Porcentaje de Asistencia Total:</span>
                {{ number_format($attendancePercentage, 2) }}%
            </div>
            <div class="summary-item">
                <span class="summary-label">Fecha de emisión:</span>
                {{ $certificate->issue_date->translatedFormat('d/m/Y') }}
            </div>
        </div>
    </div>
</body>

</html>