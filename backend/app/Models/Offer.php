<?php

namespace App\Models;

use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'seller_id',
        'price_cents',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Adds `stock` (keys in stock, see ProductKey::inStockSql()).
     *
     * @param  Builder<Offer>  $query
     */
    public function scopeWithStock(Builder $query): void
    {
        $query->withCount(['keys as stock' => fn (Builder $keys) => $keys->inStock()]);
    }

    /**
     * Active offers with at least one key in stock: what a buyer can purchase.
     *
     * @param  Builder<Offer>  $query
     */
    public function scopePurchasable(Builder $query): void
    {
        $query->where('offers.is_active', true)->whereHas('keys', fn (Builder $keys) => $keys->inStock());
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * @return HasMany<ProductKey, $this>
     */
    public function keys(): HasMany
    {
        return $this->hasMany(ProductKey::class);
    }
}
