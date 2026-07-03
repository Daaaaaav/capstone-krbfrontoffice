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
    $wireModel   = $attributes->whereStartsWith('wire:model')->first()
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
     class="relative w-fit min-w-[140px]"
>
    @if($label)
        <p class="text-sm font-medium text-gray-700 mb-2">{{ $label }}</p>
    @endif

    {{-- Trigger button --}}
    <button
        type="button"
        @click="open = !open"
        @click.outside="open = false"
        class="flex items-center justify-between gap-3 w-full h-10 px-3 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-800 shadow-sm hover:bg-gray-50 transition-colors focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10"
    >
        <span x-text="selectedLabel" class="truncate"></span>
        <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform duration-200"
             :class="open ? 'rotate-180' : ''"
             viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
        </svg>
    </button>

    {{-- Dropdown panel --}}
    <ul
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        @click.outside="open = false"
        class="absolute right-0 z-50 mt-1 w-full max-h-52 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg text-sm"
        style="display:none;"
    >
        @foreach($options as $option)
            <li
                @click="$wire.set('{{ $livewireKey }}', '{{ $option['value'] }}'); open = false;"
                class="px-3.5 py-2.5 cursor-pointer transition-colors"
                :class="String($wire.{{ $livewireKey }}) === '{{ $option['value'] }}'
                    ? 'bg-[#4E653D] text-white font-semibold'
                    : 'text-gray-700 hover:bg-gray-100'"
            >
                {{ $option['label'] }}
            </li>
        @endforeach
    </ul>
</div>
