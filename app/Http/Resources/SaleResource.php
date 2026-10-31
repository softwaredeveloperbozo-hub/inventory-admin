<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'status'         => $this->status,
            'customer_name'  => $this->customer_name,
            'payment_method' => $this->payment_method,
            'discount'       => (float) $this->discount,
            'total'          => (float) $this->total,
            'sold_at'        => $this->sold_at,

            'user' => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),

            'items'       => SaleItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),

            'profit' => $this->whenLoaded('items', fn () => round(
                $this->items->sum(
                    fn ($item) => ((float) $item->unit_price - (float) $item->unit_cost) * (float) $item->quantity
                ) - (float) $this->discount,
                2
            )),
        ];
    }
}
