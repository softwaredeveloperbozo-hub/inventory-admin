<?php

namespace App\Services;

use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function confirm(Purchase $purchase, int $userId): void
    {
        DB::transaction(function () use ($purchase, $userId) {
            $purchase = Purchase::with('items')->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status !== 'draft') {
                abort(409, 'Só é possível confirmar uma compra em rascunho.');
            }

            foreach ($purchase->items as $item) {
                $this->addStock($purchase, $item, $userId);
                $this->activatePrice($item);
            }

            $purchase->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
            ]);
        });
    }

    public function cancel(Purchase $purchase, int $userId): void
    {
        DB::transaction(function () use ($purchase, $userId) {
            $purchase = Purchase::with('items.product')->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->status === 'cancelled') {
                abort(409, 'A compra já está cancelada.');
            }

            if ($purchase->status === 'confirmed') {
                foreach ($purchase->items as $item) {
                    $this->removeStock($purchase, $item, $userId);
                    $this->restorePrice($item);
                }
            }

            $purchase->update(['status' => 'cancelled']);
        });
    }

    private function addStock(Purchase $purchase, PurchaseItem $item, int $userId): void
    {
        $stock = Stock::where('product_id', $item->product_id)->lockForUpdate()->first()
            ?? Stock::create(['product_id' => $item->product_id]);

        $stock->increment('quantity', (float) $item->quantity);
        $stock->refresh();

        $purchase->movements()->create([
            'product_id'    => $item->product_id,
            'user_id'       => $userId,
            'type'          => 'in',
            'source'        => 'purchase',
            'quantity'      => $item->quantity,
            'balance_after' => $stock->quantity,
        ]);
    }

    private function removeStock(Purchase $purchase, PurchaseItem $item, int $userId): void
    {
        $stock = Stock::where('product_id', $item->product_id)->lockForUpdate()->first();

        if (! $stock || (float) $stock->quantity < (float) $item->quantity) {
            abort(409, "Não é possível cancelar: parte do estoque de \"{$item->product->name}\" já saiu.");
        }

        $stock->decrement('quantity', (float) $item->quantity);
        $stock->refresh();

        $purchase->movements()->create([
            'product_id'    => $item->product_id,
            'user_id'       => $userId,
            'type'          => 'out',
            'source'        => 'cancellation',
            'quantity'      => $item->quantity,
            'balance_after' => $stock->quantity,
        ]);
    }

    private function activatePrice(PurchaseItem $item): void
    {
        ProductPrice::where('product_id', $item->product_id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        ProductPrice::create([
            'product_id'       => $item->product_id,
            'cost_price'       => $item->cost,
            'sale_price'       => $item->sale_price,
            'purchase_item_id' => $item->id,
            'is_active'        => true,
            'effective_from'   => now(),
        ]);
    }

    private function restorePrice(PurchaseItem $item): void
    {
        $active = ProductPrice::where('product_id', $item->product_id)
            ->where('is_active', true)
            ->first();

        // Só mexe no preço se o vigente nasceu justamente desta compra
        if (! $active || $active->purchase_item_id !== $item->id) {
            return;
        }

        $active->update(['is_active' => false]);

        ProductPrice::where('product_id', $item->product_id)
            ->where('id', '<', $active->id)
            ->whereHas('purchaseItem.purchase', fn ($q) => $q->where('status', 'confirmed'))
            ->orderByDesc('id')
            ->first()
            ?->update(['is_active' => true]);
    }
}
