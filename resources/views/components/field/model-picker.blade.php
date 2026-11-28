{{--
    Search for a record and pick it; stores its key. Results come from the
    step settings component's searchRecords(), which only searches the model
    and columns the brick declared. Keyboard: type to search, Up/Down to
    move, Enter to pick, Escape to close.
--}}
@props(['field'])

@php($id = 'flowkit-field-'.$field['name'])

<x-flowkit::field.wrapper :field="$field" {{ $attributes }}>
    <div
        x-data="{
            name: @js($field['name']),
            label: @js($field['selectedLabel'] ?? ''),
            term: '',
            results: [],
            open: false,
            active: -1,
            async search() {
                this.results = this.term.trim() === '' ? [] : await this.$wire.searchRecords(this.name, this.term)
                this.active = this.results.length ? 0 : -1
                this.open = true
            },
            pick(result) {
                this.$wire.$set('settings.' + this.name, result.value, false)
                this.label = result.label
                this.term = ''
                this.open = false
            },
            clear() {
                this.$wire.$set('settings.' + this.name, null, false)
                this.label = ''
            },
            move(offset) {
                if (! this.results.length) return
                this.active = (this.active + offset + this.results.length) % this.results.length
            },
        }"
        x-on:click.outside="open = false"
        class="relative space-y-2"
    >
        <div x-show="label" class="flex items-center justify-between rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-canvas)] px-3 py-2 text-sm">
            <span x-text="label"></span>
            <button type="button" x-on:click="clear()" class="text-[var(--flowkit-muted)] hover:text-[var(--flowkit-danger)]" aria-label="{{ __('flowkit::editor.clear_choice') }}">✕</button>
        </div>

        <input
            type="text"
            id="{{ $id }}"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="{{ $id }}-results"
            x-bind:aria-expanded="open"
            x-bind:aria-activedescendant="active >= 0 ? '{{ $id }}-result-' + active : null"
            x-model="term"
            x-on:input.debounce.300ms="search()"
            x-on:keydown.down.prevent="move(1)"
            x-on:keydown.up.prevent="move(-1)"
            x-on:keydown.enter.prevent="active >= 0 && pick(results[active])"
            x-on:keydown.escape="open = false"
            placeholder="{{ $field['placeholder'] ?? __('flowkit::editor.search_records') }}"
            autocomplete="off"
            class="w-full rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] px-3 py-2 text-sm text-[var(--flowkit-text)] focus:border-[var(--flowkit-accent)] focus:outline-none"
        />

        <ul
            id="{{ $id }}-results"
            role="listbox"
            x-show="open && term.trim() !== ''"
            x-cloak
            class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-[var(--flowkit-border)] bg-[var(--flowkit-surface)] py-1 text-sm shadow-lg"
        >
            <template x-for="(result, index) in results" x-bind:key="result.value">
                <li
                    role="option"
                    x-bind:id="'{{ $id }}-result-' + index"
                    x-bind:aria-selected="index === active"
                    x-on:click="pick(result)"
                    x-on:mouseenter="active = index"
                    x-bind:class="index === active ? 'bg-[var(--flowkit-accent)] text-[var(--flowkit-accent-contrast)]' : ''"
                    class="cursor-pointer px-3 py-1.5"
                    x-text="result.label"
                ></li>
            </template>
            <li x-show="! results.length" class="px-3 py-1.5 text-[var(--flowkit-muted)]">{{ __('flowkit::editor.no_records') }}</li>
        </ul>
    </div>
</x-flowkit::field.wrapper>
