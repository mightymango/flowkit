<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The editor UI is served under this prefix, behind this middleware. The
    | `use-flowkit` gate is always applied on top. Set 'enabled' to false to
    | register your own routes instead.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'flows',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | The Blade layout the editor pages render inside. Point this at your
    | app's own layout once it includes @livewireStyles / @livewireScripts
    | and imports flowkit.css.
    |
    */

    'layout' => 'flowkit::layouts.app',

    /*
    |--------------------------------------------------------------------------
    | Bricks
    |--------------------------------------------------------------------------
    |
    | Triggers, conditions and actions. Classes in the discovery paths that
    | extend a brick base class are registered automatically; anything else
    | can be listed explicitly.
    |
    */

    'bricks' => [
        'discover' => [
            app_path('Flows'),
        ],
        'register' => [
            // App\Flows\Actions\SendTeamsMessage::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | `core.notify` looks user recipients up by id on this model. Leave it
    | null to use the model of the default guard's user provider. The model
    | must use the Notifiable trait.
    |
    */

    'notify' => [
        'user_model' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Migrations
    |--------------------------------------------------------------------------
    |
    | Flowkit's migrations run with the app's own `php artisan migrate`. Set
    | this to false if you run them yourself instead, e.g. in each tenant's
    | database: point the tenant migrator at
    | vendor/mightymango/flowkit/database/migrations.
    |
    */

    'migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'connection' => env('FLOWKIT_QUEUE_CONNECTION'),
        'name' => env('FLOWKIT_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    |
    | When an action throws, the step is tried again after a backoff, up to
    | 'tries' attempts in all. 'backoff' is the wait in seconds before each
    | retry; the last value repeats. Actions that aren't safe to retry get one
    | attempt, unless they declare $usesIdempotencyKey. An action can set its
    | own static $tries.
    |
    */

    'retries' => [
        'tries' => 3,
        'backoff' => [10, 60, 300],
    ],

    /*
    |--------------------------------------------------------------------------
    | Governance
    |--------------------------------------------------------------------------
    |
    | 'retention_days' prunes run history, since payloads can hold personal
    | data. 'require_approval' sends new versions to pending approval before
    | they go live. 'enabled' is the global kill switch.
    |
    */

    'enabled' => env('FLOWKIT_ENABLED', true),

    'retention_days' => 90,

    'require_approval' => false,

    /*
    |--------------------------------------------------------------------------
    | Livewire component overrides
    |--------------------------------------------------------------------------
    |
    | Customisation level 4: bind your own class in place of a package
    | component, e.g. 'flow-editor' => App\Flows\FlowEditor::class.
    |
    */

    'components' => [],

];
