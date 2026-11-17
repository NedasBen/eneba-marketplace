<?php

use App\Services\Search\GameSearchText;

it('normalizes names and queries the same way', function (string $input, string $expected) {
    expect(GameSearchText::normalize($input))->toBe($expected);
})->with([
    'trademarks and case' => ['DARK SOULS™ III', 'dark souls 3'],
    'apostrophes join words' => ["Baldur's Gate 3", 'baldurs gate 3'],
    'punctuation becomes spaces' => ['The Witcher 3: Wild Hunt — Remastered', 'the witcher 3 wild hunt remastered'],
    'roman numerals become digits' => ['Grand Theft Auto V Enhanced', 'grand theft auto 5 enhanced'],
    'accents are folded' => ['Pokémon Légendes', 'pokemon legendes'],
    'a lone "I" stays a word' => ['I Am Bread', 'i am bread'],
    'extra whitespace collapses' => ['  red   dead  ', 'red dead'],
]);

it('adds acronyms in digit and roman form to the name tokens', function () {
    expect(GameSearchText::tokens('Grand Theft Auto V Enhanced'))
        ->toContain('grand', 'theft', 'auto', '5', 'enhanced', 'gta', 'gta5', 'gtav');
});

it('builds the acronyms players actually type', function (string $name, string $acronym) {
    expect(GameSearchText::tokens($name))->toContain($acronym);
})->with([
    ['Red Dead Redemption 2', 'rdr2'],
    ["Baldur's Gate 3", 'bg3'],
    ['Kingdom Come: Deliverance II', 'kcd2'],
    ['Monster Hunter: World', 'mhw'],
    ['Resident Evil 4', 're4'],
]);

it('does not create acronyms shorter than three characters', function () {
    expect(GameSearchText::tokens('ELDEN RING'))->toBe(['elden', 'ring'])
        ->and(GameSearchText::tokens('Celeste'))->toBe(['celeste']);
});
