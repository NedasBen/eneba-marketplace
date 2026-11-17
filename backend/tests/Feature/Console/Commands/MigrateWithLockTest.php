<?php

use App\Console\Commands\MigrateWithLock;
use Illuminate\Support\Facades\DB;

it('runs migrations while holding the migration lock and releases it afterwards', function () {
    $this->artisan('db:migrate-locked')
        ->expectsOutputToContain('Nothing to migrate')
        ->assertSuccessful();

    config(['database.connections.other_instance' => config('database.connections.pgsql')]);

    try {
        expect(DB::connection('other_instance')->scalar('SELECT pg_try_advisory_lock(?)', [MigrateWithLock::ADVISORY_LOCK_KEY]))->toBeTrue();
    } finally {
        DB::connection('other_instance')->select('SELECT pg_advisory_unlock_all()');
        DB::purge('other_instance');
    }
});

it('fails instead of migrating when another instance holds the lock for too long', function () {
    config(['database.connections.other_instance' => config('database.connections.pgsql')]);
    $otherInstance = DB::connection('other_instance');
    $otherInstance->select('SELECT pg_advisory_lock(?)', [MigrateWithLock::ADVISORY_LOCK_KEY]);

    try {
        $this->artisan('db:migrate-locked', ['--wait' => 1])
            ->expectsOutputToContain('Another instance held the migration lock')
            ->assertFailed();
    } finally {
        $otherInstance->select('SELECT pg_advisory_unlock_all()');
        DB::purge('other_instance');
    }
});
