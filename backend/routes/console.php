<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Where a scheduler runs (locally: php artisan schedule:work). Render has none,
// so CartService also prunes a user's old merge records when they merge.
Schedule::command('model:prune')->daily();

// Same caveat: checkout and catalog reads also run this sweep on Render.
Schedule::command('orders:release-expired')->everyMinute()->withoutOverlapping();
