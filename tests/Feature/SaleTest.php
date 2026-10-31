<?php

namespace Tests\Feature;

use App\Models\StockMovement;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase, CreatesInventory;

    private function payload(array $items, array $extra = []): array
    {
        return array_merge(['payment_method' => 'cash', 'items' => $items], $extra);
    }

    public function test_the_server_decides_the_price_and_the_client_cannot_change_it(): void
    {
        $product = $this->product();
        $this->stockUp($product, 10, 5, 10);

        $response = $this->asAdmin()
            ->postJson('/api/sales', $this->payload([
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 0.01, 'unit_cost' => 0],
            ], ['total' => 0.02]))
            ->assertCreated();

        $this->assertEquals(20, $response->json('data.total'));

        $this->assertDatabaseHas('sale_items', [
            'product_id' => $product->id,
            'unit_price' => 10,
            'unit_cost'  => 5,
            'subtotal'   => 20,
        ]);
    }

    public function test_a_sale_lowers_the_stock_and_records_the_movement(): void
    {
        $product = $this->product();
        $this->stockUp($product, 10, 5, 10);

        $id = $this->asAdmin()
            ->postJson('/api/sales', $this->payload([['product_id' => $product->id, 'quantity' => 4]]))
            ->assertCreated()
            ->json('data.id');

        $this->assertEquals(6, $this->stockOf($product));

        $this->assertDatabaseHas('stock_movements', [
            'product_id'     => $product->id,
            'type'           => 'out',
            'source'         => 'sale',
            'quantity'       => 4,
            'balance_after'  => 6,
            'reference_type' => Sale::class,
            'reference_id'   => $id,
        ]);
    }

    public function test_the_sale_code_follows_the_id(): void
    {
        $product = $this->product();
        $this->stockUp($product, 10, 5, 10);

        $response = $this->asAdmin()
            ->postJson('/api/sales', $this->payload([['product_id' => $product->id, 'quantity' => 1]]))
            ->assertCreated();

        $code = $response->json('data.code');

        $this->assertMatchesRegularExpression('/^V-\d{6}$/', $code);
        $this->assertSame(sprintf('V-%06d', $response->json('data.id')), $code);
    }

    public function test_selling_more_than_the_stock_is_refused_and_nothing_is_saved(): void
    {
        $product = $this->product();
        $this->stockUp($product, 3, 5, 10);

        $response = $this->asAdmin()
            ->postJson('/api/sales', $this->payload([['product_id' => $product->id, 'quantity' => 4]]))
            ->assertStatus(409);

        $this->assertStringContainsString('Estoque insuficiente', $response->json('message'));
        $this->assertEquals(3, $this->stockOf($product));
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
    }

    public function test_a_failure_in_one_item_undoes_the_whole_sale(): void
    {
        $plenty = $this->product();
        $short  = $this->product();

        $this->stockUp($plenty, 10, 5, 10);
        $this->stockUp($short, 2, 5, 10);

        $this->asAdmin()
            ->postJson('/api/sales', $this->payload([
                ['product_id' => $plenty->id, 'quantity' => 1],
                ['product_id' => $short->id, 'quantity' => 5],
            ]))
            ->assertStatus(409);

        $this->assertEquals(10, $this->stockOf($plenty)); // o primeiro item chegou a ser baixado, e foi desfeito
        $this->assertEquals(2, $this->stockOf($short));
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(0, StockMovement::where('source', 'sale')->count());
    }

    public function test_a_product_without_price_cannot_be_sold(): void
    {
        $product = $this->product(); // nunca teve compra

        $this->asAdmin()
            ->postJson('/api/sales', $this->payload([['product_id' => $product->id, 'quantity' => 1]]))
            ->assertUnprocessable();

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_an_inactive_product_cannot_be_sold(): void
    {
        $product = $this->product(['is_active' => false]);
        $this->stockUp($product, 10, 5, 10);

        $this->asAdmin()
            ->postJson('/api/sales', $this->payload([['product_id' => $product->id, 'quantity' => 1]]))
            ->assertUnprocessable();
    }

    public function test_discount_reduces_the_total_and_the_profit(): void
    {
        $product = $this->product();
        $this->stockUp($product, 100, 5, 10);

        $response = $this->asAdmin()
            ->postJson('/api/sales', $this->payload(
                [['product_id' => $product->id, 'quantity' => 3]],
                ['discount' => 5],
            ))
            ->assertCreated();

        $this->assertEquals(25, $response->json('data.total'));   // 30 - 5
        $this->assertEquals(10, $response->json('data.profit'));  // (10 - 5) x 3 - 5
    }

    public function test_a_discount_larger_than_the_total_is_refused_and_nothing_is_saved(): void
    {
        $product = $this->product();
        $this->stockUp($product, 100, 5, 10);

        $this->asAdmin()
            ->postJson('/api/sales', $this->payload(
                [['product_id' => $product->id, 'quantity' => 3]],
                ['discount' => 100],
            ))
            ->assertUnprocessable();

        $this->assertEquals(100, $this->stockOf($product));
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_invalid_sales_are_rejected(): void
    {
        $product = $this->product();
        $this->stockUp($product, 10, 5, 10);
        $line = ['product_id' => $product->id, 'quantity' => 1];

        $cases = [
            'sem itens'              => $this->payload([]),
            'sem forma de pagamento' => ['items' => [$line]],
            'forma inválida'         => $this->payload([$line], ['payment_method' => 'fiado']),
            'quantidade zero'        => $this->payload([array_merge($line, ['quantity' => 0])]),
            'produto repetido'       => $this->payload([$line, $line]),
            'data no futuro'         => $this->payload([$line], ['sold_at' => now()->addDays(2)->toDateString()]),
            'desconto negativo'      => $this->payload([$line], ['discount' => -1]),
        ];

        $this->asAdmin();

        foreach ($cases as $name => $payload) {
            $status = $this->postJson('/api/sales', $payload)->status();
            $this->assertSame(422, $status, "Caso: {$name}");
        }

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_cancelling_a_sale_returns_the_stock_once(): void
    {
        $product = $this->product();
        $this->stockUp($product, 10, 5, 10);
        $sale = $this->sell($product, 4);

        $this->asAdmin();

        $this->postJson("/api/sales/{$sale->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertEquals(10, $this->stockOf($product));

        $this->assertDatabaseHas('stock_movements', [
            'product_id'     => $product->id,
            'type'           => 'in',
            'source'         => 'cancellation',
            'quantity'       => 4,
            'reference_type' => Sale::class,
            'reference_id'   => $sale->id,
        ]);

        $this->postJson("/api/sales/{$sale->id}/cancel")->assertStatus(409);
        $this->assertEquals(10, $this->stockOf($product)); // não devolveu duas vezes
    }

    public function test_an_old_sale_keeps_the_price_it_was_made_with(): void
    {
        $product = $this->product();

        $this->stockUp($product, 10, 5, 10);
        $first = $this->sell($product, 1);

        $this->stockUp($product, 10, 8, 15);
        $second = $this->sell($product, 1);

        $this->assertDatabaseHas('sale_items', ['sale_id' => $first->id, 'unit_price' => 10, 'unit_cost' => 5]);
        $this->assertDatabaseHas('sale_items', ['sale_id' => $second->id, 'unit_price' => 15, 'unit_cost' => 8]);
    }

    public function test_sales_can_be_filtered_by_status(): void
    {
        $product = $this->product();
        $this->stockUp($product, 10, 5, 10);

        $this->sell($product, 1);
        $cancelled = $this->sell($product, 1);

        $this->asAdmin();
        $this->postJson("/api/sales/{$cancelled->id}/cancel")->assertOk();

        $this->getJson('/api/sales?status=completed')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/sales?status=cancelled')->assertOk()->assertJsonCount(1, 'data');
    }
}
