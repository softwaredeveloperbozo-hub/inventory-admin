<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase, CreatesInventory;

    private function draftPayload(Product $product): array
    {
        return ['items' => [[
            'product_id' => $product->id,
            'quantity'   => 5,
            'cost'       => 10,
            'sale_price' => 15,
        ]]];
    }

    public function test_operator_is_blocked_from_restricted_actions(): void
    {
        $product  = $this->product();
        $category = Category::create(['name' => 'Bebidas']);
        $supplier = Supplier::create(['name' => 'Fornecedor']);

        $stocked  = $this->product();
        $purchase = $this->stockUp($stocked, 10, 5, 10);
        $sale     = $this->sell($stocked, 1);

        $this->asOperator();

        $draftId = $this->postJson('/api/purchases', $this->draftPayload($product))
            ->assertCreated()->json('data.id');

        $this->deleteJson("/api/products/{$product->id}")->assertForbidden();
        $this->deleteJson("/api/categories/{$category->id}")->assertForbidden();
        $this->deleteJson("/api/suppliers/{$supplier->id}")->assertForbidden();
        $this->postJson("/api/purchases/{$draftId}/confirm")->assertForbidden();
        $this->postJson("/api/purchases/{$purchase->id}/cancel")->assertForbidden();
        $this->postJson("/api/sales/{$sale->id}/cancel")->assertForbidden();

        // e nada foi alterado
        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
        $this->assertSame('confirmed', $purchase->fresh()->status);
        $this->assertSame('completed', $sale->fresh()->status);

        // o rascunho continua rascunho: nem estoque nem preço mudaram
        $this->assertSame('draft', Purchase::find($draftId)->status);
        $this->assertSame(0.0, $this->stockOf($product));
        $this->assertNull($this->activePrice($product));
    }

    public function test_admin_can_do_the_restricted_actions(): void
    {
        $product  = $this->product();
        $category = Category::create(['name' => 'Bebidas']);
        $supplier = Supplier::create(['name' => 'Fornecedor']);

        $stocked  = $this->product();
        $purchase = $this->stockUp($stocked, 10, 5, 10);
        $sale     = $this->sell($stocked, 1);

        $fresh = $this->product();

        $this->asAdmin();

        $draftId = $this->postJson('/api/purchases', $this->draftPayload($fresh))
            ->assertCreated()->json('data.id');

        $this->postJson("/api/purchases/{$draftId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        // a venda precisa ser cancelada antes: ela devolve o estoque que a compra estornaria
        $this->postJson("/api/sales/{$sale->id}/cancel")->assertOk();
        $this->postJson("/api/purchases/{$purchase->id}/cancel")->assertOk();
        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();
        $this->deleteJson("/api/categories/{$category->id}")->assertNoContent();
        $this->deleteJson("/api/suppliers/{$supplier->id}")->assertNoContent();
    }

    public function test_operator_proposes_and_admin_approves_a_purchase(): void
    {
        $product = $this->product();

        $draftId = $this->asOperator()
            ->postJson('/api/purchases', $this->draftPayload($product))
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/purchases/{$draftId}/confirm")->assertForbidden();
        $this->assertSame(0.0, $this->stockOf($product));

        $this->asAdmin()
            ->postJson("/api/purchases/{$draftId}/confirm")
            ->assertOk();

        $this->assertEquals(5, $this->stockOf($product));
        $this->assertEquals(15, (float) $this->activePrice($product)->sale_price);
    }

    public function test_operator_can_do_the_daily_work(): void
    {
        $stocked = $this->product();
        $this->stockUp($stocked, 10, 5, 10); // compra já confirmada pelo administrador

        $this->asOperator();

        $productId = $this->postJson('/api/products', ['sku' => 'OP-1', 'name' => 'Feito pelo operador'])
            ->assertCreated()->json('data.id');

        $this->putJson("/api/products/{$productId}", ['name' => 'Editado'])->assertOk();

        $items = [['product_id' => $productId, 'quantity' => 5, 'cost' => 10, 'sale_price' => 15]];

        $draftId = $this->postJson('/api/purchases', ['items' => $items])->assertCreated()->json('data.id');
        $this->deleteJson("/api/purchases/{$draftId}")->assertNoContent();

        $this->postJson('/api/sales', [
            'payment_method' => 'cash',
            'items'          => [['product_id' => $stocked->id, 'quantity' => 1]],
        ])->assertCreated();
    }
}
