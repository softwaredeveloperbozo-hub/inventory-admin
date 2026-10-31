<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    protected $fillable = ['product_id', 'quantity', 'min_quantity'];

    protected function casts(): array
    {
        return [
            'quantity'     => 'decimal:3',
            'min_quantity' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isLow(): bool
    {
        return (float) $this->quantity <= (float) $this->min_quantity;
    }
}
