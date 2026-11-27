<?php

namespace MightyMango\Flowkit\Console;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

use function Laravel\Prompts\multiselect;

/**
 * Copy one or more anonymous Blade components into resources/views/flowkit,
 * where they take precedence over the package's copies. Mirrors flux:publish.
 */
class PublishCommand extends Command
{
    protected $signature = 'flowkit:publish
        {components?* : Component names, e.g. step.card field.select}
        {--all : Publish every component}
        {--force : Overwrite files that have already been published}';

    protected $description = 'Publish Flowkit Blade components so you can change their markup';

    public function handle(Filesystem $files): int
    {
        $available = $this->availableComponents();
        $requested = $this->option('all') ? array_keys($available) : $this->argument('components');

        if (! $requested) {
            $requested = multiselect(
                label: 'Which components would you like to publish?',
                options: array_combine(array_keys($available), array_keys($available)),
                scroll: 15,
                required: true,
            );
        }

        $unknown = array_diff($requested, array_keys($available));
        if ($unknown) {
            $this->components->error('Unknown component(s): '.implode(', ', $unknown));

            return self::FAILURE;
        }

        foreach ($requested as $name) {
            $target = resource_path('views/flowkit/'.str_replace('.', '/', $name).'.blade.php');

            if ($files->exists($target) && ! $this->option('force')) {
                $this->components->warn("[{$name}] is already published. Use --force to overwrite it.");

                continue;
            }

            $files->ensureDirectoryExists(dirname($target));
            $files->put($target, $this->header().$files->get($available[$name]));

            $this->components->info("Published [{$name}] to [".Str::after($target, base_path().'/').'].');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string> component name => source path
     */
    public static function availableComponents(): array
    {
        $root = realpath(__DIR__.'/../../resources/views/components');
        $components = [];

        foreach ((new Finder)->files()->name('*.blade.php')->in($root)->sortByName() as $file) {
            $name = Str::of($file->getRelativePathname())
                ->beforeLast('.blade.php')
                ->replace(DIRECTORY_SEPARATOR, '.')
                ->toString();

            $components[$name] = $file->getRealPath();
        }

        return $components;
    }

    public static function version(): string
    {
        return class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('mightymango/flowkit')
            ? (InstalledVersions::getPrettyVersion('mightymango/flowkit') ?? 'dev')
            : 'dev';
    }

    private function header(): string
    {
        return '{{-- Published from mightymango/flowkit '.self::version().'. This copy overrides the package\'s. --}}'.PHP_EOL;
    }
}
