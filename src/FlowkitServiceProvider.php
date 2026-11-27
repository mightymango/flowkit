<?php

namespace Act\Flowkit;

use Act\Flowkit\Bricks\BrickRegistry;
use Act\Flowkit\Bricks\Core;
use Act\Flowkit\Console\MakeBrickCommand;
use Act\Flowkit\Console\PublishCommand;
use Act\Flowkit\Console\RunCommand;
use Act\Flowkit\Engine\Expression;
use Act\Flowkit\Engine\Runner;
use Act\Flowkit\Livewire\FlowEditor;
use Act\Flowkit\Livewire\FlowList;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FlowkitServiceProvider extends ServiceProvider
{
    /** Bricks every app gets. */
    public const CORE_BRICKS = [
        Core\Manual::class,
        Core\ModelEvent::class,
        Core\Branch::class,
        Core\Wait::class,
        Core\SetVariable::class,
    ];

    /** Livewire components, by name under the `flowkit.` prefix. */
    public const COMPONENTS = [
        'flow-list' => FlowList::class,
        'flow-editor' => FlowEditor::class,
    ];

    public function register(): void
    {
        // 1. Config.
        $this->mergeConfigFrom(__DIR__.'/../config/flowkit.php', 'flowkit');

        $this->app->singleton(BrickRegistry::class);
        $this->app->singleton(Expression::class);
        $this->app->singleton(Runner::class);
        $this->app->singleton(Flowkit::class);
        $this->app->singleton(TriggerDispatcher::class);
    }

    public function boot(): void
    {
        // 1. Migrations.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerViews();
        $this->registerLivewireComponents();
        $this->registerBricks();
        $this->registerRoutes();
        $this->registerGate();

        if ($this->app->runningInConsole()) {
            $this->commands([PublishCommand::class, MakeBrickCommand::class, RunCommand::class]);

            $this->publishes([
                __DIR__.'/../config/flowkit.php' => config_path('flowkit.php'),
            ], 'flowkit-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'flowkit-migrations');

            $this->publishes([
                __DIR__.'/../resources/css/flowkit.css' => resource_path('css/flowkit.css'),
            ], 'flowkit-css');
        }
    }

    /**
     * 2. Views. The app's override path is registered before the package's
     * own, so a file published to resources/views/flowkit wins and every
     * other component still comes from the package. Same mechanism as Flux.
     */
    protected function registerViews(): void
    {
        Blade::anonymousComponentPath(resource_path('views/flowkit'), 'flowkit');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'flowkit');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'flowkit');
    }

    /**
     * 3. Livewire components, swappable in config (customisation level 4).
     */
    protected function registerLivewireComponents(): void
    {
        foreach (self::COMPONENTS as $name => $class) {
            Livewire::component("flowkit.{$name}", config("flowkit.components.{$name}", $class));
        }
    }

    /**
     * 4. Bricks: core, explicitly listed, then discovered.
     */
    protected function registerBricks(): void
    {
        $this->app->afterResolving(BrickRegistry::class, function (BrickRegistry $registry) {
            $registry
                ->register(self::CORE_BRICKS)
                ->register(config('flowkit.bricks.register', []))
                ->discover(config('flowkit.bricks.discover', []));
        });
    }

    /**
     * 5. Routes, behind configurable middleware and the `use-flowkit` gate.
     */
    protected function registerRoutes(): void
    {
        if (! config('flowkit.routes.enabled', true)) {
            return;
        }

        Route::group([
            'prefix' => config('flowkit.routes.prefix', 'flows'),
            'middleware' => array_merge(config('flowkit.routes.middleware', ['web']), ['can:use-flowkit']),
            'as' => 'flowkit.',
        ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/web.php'));
    }

    /**
     * Only local by default. Define `use-flowkit` in your app to open it up.
     */
    protected function registerGate(): void
    {
        $this->app->booted(function () {
            if (! Gate::has('use-flowkit')) {
                Gate::define('use-flowkit', fn ($user = null) => $this->app->environment('local'));
            }
        });
    }
}
