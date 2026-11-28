@props(['kind' => 'action'])

<span {{ $attributes->class('text-xs font-semibold uppercase tracking-wide text-[var(--flowkit-accent)]') }}>
    {{ __('flowkit::editor.badge.'.(in_array($kind, ['trigger', 'branch', 'condition'], true) ? $kind : 'action')) }}
</span>
