@props(['kind' => 'action'])

<span {{ $attributes->class('text-xs font-semibold uppercase tracking-wide text-[var(--flowkit-accent)]') }}>
    {{ match ($kind) { 'trigger' => 'When', 'branch' => 'If', 'condition' => 'Check', default => 'Then' } }}
</span>
