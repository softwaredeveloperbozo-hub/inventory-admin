<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleController extends Controller
{
    private const RELATIONS = ['user', 'items.product'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status'   => ['nullable', 'in:completed,cancelled'],
            'search'   => ['nullable', 'string', 'max:100'],
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $sales = Sale::query()
            ->with('user')
            ->withCount('items')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('code', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%");
                });
            })
            ->when($request->from, fn ($q, $date) => $q->whereDate('sold_at', '>=', $date))
            ->when($request->to, fn ($q, $date) => $q->whereDate('sold_at', '<=', $date))
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return SaleResource::collection($sales);
    }

    public function store(StoreSaleRequest $request, SaleService $service): JsonResponse
    {
        $sale = $service->create($request->validated(), $request->user('api')->id);

        return (new SaleResource($sale->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Sale $sale): SaleResource
    {
        return new SaleResource($sale->load(self::RELATIONS));
    }

    public function cancel(Request $request, Sale $sale, SaleService $service): SaleResource
    {
        $service->cancel($sale, $request->user('api')->id);

        return new SaleResource($sale->fresh(self::RELATIONS));
    }
}
