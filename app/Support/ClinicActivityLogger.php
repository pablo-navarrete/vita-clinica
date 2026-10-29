<?php

namespace App\Support;

use App\Models\ClinicActivityLog;
use App\Models\ClinicBranch;
use Illuminate\Database\Eloquent\Model;

class ClinicActivityLogger
{
    private const MODULES = [
        'Patient' => 'Pacientes', 'User' => 'Profesionales', 'Appointment' => 'Agenda',
        'Encounter' => 'Atenciones', 'ProfessionalSchedule' => 'Horarios',
        'MedicalRecord' => 'Ficha clínica', 'Allergie' => 'Ficha clínica',
        'ChronicDiseases' => 'Ficha clínica', 'RegularMedication' => 'Ficha clínica',
        'PreviousSurgerie' => 'Ficha clínica', 'Insurance' => 'Previsiones',
        'Specialty' => 'Especialidades', 'Menu' => 'Administración web',
        'WebsitePage' => 'Administración web', 'WebsitePageSection' => 'Administración web',
        'WebsiteBanner' => 'Administración web', 'WebsiteFooter' => 'Administración web',
        'WebsiteBlogPost' => 'Administración web', 'WebsiteBlogSetting' => 'Administración web',
        'WebsiteSocialSetting' => 'Administración web', 'WebsiteTopbar' => 'Administración web',
        'WebsiteMaintenance' => 'Administración web', 'Logo' => 'Administración web',
        'LogoLocation' => 'Administración web',
    ];

    private const FIELD_LABELS = [
        'Patient' => [
            'name' => 'nombre', 'rut' => 'RUT', 'birth_date' => 'fecha de nacimiento',
            'gender' => 'género', 'phone' => 'teléfono', 'email' => 'correo',
            'address' => 'dirección', 'status' => 'estado', 'insurance_id' => 'previsión',
        ],
        'User' => [
            'name' => 'nombre', 'rut' => 'RUT', 'phone' => 'teléfono',
            'email' => 'correo', 'status' => 'estado',
        ],
        'Appointment' => [
            'patient_id' => 'paciente', 'professional_id' => 'profesional',
            'specialty_id' => 'especialidad', 'start_at' => 'fecha y hora de inicio',
            'end_at' => 'hora de término', 'status' => 'estado',
            'reason' => 'motivo', 'notes' => 'observaciones',
        ],
        'Encounter' => [
            'patient_id' => 'paciente', 'appointment_id' => 'cita asociada',
            'professional_id' => 'profesional', 'specialty_id' => 'especialidad',
            'status' => 'estado', 'chief_complaint' => 'motivo de consulta',
            'clinical_assessment' => 'evaluación clínica', 'treatment_plan' => 'plan de tratamiento',
            'cancellation_reason' => 'motivo de cierre', 'started_at' => 'inicio', 'ended_at' => 'término',
        ],
        'ProfessionalSchedule' => [
            'user_id' => 'profesional', 'day_of_week' => 'día',
            'start_time' => 'hora de inicio', 'end_time' => 'hora de término',
        ],
        'Allergie' => ['allergen' => 'alergia', 'reaction' => 'reacción'],
        'ChronicDiseases' => ['name' => 'enfermedad crónica'],
        'RegularMedication' => ['name' => 'medicamento habitual'],
        'PreviousSurgerie' => ['name' => 'cirugía previa'],
        'Insurance' => ['name' => 'nombre', 'status' => 'estado'],
        'Specialty' => ['name' => 'nombre', 'status' => 'estado'],
        'Menu' => ['name' => 'nombre', 'url' => 'enlace', 'position' => 'posición', 'status' => 'estado'],
        'WebsitePage' => ['title' => 'título', 'slug' => 'enlace', 'status' => 'estado', 'banner_path' => 'banner'],
        'WebsitePageSection' => ['title' => 'título', 'body' => 'contenido', 'image_path' => 'imagen', 'image_position' => 'posición de imagen', 'position' => 'orden'],
        'WebsiteBanner' => ['title' => 'título', 'subtitle' => 'subtítulo', 'description' => 'descripción', 'image_path' => 'imagen', 'image_alt' => 'texto alternativo', 'button_text' => 'texto del botón', 'button_url' => 'enlace del botón', 'position' => 'posición', 'status' => 'estado'],
        'WebsiteBlogPost' => ['title' => 'título', 'excerpt' => 'resumen', 'content' => 'contenido', 'image_path' => 'imagen', 'status' => 'estado', 'published_at' => 'fecha de publicación'],
        'WebsiteBlogSetting' => ['enabled' => 'visibilidad del blog', 'title' => 'nombre del blog', 'banner_path' => 'imagen del banner'],
        'WebsiteFooter' => ['enabled' => 'visibilidad', 'brand_display' => 'marca del footer', 'title' => 'título', 'description' => 'descripción', 'phone' => 'teléfono', 'email' => 'correo', 'address' => 'dirección', 'show_social_links' => 'redes sociales'],
        'WebsiteSocialSetting' => ['social_links' => 'enlaces de redes sociales', 'show_in_topbar' => 'visibilidad de redes'],
        'WebsiteTopbar' => ['enabled' => 'visibilidad', 'show_social_links' => 'redes sociales', 'phone' => 'teléfono', 'email' => 'correo'],
        'WebsiteMaintenance' => ['enabled' => 'modo mantenimiento', 'message' => 'mensaje'],
        'Logo' => ['name' => 'nombre', 'url' => 'imagen', 'status' => 'estado'],
        'LogoLocation' => ['logo_id' => 'logo asignado', 'location' => 'ubicación'],
    ];

