<?php

namespace App\Models;

use App\Observers\ProductObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy(ProductObserver::class)]
class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'sku', 'barcode', 'name',
        'description', 'tax_rate', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate'  => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function currentPrice(): HasOne
    {
        return $this->hasOne(ProductPrice::class)->where('is_active', true);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function scopeFilter(Builder $query, ?string $search = null, $categoryId = null): Builder
    {
        return $query
            ->when($search, function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($categoryId, fn ($q, $id) => $q->where('category_id', $id));
    }

    public function stockLabel(): string
    {
        $quantity = (float) ($this->stock?->quantity ?? 0);

        if ($quantity <= 0) {
            return 'Sem estoque';
        }

        return $this->stock->isLow() ? 'Estoque baixo' : 'OK';
    }

}
