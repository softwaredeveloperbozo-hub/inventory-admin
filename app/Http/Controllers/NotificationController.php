<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    private const MAX_STOCK_ALERTS = 6;
    private const HOT_DAYS = 7;
    private const HOT_LIMIT = 3;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user('api');

        $items = array_merge(
            $this->stockAlerts(),
            $this->missingPrices(),
            $this->pendingPurchases($user),
            $this->hotProducts(),
        );

        return response()->json([
            'data' => $items,
            'meta' => ['count' => count($items)],
        ]);
    }

    // estoque baixo ou zerado, só de produtos que já foram comprados alguma vez
    private function stockAlerts(): array
    {
        $base = Stock::query()
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->whereColumn('stocks.quantity', '<=', 'stocks.min_quantity')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('product_prices')
                    ->whereColumn('product_prices.product_id', 'products.id');
            });

        $total = (clone $base)->count();

        $rows = $base
            ->orderBy('stocks.quantity')
            ->orderBy('products.name')
            ->limit(self::MAX_STOCK_ALERTS)
            ->get(['products.id as product_id', 'products.name', 'stocks.quantity', 'stocks.min_quantity']);

        $items = [];

        foreach ($rows as $row) {
            $out = (float) $row->quantity <= 0;

            $items[] = [
                'key'      => "stock:{$row->product_id}:".(float) $row->quantity,
                'severity' => $out ? 'danger' : 'warning',
                'title'    => ($out ? 'Sem estoque: ' : 'Estoque baixo: ').$row->name,
                'message'  => $out
                    ? 'Registe uma compra para repor.'
                    : 'Restam '.$this->number($row->quantity).' (mínimo '.$this->number($row->min_quantity).').',
                'page'     => 'products',
            ];
        }

        if ($total > self::MAX_STOCK_ALERTS) {
            $more = $total - self::MAX_STOCK_ALERTS;

            $items[] = [
                'key'      => "stock-more:{$more}",
                'severity' => 'warning',
                'title'    => $more === 1 ? 'Mais 1 produto com estoque baixo' : "Mais {$more} produtos com estoque baixo",
                'message'  => 'Veja a lista completa no painel.',
                'page'     => 'dashboard',
            ];
        }

        return $items;
    }

    // produtos ativos que ainda não têm preço (nunca houve uma compra confirmada)
    private function missingPrices(): array
    {
        $count = Product::where('is_active', true)->whereDoesntHave('currentPrice')->count();

        if ($count === 0) {
            return [];
        }

        return [[
            'key'      => "no-price:{$count}",
            'severity' => 'info',
            'title'    => $count === 1 ? '1 produto sem preço' : "{$count} produtos sem preço",
            'message'  => 'Só podem ser vendidos depois de uma compra confirmada.',
            'page'     => 'purchases',
        ]];
    }

    // o administrador vê todos os rascunhos; o operador vê só os seus
    private function pendingPurchases(User $user): array
    {
        $isAdmin = $user->role === 'admin';

        $query = Purchase::where('status', 'draft');

        if (! $isAdmin) {
            $query->where('user_id', $user->id);
        }

        $count = $query->count();

        if ($count === 0) {
            return [];
        }

        if ($isAdmin) {
            $title   = $count === 1 ? '1 compra a aguardar aprovação' : "{$count} compras a aguardar aprovação";
            $message = 'Confirme para somar o estoque e definir os preços.';
        } else {
            $title   = $count === 1 ? 'A sua compra aguarda aprovação' : "{$count} das suas compras aguardam aprovação";
            $message = 'Um administrador precisa de a confirmar.';
        }

        return [[
            'key'      => "drafts:{$count}",
            'severity' => 'info',
            'title'    => $title,
            'message'  => $message,
            'page'     => 'purchases',
        ]];
    }

    // os mais vendidos dos últimos dias
    private function hotProducts(): array
    {
        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', 'completed')
            ->where('sales.sold_at', '>=', now()->subDays(self::HOT_DAYS))
            ->whereNull('products.deleted_at')
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.id as product_id, products.name, SUM(sale_items.quantity) as sold')
            ->orderByDesc('sold')
            ->orderBy('products.name')
            ->limit(self::HOT_LIMIT)
            ->get();

        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'key'      => "hot:{$row->product_id}:".(float) $row->sold,
                'severity' => 'success',
                'title'    => 'Em alta: '.$row->name,
                'message'  => 'Vendeu '.$this->number($row->sold).' un. nos últimos '.self::HOT_DAYS.' dias.',
                'page'     => 'dashboard',
            ];
        }

        return $items;
    }

    private function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, ',', ' '), '0'), ',');
    }
}
