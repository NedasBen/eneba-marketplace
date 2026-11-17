<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\Catalog\ProductStockAggregator;
use App\Services\Homepage\HomepageBuilder;
use App\Services\Search\SearchVocabulary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the full demo marketplace in ONE transaction:
 *  - all-or-nothing: readers see either no catalog or all of it, and a failed
 *    run leaves the database empty so the next boot simply retries;
 *  - guarded by a transaction-level advisory lock, so two instances booting
 *    at once cannot both seed (the loser skips); the lock is released on
 *    commit/rollback and is safe behind Neon's transaction-mode pooler;
 *  - refuses to run on a database that already has products.
 */
class DatabaseSeeder extends Seeder
{
    /** Arbitrary application-wide key for pg_try_advisory_xact_lock(). */
    public const ADVISORY_LOCK_KEY = 2_027_001;

    public function run(): void
    {
        $startedAt = microtime(true);

        $seeded = DB::transaction(function (): bool {
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
}
