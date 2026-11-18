<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $column = DB::selectOne("SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_SET_NAME,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='patients' AND COLUMN_NAME='rut'");
        if (! $column || $column->COLUMN_TYPE !== 'varchar(255)' || $column->IS_NULLABLE !== 'NO' || $column->COLUMN_DEFAULT !== null || $column->EXTRA !== '' || $column->CHARACTER_SET_NAME !== 'utf8mb4' || $column->COLLATION_NAME !== 'utf8mb4_unicode_ci') {
            throw new LogicException('Unexpected patients.rut schema; stop.');
        }
        DB::statement('ALTER TABLE patients MODIFY rut VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL');
    }

    public function down(): void
    {
        if (DB::table('patients')->whereNull('rut')->exists()) {
            throw new LogicException('Cannot restore NOT NULL while patients have no Chilean RUT.');
        }
        DB::statement('ALTER TABLE patients MODIFY rut VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
    }
};
