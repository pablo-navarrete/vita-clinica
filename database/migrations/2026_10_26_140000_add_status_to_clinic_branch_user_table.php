<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clinic_branch_user', 'status')) {
            Schema::table('clinic_branch_user', function (Blueprint $table) {
                $table->boolean('status')->default(true)->after('user_id');
            });
        }

        DB::statement('UPDATE clinic_branch_user
            INNER JOIN users ON users.id = clinic_branch_user.user_id
            SET clinic_branch_user.status = users.status');
    }

    public function down(): void
    {
        if (Schema::hasColumn('clinic_branch_user', 'status')) {
            Schema::table('clinic_branch_user', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
