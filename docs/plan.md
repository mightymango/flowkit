# Livewire workflow builder package – plan

27 Nov 2026 · Simon Barrett

## Summary

Build one Composer package, `mightymango/flowkit` (PHP namespace `MightyMango\Flowkit`, repo github.com/mightymango/flowkit), with three layers: a headless workflow engine, a Livewire step-list editor, and a library of anonymous Blade components under the `flowkit::` namespace. The Blade components can be published and overridden the same way Flux's are.

The key decisions:

- **No Vue, React or other front-end framework.** The editor is Livewire and Blade, plus the Alpine that Livewire already ships. Reordering uses `wire:sort`, and the package has no npm build of its own.
- **Engine and UI are separate.** The engine has no UI dependency, so flows can be built, tested and run from code. The editor is one way to produce a flow definition, not the only one.
- **Every visible element is an anonymous Blade component.** `php artisan flowkit:publish step-card` copies that file into `resources/views/flowkit/`, and the app's copy wins. This mirrors `flux:publish`.
- **Theming uses CSS custom properties and Tailwind**, so most restyling needs no published files at all. Publish a component only to change its markup.
- **Bricks (triggers, conditions, actions) are PHP classes** that declare their own settings fields. The settings panel is built from that declaration, so a new brick needs no Blade.
- **Delays and waits are resumed by Flowkit itself**: a `resume_at` time on the run, a delayed resume job, and a scheduled sweeper. It sits behind a `WaitScheduler` interface, so a durable-workflow library can be plugged in later (see [Decisions](#decisions)).

**Recommendation:** build the engine and a minimal editor first (phases 1–2), prove them inside a first host app, and only then invest in the publishing and theming layer.

## Package structure

One package with clear internal boundaries is enough for a two-person team. Split into separate engine and UI packages only if another app ever needs the engine without the editor.

```
flowkit/
├── config/flowkit.php
├── database/migrations/
├── resources/
│   ├── css/flowkit.css          # tokens + base styles
│   └── views/
│       ├── components/          # anonymous Blade: flowkit::step-card etc.
│       └── app/                 # pages, layouts and Livewire editor views
├── routes/web.php
├── src/
│   ├── Engine/                  # Runner, Context, Expression, Definition
│   ├── Bricks/                  # contracts, base classes, built-in bricks
│   ├── Fields/                  # Text, Select, ModelPicker, Duration...
│   ├── Livewire/                # FlowEditor, StepSettings, RunHistory
│   ├── Models/                  # Flow, FlowVersion, FlowRun, FlowRunStep
│   ├── Console/                 # flowkit:publish, flowkit:make-brick, flowkit:run
│   ├── Facades/Flowkit.php
│   └── FlowkitServiceProvider.php
└── tests/
```

The non-component views sit in `views/app` rather than `views/livewire` because Laravel resolves `flowkit::components.*` before anonymous component paths. If the `flowkit` view namespace contained a `components` folder, the package's copy would shadow a published override.

The service provider does five things:

1. Merges the config and loads the migrations.
2. Registers the view namespace with the **app's override path first** (`resources/views/flowkit`), then the package path. This ordering is the core of the Flux-style override.
3. Registers the Livewire components under a `flowkit.` prefix.
4. Discovers bricks from configured paths (default `app/Flows`) and registers them.
5. Registers routes behind configurable middleware and a `use-flowkit` gate.

The config covers route prefix and middleware, brick discovery paths, the queue connection and name, the run-history retention period, and whether flows need approval before publishing.

## Core engine

The engine runs a versioned JSON flow definition against a context, one step at a time, on the queue. It records every step it takes.

**Data model**

| Table | Holds |
| --- | --- |
| `flows` | Name, owner, status (draft, pending approval, live, archived), pointer to the live version |
| `flow_versions` | Immutable definition JSON, version number, author, approver, published time |
| `flow_runs` | Flow version, trigger payload, status, start and finish times, correlation ID |
| `flow_run_steps` | Step ID, input, output, status, error, duration, attempt number |

Runs always point at a version, never at the flow. Editing a flow creates a new draft version, so runs already in progress finish on the definition they started with. AutoBuilder doesn't do this: its runs point at the flow, which is edited in place.

**Definition shape.** A flow is a trigger plus an ordered list of steps. A branch step holds named child lists, e.g. `yes` and `otherwise`, which maps directly onto the step-list UI.

```json
{
  "trigger": { "brick": "app.employee-created", "settings": {} },
  "steps": [
    { "id": "s1", "brick": "core.branch",
      "settings": { "when": "employee.department == 'Delivery'" },
      "branches": { "yes": [ ... ], "otherwise": [ ... ] } },
    { "id": "s2", "brick": "core.wait", "settings": { "duration": "P7D" } },
    { "id": "s3", "brick": "core.notify", "settings": { ... } }
  ]
}
```

**Runner.** A trigger listener creates a `FlowRun` and dispatches a job. The runner walks the step list, resolves each step's settings against the context, calls the brick, writes a `flow_run_steps` row and merges the output into the context. Waits hand off to the `WaitScheduler`, which resumes the run later.

**Expressions.** Templated strings, such as `{{ employee.name }}`, and conditions are evaluated with Symfony ExpressionLanguage against a whitelisted context. They are never evaluated with `eval` or Blade.

**Retries and idempotency.** Each action declares whether it is safe to retry. Unsafe actions (sending a message, calling an external API) carry an idempotency key made from run ID and step ID, so a retried job doesn't send twice.

## UI layer

The editor is a few Livewire components that compose anonymous Blade components. Logic lives in Livewire; markup lives in Blade components that can be published.

**Livewire components** (logic, not normally published):

| Component | Responsibility |
| --- | --- |
| `flowkit.flow-editor` | Loads a draft version, holds the step tree, add/move/delete steps, saves, validates |
| `flowkit.step-settings` | Renders the selected step's fields from the brick's declaration, validates them |
| `flowkit.flow-list` | Index of flows with status, owner and last run |
| `flowkit.run-history` | Runs per flow, with per-step input, output and errors |
| `flowkit.approval` | Diff between versions, approve or reject with a comment |

**Blade components** (markup, publishable), for example:

- `flowkit::editor.layout`, `flowkit::editor.header`, `flowkit::editor.toolbar`
- `flowkit::step.card`, `flowkit::step.connector`, `flowkit::step.add-button`, `flowkit::step.badge`
- `flowkit::branch.group`, `flowkit::branch.column`
- `flowkit::field.text`, `flowkit::field.select`, `flowkit::field.model-picker`, `flowkit::field.duration`, `flowkit::field.variable-chips`
- `flowkit::run.timeline`, `flowkit::run.step-row`, `flowkit::status-pill`, `flowkit::empty-state`

The step tree renders recursively: `step.card` for normal steps, `branch.group` for branches, which in turn render child step lists. Reordering uses Livewire 4's `wire:sort` and updates the tree by step ID.

Each Blade component should take props for its data and merge `$attributes` onto its root element. That way a host app can tweak a component in place, e.g. `<x-flowkit::step.card class="shadow-none" />`, without publishing it.

## Flux-style publishing and customisation

The package gives four levels of customisation. Each needs more ownership than the one before, so the aim is that most apps never go past the second.

| Level | How | Upgrade cost |
| --- | --- | --- |
| 1. Tokens | Override CSS custom properties, e.g. `--flowkit-accent`, `--flowkit-radius`, `--flowkit-surface` | None |
| 2. Attributes | Pass `class` or other attributes to a component; merged onto its root | None |
| 3. Publish a component | `php artisan flowkit:publish step.card` copies it to `resources/views/flowkit/step/card.blade.php`, which then wins | You own that file |
| 4. Swap a Livewire component | Bind your own class in config, e.g. `'components' => ['step-settings' => App\Flows\StepSettings::class]` | You own the logic |

**How the override works.** The service provider registers the `flowkit` view namespace with two paths: `resource_path('views/flowkit')` first, then the package's own views. Laravel takes the first match, so a published file replaces the package's copy and everything else still comes from the package. This is the same mechanism Flux uses.

**The publish command** should:

- Accept one or more component names, or run interactively with a searchable multi-select (Laravel Prompts) when given none.
- Support `--all` and `--force`.
- Refuse to overwrite an existing published file without `--force`.
- Add a header comment to each published file with the package version it came from, so you can tell which overrides are out of date after an upgrade.

**Theming.** Ship `flowkit.css` with the tokens defined on `:root` plus a dark-mode block, and build the components with Tailwind classes that reference those tokens, e.g. `bg-[var(--flowkit-surface)]`. The host app imports the CSS and adds the package's views to Tailwind's content sources, as Flux requires. Matching your style guide is then a single block of token overrides.

**Pinning the component contract.** A published file is only safe across upgrades if its props stay stable. Document each component's props, treat a breaking prop change as a major version, and add a `flowkit:outdated` command that lists published files whose version header is behind the installed package.

## Developer API

A host app adds behaviour by writing brick classes. `php artisan flowkit:make-brick SendTeamsMessage --action` scaffolds one in `app/Flows/Actions`.

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
        // call Graph API via the host app's own service
        return Result::ok(['message_id' => $id]);
    }
}
```

**Brick types:**

- `Trigger`: declares which event or schedule starts a run, and which variables it puts into the context. The variable list drives the variable chips in the UI.
- `Condition`: returns true or false; used by branch steps.
- `Action`: does the work and returns outputs that later steps can use.

**Fields** are small PHP classes that each map to a Blade component, e.g. `Select` renders `flowkit::field.select`. A new field type is a class plus a component, and the component can be published like any other.

**Model triggers.** Rather than listening to every model, a host model opts in with a `HasFlowTriggers` trait that declares which events (created, updated, a status change) fire triggers.

**Code-first flows.** `Flowkit::define('new-starter')` builds the same definition JSON in PHP. Use it for flows that should live in version control rather than be edited by staff, and for tests.

## Governance, security and testing

Staff-authored automation that touches personal data needs controls built in from the start, not bolted on later.

**Governance**

- Gates for viewing, editing, approving and running flows, plus per-brick permissions, so a sensitive action such as "update an employee record" can be limited to certain roles.
- An approval step before any version goes live, with a diff of what changed, configurable per flow.
- An audit trail of who created, edited, approved and paused each flow, and each run's full history.
- A retention period for run history, since run payloads can contain personal data.
- A kill switch per flow and a global one.

**Security**

- Expressions are sandboxed: no function calls beyond a whitelist, and the context exposes only the variables the trigger declared.
- Webhook triggers are signed and rate-limited.
- Field values are validated server-side, never trusted from the Livewire payload.
- Outbound HTTP actions are limited to an allowlist of hosts in config.

**Testing**

- A `Flowkit::fake()` helper that records which flows ran and which actions they called, mirroring `Queue::fake()`.
- Pest helpers such as `runFlow('new-starter', $payload)->assertStepCompleted('s3')`.
- A test run in the UI that executes against real data with actions in dry-run mode, logging what each action would have done.
- Each brick unit-tested on its own, with the runner tested against fixture definitions.

## Delivery phases and open questions

The plan has four phases, each ending with a gate. Don't start a phase until the previous one has passed its gate: the publishing layer is only worth building once the engine and editor have proved themselves.

| Phase | Scope | Gate |
| --- | --- | --- |
| 1. Engine | Versioned definitions, queue runner, core bricks, code-first flows | A flow is live in a host app |
| 2. Editor | Step-list editor, settings from fields, run history, dry-run testing | Staff build a flow unaided |
| 3. Governance | Approval with diff, audit trail, brick permissions, durable waits | Governance sign-off |
| 4. Customisation | `flowkit:publish`, CSS tokens, `flowkit:outdated`, component prop docs | A second app adopts it |

Phases 1 and 2 deliver something usable inside a host app on their own. Phases 3 and 4 make it safe for staff to edit flows and reusable in other apps.

**Open questions**

- Teams messaging through Microsoft Graph needs an Entra app registration and data-protection sign-off before phase 1's notification brick.

## Decisions

**Minimum Livewire version: 4** (#1, 27 Nov 2026). Flowkit requires `livewire/livewire ^4.0`. The editor reorders steps with `wire:sort`, so there's no SortableJS. CI tests Livewire 4 only. At the time, the one host app checked (mangofolio) was already on Livewire 4.2, and Livewire 4.0 had been out since January 2026.

**Waits: our own scheduler, no durable-workflow library** (#2, 27 Nov 2026). We compared:

- **durable-workflow/workflow**, the successor to laravel-workflow. It needs Laravel `^12.72|^13.41`, ships 83 migrations and needs real queue workers. It also expects to own execution history through deterministic replay, which would duplicate `flow_runs` and `flow_run_steps` and mean rewriting the runner.
- **Temporal**, with keepsuit/laravel-temporal. It needs a Temporal server and RoadRunner for every host app.

Our engine already persists the step tree and pins each run to a version, so resuming is "load the run and carry on after the wait step". The design:

- Store `resume_at` on `flow_runs`.
- Dispatch a `ResumeRun` job delayed by `min(wait, 15 minutes)` as a fast path. SQS caps job delays at 15 minutes.
- Run a `flowkit:resume-due` command every minute. It claims waiting runs whose `resume_at` has passed, with an atomic update so each run is claimed once. This sweeper is what guarantees long waits on every queue driver.

All of this sits behind a `WaitScheduler` interface, so a library adapter can be added later if needed. It's built in the phase 3 durable-waits issue.

**Licence: open source, MIT** (#3, 27 Nov 2026). Published on Packagist as `mightymango/flowkit`. The README is the public documentation.

**AutoBuilder and Drawflow: we use neither** (#4, 27 Nov 2026). We don't fork or copy code from either.

- **AutoBuilder** (`grazulex/laravel-autobuilder`, MIT) has a Vue editor rather than Livewire, needs PHP 8.4 or later, and stores flows as a node-and-edge graph. It has no versions and no per-step records. Its waits don't resume. It retries actions that aren't safe to repeat, with no idempotency keys, and it resolves any class named in the stored JSON. Its `ExecuteCode` and `CustomClosure` bricks run `eval` on PHP typed in the editor.
- **Drawflow** (`jerosoler/Drawflow`, MIT) is a vanilla-JS canvas for wiring nodes together, not an engine. It doesn't fit the step-list editor in `docs/mockup.html`, and its last release was 0.0.60 in September 2024.

AutoBuilder has some ideas worth rebuilding in our own model, each as its own issue when we get to it:

- Replay a failed run from the step that failed, as a new run on the same version, reusing the earlier steps' outputs.
- Retry attempts and delay that a step can override, defaulting from the brick and then config. A retry is re-dispatched with a delay rather than sleeping in the worker, and only happens when `$retrySafe` is true.
- `visibleWhen()` on fields, for settings that depend on other settings.
- A `samplePayload()` on triggers, for test runs in the editor.
- Validate a flow before it's published: it has a trigger, no step IDs are duplicated, and required settings are filled.
- Cache which flows are live for registering triggers, and skip registering triggers when the tables aren't migrated yet.
- Bricks to consider later: sub-flow, loop over a list, switch, throttle, and a webhook trigger signed with an HMAC.

If we ever copy AutoBuilder code, we must keep its notice ("Copyright (c) 2025 Jean-Marc Strauven") and the MIT licence text with it.

**Languages: English and Welsh** (#61, 28 Nov 2026). Flowkit is multilingual and ships English and Welsh. Its own interface text is in `flowkit::` PHP language files, which apps override or extend by publishing them to `lang/vendor/flowkit`. Brick and field text stays as plain English in the brick classes and is translated when displayed. Core bricks' Welsh is in the package's JSON, and an app's own bricks use the app's JSON translations, which take precedence. This keeps the brick API unchanged, and lets host apps that must be bilingual (such as mangofolio) switch the editor on.

