<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Validación de Certificado</title>
</head>
<body>
    @if($isValid)
        <h1>✅ Certificado Válido</h1>
    @else
        <h1>❌ Certificado No Válido</h1>
    @endif
</body>
</html>