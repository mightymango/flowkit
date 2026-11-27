# Flowkit

A Zapier-style workflow builder for Laravel Livewire apps. `mightymango/flowkit` is a working name.

It has three layers:

- **A headless engine** that runs versioned JSON flow definitions on the queue and records every step. It has no UI dependency, so flows can be built, tested and run from code.
- **A Livewire step-list editor** for staff to build flows.
- **Anonymous Blade components** under `flowkit::`, which you can restyle with CSS tokens, tweak with attributes, or publish and override, the same way Flux works.

> **Status:** starter scaffold, aimed at phases 1–2 of the plan. See [Roadmap](#roadmap).

## Install

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
Flowkit::test('new-starter', $payload); // unsafe actions run in dry-run mode
```

Or from the command line: `php artisan flowkit:run new-starter --payload='{"employee":{...}}' --dry-run`.

### Model triggers

```php
class Employee extends Model
{
    use HasFlowTriggers;

    protected array $flowTriggers = ['created', 'updated'];
}
```

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

Core bricks: `core.manual`, `core.model-event`, `core.branch`, `core.wait`, `core.set-variable`.

## Customisation

| Level | How | Upgrade cost |
| --- | --- | --- |
| 1. Tokens | Override `--flowkit-accent`, `--flowkit-radius`, `--flowkit-surface`… | None |
| 2. Attributes | `<x-flowkit::step.card class="shadow-none" />` | None |
| 3. Publish | `php artisan flowkit:publish step.card` → `resources/views/flowkit/step/card.blade.php` | You own that file |
| 4. Swap Livewire | `'components' => ['flow-editor' => App\Flows\FlowEditor::class]` | You own the logic |

`flowkit:publish` takes component names, `--all`, `--force`, or none for an interactive picker. Each published file starts with the package version it came from.

## Testing

```bash
composer install
composer test
```

## Roadmap

| Phase | Scope | In this scaffold |
| --- | --- | --- |
| 1. Engine | Versioned definitions, queue runner, core bricks, code-first flows | ✅ Mostly. Durable resume after `core.wait` is still to do |
| 2. Editor | Step-list editor, settings from fields, run history, dry-run testing | 🟡 Editor and dry runs; run history UI and `wire:sort` reordering to do |
| 3. Governance | Approval with diff, audit trail, brick permissions, durable waits | ⬜ Config hooks only |
| 4. Customisation | `flowkit:publish`, CSS tokens, `flowkit:outdated`, prop docs | 🟡 Publish and tokens; `flowkit:outdated` to do |

Open questions are in the project plan: package name, minimum Livewire version, durable-workflow library, Teams/Graph sign-off, and whether to borrow from AutoBuilder.
