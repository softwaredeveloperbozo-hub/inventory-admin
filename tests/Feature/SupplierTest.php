<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase, CreatesInventory;

    public function test_a_supplier_can_be_deactivated_with_a_partial_update(): void
    {
        $supplier = Supplier::create(['name' => 'Fornecedor', 'tax_id' => '123']);

        $this->asAdmin();

        $this->patchJson("/api/suppliers/{$supplier->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.tax_id', '123'); // o resto não foi tocado

        $this->getJson('/api/suppliers?is_active=1')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/suppliers?is_active=0')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_category_list_counts_only_live_products(): void
    {
        $category = \App\Models\Category::create(['name' => 'Bebidas']);

        $this->product(['category_id' => $category->id]);
        $gone = $this->product(['category_id' => $category->id]);
        $gone->delete();

        $this->asAdmin()
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.products_count', 1);
    }
}
