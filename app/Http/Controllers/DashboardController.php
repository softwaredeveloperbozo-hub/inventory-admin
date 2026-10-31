<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        $sales = Sale::where('status', 'completed')->whereBetween('sold_at', [$from, $to]);

        $salesCount = (clone $sales)->count();
        $revenue    = (float) (clone $sales)->sum('total');
        $discount   = (float) (clone $sales)->sum('discount');

        $cost = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$from, $to])
            ->sum(DB::raw('sale_items.unit_cost * sale_items.quantity'));

        $stockValue = Stock::query()
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->join('product_prices', function ($join) {
                $join->on('product_prices.product_id', '=', 'stocks.product_id')
                     ->where('product_prices.is_active', true);
            })
            ->whereNull('products.deleted_at')
            ->selectRaw('COALESCE(SUM(stocks.quantity * product_prices.cost_price), 0) as at_cost,
                         COALESCE(SUM(stocks.quantity * product_prices.sale_price), 0) as at_sale')
            ->first();

        return response()->json(['data' => [
            'period'          => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'sales_count'     => $salesCount,
            'revenue'         => round($revenue, 2),
            'profit'          => round($revenue - $cost, 2),
            'average_ticket'  => $salesCount > 0 ? round($revenue / $salesCount, 2) : 0,
            'total_discount'  => round($discount, 2),
            'products_count'  => Product::where('is_active', true)->count(),
            'low_stock_count' => $this->lowStockQuery()->count(),
            'stock_value'     => [
                'at_cost' => round((float) $stockValue->at_cost, 2),
                'at_sale' => round((float) $stockValue->at_sale, 2),
            ],
        ]]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['nullable', 'integer', 'between:1,20'],
        ]);

        [$from, $to] = $this->period($request);

        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$from, $to])
            ->groupBy('products.id', 'products.sku', 'products.name')
            ->selectRaw('products.id as product_id, products.sku, products.name,
                         SUM(sale_items.quantity) as quantity_sold,
                         SUM(sale_items.subtotal) as revenue')
            ->orderByDesc('quantity_sold')
            ->orderBy('products.name')
            ->limit($request->integer('limit', 5))
            ->get()
            ->map(fn ($row) => [
                'product_id'    => $row->product_id,
                'sku'           => $row->sku,
                'name'          => $row->name,
                'quantity_sold' => (float) $row->quantity_sold,
                'revenue'       => round((float) $row->revenue, 2),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function salesByDay(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        $rows = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$from, $to])
            ->selectRaw('date(sold_at) as day, COUNT(*) as sales_count, SUM(total) as revenue')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $series = collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->map(function ($date) use ($rows) {
                $key = $date->toDateString();
                $row = $rows->get($key);

                return [
                    'date'        => $key,
                    'sales_count' => (int) ($row->sales_count ?? 0),
                    'revenue'     => round((float) ($row->revenue ?? 0), 2),
                ];
            })
            ->values();

        return response()->json(['data' => $series]);
    }

    public function lowStock(): JsonResponse
    {
        $items = $this->lowStockQuery()
            ->orderBy('stocks.quantity')
            ->orderBy('products.name')
            ->get([
                'products.id as product_id',
                'products.sku',
                'products.name',
                'stocks.quantity',
                'stocks.min_quantity',
            ])
            ->map(fn ($row) => [
                'product_id'   => $row->product_id,
                'sku'          => $row->sku,
                'name'         => $row->name,
                'quantity'     => (float) $row->quantity,
                'min_quantity' => (float) $row->min_quantity,
                'status'       => (float) $row->quantity <= 0 ? 'out' : 'low',
            ]);

        return response()->json(['data' => $items]);
    }

    private function lowStockQuery(): Builder
    {
        return Stock::query()
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->whereColumn('stocks.quantity', '<=', 'stocks.min_quantity');
    }

    private function period(Request $request): array
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $start = $request->filled('month')
            ? Carbon::createFromFormat('!Y-m', $request->month)->startOfMonth()
            : now()->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }
}
