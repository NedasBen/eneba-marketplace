<?php

namespace App\Console\Commands;

use App\Services\Checkout\ReleaseExpiredReservations;
use Illuminate\Console\Command;

class ReleaseExpiredReservationsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'orders:release-expired';

    /**
     * @var string
     */
    protected $description = 'Expire pending orders past their deadline and return their keys to stock';

    public function handle(ReleaseExpiredReservations $expiry): int
    {
        $total = 0;

        do {
            $expired = $expiry->run();
            $total += $expired;
        } while ($expired === ReleaseExpiredReservations::BATCH_SIZE);

        $this->info("Expired {$total} ".($total === 1 ? 'order' : 'orders').'.');

        return self::SUCCESS;
    }
}
