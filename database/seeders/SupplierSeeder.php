<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::firstOrCreate(
            ['tax_id' => '5417000001'],
            [
                'name'      => 'Distribuidora Central',
                'phone'     => '923000111',
                'email'     => 'vendas@central.example',
                'address'   => 'Zona Industrial, Luanda',
                'is_active' => true,
            ]
        );

        Supplier::firstOrCreate(
            ['tax_id' => '5417000002'],
            [
                'name'      => 'Armazéns do Sul',
                'phone'     => '923000222',
                'email'     => 'comercial@sul.example',
                'address'   => 'Viana, Luanda',
                'is_active' => true,
            ]
        );
    }
}
