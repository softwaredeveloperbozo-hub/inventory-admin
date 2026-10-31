<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
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
            'unit_price' => (float) $this->unit_price,
            'unit_cost'  => (float) $this->unit_cost,
            'tax_rate'   => (float) $this->tax_rate,
            'subtotal'   => (float) $this->subtotal,
        ];
    }
}
