<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id', 'product_id', 'quantity',
        'cost', 'sale_price', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:3',
            'cost'       => 'decimal:2',
            'sale_price' => 'decimal:2',
            'subtotal'   => 'decimal:2',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
