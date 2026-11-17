<?php

namespace App\Services\Search;

use Illuminate\Support\Str;

/**
 * Builds the searchable forms of a game name. The same normalization runs on
 * stored names and on user queries, so both sides always compare alike.
 */
final class GameSearchText
{
    /** Standalone Roman numerals become digits ("I" is left alone: it is a word). */
    private const ROMAN_TO_DIGIT = [
        'ii' => '2', 'iii' => '3', 'iv' => '4', 'v' => '5', 'vi' => '6',
        'vii' => '7', 'viii' => '8', 'ix' => '9', 'x' => '10',
    ];

    private const MIN_ACRONYM_LENGTH = 3;

    /**
     * "Grand Theft Auto V Enhanced™" -> "grand theft auto 5 enhanced",
     * "Baldur's Gate 3" -> "baldurs gate 3", "Pokémon" -> "pokemon".
     */
    public static function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii(str_replace(['™', '®', '©'], '', $text)));
        $text = (string) preg_replace("/['`]/", '', $text);
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);

        $words = array_filter(explode(' ', $text), fn (string $word): bool => $word !== '');

        return implode(' ', array_map(fn (string $word): string => self::ROMAN_TO_DIGIT[$word] ?? $word, $words));
    }

    /**
     * The words of the normalized name plus acronyms from their initials
     * (numbers kept whole, in digit and Roman form), at least 3 characters:
     * "Grand Theft Auto V" -> grand, theft, auto, 5, gta, gta5, gtav.
     *
     * @return list<string>
     */
    public static function tokens(string $name): array
    {
        $normalized = self::normalize($name);

        if ($normalized === '') {
            return [];
        }

        $words = explode(' ', $normalized);
        $digitToRoman = array_flip(self::ROMAN_TO_DIGIT);

        $acronyms = [];
        $digitForm = '';
        $romanForm = '';

        foreach ($words as $word) {
            $isNumber = ctype_digit($word);
            $digitForm .= $isNumber ? $word : $word[0];
            $romanForm .= $isNumber ? ($digitToRoman[$word] ?? $word) : $word[0];

            if (strlen($digitForm) >= self::MIN_ACRONYM_LENGTH) {
                $acronyms[] = $digitForm;
                $acronyms[] = $romanForm;
            }
        }

        return array_values(array_unique([...$words, ...$acronyms]));
    }
}
