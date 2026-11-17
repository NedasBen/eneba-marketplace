<?php

namespace App\Models;

use App\Casts\PostgresTextArray;
use App\Services\Search\GameSearchText;
use App\Services\Search\SearchVocabulary;
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
     * Keep the derived search columns in step with the name, and drop the
     * cached typo-correction vocabulary whenever the catalog changes.
     */
    protected static function booted(): void
    {
        static::saving(function (Game $game): void {
            if ($game->isDirty('name') || ! $game->exists) {
                $game->search_name = GameSearchText::normalize($game->name);
                $game->search_tokens = GameSearchText::tokens($game->name);
            }
        });

        static::saved(fn () => app(SearchVocabulary::class)->forget());
        static::deleted(fn () => app(SearchVocabulary::class)->forget());
    }

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
            'search_tokens' => PostgresTextArray::class,
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
