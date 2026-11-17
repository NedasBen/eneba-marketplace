<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

const ELDEN_RING_APP_ID = 1245620;
const HOLLOW_KNIGHT_APP_ID = 367520;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function steamAppDetails(int $steamAppId, string $name, array $overrides = []): array
{
    $fixture = json_decode(File::get(base_path('tests/Fixtures/steam/appdetails-elden-ring.json')), true);

    return array_replace($fixture[(string) ELDEN_RING_APP_ID]['data'], ['steam_appid' => $steamAppId, 'name' => $name], $overrides);
}

/**
 * Fake the Steam store API. `$detailsByAppId` maps app ids to `data` payloads;
 * a closure value is called per request, and a missing id returns success:false.
 *
 * @param  array<int, array<string, mixed>|Closure>  $detailsByAppId
 */
function fakeSteamStore(array $detailsByAppId, int $assetStatus = 200): void
{
    Sleep::fake();
    Http::preventStrayRequests();

    Http::fake([
        'store.steampowered.com/api/appdetails*' => function (Request $request) use ($detailsByAppId) {
            $steamAppId = (int) $request->data()['appids'];
            $details = $detailsByAppId[$steamAppId] ?? null;

            if ($details instanceof Closure) {
                return $details();
            }

            return Http::response([(string) $steamAppId => $details === null
                ? ['success' => false]
                : ['success' => true, 'data' => $details]]);
        },
        'shared.akamai.steamstatic.com/*' => Http::response(status: $assetStatus),
    ]);
}

/**
 * @param  list<array{steam_app_id: int, name: string, platforms: list<string>}>  $sources
 * @return array{source: string, output: string}
 */
function steamCatalogFiles(array $sources, ?array $existingGames = null): array
{
    $directory = storage_path('framework/testing/steam-catalog/'.Str::random(8));
    File::ensureDirectoryExists($directory);

    $paths = ['source' => "{$directory}/sources.json", 'output' => "{$directory}/games.json"];
    File::put($paths['source'], json_encode($sources));

    if ($existingGames !== null) {
        File::put($paths['output'], json_encode($existingGames));
    }

    return $paths;
}

/**
 * @return list<array<string, mixed>>
 */
function readSeedGames(string $path): array
{
    return json_decode(File::get($path), true);
}

$eldenRingSource = ['steam_app_id' => ELDEN_RING_APP_ID, 'name' => 'ELDEN RING', 'platforms' => ['pc', 'playstation', 'xbox']];
$hollowKnightSource = ['steam_app_id' => HOLLOW_KNIGHT_APP_ID, 'name' => 'Hollow Knight', 'platforms' => ['pc', 'nintendo-switch']];

it('writes a normalized game with its curated platforms and portrait cover', function () use ($eldenRingSource) {
    fakeSteamStore([ELDEN_RING_APP_ID => steamAppDetails(ELDEN_RING_APP_ID, 'ELDEN RING')]);
    $files = steamCatalogFiles([$eldenRingSource]);

    $this->artisan('catalog:fetch-steam', ['--source' => $files['source'], '--output' => $files['output']])
        ->assertSuccessful();

    $games = readSeedGames($files['output']);
    $assetBase = 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/1245620';

    expect($games)->toHaveCount(1)
        ->and($games[0])->toMatchArray([
            'steam_app_id' => ELDEN_RING_APP_ID,
            'name' => 'ELDEN RING',
            'slug' => 'elden-ring',
            'platforms' => ['pc', 'playstation', 'xbox'],
            'genres' => ['Action', 'RPG'],
            'developer' => 'FromSoftware, Inc.',
            'publisher' => 'FromSoftware, Inc., Bandai Namco Entertainment',
            'released_at' => '2022-02-24',
            'metacritic' => 94,
            'retail_price_cents' => 5999,
            'cover_image_url' => "{$assetBase}/library_600x900_2x.jpg",
            'hero_image_url' => "{$assetBase}/library_hero.jpg",
        ])
        ->and($games[0]['description'])->toStartWith('THE CRITICALLY ACCLAIMED FANTASY ACTION RPG')->not->toContain('<')
        ->and($games[0]['screenshots'])->toHaveCount(6);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api/appdetails')
        && $request->data() === ['appids' => ELDEN_RING_APP_ID, 'cc' => 'lt', 'l' => 'english']);
});

it('falls back to the header and background images when Steam has no library artwork', function () use ($eldenRingSource) {
    $details = steamAppDetails(ELDEN_RING_APP_ID, 'ELDEN RING');
    fakeSteamStore([ELDEN_RING_APP_ID => $details], assetStatus: 404);
    $files = steamCatalogFiles([$eldenRingSource]);

    $this->artisan('catalog:fetch-steam', ['--source' => $files['source'], '--output' => $files['output']])
        ->assertSuccessful();

    expect(readSeedGames($files['output'])[0])
        ->cover_image_url->toBe($details['header_image'])
        ->hero_image_url->toBe($details['background_raw']);
});

