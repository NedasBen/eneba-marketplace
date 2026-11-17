<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One game on one platform for one region, e.g. "ELDEN RING PSN Key EUROPE".
 *
 * min_price_cents, offers_count and sold_count are maintained exclusively by
 * App\Services\Catalog\ProductStockAggregator.
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'game_id',
        'platform_id',
        'region_id',
        'name',
        'slug',
        'retail_price_cents',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retail_price_cents' => 'integer',
            'min_price_cents' => 'integer',
            'offers_count' => 'integer',
            'sold_count' => 'integer',
        ];
    }

    /**
     * Saving of the cheapest in-stock offer against retail, in percent, as SQL.
     * NULL when nothing is in stock; retail is always > 0 (CHECK constraint).
     * Shared by the store's "discount" sort and the Deals homepage block.
     */
    public const DISCOUNT_PERCENT_SQL = '(products.retail_price_cents - products.min_price_cents) * 100.0 / products.retail_price_cents';

    /**
     * Eager loads exactly what ProductCardResource reads (store, homepage rails).
     *
     * @param  Builder<Product>  $query
     */
    public function scopeForCard(Builder $query): void
    {
        $query->with([
            'game:id,name,slug,cover_image_url',
            'platform:id,slug,name,activation_service',
            'region:id,slug,name',
        ]);
    }

    /**
     * Saving of the cheapest in-stock offer against the retail price, in
     * whole percent; null when nothing is in stock.
     */
    public function discountPercent(): ?int
    {
        if ($this->min_price_cents === null || $this->retail_price_cents <= 0) {
            return null;
        }

        return max(0, (int) round(100 * ($this->retail_price_cents - $this->min_price_cents) / $this->retail_price_cents));
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
