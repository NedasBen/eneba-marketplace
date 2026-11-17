<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\Catalog\ProductStockAggregator;
use App\Services\Homepage\HomepageBuilder;
use App\Services\Search\SearchVocabulary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

/**
 * Seeds the full demo marketplace in ONE transaction:
 *  - all-or-nothing: readers see either no catalog or all of it, and a failed
 *    run leaves the database empty so the next boot simply retries;
 *  - guarded by a transaction-level advisory lock, so two instances booting
 *    at once cannot both seed (the loser skips); the lock is released on
 *    commit/rollback;
 *  - refuses to run on a database that already has products.
 *
 * In production it runs on the direct `pgsql_direct` connection
 * (`db:seed --database=pgsql_direct`), not through Neon's pooler.
 */
class DatabaseSeeder extends Seeder
{
    /** Arbitrary application-wide key for pg_try_advisory_xact_lock(). */
    public const ADVISORY_LOCK_KEY = 2_027_001;

    /** Postgres: "current transaction is aborted, commands ignored until end of transaction block". */
    private const IN_FAILED_TRANSACTION = '25P02';

    public function run(): void
    {
        $startedAt = microtime(true);
        $connection = DB::connection();

        Log::info('Seeding database.', ['connection' => $connection->getName(), 'host' => $connection->getConfig('host')]);

        try {
            $seeded = $this->seedInTransaction();
        } catch (Throwable $exception) {
            $this->reportFailure($exception);

            throw $exception;
        }

        if ($seeded) {
            // Bulk inserts bypass Game model events, so clear the cached vocabulary here.
            app(SearchVocabulary::class)->forget();
            // A request during background seeding may have cached an empty homepage.
            app(HomepageBuilder::class)->forget();

            $this->command?->info(sprintf(
                'Seeded %d products, %d offers, %d keys and %d orders in %.1fs.',
                DB::table('products')->count(),
                DB::table('offers')->count(),
                DB::table('product_keys')->count(),
                DB::table('orders')->count(),
                microtime(true) - $startedAt,
            ));
        }
    }

    /**
     * Log the failure with everything needed to diagnose it from the
     * container logs alone. A 25P02 is never the real error: an earlier
     * statement in the transaction failed without raising a PHP exception,
     * so say so and where to look, instead of leaving only the symptom.
     */
    private function reportFailure(Throwable $exception): void
    {
        // QueryException extends PDOException; both carry the SQLSTATE as their code.
        $sqlState = $exception instanceof PDOException ? (string) $exception->getCode() : null;
        $connection = DB::connection();

        Log::error('Database seeding failed; nothing was committed and the next boot will retry.', array_filter([
            'connection' => $connection->getName(),
            'host' => $connection->getConfig('host'),
            'sqlstate' => $sqlState,
            'hint' => $sqlState === self::IN_FAILED_TRANSACTION
                ? 'An earlier statement in this transaction failed without raising a PHP exception (e.g. pdo_pgsql\'s DEALLOCATE '
                    .'behind a transaction-mode pooler). The original error is the first ERROR for this transaction in the Postgres server log.'
                : null,
            'exception' => $exception,
            'previous' => $exception->getPrevious()?->getMessage(),
        ]));
    }

    private function seedInTransaction(): bool
    {
        return DB::transaction(function (): bool {
            if (! DB::scalar('SELECT pg_try_advisory_xact_lock(?)', [self::ADVISORY_LOCK_KEY])) {
                $this->command?->warn('Another process is seeding the database; skipping.');

                return false;
            }

            if (Product::query()->exists()) {
                $this->command?->info('Catalog already seeded; skipping. Use `migrate:fresh --seed` to start over.');

                return false;
            }

            $this->call([
                RegionSeeder::class,
                PlatformSeeder::class,
                SellerSeeder::class,
                CatalogSeeder::class,
                OrderHistorySeeder::class,
            ]);

            app(ProductStockAggregator::class)->refresh();

            $this->call(HomepageSeeder::class);

            return true;
        });
    }
}
