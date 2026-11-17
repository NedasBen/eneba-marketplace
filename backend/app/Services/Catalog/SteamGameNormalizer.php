<?php

namespace App\Services\Catalog;

use App\Enums\GamePlatform;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a Steam `appdetails` payload into one record of the committed catalog
 * seed (database/data/games.json). All text leaves here as plain text, so the
 * frontend never has to render third-party HTML.
 */
class SteamGameNormalizer
{
    private const MAX_DESCRIPTION_LENGTH = 5000;

    private const MAX_SCREENSHOTS = 6;

    /** Steam "genres" that describe a business model rather than a genre. */
    private const IGNORED_GENRES = ['free to play', 'early access'];

    private const RELEASE_DATE_FORMATS = ['j M, Y', 'M j, Y', 'j M Y', 'd M, Y', 'M Y'];

    /**
     * Explain why an app cannot be imported, or return null when it can.
     *
     * @param  array<string, mixed>  $details
     */
    public function skipReason(array $details, string $expectedName): ?string
    {
        if (($details['type'] ?? null) !== 'game') {
            return sprintf('not a game (type "%s")', $details['type'] ?? 'unknown');
        }

        if (($details['is_free'] ?? false) === true) {
            return 'free to play, so there is nothing to sell keys for';
        }

        $currency = $details['price_overview']['currency'] ?? null;

        if ($currency !== 'EUR' || ! is_int($details['price_overview']['initial'] ?? null)) {
            return 'no EUR price in the Steam store for this country';
        }

        $actualName = $this->cleanName((string) ($details['name'] ?? ''));

        if (! $this->namesMatch($expectedName, $actualName)) {
            return sprintf('app id points to "%s", expected "%s"', $actualName, $expectedName);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  list<GamePlatform>  $platforms
     * @return array{
     *     steam_app_id: int,
     *     name: string,
     *     slug: string,
     *     platforms: list<string>,
     *     genres: list<string>,
     *     short_description: string,
     *     description: string,
     *     developer: string|null,
     *     publisher: string|null,
     *     released_at: string|null,
     *     metacritic: int|null,
     *     retail_price_cents: int,
     *     cover_image_url: string,
     *     header_image_url: string,
     *     hero_image_url: string|null,
     *     screenshots: list<string>
     * }
     */
    public function normalize(array $details, array $platforms, ?string $coverImageUrl, ?string $heroImageUrl): array
    {
        $name = $this->cleanName((string) $details['name']);
        $headerImageUrl = (string) $details['header_image'];
        $shortDescription = $this->htmlToPlainText((string) ($details['short_description'] ?? ''));

        // Some store pages build their "About" section from images only.
        $description = $this->htmlToPlainText((string) ($details['about_the_game'] ?? $details['detailed_description'] ?? ''));

        return [
            'steam_app_id' => (int) $details['steam_appid'],
            'name' => $name,
            'slug' => Str::slug($name),
            'platforms' => array_map(fn (GamePlatform $platform): string => $platform->value, $platforms),
            'genres' => $this->genres($details['genres'] ?? []),
            'short_description' => $shortDescription,
            'description' => Str::limit($description !== '' ? $description : $shortDescription, self::MAX_DESCRIPTION_LENGTH),
            'developer' => $this->joinNames($details['developers'] ?? []),
            'publisher' => $this->joinNames($details['publishers'] ?? []),
            'released_at' => $this->releaseDate($details['release_date']['date'] ?? null),
            'metacritic' => isset($details['metacritic']['score']) ? (int) $details['metacritic']['score'] : null,
            'retail_price_cents' => (int) $details['price_overview']['initial'],
            'cover_image_url' => $coverImageUrl ?? $headerImageUrl,
            'header_image_url' => $headerImageUrl,
            'hero_image_url' => $heroImageUrl ?? ($details['background_raw'] ?? null),
            'screenshots' => array_values(array_slice(
                array_filter(array_column($details['screenshots'] ?? [], 'path_full'), 'is_string'),
                0,
                self::MAX_SCREENSHOTS,
            )),
        ];
    }

    /**
     * Convert Steam's description HTML into readable plain text.
     */
    public function htmlToPlainText(string $html): string
    {
        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\s*li[^>]*>/i', "\n• ", (string) $text);
        $text = preg_replace('/<\/\s*(p|h[1-6]|ul|ol|div)\s*>/i', "\n\n", (string) $text);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/ *\n */', "\n", (string) $text);
        $text = preg_replace('/\n{3,}/', "\n\n", (string) $text);

        return trim((string) $text);
    }

    public function cleanName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace(['™', '®', '©'], '', $name)));
    }

    /**
     * Guard against a mistyped app id in the source list silently importing
     * a different game (with the wrong platforms attached).
     */
    private function namesMatch(string $expectedName, string $actualName): bool
    {
        $normalize = fn (string $name): string => (string) preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($name)));

        $expected = $normalize($expectedName);
        $actual = $normalize($actualName);

        if ($expected === '' || $actual === '') {
            return false;
        }

        if (str_contains($actual, $expected) || str_contains($expected, $actual)) {
            return true;
        }

        similar_text($expected, $actual, $percent);

        return $percent >= 70;
    }

    /**
     * @param  array<int, array{description?: string}>  $genres
     * @return list<string>
     */
    private function genres(array $genres): array
    {
        $names = array_map(fn (array $genre): string => trim((string) ($genre['description'] ?? '')), $genres);

        return array_values(array_unique(array_filter(
            $names,
            fn (string $name): bool => $name !== '' && ! in_array(Str::lower($name), self::IGNORED_GENRES, true),
        )));
    }

    /**
     * @param  array<int, string>  $names
     */
    private function joinNames(array $names): ?string
    {
        $names = array_values(array_filter(array_map(fn (mixed $name): string => trim((string) $name), $names)));

        return $names === [] ? null : implode(', ', $names);
    }

    private function releaseDate(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        foreach (self::RELEASE_DATE_FORMATS as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!'.$format, trim($date));
            } catch (Throwable) {
                continue;
            }

            if ($parsed !== null && $parsed->format($format) === trim($date)) {
                return $parsed->toDateString();
            }
        }

        return null;
    }
}
