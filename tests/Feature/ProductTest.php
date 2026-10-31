<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProductPrice;
use App\Models\Purchase;
use App\Models\Stock;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase, CreatesInventory;

    public function test_a_new_product_starts_with_zero_stock_and_no_price(): void
    {
        $id = $this->asAdmin()
            ->postJson('/api/products', ['sku' => 'NEW-1', 'name' => 'Novo'])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('stocks', ['product_id' => $id, 'quantity' => 0]);
        $this->assertDatabaseCount('product_prices', 0);
    }

    public function test_price_and_quantity_sent_by_the_client_are_ignored(): void
    {
        $id = $this->asAdmin()
            ->postJson('/api/products', [
                'sku' => 'HACK-1', 'name' => 'Tentativa',
                'quantity' => 999, 'price' => 50, 'sale_price' => 50,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(0.0, (float) Stock::where('product_id', $id)->value('quantity'));
        $this->assertDatabaseCount('product_prices', 0);
    }

    public function test_sku_must_be_unique_but_the_same_product_can_keep_its_own(): void
    {
        $product = $this->product(['sku' => 'DUP-1']);
        $this->asAdmin();

        $this->postJson('/api/products', ['sku' => 'DUP-1', 'name' => 'Outro'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku');

        $this->putJson("/api/products/{$product->id}", ['sku' => 'DUP-1', 'name' => 'Renomeado'])
            ->assertOk();
    }

    public function test_required_fields_are_validated(): void
    {
        $this->asAdmin()
            ->postJson('/api/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku', 'name']);
    }

    public function test_deleting_a_product_is_a_soft_delete(): void
    {
        $product = $this->product();
        $this->stockUp($product, 5, 2, 4);

        $this->asAdmin()->deleteJson("/api/products/{$product->id}")->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->getJson("/api/products/{$product->id}")->assertNotFound();
        $this->assertDatabaseCount('stock_movements', 1); // o histórico permanece
    }

    public function test_search_filters_by_name_and_sku(): void
    {
        $this->product(['sku' => 'AGU-500', 'name' => 'Agua 500ml']);
        $this->product(['sku' => 'ARR-1KG', 'name' => 'Arroz 1kg']);

        $this->asAdmin();

        $this->getJson('/api/products?search=agu')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/products?search=ARR-1KG')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/products?search=zzz')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/products')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_category_filter_and_search_work_together(): void
    {
        $drinks = Category::create(['name' => 'Bebidas']);
        $food   = Category::create(['name' => 'Alimentos']);

        $this->product(['name' => 'Agua', 'category_id' => $drinks->id]);
        $this->product(['name' => 'Agua de coco', 'category_id' => $food->id]);

        $this->asAdmin()
            ->getJson("/api/products?search=agua&category_id={$drinks->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::create(['name' => 'Bebidas']);
        $this->product(['category_id' => $category->id]);

        $this->asAdmin()->deleteJson("/api/categories/{$category->id}")->assertStatus(409);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_a_supplier_with_purchases_cannot_be_deleted(): void
    {
        $supplier = Supplier::create(['name' => 'Fornecedor']);
        Purchase::create(['supplier_id' => $supplier->id, 'user_id' => $this->admin()->id, 'status' => 'draft']);

        $this->asAdmin()->deleteJson("/api/suppliers/{$supplier->id}")->assertStatus(409);

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_tax_id_must_be_unique_but_can_be_empty(): void
    {
        $this->asAdmin();

        $this->postJson('/api/suppliers', ['name' => 'A', 'tax_id' => '123'])->assertCreated();
        $this->postJson('/api/suppliers', ['name' => 'B', 'tax_id' => '123'])->assertUnprocessable();
        $this->postJson('/api/suppliers', ['name' => 'C'])->assertCreated();
        $this->postJson('/api/suppliers', ['name' => 'D'])->assertCreated();
    }

    public function test_the_database_itself_refuses_two_active_prices_for_one_product(): void
    {
        $product = $this->product();

        ProductPrice::create(['product_id' => $product->id, 'cost_price' => 1, 'sale_price' => 2, 'is_active' => true]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ProductPrice::create(['product_id' => $product->id, 'cost_price' => 1, 'sale_price' => 3, 'is_active' => true]);
    }
}
