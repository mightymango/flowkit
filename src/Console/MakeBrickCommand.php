<?php

namespace Act\Flowkit\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

/**
 * php artisan flowkit:make-brick SendTeamsMessage --action
 */
class MakeBrickCommand extends GeneratorCommand
{
    protected $name = 'flowkit:make-brick';

    protected $description = 'Create a new Flowkit trigger, condition or action';

    protected $type = 'Brick';

    protected function kind(): string
    {
        return match (true) {
            $this->option('trigger') => 'trigger',
            $this->option('condition') => 'condition',
            default => 'action',
        };
    }

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/'.$this->kind().'.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Flows\\'.Str::plural(Str::studly($this->kind()));
    }

    protected function buildClass($name): string
    {
        $base = Str::of(class_basename($name));

        return str_replace(
            ['{{ key }}', '{{ label }}'],
            [$base->kebab()->toString(), $base->headline()->lower()->ucfirst()->toString()],
            parent::buildClass($name),
        );
    }

    protected function getOptions(): array
    {
        return [
            ['action', null, InputOption::VALUE_NONE, 'Create an action (default)'],
            ['condition', null, InputOption::VALUE_NONE, 'Create a condition'],
            ['trigger', null, InputOption::VALUE_NONE, 'Create a trigger'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the brick if it exists'],
        ];
    }
}
