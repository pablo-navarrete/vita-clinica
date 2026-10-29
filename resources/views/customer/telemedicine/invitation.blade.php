<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enlace para tu teleconsulta</title>
</head>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#1f2937">
    <div style="max-width:600px;margin:32px auto;padding:32px;background:#fff;border-radius:12px">
        <h1 style="margin:0 0 16px;font-size:22px">Hola, {{ $patient->name }}</h1>
        <p>La clínica {{ $clinicName }} te invita a conectarte a tu teleconsulta.</p>
        <p>
            <strong>Profesional:</strong> {{ $professional?->name ?? 'Tu profesional' }}<br>
            <strong>Especialidad:</strong> {{ $specialty?->name ?? 'Atención médica' }}<br>
            <strong>Fecha y hora:</strong> {{ \Illuminate\Support\Carbon::parse($appointment->start_at)->timezone(config('app.timezone', 'America/Santiago'))->translatedFormat('l j \\d\\e F, H:i') }}
        </p>
        <p>Abre el siguiente enlace para ingresar a la sala y aceptar el consentimiento informado:</p>
        <p style="margin:24px 0">
            <a href="{{ $patientUrl }}" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold">Ingresar a la teleconsulta</a>
        </p>
        <p style="font-size:13px;color:#6b7280">El enlace es personal.
            @if($appointment->exists) Estará disponible hasta 2 horas después del horario de la cita.
            @else Disponible hasta {{ $session->expires_at->timezone('America/Santiago')->format('d/m/Y H:i') }}.
            @endif
            Si no esperabas este correo, puedes ignorarlo.
        </p>
    </div>
</body>
</html>
