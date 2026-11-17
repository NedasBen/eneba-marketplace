<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * `migrate --force`, serialized across instances with a Postgres advisory lock.
 *
 * When several containers boot at once against an empty database, plain
 * `migrate` races (both try to CREATE TABLE users and one crashes).
 * `migrate --isolated` cannot help on first boot: its lock lives in the cache
 * store, which is itself a database table that does not exist yet.
 *
 * The lock is session-level, so it must run over a direct (non-pooled)
 * connection; the entrypoint points DB_URL at DB_MIGRATION_URL for this.
 */
class MigrateWithLock extends Command
{
    /** Arbitrary application-wide key, distinct from the seeding lock. */
    public const ADVISORY_LOCK_KEY = 2_027_002;

    /**
     * @var string
     */
    protected $signature = 'db:migrate-locked
        {--wait=120 : Seconds to wait for another instance to finish migrating}';

    /**
     * @var string
     */
    protected $description = 'Run migrations while holding a cross-instance Postgres advisory lock';

    public function handle(): int
    {
        $waitSeconds = max(1, (int) $this->option('wait'));

        try {
            // lock_timeout also bounds advisory lock waits.
            DB::statement(sprintf("SET lock_timeout = '%ds'", $waitSeconds));
            DB::select('SELECT pg_advisory_lock(?)', [self::ADVISORY_LOCK_KEY]);
        } catch (QueryException $exception) {
            $this->components->error("Another instance held the migration lock for more than {$waitSeconds}s: {$exception->getMessage()}");

            return self::FAILURE;
        }

        DB::statement('RESET lock_timeout');

        try {
            return $this->call('migrate', ['--force' => true]);
        } finally {
            DB::select('SELECT pg_advisory_unlock(?)', [self::ADVISORY_LOCK_KEY]);
        }
    }
}
