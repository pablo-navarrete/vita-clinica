<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#1f2937">
    @php
        $eventLabel = match ($event) {
            'created' => 'Solicitud de confirmacion de cita',
            'updated' => 'Tu cita fue actualizada',
            'cancelled' => 'Tu cita fue cancelada',
            'confirmed' => 'Tu cita fue confirmada',
            'in_progress' => 'Tu atención ha comenzado',
            'attended' => 'Tu cita fue marcada como atendida',
            'telemedicine' => 'Enlace para tu teleconsulta',
            default => 'Actualizacion de tu cita',
        };
    @endphp
    <div style="max-width:620px;margin:32px auto;background:#fff;border-radius:12px;padding:28px">
        <h1 style="font-size:22px;margin:0 0 16px">{{ $eventLabel }}</h1>
        <p>Hola {{ $patient->name }},</p>
        <p>Clinica: <strong>{{ $clinicName }}</strong></p>
        <p>Profesional: <strong>{{ $appointment->professional?->name ?? 'Por asignar' }}</strong></p>
        <p>Especialidad: <strong>{{ $appointment->specialty?->name ?? 'No especificada' }}</strong></p>
        <p>Fecha y hora: <strong>{{ $appointment->start_at?->timezone(config('app.timezone', 'America/Santiago'))->format('d/m/Y H:i') }}</strong></p>
        @if ($appointment->modality !== 'telemedicine' && $appointment->clinicBranch)
            <p>Sede de atención: <strong>{{ $appointment->clinicBranch->name }}</strong>{{ $appointment->clinicBranch->address ? ' · ' . $appointment->clinicBranch->address : '' }}</p>
        @endif
        @if ($event === 'created' && $confirmationUrl)
            <p>Para confirmar que asistiras, usa este enlace:</p>
            <p><a href="{{ $confirmationUrl }}" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px">Confirmar mi cita</a></p>
        @endif
        @if ($event === 'telemedicine' && $confirmationUrl)
            <p>Ingresa a la sala de telemedicina desde este enlace:</p>
            <p><a href="{{ $confirmationUrl }}">{{ $confirmationUrl }}</a></p>
        @endif
        @if ($appointment->reason)
            <p>Motivo: {{ $appointment->reason }}</p>
        @endif
        <p style="font-size:13px;color:#6b7280;margin-top:24px">Este mensaje fue enviado por Vita en nombre de {{ $clinicName }}.</p>
    </div>
</body>
</html>
