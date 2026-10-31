<?php

namespace Tests\Feature;

use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInventory;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase, CreatesInventory;

    public function test_the_summary_ignores_cancelled_sales(): void
    {
        $product = $this->product();
        $this->stockUp($product, 100, 5, 10);

        $this->sell($product, 3);
        $cancelled = $this->sell($product, 2);
        app(SaleService::class)->cancel($cancelled, $this->admin()->id);

        $data = $this->asAdmin()->getJson('/api/dashboard/summary')->assertOk()->json('data');

        $this->assertSame(1, $data['sales_count']);
        $this->assertEquals(30, $data['revenue']);
        $this->assertEquals(15, $data['profit']);
        $this->assertEquals(10, $data['average_ticket'] / 3);
        $this->assertEquals(485, $data['stock_value']['at_cost']);  // 97 x 5
        $this->assertEquals(970, $data['stock_value']['at_sale']);  // 97 x 10
    }

    public function test_the_empty_summary_does_not_divide_by_zero(): void
    {
        $data = $this->asAdmin()->getJson('/api/dashboard/summary')->assertOk()->json('data');

        $this->assertSame(0, $data['sales_count']);
        $this->assertEquals(0, $data['revenue']);
        $this->assertEquals(0, $data['average_ticket']);
    }

    public function test_top_products_are_ordered_by_quantity_and_skip_cancelled_sales(): void
    {
        $a = $this->product(['name' => 'A']);
        $b = $this->product(['name' => 'B']);

        $this->stockUp($a, 100, 5, 10);
        $this->stockUp($b, 100, 5, 10);

        $this->sell($a, 5);
        $this->sell($b, 8);

        $big = $this->sell($a, 40);
        app(SaleService::class)->cancel($big, $this->admin()->id); // cancelada: não conta

        $rows = $this->asAdmin()->getJson('/api/dashboard/top-products')->assertOk()->json('data');

        $this->assertCount(2, $rows);
        $this->assertEquals($b->id, $rows[0]['product_id']);
        $this->assertEquals(8, $rows[0]['quantity_sold']);
        $this->assertEquals(5, $rows[1]['quantity_sold']);
    }

    public function test_top_products_respects_the_limit_and_the_month(): void
    {
        foreach (['A', 'B', 'C'] as $name) {
            $product = $this->product(['name' => $name]);
            $this->stockUp($product, 10, 5, 10);
            $this->sell($product, 1);
        }

        $this->asAdmin();

        $this->getJson('/api/dashboard/top-products?limit=2')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/dashboard/top-products?month=2020-01')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/dashboard/summary?month=2020-01')->assertOk()->assertJsonPath('data.sales_count', 0);
    }

    public function test_an_invalid_month_or_limit_is_rejected(): void
    {
        $this->asAdmin();

        $this->getJson('/api/dashboard/summary?month=2026-13')->assertUnprocessable();
        $this->getJson('/api/dashboard/summary?month=outubro')->assertUnprocessable();
        $this->getJson('/api/dashboard/top-products?limit=0')->assertUnprocessable();
    }

    public function test_sales_by_day_has_one_entry_for_every_day_of_the_month(): void
    {
        $product = $this->product();
        $this->stockUp($product, 10, 5, 10);
        $this->sell($product, 2);

        $rows = $this->asAdmin()->getJson('/api/dashboard/sales-by-day')->assertOk()->json('data');

        $this->assertCount(now()->daysInMonth, $rows);

        $today = collect($rows)->firstWhere('date', now()->toDateString());
        $this->assertSame(1, $today['sales_count']);
        $this->assertEquals(20, $today['revenue']);
    }

    public function test_low_stock_lists_only_active_products_at_or_below_the_minimum(): void
    {
        $low = $this->product(['sku' => 'LOW']);
        $this->stockUp($low, 3, 5, 10);                 // 3 <= mínimo 5

        $this->product(['sku' => 'OUT']);               // nunca comprado: zero

        $ok = $this->product(['sku' => 'OK']);
        $this->stockUp($ok, 50, 5, 10);

        $gone = $this->product(['sku' => 'GONE']);
        $gone->delete();                                // excluído: não aparece

        $rows     = $this->asAdmin()->getJson('/api/dashboard/low-stock')->assertOk()->json('data');
        $statuses = collect($rows)->pluck('status', 'sku')->all();

        $this->assertSame('low', $statuses['LOW']);
        $this->assertSame('out', $statuses['OUT']);
        $this->assertArrayNotHasKey('OK', $statuses);
        $this->assertArrayNotHasKey('GONE', $statuses);
    }
}
