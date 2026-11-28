{{--
    Stores an ISO 8601 duration. Shows a number and a unit (minutes, hours,
    days or weeks); a stored value that doesn't fit one unit, such as P1DT2H,
    opens as "Other" with the raw ISO 8601 text.
--}}
@props(['field'])

@php($id = 'flowkit-field-'.$field['name'])

<x-flowkit::field.wrapper :field="$field" {{ $attributes }}>
    <div
        x-data="{
            path: @js('settings.'.$field['name']),
            amount: '',
            unit: 'days',
            other: '',
            formats: { minutes: ['PT', 'M'], hours: ['PT', 'H'], days: ['P', 'D'], weeks: ['P', 'W'] },
            init() {
                let value = this.$wire.$get(this.path) || ''
                if (value === '') return
                for (let [unit, [prefix, suffix]] of Object.entries(this.formats)) {
                    let match = value.match(new RegExp('^' + prefix + '(\\d+)' + suffix + '$'))
                    if (match) { this.unit = unit; this.amount = match[1]; return }
                }
                this.unit = 'other'
                this.other = value
            },
            sync() {
                let value = ''
                if (this.unit === 'other') {
                    value = this.other.trim()
                } else if (String(this.amount).trim() !== '') {
                    let [prefix, suffix] = this.formats[this.unit]
                    value = prefix + String(this.amount).trim() + suffix
                }
                this.$wire.$set(this.path, value, false)
            },
        }"
        class="flex gap-2"
    >
        <input
            type="text"
            inputmode="numeric"
            id="{{ $id }}"
            x-show="unit !== 'other'"
            x-model="amount"
            x-on:input="sync()"
            aria-describedby="{{ $id }}-unit"
            class="w-24 rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 text-sm text-[var(--flowkit-text)] focus:border-[var(--flowkit-accent)] focus:outline-none"
        />

        <select
            id="{{ $id }}-unit"
            x-model="unit"
            x-on:change="sync()"
            aria-label="{{ __('flowkit::editor.duration_unit') }}"
            class="rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 text-sm text-[var(--flowkit-text)] focus:border-[var(--flowkit-accent)] focus:outline-none"
        >
            @foreach (['minutes', 'hours', 'days', 'weeks', 'other'] as $unit)
                <option value="{{ $unit }}">{{ __("flowkit::editor.duration_units.{$unit}") }}</option>
            @endforeach
        </select>

        <input
            type="text"
            x-show="unit === 'other'"
            x-cloak
            x-model="other"
            x-on:input="sync()"
            aria-label="{{ __('flowkit::editor.duration_iso') }}"
            placeholder="{{ $field['placeholder'] ?? __('flowkit::editor.duration_placeholder') }}"
            class="flex-1 rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 font-mono text-sm text-[var(--flowkit-text)] focus:border-[var(--flowkit-accent)] focus:outline-none"
        />
    </div>
</x-flowkit::field.wrapper>
