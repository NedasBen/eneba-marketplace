<?php

namespace App\Services\Homepage;

use Illuminate\Contracts\Container\Container;
use LogicException;
use ReflectionClass;

/**
 * Maps `homepage_blocks.type` to its provider.
 *
 * Providers are discovered by scanning app/Services/Homepage/Blocks for
 * concrete classes implementing HomepageBlockProvider, so adding a block type
 * is adding one class. In production the discovered type => class map is
 * read from bootstrap/cache/homepage-blocks.php (written by `php artisan
 * homepage:cache`, which `php artisan optimize` runs; removed by
 * `optimize:clear`), so requests never touch the filesystem to find them.
 */
class HomepageBlockRegistry
{
    public const BLOCKS_NAMESPACE = 'App\\Services\\Homepage\\Blocks\\';

    /** @var array<string, class-string<HomepageBlockProvider>>|null */
    private ?array $classes = null;

    /** @var array<string, HomepageBlockProvider> */
    private array $instances = [];

    public function __construct(private readonly Container $container) {}

    public static function cachePath(): string
    {
        return app()->bootstrapPath('cache/homepage-blocks.php');
    }

    public function for(string $type): ?HomepageBlockProvider
    {
        if (isset($this->instances[$type])) {
            return $this->instances[$type];
        }

        $class = $this->classes()[$type] ?? null;

        return $class === null ? null : $this->instances[$type] = $this->container->make($class);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->classes() + $this->instances);
    }

    /**
     * Register a provider instance directly (tests, or packages adding blocks).
     */
    public function register(HomepageBlockProvider $provider): void
    {
        $this->instances[$provider::type()] = $provider;
    }

    /**
     * Scan the Blocks directory. Fails loudly on two providers claiming one type.
     *
     * @return array<string, class-string<HomepageBlockProvider>>
     */
    public static function discover(): array
    {
        $classes = [];

        foreach (glob(app_path('Services/Homepage/Blocks/*.php')) ?: [] as $file) {
            $class = self::BLOCKS_NAMESPACE.basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (! $reflection->isInstantiable() || ! $reflection->implementsInterface(HomepageBlockProvider::class)) {
                continue;
            }

            $type = $class::type();

            if (isset($classes[$type])) {
                throw new LogicException("Homepage block type \"{$type}\" is provided by both {$classes[$type]} and {$class}.");
            }

            $classes[$type] = $class;
        }

        ksort($classes);

        return $classes;
    }

    /**
     * @return array<string, class-string<HomepageBlockProvider>>
     */
    private function classes(): array
    {
        if ($this->classes !== null) {
            return $this->classes;
        }

        $cachePath = self::cachePath();

        return $this->classes = is_file($cachePath) ? require $cachePath : self::discover();
    }
}
