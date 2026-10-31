<?php

namespace Database\Seeders;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class RestockSeeder extends Seeder
{
    public function run(): void
    {
        if (Purchase::count() >= 2) {
            return;
        }

        $admin    = User::where('email', 'admin@teste.com')->firstOrFail();
        $supplier = Supplier::where('name', 'Armazéns do Sul')->first();

        // Reposição com preço novo: o custo e o preço de venda mudaram
        app(PurchaseSeeder::class)->buy($supplier, $admin, [
            'AGU-500' => [100, 90, 170],
            'ARR-1KG' => [100, 500, 750],
        ]);
    }
}
