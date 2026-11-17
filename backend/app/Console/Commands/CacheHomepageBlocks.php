<?php

namespace App\Console\Commands;

use App\Services\Homepage\HomepageBlockRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Writes the discovered homepage block providers to
 * bootstrap/cache/homepage-blocks.php. Runs as part of `php artisan optimize`
 * (and therefore on every container start in production).
 */
class CacheHomepageBlocks extends Command
{
    /**
     * @var string
     */
    protected $signature = 'homepage:cache';

    /**
     * @var string
     */
    protected $description = 'Cache the discovered homepage block providers';

    public function handle(): int
    {
        $classes = HomepageBlockRegistry::discover();

        File::put(HomepageBlockRegistry::cachePath(), '<?php return '.var_export($classes, true).';'.PHP_EOL);

        $this->components->info(sprintf('Homepage blocks cached: %s.', implode(', ', array_keys($classes))));

        return self::SUCCESS;
    }
}
