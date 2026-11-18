<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $column = DB::selectOne("SELECT COLUMN_TYPE,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='rut'");
        if (! $column || $column->COLUMN_TYPE !== 'varchar(255)' || $column->CHARACTER_SET_NAME !== 'utf8mb4' || $column->COLLATION_NAME !== 'utf8mb4_unicode_ci') {
            throw new LogicException('Unexpected legacy RUT schema; stop for review.');
        }
        DB::statement('ALTER TABLE customers MODIFY rut VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL');
    }

    public function down(): void
    {
        if (DB::table('customers')->whereNull('rut')->exists()) {
            throw new LogicException('Cannot restore NOT NULL while customers have no Chilean RUT. Preserve their identifiers.');
        }
        DB::statement('ALTER TABLE customers MODIFY rut VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
    }
};
