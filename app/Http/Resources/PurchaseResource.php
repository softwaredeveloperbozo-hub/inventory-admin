<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status,
            'total'        => (float) $this->total,
            'confirmed_at' => $this->confirmed_at,
            'created_at'   => $this->created_at,

            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier
                ? ['id' => $this->supplier->id, 'name' => $this->supplier->name]
                : null),

            'user' => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),

            'items'       => PurchaseItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
