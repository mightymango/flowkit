@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $label = method_exists($status, 'label') ? $status->label() : \Illuminate\Support\Str::headline($value);
    $tone = match ($value) {
        'live', 'completed' => 'text-[var(--flowkit-success)] border-[var(--flowkit-success)]/30',
        'failed' => 'text-[var(--flowkit-danger)] border-[var(--flowkit-danger)]/30',
        'pending_approval', 'waiting', 'running' => 'text-[var(--flowkit-warning)] border-[var(--flowkit-warning)]/30',
        default => 'text-[var(--flowkit-muted)] border-[var(--flowkit-border)]',
    };
@endphp

<span {{ $attributes->class("inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium {$tone}") }}>
    {{ $label }}
</span>
