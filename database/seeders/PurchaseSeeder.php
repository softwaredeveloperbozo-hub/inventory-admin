<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Purchase::exists()) {
            return;
        }

        $admin    = User::where('email', 'admin@teste.com')->firstOrFail();
        $supplier = Supplier::where('name', 'Distribuidora Central')->first();

        // sku => [quantidade, custo, preço de venda]
        // O "Caderno A4" fica de fora de propósito: produto cadastrado e nunca comprado.
       $this->buy($supplier, $admin, [
            'AGU-500' => [800, 80, 150],
            'REF-350' => [800, 150, 250],
            'SUM-1L'  => [800, 300, 480],
            'CER-330' => [800, 200, 350],
            'ARR-1KG' => [800, 450, 700],
            'OLE-1L'  => [800, 900, 1300],
            'ACU-1KG' => [800, 400, 600],
            'MAS-500' => [800, 250, 400],
            'FEI-1KG' => [800, 700, 1000],
            'SAB-90'  => [800, 100, 180],
            'DET-500' => [800, 350, 550],
            'PAP-4R'  => [4, 500, 800],   // estoque baixo
            'CAN-AZ'  => [3, 50, 100],    // estoque baixo
        ]);
    }

    public function buy(?Supplier $supplier, User $user, array $rows): Purchase
    {
        $purchase = DB::transaction(function () use ($supplier, $user, $rows) {
            $purchase = Purchase::create([
                'supplier_id' => $supplier?->id,
                'user_id'     => $user->id,
                'status'      => 'draft',
            ]);

            $total = 0;

            foreach ($rows as $sku => [$quantity, $cost, $salePrice]) {
                $product  = Product::where('sku', $sku)->firstOrFail();
                $subtotal = round($quantity * $cost, 2);

                $purchase->items()->create([
                    'product_id' => $product->id,
                    'quantity'   => $quantity,
                    'cost'       => $cost,
                    'sale_price' => $salePrice,
                    'subtotal'   => $subtotal,
                ]);

                $total += $subtotal;
            }

            $purchase->update(['total' => round($total, 2)]);

            return $purchase;
        });

        app(PurchaseService::class)->confirm($purchase, $user->id);

        return $purchase->fresh();
    }
}