    public static function recordModel(Model $model, string $event): void
    {
        if (!isset(self::MODULES[class_basename($model)])) {
            return;
        }

        $customerId = self::customerId($model, class_basename($model));
        if (!$customerId || !app()->bound('customer') || $customerId !== (int) app('customer')->id) {
            return;
        }

        $type = class_basename($model);
        $fieldLabels = self::FIELD_LABELS[$type] ?? [];
        if ($event === 'created') {
            $changedKeys = array_values(array_intersect(array_keys($model->getAttributes()), array_keys($fieldLabels)));
        } else {
            $attributes = $event === 'updated' ? array_keys($model->getChanges()) : [];
            $changedKeys = array_values(array_intersect($attributes, array_keys($fieldLabels)));
        }

        if ($event === 'updated' && !$changedKeys) {
            return;
        }

        $label = self::subjectLabel($model, $type);
        $actionText = match ($event) {
            'created' => 'creó',
            'deleted', 'trashed' => 'eliminó',
            default => 'actualizó',
        };
        $subject = self::subjectType($type);
        $changedLabels = array_map(fn ($key) => $fieldLabels[$key], $changedKeys);
        $summary = ucfirst($actionText) . ' ' . $subject . ' «' . $label . '»';
        if ($changedLabels) {
            $summary .= ' (' . implode(', ', array_unique($changedLabels)) . ')';
        }
        $summary .= '.';

        self::record(
            $customerId,
            self::MODULES[$type],
            $event === 'trashed' ? 'deleted' : $event,
            $subject,
            $label,
            $summary,
            $changedLabels,
            get_class($model),
            $model->getKey(),
            $model
        );
    }

    public static function recordManual(
        int $customerId,
        string $module,
        string $action,
        string $subject,
        string $label,
        string $summary,
        array $changes = [],
        ?string $subjectType = null,
        int|string|null $subjectId = null
    ): void {
        if (!app()->bound('customer') || $customerId !== (int) app('customer')->id) {
            return;
        }

        self::record($customerId, $module, $action, $subject, $label, $summary, $changes, $subjectType, $subjectId);
    }

    private static function record(
        int $customerId,
        string $module,
        string $action,
        string $subject,
        string $label,
        string $summary,
        array $changes,
        ?string $subjectType,
        int|string|null $subjectId,
        ?Model $model = null
    ): void {
        ClinicActivityLog::create([
            'customer_id' => $customerId,
            'clinic_branch_id' => self::branchId($customerId, $model),
            'user_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
            'module' => $module,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => $label,
            'summary' => $summary,
            'changes' => $changes ? ['fields' => array_values(array_unique($changes))] : null,
        ]);
    }

