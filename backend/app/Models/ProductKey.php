<?php

namespace App\Models;

use App\Enums\ProductKeyStatus;
use Database\Factories\ProductKeyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductKey extends Model
{
    /** @use HasFactory<ProductKeyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'offer_id',
        'code',
        'status',
        'reserved_until',
        'order_item_id',
    ];

    /**
     * The license code must only ever leave the API through an explicit,
     * ownership-checked resource, never by serializing the model.
     *
     * @var list<string>
     */
    protected $hidden = [
        'code',
    ];

    /**
     * The single definition of "in stock": available, or reserved with an
     * expired hold (lazy expiry: an expired reservation is free stock).
     * Used by the store aggregates and the product page's offer stock, so
     * the two can never disagree.
     */
    public static function inStockSql(string $table = 'product_keys'): string
    {
        return "({$table}.status = 'available' OR ({$table}.status = 'reserved' AND {$table}.reserved_until < now()))";
    }

    /**
     * @param  Builder<ProductKey>  $query
     */
    public function scopeInStock(Builder $query): void
    {
        $query->whereRaw(self::inStockSql($query->getModel()->getTable()));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => 'encrypted',
            'status' => ProductKeyStatus::class,
            'reserved_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
