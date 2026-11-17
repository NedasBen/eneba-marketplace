<?php

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'steam_app_id',
        'name',
        'slug',
        'short_description',
        'description',
        'cover_image_url',
        'header_image_url',
        'hero_image_url',
        'screenshots',
        'released_at',
        'metacritic',
        'developer',
        'publisher',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'steam_app_id' => 'integer',
            'screenshots' => 'array',
            'released_at' => 'date',
            'metacritic' => 'integer',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return BelongsToMany<Genre, $this>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }
}
