<?php

namespace App\Services\Homepage;

use App\Models\HomepageBlock;

/**
 * One homepage block type. Implementations live in
 * app/Services/Homepage/Blocks and are discovered automatically by
 * HomepageBlockRegistry: adding a block type means adding one class here
 * plus a `homepage_blocks` row that uses its type.
 */
interface HomepageBlockProvider
{
    /** Matches `homepage_blocks.type`, e.g. "best_sellers". */
    public static function type(): string;

    /** Which frontend component renders the data, e.g. "hero" or "product_rail". */
    public static function layout(): string;

    /**
     * The block's content as plain arrays (fully resolved: it runs inside a
     * short-lived transaction with a statement timeout). Return null or an
     * empty array when there is nothing to show; the block is then omitted.
     *
     * @return array<string, mixed>|null
     */
    public function data(HomepageBlock $block): ?array;
}
