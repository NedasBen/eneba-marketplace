<?php

use App\Enums\GamePlatform;
use App\Services\Catalog\SteamGameNormalizer;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sellableSteamDetails(array $overrides = []): array
{
    return array_replace([
        'type' => 'game',
        'name' => 'ELDEN RING',
        'steam_appid' => 1245620,
        'is_free' => false,
        'price_overview' => ['currency' => 'EUR', 'initial' => 5999, 'final' => 5999],
        'header_image' => 'https://cdn.example/header.jpg',
        'release_date' => ['date' => '24 Feb, 2022'],
    ], $overrides);
}

it('accepts a paid game whose name matches the curated name', function () {
    expect((new SteamGameNormalizer)->skipReason(sellableSteamDetails(), 'Elden Ring'))->toBeNull();
});

it('explains why an app cannot be imported', function (array $overrides, string $expectedName, string $reason) {
    expect((new SteamGameNormalizer)->skipReason(sellableSteamDetails($overrides), $expectedName))
        ->toContain($reason);
})->with([
    'downloadable content' => [['type' => 'dlc'], 'ELDEN RING', 'not a game'],
    'free to play' => [['is_free' => true], 'ELDEN RING', 'free to play'],
    'no price' => [['price_overview' => null], 'ELDEN RING', 'no EUR price'],
    'non-euro price' => [['price_overview' => ['currency' => 'USD', 'initial' => 5999]], 'ELDEN RING', 'no EUR price'],
    'mistyped app id' => [['name' => 'Goat Simulator'], 'ELDEN RING', 'app id points to "Goat Simulator"'],
]);

it('tolerates trademark symbols and punctuation when matching names', function () {
    $details = sellableSteamDetails(['name' => 'Sekiro™: Shadows Die Twice - GOTY Edition']);

    expect((new SteamGameNormalizer)->skipReason($details, 'Sekiro: Shadows Die Twice'))->toBeNull();
});

it('converts Steam description HTML into plain text', function () {
    $html = '<strong>BIG &amp; BOLD</strong><br><h2 class="bb_tag">Features</h2><ul class="bb_ul"><li>Open&nbsp;world</li><li>Co-op</li></ul><p>It&#39;s <script>alert(1)</script>here.</p><img src="x.gif">';

    expect((new SteamGameNormalizer)->htmlToPlainText($html))
        ->toBe("BIG & BOLD\nFeatures\n\n• Open world\n• Co-op\n\nIt's alert(1)here.");
});

it('falls back to the short description when the long description is only images', function () {
    $game = (new SteamGameNormalizer)->normalize(
        sellableSteamDetails([
            'short_description' => 'An open-world RPG.',
            'about_the_game' => '<img src="https://cdn.example/banner.gif"><br><img src="https://cdn.example/feature.gif">',
        ]),
        [GamePlatform::Pc],
        null,
        null,
    );

    expect($game['description'])->toBe('An open-world RPG.');
});

it('removes trademark symbols from game names', function () {
    expect((new SteamGameNormalizer)->cleanName('  DARK SOULS™ III ® '))->toBe('DARK SOULS III');
});

it('parses the release date formats Steam uses', function (string $steamDate, ?string $expected) {
    $game = (new SteamGameNormalizer)->normalize(
        sellableSteamDetails(['release_date' => ['date' => $steamDate]]),
        [GamePlatform::Pc],
        null,
        null,
    );

    expect($game['released_at'])->toBe($expected);
})->with([
    'day first' => ['24 Feb, 2022', '2022-02-24'],
    'month first' => ['Feb 24, 2022', '2022-02-24'],
    'month only' => ['Feb 2022', '2022-02-01'],
    'unannounced' => ['Coming soon', null],
]);

it('drops business-model labels from genres', function () {
    $game = (new SteamGameNormalizer)->normalize(
        sellableSteamDetails(['genres' => [['description' => 'Action'], ['description' => 'Free To Play'], ['description' => 'Early Access'], ['description' => 'RPG']]]),
        [GamePlatform::Pc, GamePlatform::Xbox],
        null,
        null,
    );

    expect($game)->genres->toBe(['Action', 'RPG'])
        ->platforms->toBe(['pc', 'xbox']);
});
