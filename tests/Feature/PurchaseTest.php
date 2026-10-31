<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase, CreatesInventory;

    private function payload(Product $product, array $item = []): array
    {
        return ['items' => [array_merge([
            'product_id' => $product->id,
            'quantity'   => 10,
            'cost'       => 2.5,
            'sale_price' => 4,
        ], $item)]];
    }

    public function test_a_draft_does_not_touch_stock_or_price(): void
    {
        $product = $this->product();

        $response = $this->asAdmin()
            ->postJson('/api/purchases', $this->payload($product))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $this->assertEquals(25, $response->json('data.total'));
        $this->assertSame(0.0, $this->stockOf($product));
        $this->assertNull($this->activePrice($product));
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_the_total_is_calculated_by_the_server(): void
    {
        $product = $this->product();

        $payload = $this->payload($product, ['subtotal' => 1]);
        $payload['total'] = 1;

        $this->asAdmin()->postJson('/api/purchases', $payload)->assertCreated();

        $this->assertDatabaseHas('purchases', ['total' => 25]);
    }

    public function test_invalid_purchases_are_rejected(): void
    {
        $product = $this->product();
        $valid   = ['product_id' => $product->id, 'quantity' => 1, 'cost' => 1, 'sale_price' => 2];

        $cases = [
            'sem itens'           => ['items' => []],
            'produto repetido'    => ['items' => [$valid, $valid]],
            'quantidade zero'     => ['items' => [array_merge($valid, ['quantity' => 0])]],
            'custo negativo'      => ['items' => [array_merge($valid, ['cost' => -1])]],
            'produto inexistente' => ['items' => [array_merge($valid, ['product_id' => 9999])]],
            'sem preço de venda'  => ['items' => [array_diff_key($valid, ['sale_price' => 1])]],
        ];

        $this->asAdmin();

        foreach ($cases as $name => $payload) {
            $status = $this->postJson('/api/purchases', $payload)->status();
            $this->assertSame(422, $status, "Caso: {$name}");
        }

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_deleted_products_and_inactive_suppliers_are_refused(): void
    {
        $gone = $this->product();
        $gone->delete();

        $inactive = Supplier::create(['name' => 'Inativo', 'is_active' => false]);
        $live     = $this->product();

        $this->asAdmin();

        $this->postJson('/api/purchases', $this->payload($gone))->assertUnprocessable();

        $payload = $this->payload($live);
        $payload['supplier_id'] = $inactive->id;
        $this->postJson('/api/purchases', $payload)->assertUnprocessable();
    }

    public function test_confirming_adds_stock_records_the_movement_and_creates_the_price(): void
    {
        $product = $this->product();

        $this->asAdmin();
        $id = $this->postJson('/api/purchases', $this->payload($product))->json('data.id');

        $this->postJson("/api/purchases/{$id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertEquals(10, $this->stockOf($product));

        $price = $this->activePrice($product);
        $this->assertEquals(2.5, (float) $price->cost_price);
        $this->assertEquals(4, (float) $price->sale_price);
        $this->assertEquals(PurchaseItem::where('purchase_id', $id)->value('id'), $price->purchase_item_id);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'     => $product->id,
            'type'           => 'in',
            'source'         => 'purchase',
            'quantity'       => 10,
            'balance_after'  => 10,
            'reference_type' => Purchase::class,
            'reference_id'   => $id,
        ]);
    }

    public function test_confirming_twice_is_refused_and_changes_nothing(): void
    {
        $product = $this->product();

        $this->asAdmin();
        $id = $this->postJson('/api/purchases', $this->payload($product))->json('data.id');

        $this->postJson("/api/purchases/{$id}/confirm")->assertOk();
        $this->postJson("/api/purchases/{$id}/confirm")->assertStatus(409);

        $this->assertEquals(10, $this->stockOf($product));
        $this->assertSame(1, ProductPrice::count());
        $this->assertSame(1, StockMovement::count());
    }

    public function test_a_new_purchase_replaces_the_active_price_and_keeps_the_history(): void
    {
        $product = $this->product();

        $this->stockUp($product, 10, 5, 10);
        $this->stockUp($product, 10, 6, 12);

        $this->assertSame(2, ProductPrice::where('product_id', $product->id)->count());
        $this->assertSame(1, ProductPrice::where('product_id', $product->id)->where('is_active', true)->count());
        $this->assertEquals(12, (float) $this->activePrice($product)->sale_price);
        $this->assertEquals(10, (float) ProductPrice::where('is_active', false)->first()->sale_price);
        $this->assertEquals(20, $this->stockOf($product));
    }

    public function test_cancelling_a_confirmed_purchase_reverses_stock_and_restores_the_old_price(): void
    {
        $product = $this->product();

        $this->stockUp($product, 10, 5, 10);
        $second = $this->stockUp($product, 10, 6, 12);

        $this->asAdmin()->postJson("/api/purchases/{$second->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertEquals(10, $this->stockOf($product));
        $this->assertEquals(10, (float) $this->activePrice($product)->sale_price);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type'       => 'out',
            'source'     => 'cancellation',
            'quantity'   => 10,
        ]);
    }

    public function test_cancelling_an_older_purchase_does_not_touch_the_current_price(): void
    {
        $product = $this->product();

        $first = $this->stockUp($product, 10, 5, 10);
        $this->stockUp($product, 10, 6, 12);

        $this->asAdmin()->postJson("/api/purchases/{$first->id}/cancel")->assertOk();

        $this->assertEquals(12, (float) $this->activePrice($product)->sale_price);
        $this->assertEquals(10, $this->stockOf($product));
    }

    public function test_cancelling_is_refused_when_part_of_the_goods_was_already_sold(): void
    {
        $product  = $this->product();
        $purchase = $this->stockUp($product, 10, 5, 10);
        $this->sell($product, 6);

        $movements = StockMovement::count();

        $this->asAdmin()->postJson("/api/purchases/{$purchase->id}/cancel")->assertStatus(409);

        $this->assertSame('confirmed', $purchase->fresh()->status);
        $this->assertEquals(4, $this->stockOf($product));
        $this->assertEquals(10, (float) $this->activePrice($product)->sale_price);
        $this->assertSame($movements, StockMovement::count());
    }

    public function test_cancelling_a_draft_changes_nothing_in_stock(): void
    {
        $product = $this->product();

        $this->asAdmin();
        $id = $this->postJson('/api/purchases', $this->payload($product))->json('data.id');

        $this->postJson("/api/purchases/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/purchases/{$id}/cancel")->assertStatus(409);

        $this->assertSame(0.0, $this->stockOf($product));
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_only_drafts_can_be_deleted(): void
    {
        $product   = $this->product();
        $confirmed = $this->stockUp($product, 5, 2, 4);

        $this->asAdmin();
        $draftId = $this->postJson('/api/purchases', $this->payload($product))->json('data.id');

        $this->deleteJson("/api/purchases/{$draftId}")->assertNoContent();
        $this->deleteJson("/api/purchases/{$confirmed->id}")->assertStatus(409);

        $this->assertDatabaseHas('purchases', ['id' => $confirmed->id]);
    }
}
