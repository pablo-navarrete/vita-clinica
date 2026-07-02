<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Super Administrador'],
            ['id' => 2, 'name' => 'Administrador'],
            ['id' => 3, 'name' => 'Doctor'],
            ['id' => 4, 'name' => 'Secretaria']
        ]);
    }
}
