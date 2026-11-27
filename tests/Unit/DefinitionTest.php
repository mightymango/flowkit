<?php

use MightyMango\Flowkit\Engine\Definition;
use MightyMango\Flowkit\Exceptions\InvalidDefinition;

it('flattens the step tree', function () {
    $definition = Definition::fromArray([
        'steps' => [
            ['id' => 's1', 'brick' => 'core.branch', 'settings' => ['when' => 'true'], 'branches' => [
                'yes' => [['id' => 's2', 'brick' => 'core.set-variable']],
                'otherwise' => [],
            ]],
            ['id' => 's3', 'brick' => 'core.wait'],
        ],
    ]);

    expect(array_keys($definition->allSteps()))->toBe(['s1', 's2', 's3'])
        ->and($definition->step('s2')['brick'])->toBe('core.set-variable');
});

it('rejects duplicate ids and unknown bricks', function () {
    Definition::fromArray(['steps' => [
        ['id' => 's1', 'brick' => 'core.wait'],
        ['id' => 's1', 'brick' => 'nope'],
    ]])->validate();
})->throws(InvalidDefinition::class, 'Duplicate step id [s1]');
