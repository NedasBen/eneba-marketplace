<?php

namespace App\Services\Search;

use Closure;

/**
 * Corrects misspelled query words against the catalog vocabulary before the
 * database search runs, e.g. "wichter" -> "witcher", "eldn" -> "elden".
 *
 * A word is left alone when it is a number, shorter than 4 characters, a
 * vocabulary word, or the start of one (the user is still typing it).
 * Otherwise it becomes the closest vocabulary word within the allowed
 * Levenshtein distance: 1 edit for 4-5 characters, 2 for longer words.
 */
final class QueryCorrector
{
    private const MIN_CORRECTABLE_LENGTH = 4;

    private const LONG_WORD_LENGTH = 6;

    /**
     * @param  Closure(): list<string>  $vocabulary  loaded only if a word needs correcting
     */
    public function correct(string $normalizedQuery, Closure $vocabulary): string
    {
        $words = $normalizedQuery === '' ? [] : explode(' ', $normalizedQuery);
        $knownWords = null;

        foreach ($words as $index => $word) {
            if (! $this->isCorrectable($word)) {
                continue;
            }

            $knownWords ??= $vocabulary();

            if (! $this->isKnownOrPartial($word, $knownWords)) {
                $words[$index] = $this->closestWord($word, $knownWords) ?? $word;
            }
        }

        return implode(' ', $words);
    }

    private function isCorrectable(string $word): bool
    {
        return strlen($word) >= self::MIN_CORRECTABLE_LENGTH && ! ctype_digit($word);
    }

    /**
     * @param  list<string>  $knownWords
     */
    private function isKnownOrPartial(string $word, array $knownWords): bool
    {
        foreach ($knownWords as $knownWord) {
            if (str_starts_with($knownWord, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fewest edits wins; ties prefer the same first letter, then the closest
     * length, then alphabetical order (deterministic).
     *
     * @param  list<string>  $knownWords
     */
    private function closestWord(string $word, array $knownWords): ?string
    {
        $maxDistance = strlen($word) >= self::LONG_WORD_LENGTH ? 2 : 1;
        $best = null;
        $bestRank = null;

        foreach ($knownWords as $candidate) {
            if (ctype_digit($candidate) || abs(strlen($candidate) - strlen($word)) > $maxDistance) {
                continue;
            }

            $distance = levenshtein($word, $candidate);

            if ($distance > $maxDistance) {
                continue;
            }

            $rank = [$distance, $candidate[0] === $word[0] ? 0 : 1, abs(strlen($candidate) - strlen($word)), $candidate];

            if ($bestRank === null || $rank < $bestRank) {
                $best = $candidate;
                $bestRank = $rank;
            }
        }

        return $best;
    }
}
