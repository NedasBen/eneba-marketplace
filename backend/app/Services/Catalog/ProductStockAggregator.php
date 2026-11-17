<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;

/**
 * Maintains the denormalised product columns `min_price_cents`, `offers_count`
 * and `sold_count`.
 *
 * Every value is recomputed from the source rows (offers and product keys)
 * rather than incremented, so calling refresh() is idempotent: running it
 * after any change, or for every product at once, always converges to the
 * correct state.
 *
 * Definitions:
 *  - a key is in stock when it is `available`, or `reserved` with an expired
 *    `reserved_until` (lazy expiry: expired holds count as free stock);
 *  - an offer is in stock when it is active and has at least one key in stock;
 *  - min_price_cents = cheapest in-stock offer (null when none);
 *  - offers_count    = number of in-stock offers;
 *  - sold_count      = number of keys sold across all of the product's offers.
 */
class ProductStockAggregator
{
    private const STATS_SQL = <<<'SQL'
        SELECT p.id,
               MIN(o.price_cents) FILTER (WHERE o.is_active AND keys.in_stock > 0) AS min_price_cents,
               COUNT(o.id) FILTER (WHERE o.is_active AND keys.in_stock > 0)        AS offers_count,
               COALESCE(SUM(keys.sold), 0)                                         AS sold_count
        FROM products p
        LEFT JOIN offers o ON o.product_id = p.id
        LEFT JOIN LATERAL (
            SELECT COUNT(*) FILTER (
                       WHERE pk.status = 'available'
                          OR (pk.status = 'reserved' AND pk.reserved_until < now())
                   ) AS in_stock,
                   COUNT(*) FILTER (WHERE pk.status = 'sold') AS sold
            FROM product_keys pk
            WHERE pk.offer_id = o.id
        ) keys ON true
        %s
        GROUP BY p.id
        SQL;

    private const DRIFT_CONDITION = <<<'SQL'
        p.min_price_cents IS DISTINCT FROM stats.min_price_cents
        OR p.offers_count <> stats.offers_count
        OR p.sold_count <> stats.sold_count
        SQL;

    /**
     * Recompute the aggregates for the given products (or all products when
     * null) and return how many rows actually changed.
     *
     * Call this inside the same transaction as the stock change that caused
     * it: if anything fails, both roll back together and nothing drifts.
     *
     * @param  list<int>|null  $productIds
     */
    public function refresh(?array $productIds = null): int
    {
        if ($productIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($productIds): int {
            // Lock the product rows first, in a stable order to avoid deadlocks.
            // Under READ COMMITTED the UPDATE below takes a fresh snapshot, so a
            // concurrent transaction that changed the same product's stock has
            // either committed (and is visible) or is still waiting on this lock.
            // Computing first and locking later could overwrite newer stats with
            // stale ones.
            [$whereProducts, $bindings] = $this->productFilter($productIds);

            DB::select("SELECT id FROM products p {$whereProducts} ORDER BY id FOR UPDATE", $bindings);

            $statsSql = sprintf(self::STATS_SQL, $whereProducts);
            $driftCondition = self::DRIFT_CONDITION;

            // Only rows that actually differ are written, so the return value
            // is the number of products that had drifted.
            return DB::affectingStatement(<<<SQL
                WITH stats AS ({$statsSql})
                UPDATE products p
                SET min_price_cents = stats.min_price_cents,
                    offers_count = stats.offers_count,
                    sold_count = stats.sold_count,
                    updated_at = now()
                FROM stats
                WHERE p.id = stats.id AND ({$driftCondition})
                SQL, $bindings);
        });
    }

    /**
     * List products whose stored aggregates differ from the recomputed ones,
     * without changing anything.
     *
     * @param  list<int>|null  $productIds
     * @return list<object{id: int, name: string, stored_min_price_cents: int|null, min_price_cents: int|null, stored_offers_count: int, offers_count: int, stored_sold_count: int, sold_count: int}>
     */
    public function drift(?array $productIds = null): array
    {
        if ($productIds === []) {
            return [];
        }

        [$whereProducts, $bindings] = $this->productFilter($productIds);
        $statsSql = sprintf(self::STATS_SQL, $whereProducts);
        $driftCondition = self::DRIFT_CONDITION;

        return DB::select(<<<SQL
            WITH stats AS ({$statsSql})
            SELECT p.id, p.name,
                   p.min_price_cents AS stored_min_price_cents, stats.min_price_cents,
                   p.offers_count    AS stored_offers_count,    stats.offers_count,
                   p.sold_count      AS stored_sold_count,      stats.sold_count
            FROM products p
            JOIN stats ON stats.id = p.id
            WHERE {$driftCondition}
            ORDER BY p.id
            SQL, $bindings);
    }

    /**
     * @param  list<int>|null  $productIds
     * @return array{0: string, 1: list<string>}
     */
    private function productFilter(?array $productIds): array
    {
        if ($productIds === null) {
            return ['', []];
        }

        $ids = array_values(array_unique(array_map('intval', $productIds)));

        return ['WHERE p.id = ANY(?::bigint[])', ['{'.implode(',', $ids).'}']];
    }
}
