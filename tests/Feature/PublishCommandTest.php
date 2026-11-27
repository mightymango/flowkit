<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

afterEach(fn () => File::deleteDirectory(resource_path('views/flowkit')));

it('publishes a component with a version header', function () {
    $this->artisan('flowkit:publish', ['components' => ['step.card']])->assertSuccessful();

    $path = resource_path('views/flowkit/step/card.blade.php');

    expect(File::exists($path))->toBeTrue()
        ->and(File::get($path))->toStartWith('{{-- Published from mightymango/flowkit');
});

it('refuses to overwrite without --force', function () {
    $this->artisan('flowkit:publish', ['components' => ['step.card']]);
    File::put(resource_path('views/flowkit/step/card.blade.php'), 'mine');

    $this->artisan('flowkit:publish', ['components' => ['step.card']])->assertSuccessful();
    expect(File::get(resource_path('views/flowkit/step/card.blade.php')))->toBe('mine');

    $this->artisan('flowkit:publish', ['components' => ['step.card'], '--force' => true]);
    expect(File::get(resource_path('views/flowkit/step/card.blade.php')))->not->toBe('mine');
});

it('rejects unknown components', function () {
    $this->artisan('flowkit:publish', ['components' => ['nope']])->assertFailed();
});

it('lets a published component override the package copy', function () {
    File::ensureDirectoryExists(resource_path('views/flowkit'));
    File::put(resource_path('views/flowkit/empty-state.blade.php'), '<p>overridden {{ $title }}</p>');

    expect(Blade::render('<x-flowkit::empty-state title="Hi" />'))->toContain('overridden Hi');
});
