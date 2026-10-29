<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unique(['customer_id', 'name']);
        });

        $baseRoles = DB::table('roles')->whereNull('customer_id')->where('name', '!=', 'Super Administrador')->get();
        $customers = DB::table('customers')->pluck('id');

        foreach ($customers as $customerId) {
            foreach ($baseRoles as $baseRole) {
                $clinicRoleId = DB::table('roles')->insertGetId([
                    'customer_id' => $customerId,
                    'name' => $baseRole->name,
                    'permissions_configured' => $baseRole->permissions_configured ?? false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $permissionIds = DB::table('permission_role')->where('role_id', $baseRole->id)->pluck('permission_id');
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $clinicRoleId,
                        'permission_id' => $permissionId,
                    ]);
                }

                $userIds = DB::table('role_user')
                    ->join('users', 'users.id', '=', 'role_user.user_id')
                    ->where('users.customer_id', $customerId)
                    ->where('role_user.role_id', $baseRole->id)
                    ->pluck('role_user.user_id');

                foreach ($userIds as $userId) {
                    DB::table('role_user')->insertOrIgnore([
                        'user_id' => $userId,
                        'role_id' => $clinicRoleId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('role_user')->where('user_id', $userId)->where('role_id', $baseRole->id)->delete();
                }
            }
        }
    }

    public function down(): void
    {
        $clinicRoles = DB::table('roles')->whereNotNull('customer_id')->get();
        foreach ($clinicRoles as $clinicRole) {
            $globalRole = DB::table('roles')->whereNull('customer_id')->where('name', $clinicRole->name)->first();
            if ($globalRole) {
                $userIds = DB::table('role_user')->where('role_id', $clinicRole->id)->pluck('user_id');
                foreach ($userIds as $userId) {
                    DB::table('role_user')->insertOrIgnore([
                        'user_id' => $userId,
                        'role_id' => $globalRole->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            DB::table('role_user')->where('role_id', $clinicRole->id)->delete();
            DB::table('permission_role')->where('role_id', $clinicRole->id)->delete();
            DB::table('roles')->where('id', $clinicRole->id)->delete();
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'name']);
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
