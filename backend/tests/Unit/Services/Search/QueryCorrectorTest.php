<?php

use App\Services\Search\QueryCorrector;

const SEARCH_VOCABULARY = [
    'the', 'witcher', '3', 'wild', 'hunt', 'elden', 'ring', 'elder', 'scrolls', 'cyberpunk', '2077',
    'red', 'dead', 'redemption', '2', 'fallout', '4', '76', 'grand', 'theft', 'auto', '5', 'gta',
    'hollow', 'knight', 'persona', 'diablo',
];

function correctQuery(string $normalizedQuery): string
{
    return (new QueryCorrector)->correct($normalizedQuery, fn (): array => SEARCH_VOCABULARY);
}

it('corrects misspelled words to the closest catalog word', function (string $query, string $expected) {
    expect(correctQuery($query))->toBe($expected);
})->with([
    'swapped letters (2 edits on a long word)' => ['wichter', 'witcher'],
    'missing letter' => ['eldn ring', 'elden ring'],
    'missing vowel' => ['cyberpnk', 'cyberpunk'],
    'one typo in a five-letter word' => ['diabol', 'diablo'],
]);

it('leaves words alone that need no correction', function (string $query) {
    expect(correctQuery($query))->toBe($query);
})->with([
    'known words' => ['red dead'],
    'partial word still being typed' => ['cyber'],
    'numbers, even unknown ones' => ['fallout 77'],
    'short words' => ['gta 5'],
    'three letters are never corrected' => ['wtc'],
    'nothing close enough' => ['zzzzqx'],
    'too many typos for a short word' => ['hllwo'],
]);

it('prefers a candidate with the same first letter when distances tie', function () {
    $corrector = new QueryCorrector;

    // "bart" is one edit from both; "bark" keeps the first letter the user typed.
    expect($corrector->correct('bart', fn (): array => ['cart', 'bark']))->toBe('bark');
});

it('does not load the vocabulary when no word could need correcting', function () {
    $loaded = false;

    (new QueryCorrector)->correct('gta 5', function () use (&$loaded): array {
        $loaded = true;

        return SEARCH_VOCABULARY;
    });

    expect($loaded)->toBeFalse();
});
