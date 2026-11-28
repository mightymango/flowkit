<?php

use Livewire\Livewire;
use MightyMango\Flowkit\Livewire\FlowEditor;
use MightyMango\Flowkit\Models\Flow;

/** An editor with: a, b (branch: yes [c], otherwise []), d. */
function reorderEditor()
{
    $flow = Flow::create(['key' => 'reorder', 'name' => 'Reorder']);
    $flow->draft()->update(['definition' => [
        'trigger' => ['brick' => 'core.manual', 'settings' => []],
        'steps' => [
            ['id' => 'a', 'brick' => 'core.set-variable', 'settings' => ['value' => 'a']],
            ['id' => 'b', 'brick' => 'core.branch', 'settings' => ['when' => 'true'], 'branches' => [
                'yes' => [['id' => 'c', 'brick' => 'core.set-variable', 'settings' => ['value' => 'c']]],
                'otherwise' => [],
            ]],
            ['id' => 'd', 'brick' => 'core.set-variable', 'settings' => ['value' => 'd']],
        ],
    ]]);

    return Livewire::test(FlowEditor::class, ['flow' => $flow]);
}

function shape(array $steps): array
{
    return array_map(fn ($step) => isset($step['branches'])
        ? [$step['id'] => array_map(fn ($children) => shape($children), $step['branches'])]
        : $step['id'], $steps);
}

it('reorders steps within a list when one is dropped', function () {
    $editor = reorderEditor()->call('handleSort', 'd', 0, 'root');

    expect(shape($editor->get('steps')))->toBe(['d', 'a', ['b' => ['yes' => ['c'], 'otherwise' => []]]]);

    $editor->call('handleSort', 'd', 2, 'root');
    expect(shape($editor->get('steps')))->toBe(['a', ['b' => ['yes' => ['c'], 'otherwise' => []]], 'd']);
});

it('moves a step into a branch, between branches and out again', function () {
    $editor = reorderEditor()->call('handleSort', 'a', 0, 'b:otherwise');
    expect(shape($editor->get('steps')))->toBe([['b' => ['yes' => ['c'], 'otherwise' => ['a']]], 'd']);

    $editor->call('handleSort', 'c', 1, 'b:otherwise');
    expect(shape($editor->get('steps')))->toBe([['b' => ['yes' => [], 'otherwise' => ['a', 'c']]], 'd']);

    $editor->call('handleSort', 'c', 0, 'root');
    expect(shape($editor->get('steps')))->toBe(['c', ['b' => ['yes' => [], 'otherwise' => ['a']]], 'd']);
});

it('keeps a step\'s settings and branches when it moves', function () {
    $editor = reorderEditor()->call('handleSort', 'b', 2, 'root');

    expect($editor->get('steps')[2])->toMatchArray(['id' => 'b', 'settings' => ['when' => 'true']])
        ->and(shape($editor->get('steps')))->toBe(['a', 'd', ['b' => ['yes' => ['c'], 'otherwise' => []]]]);
});

it('ignores a move into the step\'s own branch, or to a list that does not exist', function () {
    $editor = reorderEditor();
    $before = $editor->get('steps');

    $editor->call('handleSort', 'b', 0, 'b:yes')
        ->call('handleSort', 'a', 0, 'nope:yes')
        ->call('handleSort', 'a', 0, 'b:maybe')
        ->call('handleSort', 'zzz', 0, 'root');

    expect($editor->get('steps'))->toBe($before)->and($editor->get('history'))->toBe([]);
});

it('moves a step with the keyboard alternative, to the end of a list', function () {
    $editor = reorderEditor()
        ->call('select', 'a')
        ->assertSeeHtml('id="flowkit-move-to"')
        ->assertSeeHtml('<option value="b:yes">If / otherwise (b) → Yes</option>')
        ->call('moveTo', 'a', 'b:yes');

    expect(shape($editor->get('steps')))->toBe([['b' => ['yes' => ['c', 'a'], 'otherwise' => []]], 'd']);
});

it('does not offer a branch step its own branches as destinations', function () {
    reorderEditor()
        ->call('select', 'b')
        ->assertSeeHtml('<option value="root">Main flow</option>')
        ->assertDontSeeHtml('<option value="b:yes">');
});

it('can undo a move', function () {
    $editor = reorderEditor();
    $before = $editor->get('steps');

    $editor->call('handleSort', 'd', 0, 'root')->call('undo');

    expect($editor->get('steps'))->toBe($before);
});

it('renders sortable lists with drag handles', function () {
    reorderEditor()
        ->assertSeeHtml('wire:sort="handleSort"')
        ->assertSeeHtml('wire:sort:group="flowkit-steps"')
        ->assertSeeHtml('wire:sort:group-id="root"')
        ->assertSeeHtml('wire:sort:group-id="b:yes"')
        ->assertSeeHtml('wire:sort:item="c"')
        ->assertSeeHtml('wire:sort:handle');
});
