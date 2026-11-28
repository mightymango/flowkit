@props(['kind' => 'action'])

@php
    $kind = in_array($kind, ['trigger', 'branch', 'condition'], true) ? $kind : 'action';
    $tone = match ($kind) {
        'trigger' => 'text-[var(--flowkit-accent)]',
        'branch', 'condition' => 'text-[var(--flowkit-warning)]',
        default => 'text-[var(--flowkit-success)]',
    };
@endphp

<span {{ $attributes->class("text-xs font-semibold uppercase tracking-wide {$tone}") }}>
    {{ __('flowkit::editor.badge.'.$kind) }}
</span>
