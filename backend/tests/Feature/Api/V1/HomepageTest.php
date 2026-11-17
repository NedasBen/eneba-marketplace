<?php

use App\Models\HomepageBlock;
use App\Services\Homepage\HomepageBlockProvider;
use App\Services\Homepage\HomepageBlockRegistry;
use App\Services\Homepage\HomepageBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Register a provider that only exists for the test (e.g. one that throws).
 *
 * @param  Closure(HomepageBlock): ?array  $data
 */
function registerTestBlock(string $type, Closure $data): void
{
    $provider = new class($type, $data) implements HomepageBlockProvider
    {
        private static string $currentType = '';

        public function __construct(string $type, private readonly Closure $resolve)
        {
            self::$currentType = $type;
        }

        public static function type(): string
        {
            return self::$currentType;
        }

        public static function layout(): string
        {
            return 'product_rail';
        }

        public function data(HomepageBlock $block): ?array
        {
            return ($this->resolve)($block);
        }
    };

    app(HomepageBlockRegistry::class)->register($provider);
}

it('returns active blocks in position order with type, layout, title and data', function () {
    catalogProduct(genres: ['rpg'], attributes: ['name' => 'Some RPG']);
    HomepageBlock::factory()->create(['type' => 'genre_rail', 'title' => 'Top RPGs', 'position' => 3, 'settings' => ['genre' => 'rpg']]);
    HomepageBlock::factory()->create(['type' => 'best_sellers', 'title' => 'Best sellers', 'position' => 1]);
    HomepageBlock::factory()->create(['type' => 'new_releases', 'title' => 'New releases', 'position' => 2]);

    $this->getJson('/api/v1/homepage')
        ->assertOk()
        ->assertJsonPath('data.*.title', ['Best sellers', 'New releases', 'Top RPGs'])
        ->assertJsonPath('data.0.type', 'best_sellers')
        ->assertJsonPath('data.0.layout', 'product_rail')
        ->assertJsonPath('data.0.data.products.0.name', 'Some RPG')
        ->assertJsonPath('data.0.data.see_all', '?sort=popularity');
});

it('leaves out inactive blocks', function () {
    catalogProduct();
    HomepageBlock::factory()->create(['type' => 'best_sellers', 'title' => 'Shown', 'position' => 1]);
    HomepageBlock::factory()->create(['type' => 'best_sellers', 'title' => 'Hidden', 'position' => 2, 'is_active' => false]);

    $this->getJson('/api/v1/homepage')->assertOk()->assertJsonPath('data.*.title', ['Shown']);
});

it('leaves out blocks that have nothing to show', function () {
    catalogProduct();
    HomepageBlock::factory()->create(['type' => 'best_sellers', 'title' => 'Best sellers', 'position' => 1]);
    HomepageBlock::factory()->create(['type' => 'genre_rail', 'title' => 'Unknown genre', 'position' => 2, 'settings' => ['genre' => 'opera']]);

    $this->getJson('/api/v1/homepage')->assertOk()->assertJsonPath('data.*.title', ['Best sellers']);
});

it('skips and logs a block whose type has no provider', function () {
    Log::spy();
    catalogProduct();
    HomepageBlock::factory()->create(['type' => 'best_sellers', 'title' => 'Best sellers', 'position' => 1]);
    $mystery = HomepageBlock::factory()->create(['type' => 'mystery_block', 'title' => 'Mystery', 'position' => 2]);

    $this->getJson('/api/v1/homepage')->assertOk()->assertJsonPath('data.*.title', ['Best sellers']);

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'no provider') && $context['block_id'] === $mystery->id);
});

it('serves the rest of the page and logs it when a provider fails', function () {
    Log::spy();
    catalogProduct();
    registerTestBlock('exploding', fn (): never => throw new RuntimeException('Recommendation service is down'));
    HomepageBlock::factory()->create(['type' => 'best_sellers', 'title' => 'Before', 'position' => 1]);
    HomepageBlock::factory()->create(['type' => 'exploding', 'title' => 'Broken', 'position' => 2]);
    HomepageBlock::factory()->create(['type' => 'best_sellers', 'title' => 'After', 'position' => 3]);

    $this->getJson('/api/v1/homepage')->assertOk()->assertJsonPath('data.*.title', ['Before', 'After']);

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'provider failed')
        && $context['type'] === 'exploding'
        && $context['exception'] instanceof RuntimeException);
});

it('cancels a slow provider at the statement timeout and treats it as failed', function () {
    Log::spy();
    config(['homepage.block_timeout_ms' => 100]);
    catalogProduct();
    registerTestBlock('slow', function (): array {
        DB::select('SELECT pg_sleep(2)');

        return ['products' => []];
    });
    HomepageBlock::factory()->create(['type' => 'slow', 'title' => 'Slow', 'position' => 1]);
    HomepageBlock::factory()->create(['type' => 'best_sellers', 'title' => 'Fast', 'position' => 2]);

    $startedAt = microtime(true);
    $this->getJson('/api/v1/homepage')->assertOk()->assertJsonPath('data.*.title', ['Fast']);

    expect(microtime(true) - $startedAt)->toBeLessThan(1.5);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'timed out') && $context['type'] === 'slow');
});

it('caches a complete homepage for the full TTL and answers from the cache without queries', function () {
    config(['homepage.cache_ttl' => 60]);
    catalogProduct();
    HomepageBlock::factory()->create(['type' => 'best_sellers', 'position' => 1]);

    $this->getJson('/api/v1/homepage')->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson('/api/v1/homepage')->assertOk()->assertJsonCount(1, 'data');
    DB::disableQueryLog();

    expect(DB::getQueryLog())->toBe([]);

    $this->travel(59)->seconds();
    expect(Cache::has(HomepageBuilder::CACHE_KEY))->toBeTrue();
    $this->travel(2)->seconds();
    expect(Cache::has(HomepageBuilder::CACHE_KEY))->toBeFalse();
});

it('caches a degraded homepage only briefly so a failed block comes back soon', function () {
    config(['homepage.cache_ttl' => 60, 'homepage.degraded_cache_ttl' => 5]);
    catalogProduct();
    registerTestBlock('exploding', fn (): never => throw new RuntimeException('down'));
    HomepageBlock::factory()->create(['type' => 'exploding', 'position' => 1]);
    HomepageBlock::factory()->create(['type' => 'best_sellers', 'position' => 2]);

    $this->getJson('/api/v1/homepage')->assertOk();

    $this->travel(4)->seconds();
    expect(Cache::has(HomepageBuilder::CACHE_KEY))->toBeTrue();
    $this->travel(2)->seconds();
    expect(Cache::has(HomepageBuilder::CACHE_KEY))->toBeFalse();
});

it('returns an empty list when no block is configured', function () {
    $this->getJson('/api/v1/homepage')->assertOk()->assertExactJson(['data' => []]);
});
