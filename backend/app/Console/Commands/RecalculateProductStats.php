<?php

namespace App\Console\Commands;

use App\Services\Catalog\ProductStockAggregator;
use Illuminate\Console\Command;

/**
 * Rebuilds the denormalised product aggregates from offers and keys.
 *
 * Safe to run at any time (it recomputes from source rows and only writes
 * products that differ); use it after manual data fixes, imports, or if a
 * code path is ever suspected of skipping ProductStockAggregator::refresh().
 */
class RecalculateProductStats extends Command
{
    /**
     * @var string
     */
    protected $signature = 'catalog:recalculate-product-stats
        {--dry-run : Report drifted products without changing them}';

    /**
     * @var string
     */
    protected $description = 'Recalculate products.min_price_cents, offers_count and sold_count from offers and keys';

    public function handle(ProductStockAggregator $aggregator): int
    {
        $drifted = $aggregator->drift();

        if ($drifted !== []) {
            $this->table(
                ['Product', 'Min price (stored → actual)', 'Offers in stock', 'Keys sold'],
                array_map(fn (object $row): array => [
                    "#{$row->id} {$row->name}",
                    $this->formatCents($row->stored_min_price_cents).' → '.$this->formatCents($row->min_price_cents),
                    "{$row->stored_offers_count} → {$row->offers_count}",
                    "{$row->stored_sold_count} → {$row->sold_count}",
                ], array_slice($drifted, 0, 50)),
            );
        }

        if ($this->option('dry-run')) {
            $this->components->info(count($drifted).' product(s) have drifted. Dry run: nothing was changed.');

            return self::SUCCESS;
        }

        $updated = $aggregator->refresh();

        $this->components->info("Recalculated product stats: {$updated} product(s) updated.");

        return self::SUCCESS;
    }

    private function formatCents(?int $cents): string
    {
        return $cents === null ? 'none' : number_format($cents / 100, 2).' EUR';
    }
}
