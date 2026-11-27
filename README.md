# Flowkit

A Zapier-style workflow builder for Laravel Livewire apps.

It has three layers:

- **A headless engine** that runs versioned JSON flow definitions on the queue and records every step. It has no UI dependency, so flows can be built, tested and run from code.
- **A Livewire step-list editor** for staff to build flows.
- **Anonymous Blade components** under `flowkit::`, which you can restyle with CSS tokens, tweak with attributes, or publish and override, the same way Flux works.

> **Status:** starter scaffold, aimed at phases 1–2 of the plan. See [Roadmap](#roadmap), the full design in [docs/plan.md](docs/plan.md) and the editor mockup in [docs/mockup.html](docs/mockup.html).

## Install

Requires PHP 8.2+, Laravel 12 or 13, and Livewire 4.

```bash
composer require mightymango/flowkit
php artisan migrate
```

Allow access to the editor (it's local-only by default):

```php
// AppServiceProvider::boot()
Gate::define('use-flowkit', fn (User $user) => $user->isAdmin());
```

Import the tokens and let Tailwind see the package views:

```css
@import "../../vendor/mightymango/flowkit/resources/css/flowkit.css";
@source "../../vendor/mightymango/flowkit/resources/views";
```

The editor lives at `/flows`. Point `flowkit.layout` at your own layout (it needs a `content` section and Livewire's assets).

## Flows

A flow is a trigger plus an ordered list of steps. Branch steps hold named child lists.

```json
{
  "trigger": { "brick": "core.model-event", "settings": { "model": "App\\Models\\Employee", "event": "created" } },
  "steps": [
    { "id": "s1", "brick": "core.branch", "settings": { "when": "employee.department == 'Delivery'" },
      "branches": { "yes": [ ... ], "otherwise": [ ... ] } },
    { "id": "s2", "brick": "core.wait", "settings": { "duration": "P7D" } }
  ]
}
```

Runs point at a **version**, never the flow. Editing creates a new draft version, so runs in progress finish on the definition they started with. Published versions are immutable.

### Code-first flows

```php
Flowkit::define('new-starter')
    ->name('New starter')
    ->trigger('core.manual')
    ->branch('s1', "employee.department == 'Delivery'",
        yes: fn ($b) => $b->step('s2', 'teams.message', ['to' => '{{ employee.manager_id }}']),
    )
    ->save();

Flowkit::start('new-starter', ['employee' => $employee->toArray()]);
Flowkit::run('new-starter', $payload);  // runs now, in this process, not on the queue
Flowkit::test('new-starter', $payload); // unsafe actions run in dry-run mode
```

Or from the command line: `php artisan flowkit:run new-starter --payload='{"employee":{...}}' --dry-run`.

### Model triggers

```php
class Employee extends Model
{
    use HasFlowTriggers;

    protected array $flowTriggers = ['created', 'updated', 'status' => 'changed'];
}
```

Plain entries (`created`, `updated`, `deleted`) fire on those model events. Without `$flowTriggers`, a model fires `created` and `updated`. The model's attributes are in the payload under its snake-cased class name, e.g. `employee.department`; override `toFlowPayload()` to limit what flows see.

An `'attribute' => 'changed'` entry watches that attribute: whenever an update changes it, the model fires a `changed` event with the old and new values under `changes`:

```json
{
  "employee": { "id": 7, "name": "Ana", "status": "leaver" },
  "event": "changed",
  "changes": { "status": { "from": "active", "to": "leaver" } }
}
```

Enum casts are reduced to their value and dates to ISO 8601 strings. To start a flow when an employee becomes a leaver, set the `core.model-event` trigger to:

```php
->trigger('core.model-event', [
    'model' => Employee::class,
    'event' => 'changed',
    'attribute' => 'status',   // must be watched in $flowTriggers
    'from' => null,            // optional: only when the old value is this
    'to' => 'leaver',          // optional: only when the new value is this
])
```

An empty `from` or `to` matches any value; values are compared as strings. A trigger whose settings fail validation never matches.

### Scheduled flows

The `core.schedule` trigger starts a flow on a cron expression, such as `0 9 * * 1` for 9am every Monday, with an optional time zone (the app's by default). The run gets `schedule.scheduled_at` and `schedule.timezone`.

Schedule the tick command once in your app; it starts every live, unpaused flow that is due:

```php
// routes/console.php
Schedule::command('flowkit:tick')->everyMinute();
```

It's safe to run on several servers or to let ticks overlap: each flow is claimed for a minute with an atomic update to `flows.last_scheduled_at`, so it starts once. Missed minutes (if the scheduler was down) aren't caught up.

### Expressions

Conditions and `{{ templates }}` are evaluated with Symfony ExpressionLanguage, never `eval` or Blade. No functions are registered. Each step's output is available to later steps as `steps.<id>.<key>`.

## Bricks

Triggers, conditions and actions are PHP classes that declare their own fields. The settings panel is built from them, so a new brick needs no Blade.

```bash
php artisan flowkit:make-brick SendTeamsMessage --action   # app/Flows/Actions
```

```php
class SendTeamsMessage extends Action
{
    public static string $key = 'teams.message';
    public static string $label = 'Send Teams message';
    public static string $group = 'Teams';
    public static bool $retrySafe = false;

    public function fields(): array
    {
        return [
            Select::make('to')->label('Send to')->variables('user')->required(),
            Textarea::make('message')->supportsVariables()->required(),
        ];
    }

    public function handle(Context $context, array $settings): Result
    {
        // $context->idempotencyKey('...') for anything that mustn't happen twice
        return Result::ok(['message_id' => $id]);
    }
}
```

Bricks in `app/Flows` are discovered automatically; others can be listed in `config/flowkit.php`.

### Conditions

A `Condition` brick returns true or false. Use one in two ways:

- **In a branch**, instead of an expression. The branch's settings name the condition and hold its own settings under `condition_settings`. In the editor, pick it from the branch's **Check** list.
- **As a step on its own**, as a filter. If it's false, the run stops there and completes without running the steps after it. The step's output is `{"passed": false, "stopped": true}`.

```php
Flowkit::define('managers')
    ->trigger('core.manual')
    ->branchOn('s1', 'app.is-manager', ['team' => '{{ employee.team }}'],
        yes: fn ($b) => $b->step('s2', 'core.set-variable', ['value' => 'manager']),
    )
    ->step('s3', 'app.is-active', [])   // stops the run if false
    ->save();
```

Core bricks: `core.manual`, `core.model-event`, `core.schedule`, `core.branch`, `core.wait`, `core.set-variable`.

## Customisation

| Level | How | Upgrade cost |
| --- | --- | --- |
| 1. Tokens | Override `--flowkit-accent`, `--flowkit-radius`, `--flowkit-surface`… | None |
| 2. Attributes | `<x-flowkit::step.card class="shadow-none" />` | None |
| 3. Publish | `php artisan flowkit:publish step.card` → `resources/views/flowkit/step/card.blade.php` | You own that file |
| 4. Swap Livewire | `'components' => ['flow-editor' => App\Flows\FlowEditor::class]` | You own the logic |

`flowkit:publish` takes component names, `--all`, `--force`, or none for an interactive picker. Each published file starts with the package version it came from.

## Testing

### Testing your flows

`Flowkit::fake()` works like `Queue::fake()`. Flows started with `Flowkit::start()`, `Flowkit::test()` or a model trigger are recorded as pending runs and never executed, so you can assert that your code started the right flow without any actions running:

```php
use MightyMango\Flowkit\Facades\Flowkit;

Flowkit::fake();

Employee::create(['name' => 'Ana', 'department' => 'Delivery']);

Flowkit::assertStarted('new-starter');
Flowkit::assertStarted('new-starter', fn (array $payload, FlowRun $run) => $payload['employee']['name'] === 'Ana');
Flowkit::assertStartedTimes('new-starter', 1);
Flowkit::assertNotStarted('leaver');
Flowkit::assertNothingStarted();
```

To test what a flow does, run it with `runFlow()`. It runs the flow straight away, in the test's process, whatever your queue driver, and returns the run with assertions:

```php
runFlow('new-starter', ['employee' => ['name' => 'Ana', 'department' => 'Delivery']])
    ->assertCompleted()                 // or assertFailed(), assertWaiting()
    ->assertStepsRan(['s1', 's2', 's4'])
    ->assertStepCompleted('s2')         // or assertStepSkipped(), assertStepFailed('s2', 'error text'), assertStepWaiting()
    ->assertStepNotReached('s3')
    ->assertStepOutput('s1', 'branch', 'yes');

runFlow('new-starter', $payload, dryRun: true); // unsafe actions run in dry-run mode
```

Runs started with `runFlow()` or `Flowkit::run()` still execute under the fake, and the fake records every action they reach:

```php
Flowkit::fake();

runFlow('new-starter', $payload);

Flowkit::assertActionRan('teams.message');
Flowkit::assertActionRan('teams.message', fn (array $input, array $output) => $input['to'] === 'delivery-lead');
Flowkit::assertActionRanTimes('teams.message', 1);
Flowkit::assertActionNotRan('hr.update-record');
```

The callback gets the step's resolved settings, its output and the `FlowRunStep`. Unsafe actions in a dry run don't count as having run. The helpers use plain PHPUnit assertions, so they work with Pest or PHPUnit; `runFlow()` is autoloaded with the package and is skipped if your app already defines a function of that name (use `new MightyMango\Flowkit\Testing\TestRun(Flowkit::run(...))` instead).

### Testing the package

```bash
composer install
composer test
```

## Roadmap

| Phase | Scope | In this scaffold |
| --- | --- | --- |
| 1. Engine | Versioned definitions, queue runner, core bricks, code-first flows | ✅ Mostly, including scheduled triggers and the `Flowkit::fake()` and `runFlow()` test helpers. Durable resume after `core.wait` is still to do |
| 2. Editor | Step-list editor, settings from fields, run history, dry-run testing | 🟡 Editor and dry runs; run history UI and `wire:sort` reordering to do |
| 3. Governance | Approval with diff, audit trail, brick permissions, durable waits | ⬜ Config hooks only |
| 4. Customisation | `flowkit:publish`, CSS tokens, `flowkit:outdated`, prop docs | 🟡 Publish and tokens; `flowkit:outdated` to do |

Work is tracked as GitHub issues under four phase milestones. The one open question, Teams/Graph sign-off, is in [docs/plan.md](docs/plan.md). Settled decisions are recorded under [Decisions](docs/plan.md#decisions).

## Licence

Flowkit is open-source software licensed under the [MIT licence](LICENSE.md).
