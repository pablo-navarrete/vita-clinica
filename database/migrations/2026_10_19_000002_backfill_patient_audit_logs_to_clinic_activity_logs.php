<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('patient_audit_logs') || !Schema::hasTable('clinic_activity_logs')) {
            return;
        }

        $lastId = 0;
        do {
            $rows = DB::table('patient_audit_logs as audit')
                ->leftJoin('patients', 'patients.id', '=', 'audit.patient_id')
                ->leftJoin('users', 'users.id', '=', 'audit.user_id')
                ->where('audit.id', '>', $lastId)
                ->orderBy('audit.id')
                ->limit(500)
                ->get([
                    'audit.id', 'audit.customer_id', 'audit.patient_id', 'audit.user_id',
                    'audit.action', 'audit.section', 'audit.changes', 'audit.created_at',
                    'patients.name as patient_name', 'users.name as actor_name',
                ]);

            foreach ($rows as $row) {
                $lastId = (int) $row->id;
                $module = match ($row->section) {
                    'Cita médica' => 'Agenda',
                    'Atención médica' => 'Atenciones',
                    'Datos del paciente', 'Ficha clínica' => 'Pacientes',
                    default => 'Ficha clínica',
                };
                $action = in_array($row->action, ['created', 'deleted'], true) ? $row->action : 'updated';
                $actionText = match ($row->action) {
                    'created' => 'Creó', 'started' => 'Inició', 'finished' => 'Finalizó',
                    'cancelled' => 'Canceló', 'deleted' => 'Eliminó', default => 'Actualizó',
                };
                $changes = is_array($row->changes) ? $row->changes : json_decode((string) $row->changes, true);
                $fields = array_keys(is_array($changes) ? $changes : []);
                if (in_array('items', $fields, true)) {
                    $fields = ['antecedentes clínicos'];
                }

                DB::table('clinic_activity_logs')->insert([
                    'customer_id' => $row->customer_id,
                    'user_id' => $row->user_id,
                    'actor_name' => $row->actor_name,
                    'module' => $module,
                    'action' => $action,
                    'subject_type' => App\Models\Patient::class,
                    'subject_id' => $row->patient_id,
                    'subject_label' => $row->patient_name ?: 'Paciente',
                    'summary' => $actionText . ' «' . $row->section . '» del paciente «' . ($row->patient_name ?: 'Paciente') . '».',
                    'changes' => $fields ? json_encode(['fields' => array_values($fields)], JSON_UNESCAPED_UNICODE) : null,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->created_at,
                ]);
            }
        } while ($rows->count() === 500);
    }

    public function down(): void
    {
        // No se borran registros importados para evitar eliminar actividad posterior.
    }
};
