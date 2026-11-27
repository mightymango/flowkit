<?php

namespace MightyMango\Flowkit\Bricks;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use InvalidArgumentException;
use MightyMango\Flowkit\Exceptions\UnknownBrick;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class BrickRegistry
{
    /** @var array<string, class-string<Brick>> */
    private array $bricks = [];

    public function __construct(private Container $container) {}

    /**
     * @param  class-string<Brick>|list<class-string<Brick>>  $classes
     */
    public function register(string|array $classes): self
    {
        foreach ((array) $classes as $class) {
            if (! is_subclass_of($class, Brick::class)) {
                throw new InvalidArgumentException("[{$class}] is not a Flowkit brick.");
            }

            $this->bricks[$class::$key] = $class;
        }

        return $this;
    }

    /**
     * Register every concrete brick class found in the given directories.
     * Paths under app_path() are mapped to the app's namespace.
     */
    public function discover(array $paths): self
    {
        $paths = array_filter($paths, 'is_dir');

        if (! $paths) {
            return $this;
        }

        foreach ((new Finder)->files()->name('*.php')->in($paths) as $file) {
            $class = $this->classFromPath($file->getRealPath());

            if ($class && class_exists($class) && is_subclass_of($class, Brick::class)
                && ! (new ReflectionClass($class))->isAbstract()) {
                $this->register($class);
            }
        }

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->bricks[$key]);
    }

    public function resolve(string $key): Brick
    {
        if (! $this->has($key)) {
            throw UnknownBrick::key($key);
        }

        return $this->container->make($this->bricks[$key]);
    }

    /**
     * @return array<string, class-string<Brick>>
     */
    public function all(?string $type = null): array
    {
        if ($type === null) {
            return $this->bricks;
        }

        return array_filter($this->bricks, fn (string $class) => $class::type() === $type);
    }

    /**
     * Bricks for the editor's picker, grouped by their $group.
     */
    public function grouped(?string $type = null): array
    {
        $groups = [];

        foreach ($this->all($type) as $class) {
            $groups[$class::$group][] = $class::meta();
        }

        ksort($groups);

        return $groups;
    }

    private function classFromPath(string $path): ?string
    {
        $appPath = realpath(app_path());

        if (! $appPath || ! str_starts_with($path, $appPath)) {
            return null;
        }

        $relative = Str::of($path)->after($appPath.DIRECTORY_SEPARATOR)->beforeLast('.php')
            ->replace(DIRECTORY_SEPARATOR, '\\');

        return app()->getNamespace().$relative;
    }
}
