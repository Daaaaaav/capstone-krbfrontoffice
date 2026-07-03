{{--
    Custom Select Component
    Props:
      - wire:model / model: Livewire property name to bind (passed as attribute)
      - options: array of ['value' => ..., 'label' => ...] (required)
      - label: optional label above the dropdown
      - placeholder: optional default display text when nothing matched

    Usage:
      <x-custom-select
          wire:model.live="timeRange"
          :options="[
              ['value' => '7days',  'label' => __('app.7_days')],
              ['value' => '30days', 'label' => __('app.30_days')],
              ['value' => '90days', 'label' => __('app.90_days')],
          ]"
      />
--}}

@props([
    'options'     => [],
    'label'       => null,
    'placeholder' => null,
])

@php
    // Resolve the bound value from wire:model / model attributes
    $modelAttr = $attributes->whereStartsWith('wire:model')->first();
    // We pass the current value via a dedicated :value prop if needed,
    // but Alpine reads it directly from Livewire's $wire entangle.
    $wireModel  = $attributes->whereStartsWith('wire:model')->first()
                  ?? $attributes->get('model', '');
    $livewireKey = preg_replace('/^wire:model(?:\.[a-z]+)*=?/', '', $wireModel);
    $livewireKey = trim($livewireKey, '"\'');
@endphp

<div {{ $attributes->only('class') }}
     x-data="{
        open: false,
        get selectedLabel() {
            const val = $wire.{{ $livewireKey }};
            const opt = {{ json_encode($options) }}.find(o => String(o.value) === String(val));
            return opt ? opt.label : '{{ addslashes($placeholder ?? ($options[0]['label'] ?? '')) }}';
        }
     }"
     @keydown.escape.window="open = false"
     class="relative w-fit min-w-[180px]"
>
    @if($label)
        <p class="text-xs font-medium text-muted-foreground mb-1.5">{{ $label }}</p>
    @endif

    {{-- Trigger button --}}
    <button
        type="button"
        @click="open = !open"
        @click.outside="open = false"
        class="flex items-center justify-between gap-3 w-full px-4 py-2.5 rounded-full border border-border bg-card text-sm font-medium text-card-foreground shadow-sm hover:bg-accent transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
        <span x-text="selectedLabel" class="truncate"></span>
        <svg class="w-4 h-4 text-muted-foreground shrink-0 transition-transform duration-200"
             :class="open ? 'rotate-180' : ''"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="6 9 12 15 18 9"/>
        </svg>
    </button>

    {{-- Dropdown panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.outside="open = false"
        class="absolute right-0 z-50 mt-1.5 w-full min-w-[180px] rounded-xl border border-border bg-card shadow-lg overflow-hidden"
        style="display:none;"
    >
        @foreach($options as $option)
            <button
                type="button"
                @click="$wire.set('{{ $livewireKey }}', '{{ $option['value'] }}'); open = false;"
                class="w-full text-left px-4 py-2.5 text-sm transition-colors"
                :class="String($wire.{{ $livewireKey }}) === '{{ $option['value'] }}'
                    ? 'bg-primary text-primary-foreground font-semibold'
                    : 'text-card-foreground hover:bg-accent'"
            >
                {{ $option['label'] }}
            </button>
        @endforeach
    </div>
</div>
