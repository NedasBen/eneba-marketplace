<?php

namespace App\Models;

use App\Enums\ProductKeyStatus;
use Database\Factories\ProductKeyFactory;
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
