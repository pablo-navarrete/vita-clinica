<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'admin@vita-clinica.cl'], // evita duplicados
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('12345678'),
                'customer_id' => null, // clave
            ]
        );

        $user->roles()->sync([1]);
    }
}
