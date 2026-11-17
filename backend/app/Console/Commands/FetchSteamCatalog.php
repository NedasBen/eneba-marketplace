<?php

namespace App\Console\Commands;

use App\Enums\GamePlatform;
use App\Services\Catalog\SteamGameNormalizer;
use App\Services\Catalog\SteamStoreClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Sleep;
use JsonException;

/**
 * Builds database/data/games.json from a hand-curated list of Steam app ids.
 *
 * Runs once at development time; the output is committed so seeding and
 * deploys never depend on Steam. The run is resumable: games already in the
 * output file are skipped, and progress is saved after every game.
 */
class FetchSteamCatalog extends Command
{
    /**
     * @var string
     */
    protected $signature = 'catalog:fetch-steam
        {--fresh : Re-fetch every game instead of skipping ones already in the output file}
        {--source= : Curated source list (defaults to database/data/steam-catalog-sources.json)}
        {--output= : Seed file to write (defaults to database/data/games.json)}';

    /**
     * @var string
     */
    protected $description = 'Fetch game details and artwork from the Steam Store into the committed catalog seed file';

    public function handle(SteamStoreClient $steam, SteamGameNormalizer $normalizer): int
    {
        $sourcePath = $this->option('source') ?: database_path('data/steam-catalog-sources.json');
        $outputPath = $this->option('output') ?: database_path('data/games.json');

        try {
            $sources = $this->readSources($sourcePath);
            $games = $this->option('fresh') ? [] : $this->readExistingGames($outputPath);
        } catch (JsonException|\InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        // Drop games that were removed from the curated list.
        $games = array_intersect_key($games, $sources);
        $pending = array_diff_key($sources, $games);

        $this->components->info(sprintf(
            '%d games in source list: %d already fetched, %d to fetch (country "%s").',
            count($sources),
            count($games),
            count($pending),
            config('services.steam.country_code'),
        ));

        $fetched = 0;
        $skipped = [];
        $delayMs = (int) config('services.steam.request_delay_ms');

        foreach (array_values($pending) as $index => $source) {
            if ($index > 0) {
                Sleep::for($delayMs)->milliseconds();
            }

            $steamAppId = $source['steam_app_id'];

            try {
                $result = $this->fetchGame($steam, $normalizer, $source);
            } catch (ConnectionException|RequestException $exception) {
                $this->writeGames($outputPath, $games, $sources);
                $this->components->error(sprintf(
                    'Steam request failed at %s (%d): %s. Progress saved (%d games); run the command again to resume.',
                    $source['name'],
                    $steamAppId,
                    $exception->getMessage(),
                    count($games),
                ));

                return self::FAILURE;
            }

            if (is_string($result)) {
                $skipped[] = [$steamAppId, $source['name'], $result];
                $this->components->warn("Skipped {$source['name']} ({$steamAppId}): {$result}");

                continue;
            }

            $games[$steamAppId] = $result;
            $this->writeGames($outputPath, $games, $sources);
            $fetched++;

            $this->components->twoColumnDetail($result['name'], implode(', ', $result['platforms']));
        }

        $this->writeGames($outputPath, $games, $sources);

        if ($skipped !== []) {
            $this->table(['Steam app id', 'Name', 'Skipped because'], $skipped);
        }

        $this->components->info(sprintf(
            'Done: %d fetched, %d skipped, %d games in %s.',
            $fetched,
            count($skipped),
            count($games),
            $outputPath,
        ));

        return self::SUCCESS;
    }

    /**
     * Fetch and normalize one game, or return the reason it was skipped.
     *
     * @param  array{steam_app_id: int, name: string, platforms: list<GamePlatform>}  $source
     * @return array<string, mixed>|string
     *
     * @throws ConnectionException|RequestException
     */
    private function fetchGame(SteamStoreClient $steam, SteamGameNormalizer $normalizer, array $source): array|string
    {
        $steamAppId = $source['steam_app_id'];
        $details = $steam->appDetails($steamAppId);

        if ($details === null) {
            return 'not available in the Steam store for this country';
        }

        $skipReason = $normalizer->skipReason($details, $source['name']);

        if ($skipReason !== null) {
            return $skipReason;
        }

        return $normalizer->normalize(
            $details,
            $source['platforms'],
            $steam->firstExistingAssetUrl($steamAppId, ['library_600x900_2x.jpg', 'library_600x900.jpg']),
            $steam->firstExistingAssetUrl($steamAppId, ['library_hero.jpg']),
        );
    }

    /**
     * @return array<int, array{steam_app_id: int, name: string, platforms: list<GamePlatform>}>
     *
     * @throws JsonException
     */
    private function readSources(string $path): array
    {
        if (! File::exists($path)) {
            throw new \InvalidArgumentException("Source list not found: {$path}");
        }

        $sources = [];

        foreach (json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR) as $position => $entry) {
            $steamAppId = $entry['steam_app_id'] ?? null;
            $name = $entry['name'] ?? null;
            $platformSlugs = $entry['platforms'] ?? null;

            if (! is_int($steamAppId) || ! is_string($name) || ! is_array($platformSlugs) || $platformSlugs === []) {
                throw new \InvalidArgumentException("Source entry #{$position} needs an integer steam_app_id, a name and a non-empty platforms list.");
            }

            $platforms = array_map(function (mixed $slug) use ($name): GamePlatform {
                return GamePlatform::tryFrom((string) $slug)
                    ?? throw new \InvalidArgumentException(sprintf(
                        'Unknown platform "%s" for %s. Allowed: %s.',
                        $slug,
                        $name,
                        implode(', ', array_column(GamePlatform::cases(), 'value')),
                    ));
            }, $platformSlugs);

            if (isset($sources[$steamAppId])) {
                throw new \InvalidArgumentException("Duplicate steam_app_id {$steamAppId} in source list.");
            }

            $sources[$steamAppId] = ['steam_app_id' => $steamAppId, 'name' => $name, 'platforms' => $platforms];
        }

        return $sources;
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws JsonException
     */
    private function readExistingGames(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $games = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        return array_column($games, null, 'steam_app_id');
    }

    /**
     * Atomically write the games in source-list order, so diffs stay stable.
     *
     * @param  array<int, array<string, mixed>>  $games
     * @param  array<int, mixed>  $sources
     */
    private function writeGames(string $path, array $games, array $sources): void
    {
        $ordered = array_values(array_filter(array_map(
            fn (int $steamAppId): ?array => $games[$steamAppId] ?? null,
            array_keys($sources),
        )));

        File::ensureDirectoryExists(dirname($path));
        File::replace($path, json_encode($ordered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }
}
