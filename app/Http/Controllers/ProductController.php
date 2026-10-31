<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    private const RELATIONS = ['category', 'stock', 'currentPrice'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search'      => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'per_page'    => ['nullable', 'integer', 'between:1,100'],
        ]);

        $products = Product::query()
            ->with(self::RELATIONS)
            ->filter($request->search, $request->category_id)
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated())->fresh(self::RELATIONS);

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(self::RELATIONS));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->fresh(self::RELATIONS));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, 204);
    }
}
