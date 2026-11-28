# Flowkit

[![tests](https://github.com/mightymango/flowkit/actions/workflows/tests.yml/badge.svg)](https://github.com/mightymango/flowkit/actions/workflows/tests.yml)
[![quality](https://github.com/mightymango/flowkit/actions/workflows/quality.yml/badge.svg)](https://github.com/mightymango/flowkit/actions/workflows/quality.yml)
[![Licence: MIT](https://img.shields.io/badge/licence-MIT-blue.svg)](LICENSE.md)

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

### Multi-tenant apps

Flowkit has no tenancy code of its own. It uses the default database connection, queue and cache, so it works inside whatever tenant your tenancy package has initialised. With a database per tenant (e.g. stancl/tenancy):

- **Migrations:** set `'migrations' => false` in `config/flowkit.php`, so `php artisan migrate` doesn't create Flowkit's tables in the central database. Then add `vendor/mightymango/flowkit/database/migrations` to your tenant migration paths. With stancl/tenancy, that's `tenancy.migration_parameters.--path`.
- **Queued runs:** these need to run in the right tenant. stancl/tenancy's `QueueTenancyBootstrapper` does this for Flowkit's jobs.
- **Scheduled flows:** run the tick for every tenant: `Schedule::command('tenants:run flowkit:tick')->everyMinute()`.
- **Code-first flows:** save them inside each tenant, e.g. from a tenant migration or `tenants:run`. `Flowkit::define(...)->save()` doesn't publish a new version if the definition hasn't changed, so it's safe to run again.
- **The editor:** its routes need your tenant middleware. Set `flowkit.routes.middleware`, or set `flowkit.routes.enabled` to false and register the routes yourself.

### Languages

Flowkit ships in English and Welsh, and follows the app's locale (`app()->getLocale()`).

- **Flowkit's own text** (buttons, headings, errors, status names) is in `flowkit::` language files. To change any of it, or add another language, publish them and edit the copies in `lang/vendor/flowkit`:

  ```bash
  php artisan vendor:publish --tag=flowkit-lang
  ```

- **Brick and field text** stays as plain English in your PHP classes: labels, groups, descriptions, field labels, placeholders, help and option labels. Flowkit translates it when it shows it, through your app's JSON translations. For example, `lang/cy.json` with `"Send Teams message": "Anfon neges Teams"`. Core bricks' Welsh ships with Flowkit, and your own JSON overrides it.
- **Validation messages** use the translated field labels as attribute names, and the app's own `validation` translations.

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
        // Claim the step before sending, so a retry can't send twice.
        if (! $context->claimOnce()) {
            return Result::skip(['already_sent' => true]);
        }

        // ...send the message...

        return Result::ok(['message_id' => $id]);
    }
}
```

Bricks in `app/Flows` are discovered automatically; others can be listed in `config/flowkit.php`.

An action declares what it puts into the run for later steps in `outputs()`, e.g. `['message_id' => 'Message ID']`, available as `{{ steps.<id>.message_id }}`. In the editor, fields that take variables show chips that insert the trigger's variables and the outputs of steps that always run before this one (not steps inside a branch that may not have run).

A trigger declares the variables it puts into the run in `variables()` (or `variablesFor($settings)` when they depend on its settings). A variable can have a type, which selects ask for: `'employee.manager_id' => ['label' => 'Manager', 'type' => 'user']`.

### Fields

A brick's `fields()` declare its settings. Each field renders in the editor and supplies the validation rules that the editor and the runner both apply.

| Field | Stores | Notes |
| --- | --- | --- |
| `Text`, `Textarea` | A string | `->supportsVariables()` allows `{{ placeholders }}` |
| `Select` | One of `->options([...])` | Options can be a closure. `->variables('user')` offers the trigger's variables of that type instead |
| `ModelPicker` | A record's key | `->model(User::class)->searchUsing(['name', 'email'])->display('name')`, optional `->scope()`. Only the declared model and columns are searched |
| `Number` | A number | `->integer()`, `->min()`, `->max()`. With `->supportsVariables()`, the editor accepts a single `{{ placeholder }}` and the runner checks the resolved value |
| `Duration` | An ISO 8601 duration, e.g. `P7D` | |

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

Core bricks: `core.manual`, `core.model-event`, `core.schedule`, `core.branch`, `core.wait`, `core.set-variable`, `core.notify`.

`core.notify` sends an email to a user (a user id from a variable, looked up on `flowkit.notify.user_model` or the auth provider's model) or to an email address. The subject and message take `{{ variables }}`; the message is sent as plain text, never rendered as Blade. A dry run shows what would be sent, and the step's idempotency key stops a retry from sending twice.

### Retries

If an action throws, its step is tried again after a backoff, and each attempt is recorded in `flow_run_steps.attempt`. By default that's 3 attempts in all, waiting 10s, then 60s, then 300s, set in `flowkit.retries`. An action can set its own `public static ?int $tries`.

- **Not retried:** a step whose settings fail validation, or an action that returns `Result::fail()`.
- **Unsafe actions** (`$retrySafe = false`) get one attempt. The exception is one that sets `public static bool $usesIdempotencyKey = true`, meaning its `handle()` calls `$context->claimOnce()` before doing anything that mustn't happen twice. The claim is a row in the `flow_claims` table, keyed by run and step, and is deleted with its run; it doesn't use the cache, so it works with any cache store, including under tenancy packages that tag cache calls.

Resume a failed run from the step that failed:

```php
Flowkit::retry($run);
```

```bash
php artisan flowkit:retry 42
```

A resumed run doesn't repeat steps that already completed. It follows the branches it took before and starts the failed step as a new attempt. If that step isn't safe to repeat, the command asks first; skip the question with `--force`.

### Events

The runner fires Laravel events you can listen for, e.g. for metrics or alerts:

| Event | When | Properties |
| --- | --- | --- |
| `MightyMango\Flowkit\Events\RunStarted` | A run starts executing, and again each time it resumes | `runId`, `flowVersionId`, `correlationId`, `dryRun`, `resumed` |
| `MightyMango\Flowkit\Events\StepFinished` | Each attempt at a step is recorded | `runId`, `stepRecordId`, `stepId`, `brick`, `status`, `attempt`, `handled` |
| `MightyMango\Flowkit\Events\RunFinished` | A run completes or fails (not when it waits or is queued for a retry) | `runId`, `flowVersionId`, `correlationId`, `status`, `dryRun` |

`handled` is true when the brick's own logic really ran: an action's `handle()`, a condition's check or a branch's decision. It's false when the settings failed validation, or when an unsafe action was replaced by its dry run.

The events carry IDs and status only, never the payload, input or output, because run data can contain personal data. If a listener loads the run or step record for more, take the same care with it.

## Customisation

| Level | How | Upgrade cost |
| --- | --- | --- |
| 1. Tokens | Override `--flowkit-accent`, `--flowkit-radius`, `--flowkit-surface`… | None |
| 2. Attributes | `<x-flowkit::step.card class="shadow-none" />` | None |
| 3. Publish | `php artisan flowkit:publish step.card` → `resources/views/flowkit/step/card.blade.php` | You own that file |
| 4. Swap Livewire | `'components' => ['flow-editor' => App\Flows\FlowEditor::class]`; also `flow-list` and `step-settings` | You own the logic |

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

Runs started with `runFlow()` or `Flowkit::run()` still execute under the fake, and the fake records every action whose `handle()` runs, one entry per attempt:

```php
Flowkit::fake();

runFlow('new-starter', $payload);

Flowkit::assertActionRan('teams.message');
Flowkit::assertActionRan('teams.message', fn (array $input, array $output) => $input['to'] === 'delivery-lead');
Flowkit::assertActionRanTimes('teams.message', 1);
Flowkit::assertActionNotRan('hr.update-record');
```

The callback gets the step's resolved settings, its output and the `FlowRunStep`. An action whose settings failed validation, or an unsafe action replaced by its dry run, doesn't count as having run. The fake listens for the runner's `StepFinished` event, so don't use `Event::fake()` for that event in the same test. The helpers use plain PHPUnit assertions, so they work with Pest or PHPUnit; `runFlow()` is autoloaded with the package and is skipped if your app already defines a function of that name (use `new MightyMango\Flowkit\Testing\TestRun(Flowkit::run(...))` instead).

Under the fake, `Flowkit::retry($run)` and `flowkit:retry` record the retry rather than running it. As with a real retry, a run that hasn't failed is refused, and a retried run goes back to `pending`:

```php
Flowkit::fake();

Flowkit::retry($run);

Flowkit::assertRetried($run);
Flowkit::assertNotRetried($otherRun);
Flowkit::assertNothingRetried();
```

### Testing the package

```bash
composer install
composer test        # Pest
composer lint        # Pint: fix code style (composer lint -- --test to check only)
composer analyse     # Larastan, level 5
```

## Roadmap

| Phase | Scope | In this scaffold |
| --- | --- | --- |
| 1. Engine | Versioned definitions, queue runner, core bricks, code-first flows | ✅ Mostly, including scheduled triggers and the `Flowkit::fake()` and `runFlow()` test helpers. Durable resume after `core.wait` is still to do |
| 2. Editor | Step-list editor, settings from fields, run history, dry-run testing | 🟡 Editor, settings panel, number, duration and model-picker fields, variable chips, run history (`/flows/{flow}/runs`) and testing the draft from the editor; `wire:sort` reordering to do |
| 3. Governance | Approval with diff, audit trail, brick permissions, durable waits | ⬜ Config hooks only |
| 4. Customisation | `flowkit:publish`, CSS tokens, `flowkit:outdated`, prop docs | 🟡 Publish and tokens; `flowkit:outdated` to do |

Work is tracked as GitHub issues under four phase milestones. The one open question, Teams/Graph sign-off, is in [docs/plan.md](docs/plan.md). Settled decisions are recorded under [Decisions](docs/plan.md#decisions).

## Licence

Flowkit is open-source software licensed under the [MIT licence](LICENSE.md).
