<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase, CreatesInventory;

    private function titles(): \Illuminate\Support\Collection
    {
        return collect($this->getJson('/api/notifications')->assertOk()->json('data'));
    }

    public function test_notifications_require_a_token(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_low_and_out_of_stock_are_reported_with_the_right_severity(): void
    {
        $low = $this->product(['name' => 'Pouco']);
        $this->stockUp($low, 3, 5, 10);

        $out = $this->product(['name' => 'Acabou']);
        $this->stockUp($out, 5, 5, 10);
        $this->sell($out, 5);

        $ok = $this->product(['name' => 'Tranquilo']);
        $this->stockUp($ok, 100, 5, 10);

        $items = $this->asAdmin()->titles();

        $this->assertSame('warning', $items->firstWhere('title', 'Estoque baixo: Pouco')['severity']);
        $this->assertSame('danger', $items->firstWhere('title', 'Sem estoque: Acabou')['severity']);
        $this->assertNull($items->firstWhere('title', 'Estoque baixo: Tranquilo'));
    }

    public function test_products_never_bought_are_grouped_as_missing_a_price(): void
    {
        $this->product(['name' => 'Nunca comprado']);

        $items = $this->asAdmin()->titles();

        $this->assertNull($items->firstWhere('title', 'Sem estoque: Nunca comprado'));
        $this->assertNotNull($items->firstWhere('title', '1 produto sem preço'));
    }

    public function test_the_best_sellers_of_the_week_are_reported(): void
    {
        $product = $this->product(['name' => 'Campeão']);
        $this->stockUp($product, 100, 5, 10);
        $this->sell($product, 7);

        $hot = $this->asAdmin()->titles()->firstWhere('title', 'Em alta: Campeão');

        $this->assertNotNull($hot);
        $this->assertSame('success', $hot['severity']);
        $this->assertStringContainsString('7', $hot['message']);
    }

    public function test_pending_purchases_notify_the_admin_and_only_their_author(): void
    {
        $product = $this->product();

        $this->asOperator()->postJson('/api/purchases', ['items' => [[
            'product_id' => $product->id, 'quantity' => 5, 'cost' => 10, 'sale_price' => 15,
        ]]])->assertCreated();

        $this->assertNotNull($this->asAdmin()->titles()->firstWhere('title', '1 compra a aguardar aprovação'));
        $this->assertNotNull($this->asOperator()->titles()->firstWhere('title', 'A sua compra aguarda aprovação'));

        $other = User::create([
            'name' => 'Outro', 'email' => 'outro@teste.com',
            'password' => '12345678', 'role' => 'operator', 'is_active' => true,
        ]);

        $theirs = $this->actingAs($other, 'api')->titles();
        $this->assertNull($theirs->firstWhere('title', 'A sua compra aguarda aprovação'));
    }

    public function test_the_stock_alerts_are_capped_with_a_summary(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->stockUp($this->product(['name' => "Produto {$i}"]), 2, 5, 10);
        }

        $items = $this->asAdmin()->titles();

        $this->assertSame(6, $items->filter(fn ($i) => str_starts_with($i['title'], 'Estoque baixo:'))->count());
        $this->assertNotNull($items->firstWhere('title', 'Mais 2 produtos com estoque baixo'));
    }
}
