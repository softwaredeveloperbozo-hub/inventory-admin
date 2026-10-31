<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'sku'         => $this->sku,
            'barcode'     => $this->barcode,
            'name'        => $this->name,
            'description' => $this->description,
            'tax_rate'    => (float) $this->tax_rate,
            'is_active'   => $this->is_active,

            'category' => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),

            'stock' => $this->whenLoaded('stock', fn () => [
                'quantity'     => (float) $this->stock->quantity,
                'min_quantity' => (float) $this->stock->min_quantity,
                'is_low'       => $this->stock->isLow(),
            ]),

            'price' => $this->whenLoaded('currentPrice', fn () => [
                'cost_price' => (float) $this->currentPrice->cost_price,
                'sale_price' => (float) $this->currentPrice->sale_price,
            ]),

            'created_at' => $this->created_at,
        ];
    }
}
