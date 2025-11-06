<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificado</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .certificate { text-align: center; }
        .fullname { font-size: 24px; margin: 20px 0; }
        .qr-code { margin-top: 30px; }
    </style>
</head>
<body>
    <div class="certificate">
        <h1>Certificado</h1>
        <div class="fullname">{{ $fullname }}</div>
        <div class="qr-code">
            <img src="data:image/png;base64,{{ base64_encode(SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(200)->generate(json_encode($qrData))) }}" alt="QR Code">
        </div>
    </div>
</body>
</html>