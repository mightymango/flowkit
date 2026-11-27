<?php

namespace MightyMango\Flowkit\Tests;

use MightyMango\Flowkit\FlowkitServiceProvider;
use MightyMango\Flowkit\Tests\Fixtures\Bricks\HasRole;
use MightyMango\Flowkit\Tests\Fixtures\Bricks\SendMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            FlowkitServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('flowkit.bricks.register', [SendMessage::class, HasRole::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        SendMessage::$sent = [];
    }
}