    private static function branchId(int $customerId, ?Model $model = null): ?int
    {
        if ((int) session('active_branch_customer_id') === $customerId && session()->has('active_branch_id')) {
            $activeBranchId = ClinicBranch::withTrashed()
                ->where('customer_id', $customerId)
                ->whereKey((int) session('active_branch_id'))
                ->value('id');

            if ($activeBranchId !== null) {
                return (int) $activeBranchId;
            }
        }

        if (!$model) {
            return null;
        }

        $branchId = $model->getAttribute('clinic_branch_id');
        if (!$branchId) {
            foreach (['appointment', 'patient', 'medicalRecord'] as $relation) {
                if (method_exists($model, $relation) && ($related = $model->{$relation})) {
                    $branchId = $related->getAttribute('clinic_branch_id');
                    if (!$branchId && method_exists($related, 'patient')) {
                        $branchId = $related->patient?->getAttribute('clinic_branch_id');
                    }
                    if ($branchId) {
                        break;
                    }
                }
            }
        }

        if (!$branchId) {
            return null;
        }

        $branchId = ClinicBranch::withTrashed()->where('customer_id', $customerId)->whereKey((int) $branchId)->value('id');

        return $branchId === null ? null : (int) $branchId;
    }

    private static function subjectLabel(Model $model, string $type): string
    {
        $value = match ($type) {
            'Patient', 'User', 'Insurance', 'Specialty', 'Menu', 'WebsitePage', 'WebsiteFooter',
            'WebsiteBanner', 'WebsiteBlogPost', 'Logo' => $model->getAttribute(match ($type) {
                'WebsitePage', 'WebsiteBanner', 'WebsiteBlogPost', 'WebsiteFooter' => 'title',
                default => 'name',
            }),
            'Appointment', 'Encounter' => $model->patient?->name,
            'ProfessionalSchedule' => $model->professional?->name,
            'WebsitePageSection' => $model->page?->title,
            'Allergie', 'ChronicDiseases', 'RegularMedication', 'PreviousSurgerie' => $model->medicalRecord?->patient?->name,
            'MedicalRecord' => $model->patient?->name,
            'WebsiteSocialSetting' => 'Redes sociales',
            'WebsiteBlogSetting' => 'Visibilidad del blog',
            'WebsiteTopbar' => 'Barra superior',
            'WebsiteMaintenance' => 'Modo mantenimiento',
            'LogoLocation' => $model->location,
            default => null,
        };

        return trim((string) ($value ?: $type));
    }

    private static function customerId(Model $model, string $type): int
    {
        $customerId = (int) ($model->getAttribute('customer_id') ?: 0);
        if (!$customerId && $type === 'WebsitePageSection') {
            $customerId = (int) ($model->page?->customer_id ?: 0);
        }
        return $customerId;
    }

    private static function subjectType(string $type): string
    {
        return match ($type) {
            'Patient' => 'paciente', 'User' => 'profesional', 'Appointment' => 'cita',
            'Encounter' => 'atención', 'ProfessionalSchedule' => 'horario',
            'MedicalRecord' => 'ficha clínica', 'Allergie', 'ChronicDiseases', 'RegularMedication', 'PreviousSurgerie' => 'antecedente clínico',
            'Insurance' => 'previsión', 'Specialty' => 'especialidad', 'Menu' => 'menú',
            'WebsitePage' => 'página web', 'WebsitePageSection' => 'sección web',
            'WebsiteBanner' => 'banner', 'WebsiteFooter' => 'footer',
            'WebsiteBlogPost' => 'artículo del blog', 'WebsiteBlogSetting' => 'visibilidad del blog',
            'WebsiteSocialSetting' => 'redes sociales', 'WebsiteTopbar' => 'barra superior',
            'WebsiteMaintenance' => 'modo mantenimiento', 'Logo' => 'logo', 'LogoLocation' => 'ubicación de logo',
            default => 'registro',
        };
    }
}
