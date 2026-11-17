<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
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
        //
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
    }
}
