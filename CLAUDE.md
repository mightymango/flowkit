# Flowkit

A Zapier-style workflow builder package for Laravel Livewire apps: `mightymango/flowkit`, namespace `MightyMango\Flowkit`.

- `docs/plan.md` is the design and the source of truth for intent. Read it before starting an issue.
- `docs/mockup.html` is the target look for the editor (step list on the left, settings panel on the right).
- Work is tracked as GitHub issues, grouped into four milestones (Phase 1–4). Each phase ends with a `gate` issue; don't start a phase's work until the previous gate has passed unless asked. Issues labelled `decision` need Simon's answer — propose options, don't decide them yourself. Issues labelled `blocked` say what they're waiting on.

## Commands

```bash
composer install
composer test          # Pest, via Orchestra Testbench (sqlite in memory)
vendor/bin/pest --filter=RunnerTest
composer lint          # Pint (Laravel preset)
composer analyse       # Larastan, level 5 (phpstan.neon.dist)
```

CI (`.github/workflows/tests.yml`) runs PHP 8.2–8.5 against Laravel 12 and 13 with Livewire 4 (Laravel 13 needs PHP 8.3+); `.github/workflows/quality.yml` runs Pint and Larastan. Keep both green. If Packagist isn't reachable from your environment, push a branch and read the Actions results instead.

## Architecture

- `src/Engine` — `Definition` (trigger + step tree), `Runner` (walks steps, records `flow_run_steps`), `Context` (run data; step outputs under `steps.<id>`), `Expression` (Symfony ExpressionLanguage, no functions registered), `Result`, `FlowBuilder` (code-first flows).
- `src/Bricks` — `Brick` base; `Trigger`, `Condition`, `Action`; `Core/` built-ins; `BrickRegistry` (core + config list + discovery in `app/Flows`).
- `src/Fields` — settings fields; each maps to a `flowkit::field.*` Blade component and supplies validation rules.
- `src/Models` — `Flow`, `FlowVersion` (immutable once published), `FlowRun` (points at a version, never the flow; prunable), `FlowRunStep`.
- `src/Livewire` — `FlowList`, `FlowEditor`. Logic here; markup in Blade components.
- `resources/views/components` — anonymous `flowkit::` components (publishable).
- `resources/views/app` — pages, layouts and Livewire views, registered as the `flowkit::` view namespace.

## Rules and gotchas

- **Never put a `components` folder in the `flowkit` view namespace.** Laravel resolves `flowkit::components.<name>` before anonymous component paths, so it would shadow components an app has published to `resources/views/flowkit`. That's why non-component views live in `resources/views/app`. `PublishCommandTest` covers this.
- **Published versions are immutable.** Edit a draft (`$flow->draft()`), then `publish()`. Runs always use the version they started on.
- **Expressions are sandboxed.** Never evaluate user input with `eval`, Blade or `include`. Don't register ExpressionLanguage functions unless an issue asks for a specific, safe one.
- **Validate settings server-side** from the brick's fields, in both the runner and the editor. Never trust the Livewire payload.
- **Unsafe actions** (`$retrySafe = false`) must use `$context->idempotencyKey($stepId)` and must not run for real in dry-run mode.
- **No Vue, React or other JS framework.** The UI is Livewire and Blade, plus the Alpine bundled with Livewire. Don't add a `package.json` or a JS build step.
- **Blade components:** take data as props and merge `$attributes` onto the root element. Style with Tailwind classes that reference the `--flowkit-*` tokens in `resources/css/flowkit.css`. Treat a prop change as a breaking change once phase 4 starts.
- **Laravel 12 and 13.** Support the current major and the previous one; add Laravel 14 when it's released (it needs PHP 8.4) and drop 12 when its security support ends. Laravel 11 is out: Composer's security-advisory policy blocks every 11.x release.
- Run data can contain personal data (e.g. employee or customer records). Don't log payloads, and keep retention in mind.

## Conventions

- PHP 8.2+, typed properties and return types, constructor promotion.
- Every new brick, field, command or Livewire action gets a Pest test. Engine tests use code-first flows (see `tests/Feature/RunnerTest.php`) and the `SendMessage` fixture for unsafe actions.
- Keep the README's feature list and roadmap table in step with what's built.
- One issue per branch/PR; reference the issue number in the PR (`Closes #N`).
- UK English in user-facing text and docs.
