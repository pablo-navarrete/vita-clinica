<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirmar cita</title>
</head>
<body style="margin:0;background:#f3f4f6;color:#1f2937;font-family:Arial,sans-serif">
    <main style="box-sizing:border-box;max-width:560px;margin:8vh auto;padding:28px;background:#fff;border-radius:16px;box-shadow:0 12px 40px rgba(15,23,42,.1)">
        <h1 style="margin:0 0 16px;font-size:24px">Confirmacion de cita</h1>
        <p>Hola {{ $appointment->patient?->name }},</p>
        <p>Clinica: <strong>{{ app('customer')->name ?? config('app.name') }}</strong></p>
        <p>Profesional: <strong>{{ $appointment->professional?->name ?? 'Por asignar' }}</strong></p>
        <p>Fecha y hora: <strong>{{ $appointment->start_at?->timezone(config('app.timezone', 'America/Santiago'))->format('d/m/Y H:i') }}</strong></p>
        @if ($appointment->modality !== 'telemedicine' && $appointment->clinicBranch)
            <p>Sede de atención: <strong>{{ $appointment->clinicBranch->name }}</strong>{{ $appointment->clinicBranch->address ? ' · ' . $appointment->clinicBranch->address : '' }}</p>
        @endif

        @if ((int) $appointment->status === 1)
            <p style="padding:12px;border-radius:8px;background:#ecfdf5;color:#047857">Esta cita ya esta confirmada.</p>
        @elseif ((int) $appointment->status === 0 && $appointment->start_at?->isFuture())
            <p>Confirma que asistirás a esta cita:</p>
            <form method="POST" action="{{ request()->fullUrl() }}">
                @csrf
                <button type="submit" style="border:0;border-radius:8px;background:{{ themeColor(600) }};color:#fff;padding:12px 18px;font-weight:700;cursor:pointer">Confirmar asistencia</button>
            </form>
        @else
            <p style="padding:12px;border-radius:8px;background:#fff7ed;color:#c2410c">Esta cita ya no se puede confirmar. Contacta a la clinica.</p>
        @endif
    </main>
</body>
</html>
