<?php

use MightyMango\Flowkit\FlowkitServiceProvider;

/**
 * A provider that records the migration paths it would register.
 */
function recordingProvider(): FlowkitServiceProvider
{
    return new class(app()) extends FlowkitServiceProvider
    {
        /** @var list<string|array<string>> */
        public array $loaded = [];

        protected function loadMigrationsFrom($paths): void
        {
            $this->loaded[] = $paths;
        }
    };
}

it('loads its migrations by default', function () {
    $provider = recordingProvider();
    $provider->boot();

    expect($provider->loaded)->toBe([FlowkitServiceProvider::MIGRATIONS_PATH])
        ->and(app('migrator')->paths())->toContain(FlowkitServiceProvider::MIGRATIONS_PATH);
});

it('leaves its migrations to the app when migrations is false', function () {
    config(['flowkit.migrations' => false]);

    $provider = recordingProvider();
    $provider->boot();

    expect($provider->loaded)->toBe([]);
});
