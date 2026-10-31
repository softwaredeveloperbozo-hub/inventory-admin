<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    private const RELATIONS = ['supplier', 'user', 'items.product'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status'      => ['nullable', 'in:draft,confirmed,cancelled'],
            'supplier_id' => ['nullable', 'integer'],
            'per_page'    => ['nullable', 'integer', 'between:1,100'],
        ]);

        $purchases = Purchase::query()
            ->with(['supplier', 'user'])
            ->withCount('items')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->supplier_id, fn ($q, $id) => $q->where('supplier_id', $id))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return PurchaseResource::collection($purchases);
    }

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $purchase = DB::transaction(function () use ($request) {
            $purchase = Purchase::create([
                'supplier_id' => $request->validated('supplier_id'),
                'user_id'     => $request->user('api')->id,
                'status'      => 'draft',
            ]);

            $total = 0;

            foreach ($request->validated('items') as $row) {
                $subtotal = round($row['quantity'] * $row['cost'], 2);

                $purchase->items()->create([
                    'product_id' => $row['product_id'],
                    'quantity'   => $row['quantity'],
                    'cost'       => $row['cost'],
                    'sale_price' => $row['sale_price'],
                    'subtotal'   => $subtotal,
                ]);

                $total += $subtotal;
            }

            $purchase->update(['total' => round($total, 2)]);

            return $purchase;
        });

        return (new PurchaseResource($purchase->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Purchase $purchase): PurchaseResource
    {
        return new PurchaseResource($purchase->load(self::RELATIONS));
    }

    public function confirm(Request $request, Purchase $purchase, PurchaseService $service): PurchaseResource
    {
        $service->confirm($purchase, $request->user('api')->id);

        return new PurchaseResource($purchase->fresh(self::RELATIONS));
    }

    public function cancel(Request $request, Purchase $purchase, PurchaseService $service): PurchaseResource
    {
        $service->cancel($purchase, $request->user('api')->id);

        return new PurchaseResource($purchase->fresh(self::RELATIONS));
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        if ($purchase->status !== 'draft') {
            return response()->json([
                'message' => 'Só rascunhos podem ser excluídos. Cancele a compra.',
            ], 409);
        }

        $purchase->delete();

        return response()->json(null, 204);
    }
}
