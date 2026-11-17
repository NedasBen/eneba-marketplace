<?php

namespace App\Services\Catalog;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin wrapper around the public (keyless, undocumented) Steam Store API.
 */
class SteamStoreClient
{
    private const USER_AGENT = 'eneba-marketplace-demo/1.0 (non-commercial catalog import)';

    /**
     * Fetch the store details of one app, or null when Steam reports
     * `success: false` (unknown app, or not sold in the configured country).
     *
     * @return array<string, mixed>|null
     *
     * @throws ConnectionException|RequestException when Steam stays unreachable or rate limited after retries
     */
    public function appDetails(int $steamAppId): ?array
    {
        $payload = $this->request()
            ->get(config('services.steam.store_url').'/api/appdetails', [
                'appids' => $steamAppId,
                'cc' => config('services.steam.country_code'),
                'l' => config('services.steam.language'),
            ])
            ->throw()
            ->json((string) $steamAppId);

        if (! is_array($payload) || ($payload['success'] ?? false) !== true || ! is_array($payload['data'] ?? null)) {
            return null;
        }

        return $payload['data'];
    }

    /**
     * Return the URL of the first library asset (e.g. "library_600x900_2x.jpg")
     * that exists on Steam's CDN. Newer games sometimes lack the classic paths.
     *
     * @param  list<string>  $fileNames
     */
    public function firstExistingAssetUrl(int $steamAppId, array $fileNames): ?string
    {
        foreach ($fileNames as $fileName) {
            $url = sprintf('%s/%d/%s', config('services.steam.asset_url'), $steamAppId, $fileName);

            if ($this->request()->head($url)->successful()) {
                return $url;
            }
        }

        return null;
    }

    private function request(): PendingRequest
    {
        return Http::withUserAgent(self::USER_AGENT)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry([2000, 5000, 15000], 0, function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->tooManyRequests()));
            }, throw: false);
    }
}
