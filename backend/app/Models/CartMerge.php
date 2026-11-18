<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A processed guest-cart merge (idempotency record for POST /cart/merge).
 * Kept for a week: long enough to cover any client retry.
 */
class CartMerge extends Model
{
    use MassPrunable;

    public const RETENTION_DAYS = 7;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'merge_id',
        'adjustments',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adjustments' => 'array',
        ];
    }

    /**
     * @return Builder<CartMerge>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
