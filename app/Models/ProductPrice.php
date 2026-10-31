<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    protected $fillable = [
        'product_id', 'cost_price', 'sale_price',
        'purchase_item_id', 'is_active', 'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'cost_price'     => 'decimal:2',
            'sale_price'     => 'decimal:2',
            'is_active'      => 'boolean',
            'effective_from' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }
}
