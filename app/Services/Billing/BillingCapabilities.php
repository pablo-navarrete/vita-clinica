<?php
namespace App\Services\Billing;

/** Technical capabilities only: commercial values belong exclusively to the database. */
final class BillingCapabilities
{
    public const FEATURES = [
        'appointments' => 'Agenda y citas', 'medical_records' => 'Fichas y atenciones clínicas',
        'telemedicine' => 'Telemedicina', 'electronic_signatures' => 'Firma electrónica y adendas',
        'internal_chat' => 'Chat interno', 'public_website' => 'Sitio web público',
        'viti' => 'Asistente Viti', 'clinical_exports' => 'Exportaciones clínicas PDF/FHIR',
    ];
    public const LIMITS = ['users' => 'Usuarios activos únicos', 'professionals' => 'Profesionales activos únicos (rol Doctor)', 'branches' => 'Sedes contratadas'];
    public const QUOTAS = ['ai_tokens' => ['label' => 'IA: tokens de entrada y salida', 'unit' => 'tokens', 'periods' => ['month', 'billing_cycle']],
        'storage_bytes' => ['label' => 'Almacenamiento ocupado', 'unit' => 'bytes', 'periods' => ['none']]];
}
