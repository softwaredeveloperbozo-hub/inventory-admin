<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleService
{
    public function create(array $data, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $userId) {
            // Ordenar evita deadlock quando duas vendas travam os mesmos produtos
            $rows = collect($data['items'])->sortBy('product_id')->values();

            $sale = Sale::create([
                'user_id'        => $userId,
                'code'           => 'T-'.Str::random(16), // provisório, vira V-000001 abaixo
                'customer_name'  => $data['customer_name'] ?? null,
                'payment_method' => $data['payment_method'],
                'discount'       => 0,
                'total'          => 0,
                'status'         => 'completed',
                'sold_at'        => $data['sold_at'] ?? now(),
            ]);

            $gross = 0;

            foreach ($rows as $row) {
                $quantity = (float) $row['quantity'];

                $product = Product::with('currentPrice')->find($row['product_id']);

                if (! $product || ! $product->is_active) {
                    abort(422, 'Produto indisponível para venda.');
                }

                $price = $product->currentPrice;

                if (! $price) {
                    abort(422, "O produto \"{$product->name}\" ainda não tem preço (nenhuma compra confirmada).");
                }

                $stock = Stock::where('product_id', $product->id)->lockForUpdate()->first();

                if (! $stock || (float) $stock->quantity < $quantity) {
                    $available = $stock ? (float) $stock->quantity : 0;
                    abort(409, "Estoque insuficiente de \"{$product->name}\". Disponível: {$available}.");
                }

                $subtotal = round($quantity * (float) $price->sale_price, 2);

                $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity'   => $quantity,
                    'unit_price' => $price->sale_price,
                    'unit_cost'  => $price->cost_price,
                    'tax_rate'   => $product->tax_rate,
                    'subtotal'   => $subtotal,
                ]);

                $stock->decrement('quantity', $quantity);
                $stock->refresh();

                $sale->movements()->create([
                    'product_id'    => $product->id,
                    'user_id'       => $userId,
                    'type'          => 'out',
                    'source'        => 'sale',
                    'quantity'      => $quantity,
                    'balance_after' => $stock->quantity,
                ]);

                $gross += $subtotal;
            }

            $discount = round((float) ($data['discount'] ?? 0), 2);

            if ($discount > $gross) {
                abort(422, 'O desconto não pode ser maior que o total da venda.');
            }

            $sale->update([
                'code'     => sprintf('V-%06d', $sale->id),
                'discount' => $discount,
                'total'    => round($gross - $discount, 2),
            ]);

            return $sale;
        });
    }

    public function cancel(Sale $sale, int $userId): void
    {
        DB::transaction(function () use ($sale, $userId) {
            $sale = Sale::with('items')->lockForUpdate()->findOrFail($sale->id);

            if ($sale->status === 'cancelled') {
                abort(409, 'A venda já está cancelada.');
            }

            foreach ($sale->items->sortBy('product_id') as $item) {
                $stock = Stock::where('product_id', $item->product_id)->lockForUpdate()->first()
                    ?? Stock::create(['product_id' => $item->product_id]);

                $stock->increment('quantity', (float) $item->quantity);
                $stock->refresh();

                $sale->movements()->create([
                    'product_id'    => $item->product_id,
                    'user_id'       => $userId,
                    'type'          => 'in',
                    'source'        => 'cancellation',
                    'quantity'      => $item->quantity,
                    'balance_after' => $stock->quantity,
                ]);
            }

            $sale->update(['status' => 'cancelled']);
        });
    }
}
