<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'branches.admin_all'],
            ['name' => 'Administrar todas las sedes desde la sede principal', 'module' => 'Sedes', 'updated_at' => $now, 'created_at' => $now]
        );

        $permissionId = DB::table('permissions')->where('slug', 'branches.admin_all')->value('id');
        $adminRoleIds = DB::table('roles')
            ->whereNotNull('customer_id')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['administrador'])
            ->pluck('id');

        foreach ($adminRoleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('slug', 'branches.admin_all')->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
