<?php

use App\Services\Homepage\Blocks\BestSellersBlock;
use App\Services\Homepage\Blocks\DealsBlock;
use App\Services\Homepage\Blocks\GenreRailBlock;
use App\Services\Homepage\Blocks\HeroBannerBlock;
use App\Services\Homepage\Blocks\NewReleasesBlock;
use App\Services\Homepage\HomepageBlockRegistry;
use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete(HomepageBlockRegistry::cachePath());
});

it('discovers every provider class in the Blocks directory by its type', function () {
    expect(HomepageBlockRegistry::discover())->toBe([
        'best_sellers' => BestSellersBlock::class,
        'deals' => DealsBlock::class,
        'genre_rail' => GenreRailBlock::class,
        'hero_banner' => HeroBannerBlock::class,
        'new_releases' => NewReleasesBlock::class,
    ]);
});

it('resolves providers by type and returns null for unknown types', function () {
    $registry = app(HomepageBlockRegistry::class);

    expect($registry->for('hero_banner'))->toBeInstanceOf(HeroBannerBlock::class)
        ->and($registry->for('not_a_block'))->toBeNull();
});

it('uses the cached class map instead of scanning the directory when it exists', function () {
    // A map the directory scan could never produce proves the cache file was read.
    File::put(HomepageBlockRegistry::cachePath(), '<?php return '.var_export(['only_cached' => BestSellersBlock::class], true).';');

    $registry = new HomepageBlockRegistry(app());

    expect($registry->types())->toBe(['only_cached'])
        ->and($registry->for('best_sellers'))->toBeNull();
});

it('writes the cache with homepage:cache and removes it with optimize:clear', function () {
    $this->artisan('homepage:cache')->assertSuccessful();

    expect(require HomepageBlockRegistry::cachePath())->toBe(HomepageBlockRegistry::discover());

    $this->artisan('optimize:clear')->assertSuccessful();

    expect(File::exists(HomepageBlockRegistry::cachePath()))->toBeFalse();
});
