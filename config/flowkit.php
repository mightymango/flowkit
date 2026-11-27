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
    | Queue
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'connection' => env('FLOWKIT_QUEUE_CONNECTION'),
        'name' => env('FLOWKIT_QUEUE', 'default'),
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
