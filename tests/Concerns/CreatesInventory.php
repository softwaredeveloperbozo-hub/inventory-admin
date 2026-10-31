<?php

namespace Tests\Concerns;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SaleService;

trait CreatesInventory
{
    protected int $skuCounter = 0;

    protected function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@teste.com'],
            ['name' => 'Administrador', 'password' => '12345678', 'role' => 'admin', 'is_active' => true]
        );
    }

    protected function operator(): User
    {
        return User::firstOrCreate(
            ['email' => 'operador@teste.com'],
            ['name' => 'Operador', 'password' => '12345678', 'role' => 'operator', 'is_active' => true]
        );
    }

    protected function asAdmin(): static
    {
        return $this->actingAs($this->admin(), 'api');
    }

    protected function asOperator(): static
    {
        return $this->actingAs($this->operator(), 'api');
    }

    protected function product(array $attributes = []): Product
    {
        $this->skuCounter++;

        return Product::create(array_merge([
            'sku'  => 'SKU-'.$this->skuCounter,
            'name' => 'Produto '.$this->skuCounter,
        ], $attributes));
    }

    // Faz uma compra confirmada, passando pelo mesmo Service da API
    protected function stockUp(Product $product, float $quantity, float $cost, float $salePrice): Purchase
    {
        $user     = $this->admin();
        $subtotal = round($quantity * $cost, 2);

        $purchase = Purchase::create([
            'user_id' => $user->id,
            'status'  => 'draft',
            'total'   => $subtotal,
        ]);

        $purchase->items()->create([
            'product_id' => $product->id,
            'quantity'   => $quantity,
            'cost'       => $cost,
            'sale_price' => $salePrice,
            'subtotal'   => $subtotal,
        ]);

        app(PurchaseService::class)->confirm($purchase, $user->id);

        return $purchase->fresh();
    }

    // Faz uma venda, passando pelo mesmo Service da API
    protected function sell(Product $product, float $quantity): Sale
    {
        return app(SaleService::class)->create([
            'payment_method' => 'cash',
            'items'          => [['product_id' => $product->id, 'quantity' => $quantity]],
        ], $this->admin()->id);
    }

    protected function stockOf(Product $product): float
    {
        return (float) Stock::where('product_id', $product->id)->value('quantity');
    }

    protected function activePrice(Product $product): ?ProductPrice
    {
        return ProductPrice::where('product_id', $product->id)->where('is_active', true)->first();
    }
}
