<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@teste.com'],
            [
                'name'      => 'Administrador',
                'password'  => '12345678',
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'operador@teste.com'],
            [
                'name'      => 'Operador',
                'password'  => '12345678',
                'role'      => 'operator',
                'is_active' => true,
            ]
        );
    }
}
