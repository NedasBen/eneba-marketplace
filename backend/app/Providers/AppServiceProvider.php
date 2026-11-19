<?php

namespace App\Providers;

use App\Database\PostgresConnection;
use App\Services\Homepage\HomepageBlockRegistry;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SimulatedPaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One registry per request/process, so discovered providers are resolved once.
        $this->app->singleton(HomepageBlockRegistry::class);

        // The only payment provider in this demo (test cards, no money moves).
        $this->app->bind(PaymentGateway::class, SimulatedPaymentGateway::class);

        // Boolean-safe bindings for emulated prepares (needed behind Neon's pooler).
        Connection::resolverFor('pgsql', fn ($pdo, string $database, string $prefix, array $config): PostgresConnection => new PostgresConnection($pdo, $database, $prefix, $config));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail loudly on lazy loading, silently discarded attributes and
        // missing attributes everywhere except production.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Autocomplete fires on keystrokes: generous, but bounded per client.
        RateLimiter::for('search', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));

        // Public health check: plenty for monitoring or a wake-up poll, not a free database load test.
        RateLimiter::for('ping', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));

        // Login/register ceiling per IP, so one client can't cycle through many emails.
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));

        // Card attempts per user: enough for a few retries, not for guessing cards.
        RateLimiter::for('payments', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->user()?->id ?: $request->ip()));

        // `optimize` caches the discovered homepage block providers, `optimize:clear` removes them.
        $this->optimizes(optimize: 'homepage:cache', clear: 'homepage:clear', key: 'homepage-blocks');
    }
}
