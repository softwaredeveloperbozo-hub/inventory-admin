<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'product'    => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'sku'  => $this->product->sku,
                'name' => $this->product->name,
            ]),
            'quantity'   => (float) $this->quantity,
            'cost'       => (float) $this->cost,
            'sale_price' => (float) $this->sale_price,
            'subtotal'   => (float) $this->subtotal,
        ];
    }
}
