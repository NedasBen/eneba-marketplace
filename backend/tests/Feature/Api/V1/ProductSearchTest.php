<?php

/**
 * Real catalog names, including deliberate near-misses (Elder Scrolls vs
 * Elden Ring, GTFO vs GTA, Fallout 76 vs Fallout 4, Ready or Not vs Red Dead).
 */
function seedSearchCatalog(): void
{
    $games = [
        'The Witcher 3: Wild Hunt — Remastered' => 40,
        'ELDEN RING' => 90,
        'The Elder Scrolls V: Skyrim Special Edition' => 60,
        'Grand Theft Auto V Enhanced' => 80,
        'GTFO' => 10,
        'Cyberpunk 2077' => 70,
        'Red Dead Redemption 2' => 50,
        'Ready or Not' => 20,
        'Monster Hunter Wilds' => 30,
        'Fallout 4' => 15,
        'Fallout 76' => 25,
        "Baldur's Gate 3" => 85,
        'Diablo IV' => 35,
        'Resident Evil 4' => 45,
    ];

    foreach ($games as $name => $soldCount) {
        catalogProduct(attributes: ['name' => "{$name} Steam Key EUROPE", 'sold_count' => $soldCount], game: ['name' => $name]);
    }
}

/**
 * @return list<string> game names in result order
 */
function searchResultNames(string $query): array
{
    return test()->getJson('/api/v1/products?'.$query)->assertOk()->json('data.*.game.name');
}

it('finds the intended game first despite typos, partial names, numerals and acronyms', function (string $query, string $expectedFirst) {
    seedSearchCatalog();

    expect(searchResultNames('q='.urlencode($query))[0] ?? null)->toBe($expectedFirst);
})->with([
    'swapped letters' => ['wichter', 'The Witcher 3: Wild Hunt — Remastered'],
    'missing letter' => ['eldn ring', 'ELDEN RING'],
    'acronym with arabic numeral' => ['gta 5', 'Grand Theft Auto V Enhanced'],
    'missing vowel' => ['cyberpnk', 'Cyberpunk 2077'],
    'partial name' => ['red dead', 'Red Dead Redemption 2'],
    'acronym with roman numeral' => ['gtav', 'Grand Theft Auto V Enhanced'],
    'acronym with number' => ['rdr2', 'Red Dead Redemption 2'],
    'apostrophe dropped' => ['bg3', "Baldur's Gate 3"],
    'digit for a roman numeral' => ['diablo 4', 'Diablo IV'],
    'word still being typed' => ['cyber', 'Cyberpunk 2077'],
    'word inside the name' => ['witcher 3', 'The Witcher 3: Wild Hunt — Remastered'],
]);

it('reports the original and corrected query so the UI can explain the results', function () {
    seedSearchCatalog();

    $this->getJson('/api/v1/products?q=wichter')
        ->assertOk()
        ->assertJsonPath('query', ['original' => 'wichter', 'corrected' => 'witcher']);

    $this->getJson('/api/v1/products?q=Red%20Dead')
        ->assertJsonPath('query', ['original' => 'Red Dead', 'corrected' => null]);

    $this->getJson('/api/v1/products')->assertJsonPath('query', null);
});

it('ranks the closer name first when several games match equally well', function () {
    seedSearchCatalog();

    expect(array_slice(searchResultNames('q=fallout%204'), 0, 2))->toBe(['Fallout 4', 'Fallout 76']);
});

it('ranks exact and prefix matches above word matches and those above fuzzy matches', function () {
    seedSearchCatalog();

    // "elden ring" = exact; "elde" is a prefix of ELDEN RING and a word prefix in Elder Scrolls.
    expect(searchResultNames('q=elde'))->toBe(['ELDEN RING', 'The Elder Scrolls V: Skyrim Special Edition']);
});

it('returns no results for a query that matches nothing', function () {
    seedSearchCatalog();

    expect(searchResultNames('q=zzzzqx'))->toBe([]);
});

it('matches two-letter queries only at the start of words, without typo tolerance', function () {
    seedSearchCatalog();

    expect(searchResultNames('q=re'))->toEqualCanonicalizing(['Red Dead Redemption 2', 'Ready or Not', 'Resident Evil 4', 'The Witcher 3: Wild Hunt — Remastered'])
        ->and(searchResultNames('q=el'))->toEqualCanonicalizing(['ELDEN RING', 'The Elder Scrolls V: Skyrim Special Edition']);
});

it('ignores single-character queries', function () {
    seedSearchCatalog();

    $this->getJson('/api/v1/products?q=e&per_page=48')
        ->assertOk()
        ->assertJsonCount(14, 'data')
        ->assertJsonPath('query', null);
});

it('never corrects numbers', function () {
    seedSearchCatalog();

    $this->getJson('/api/v1/products?q=fallout%2077')
        ->assertOk()
        ->assertJsonPath('query.corrected', null);
});

it('keeps filtering by the query when another sort is chosen', function () {
    seedSearchCatalog();

    expect(searchResultNames('q=fallout&sort=name'))->toBe(['Fallout 4', 'Fallout 76'])
        ->and(searchResultNames('q=fallout&sort=popularity'))->toBe(['Fallout 76', 'Fallout 4']);
});

it('falls back to popularity when relevance is requested without a query', function () {
    catalogProduct(attributes: ['sold_count' => 1], game: ['name' => 'Less popular']);
    catalogProduct(attributes: ['sold_count' => 9], game: ['name' => 'More popular']);

    expect(searchResultNames('sort=relevance'))->toBe(['More popular', 'Less popular']);
});

it('combines the query with facet filters', function () {
    catalogProduct('pc', game: ['name' => 'Fallout 4']);
    catalogProduct('xbox', game: ['name' => 'Fallout 4']);
    catalogProduct('xbox', game: ['name' => 'Cyberpunk 2077']);

    $this->getJson('/api/v1/products?q=fallout&platform=xbox')
        ->assertOk()
        ->assertJsonPath('data.*.game.name', ['Fallout 4'])
        ->assertJsonPath('data.0.platform.slug', 'xbox');
});

it('counts filter options within the search results and defaults to relevance', function () {
    catalogProduct('pc', game: ['name' => 'Fallout 4']);
    catalogProduct('xbox', game: ['name' => 'Fallout 76']);
    catalogProduct('xbox', game: ['name' => 'Cyberpunk 2077']);

    $facets = $this->getJson('/api/v1/catalog/filters?q=fallout')->assertOk()->json('data');

    expect(array_column($facets['platforms'], 'count', 'slug'))->toBe(['pc' => 1, 'xbox' => 1])
        ->and($facets['default_sort'])->toBe('relevance');
});

it('picks up a newly saved game because saving clears the cached vocabulary', function () {
    catalogProduct(game: ['name' => 'Cyberpunk 2077']);

    $this->getJson('/api/v1/products?q=wichter')->assertOk()->assertJsonCount(0, 'data');

    catalogProduct(game: ['name' => 'The Witcher 3: Wild Hunt']);

    $this->getJson('/api/v1/products?q=wichter')
        ->assertOk()
        ->assertJsonPath('data.0.game.name', 'The Witcher 3: Wild Hunt')
        ->assertJsonPath('query.corrected', 'witcher');
});
