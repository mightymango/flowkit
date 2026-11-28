<?php

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use MightyMango\Flowkit\Bricks\Trigger;
use MightyMango\Flowkit\Engine\Definition;
use MightyMango\Flowkit\Enums\FlowStatus;
use MightyMango\Flowkit\Enums\RunStatus;
use MightyMango\Flowkit\Exceptions\InvalidDefinition;
use MightyMango\Flowkit\Fields\Select;
use MightyMango\Flowkit\FlowkitServiceProvider;
use MightyMango\Flowkit\Livewire\FlowEditor;
use MightyMango\Flowkit\Livewire\FlowList;
use MightyMango\Flowkit\Models\Flow;

/**
 * The English text of every core brick that a person reads: labels, groups,
 * descriptions, field labels, help, option labels and trigger variables.
 *
 * @return list<string>
 */
function coreBrickTexts(): array
{
    $texts = [];

    foreach (FlowkitServiceProvider::CORE_BRICKS as $class) {
        $brick = app($class);
        $texts = [...$texts, $class::$label, $class::$group, ...array_filter([$class::$description])];

        if ($brick instanceof Trigger) {
            $texts = [...$texts, ...array_values($brick->variables())];
        }

        foreach ($brick->fields() as $field) {
            $texts = [...$texts, ...array_filter([(fn () => $this->label)->call($field), (fn () => $this->help)->call($field)])];

            // Option lists built at runtime (other bricks, time zones) aren't the brick's own text.
            if ($field instanceof Select && ! (fn () => $this->options)->call($field) instanceof Closure) {
                $texts = [...$texts, ...array_values($field->getOptions())];
            }
        }
    }

    return array_values(array_unique($texts));
}

it('has the same keys in English and Welsh', function (string $file) {
    $english = Arr::dot(require FlowkitServiceProvider::LANG_PATH."/en/{$file}.php");
    $welsh = Arr::dot(require FlowkitServiceProvider::LANG_PATH."/cy/{$file}.php");

    expect(array_keys($welsh))->toEqualCanonicalizing(array_keys($english));
})->with(['editor', 'errors', 'status']);

it('translates every core brick into Welsh', function () {
    $welsh = json_decode(file_get_contents(FlowkitServiceProvider::LANG_PATH.'/cy.json'), true);

    expect(array_values(array_diff(coreBrickTexts(), array_keys($welsh))))->toBe([]);
});

it('renders the editor in Welsh', function () {
    app()->setLocale('cy');
    $flow = Flow::create(['key' => 'onboarding', 'name' => 'Croeso']);

    $editor = Livewire::test(FlowEditor::class, ['flow' => $flow])
        ->assertSee('Dewiswch sbardun')
        ->assertSee('Rhedeg â llaw')
        ->call('setTrigger', 'core.manual')
        ->assertSee(['Pan', 'Cadw drafft', 'Cyhoeddi', 'Ychwanegu cam'])
        ->call('addStep', 'core.branch')
        ->assertSee(['Os / fel arall', 'Ie', 'Fel arall', 'Gwiriad', 'Mynegiad', 'Cymhwyso']);

    $editor->call('applySettings')->assertHasErrors('settings.when');

    expect($editor->errors()->first('settings.when'))->toContain('Mynegiad');
});

it('renders the flow list in Welsh', function () {
    app()->setLocale('cy');

    Livewire::test(FlowList::class)
        ->assertSee(['Dim llifoedd eto', 'Creu llif', "Enw'r llif"]);
});

it('translates statuses and publishing errors', function () {
    app()->setLocale('cy');

    expect(FlowStatus::Live->label())->toBe('Byw')
        ->and(RunStatus::Failed->label())->toBe('Wedi methu');

    try {
        Definition::fromArray(['trigger' => ['brick' => 'nope'], 'steps' => []])->validate();
    } catch (InvalidDefinition $e) {
        expect($e->errors)->toBe(['Bric sbardun anhysbys [nope].']);
    }
});

it('lets an app override Flowkit\'s text with its own translations', function () {
    $dir = sys_get_temp_dir().'/flowkit-lang-'.bin2hex(random_bytes(4));
    mkdir("{$dir}/vendor/flowkit/cy", recursive: true);
    file_put_contents("{$dir}/cy.json", json_encode(['Run manually' => 'Rhedeg nawr']));
    file_put_contents("{$dir}/vendor/flowkit/cy/editor.php", "<?php return ['publish' => 'Cyhoeddi nawr'];");

    app()->useLangPath($dir);
    app()->forgetInstance('translation.loader');
    app()->forgetInstance('translator');
    app()->setLocale('cy');

    expect(__('Run manually'))->toBe('Rhedeg nawr')
        ->and(__('flowkit::editor.publish'))->toBe('Cyhoeddi nawr')
        ->and(__('flowkit::editor.save_draft'))->toBe('Cadw drafft');

    array_map('unlink', [...glob("{$dir}/vendor/flowkit/cy/*"), "{$dir}/cy.json"]);
});

it('publishes the language files', function () {
    expect(ServiceProvider::pathsToPublish(FlowkitServiceProvider::class, 'flowkit-lang'))
        ->toBe([FlowkitServiceProvider::LANG_PATH => app()->langPath('vendor/flowkit')]);
});
