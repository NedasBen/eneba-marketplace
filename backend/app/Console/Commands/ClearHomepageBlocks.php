<?php

namespace App\Console\Commands;

use App\Services\Homepage\HomepageBlockRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Removes the cached homepage block provider list, so the Blocks directory is
 * scanned again. Runs as part of `php artisan optimize:clear`.
 */
class ClearHomepageBlocks extends Command
{
    /**
     * @var string
     */
    protected $signature = 'homepage:clear';

    /**
     * @var string
     */
    protected $description = 'Remove the cached homepage block provider list';

    public function handle(): int
    {
        File::delete(HomepageBlockRegistry::cachePath());

        $this->components->info('Homepage block cache cleared.');

        return self::SUCCESS;
    }
}