it('skips games already in the output file', function () use ($eldenRingSource, $hollowKnightSource) {
    fakeSteamStore([HOLLOW_KNIGHT_APP_ID => steamAppDetails(HOLLOW_KNIGHT_APP_ID, 'Hollow Knight')]);
    $cachedGame = ['steam_app_id' => ELDEN_RING_APP_ID, 'name' => 'Cached Elden Ring'];
    $files = steamCatalogFiles([$eldenRingSource, $hollowKnightSource], existingGames: [$cachedGame]);

    $this->artisan('catalog:fetch-steam', ['--source' => $files['source'], '--output' => $files['output']])
        ->assertSuccessful();

    expect(array_column(readSeedGames($files['output']), 'name'))->toBe(['Cached Elden Ring', 'Hollow Knight']);
    Http::assertSentCount(3); // Hollow Knight: appdetails + cover HEAD + hero HEAD
    Http::assertNotSent(fn (Request $request) => (int) ($request->data()['appids'] ?? 0) === ELDEN_RING_APP_ID);
});

it('re-fetches games already in the output file when --fresh is passed', function () use ($eldenRingSource) {
    fakeSteamStore([ELDEN_RING_APP_ID => steamAppDetails(ELDEN_RING_APP_ID, 'ELDEN RING')]);
    $files = steamCatalogFiles([$eldenRingSource], existingGames: [['steam_app_id' => ELDEN_RING_APP_ID, 'name' => 'Cached Elden Ring']]);

    $this->artisan('catalog:fetch-steam', ['--fresh' => true, '--source' => $files['source'], '--output' => $files['output']])
        ->assertSuccessful();

    expect(readSeedGames($files['output']))->sequence(fn ($game) => $game->name->toBe('ELDEN RING'));
});

it('drops games that were removed from the source list', function () use ($hollowKnightSource) {
    fakeSteamStore([HOLLOW_KNIGHT_APP_ID => steamAppDetails(HOLLOW_KNIGHT_APP_ID, 'Hollow Knight')]);
    $files = steamCatalogFiles([$hollowKnightSource], existingGames: [['steam_app_id' => ELDEN_RING_APP_ID, 'name' => 'ELDEN RING']]);

    $this->artisan('catalog:fetch-steam', ['--source' => $files['source'], '--output' => $files['output']])
        ->assertSuccessful();

    expect(array_column(readSeedGames($files['output']), 'steam_app_id'))->toBe([HOLLOW_KNIGHT_APP_ID]);
});

it('does not write games that Steam cannot sell in the configured country', function () use ($eldenRingSource) {
    fakeSteamStore([]);
    $files = steamCatalogFiles([$eldenRingSource]);

    $this->artisan('catalog:fetch-steam', ['--source' => $files['source'], '--output' => $files['output']])
        ->expectsOutputToContain('not available in the Steam store for this country')
        ->assertSuccessful();

    expect(readSeedGames($files['output']))->toBe([]);
});

it('saves progress and fails when Steam keeps rate limiting, then resumes on the next run', function () use ($eldenRingSource, $hollowKnightSource) {
    $isRateLimited = true;
    fakeSteamStore([
        ELDEN_RING_APP_ID => steamAppDetails(ELDEN_RING_APP_ID, 'ELDEN RING'),
        HOLLOW_KNIGHT_APP_ID => function () use (&$isRateLimited) {
            return $isRateLimited
                ? Http::response('Too Many Requests', 429)
                : Http::response([(string) HOLLOW_KNIGHT_APP_ID => ['success' => true, 'data' => steamAppDetails(HOLLOW_KNIGHT_APP_ID, 'Hollow Knight')]]);
        },
    ]);
    $files = steamCatalogFiles([$eldenRingSource, $hollowKnightSource]);
    $arguments = ['--source' => $files['source'], '--output' => $files['output']];

    $this->artisan('catalog:fetch-steam', $arguments)
        ->expectsOutputToContain('run the command again to resume')
        ->assertFailed();

    expect(array_column(readSeedGames($files['output']), 'name'))->toBe(['ELDEN RING']);

    $isRateLimited = false;

    $this->artisan('catalog:fetch-steam', $arguments)->assertSuccessful();

    expect(array_column(readSeedGames($files['output']), 'name'))->toBe(['ELDEN RING', 'Hollow Knight']);
});

it('waits the configured delay between Steam games', function () use ($eldenRingSource, $hollowKnightSource) {
    config(['services.steam.request_delay_ms' => 1500]);
    fakeSteamStore([
        ELDEN_RING_APP_ID => steamAppDetails(ELDEN_RING_APP_ID, 'ELDEN RING'),
        HOLLOW_KNIGHT_APP_ID => steamAppDetails(HOLLOW_KNIGHT_APP_ID, 'Hollow Knight'),
    ]);
    $files = steamCatalogFiles([$eldenRingSource, $hollowKnightSource]);

    $this->artisan('catalog:fetch-steam', ['--source' => $files['source'], '--output' => $files['output']])
        ->assertSuccessful();

    Sleep::assertSequence([Sleep::for(1500)->milliseconds()]);
});

it('rejects a source list entry with an unknown platform', function () {
    fakeSteamStore([]);
    $files = steamCatalogFiles([['steam_app_id' => ELDEN_RING_APP_ID, 'name' => 'ELDEN RING', 'platforms' => ['dreamcast']]]);

    $this->artisan('catalog:fetch-steam', ['--source' => $files['source'], '--output' => $files['output']])
        ->expectsOutputToContain('Unknown platform "dreamcast"')
        ->assertFailed();

    Http::assertNothingSent();
});

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing/steam-catalog'));
});
