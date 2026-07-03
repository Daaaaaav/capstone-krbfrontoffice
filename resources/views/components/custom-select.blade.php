{{--
    Custom Select Component
    Props:
      - wire:model / model: Livewire property name to bind
      - options: array of ['value' => ..., 'label' => ...]
      - label: optional label above the dropdown
      - placeholder: optional default display text
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

<style>
.cs-wrap { position: relative; display: inline-block; min-width: 130px; }
.cs-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
    height: 40px;
    padding: 0 12px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    font-size: 0.875rem;
    font-weight: 500;
    color: #111827 !important;
    -webkit-text-fill-color: #111827 !important;
    cursor: pointer;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    outline: none;
}
.cs-trigger:hover { background: #f9fafb; }
.cs-chevron { flex-shrink: 0; width: 16px; height: 16px; color: #6b7280; transition: transform 0.2s; }
.cs-chevron.open { transform: rotate(180deg); }
/* Opens UPWARD so it's never clipped by a card boundary below */
.cs-dropdown {
    position: absolute;
    right: 0;
    left: 0;
    bottom: calc(100% + 4px);
    z-index: 9999;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    box-shadow: 0 -4px 16px rgba(0,0,0,0.12);
    overflow-y: auto;
    overflow-x: hidden;
    list-style: none;
    padding: 2px 0;
    margin: 0;
    max-height: 260px;
    min-width: 130px;
}
.cs-option {
    padding: 10px 14px;
    font-size: 0.875rem;
    color: #111827 !important;
    -webkit-text-fill-color: #111827 !important;
    cursor: pointer;
    list-style: none;
}
.cs-option:hover { background: #f3f4f6; }
.cs-option.cs-selected {
    background: #4E653D !important;
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
    font-weight: 600;
}
.cs-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: 8px;
}
</style>

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
     @click.outside="open = false"
     class="cs-wrap"
>
    @if($label)
        <span class="cs-label">{{ $label }}</span>
    @endif

    <button type="button" class="cs-trigger" @click.stop="open = !open">
        <span x-text="selectedLabel"></span>
        <svg class="cs-chevron" :class="open ? 'open' : ''" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
        </svg>
    </button>

    <ul class="cs-dropdown" x-show="open" style="display:none">
        @foreach($options as $option)
            <li class="cs-option"
                :class="String($wire.{{ $livewireKey }}) === '{{ $option['value'] }}' ? 'cs-selected' : ''"
                @click.stop="$wire.set('{{ $livewireKey }}', '{{ $option['value'] }}'); open = false;">
                {{ $option['label'] }}
            </li>
        @endforeach
    </ul>
</div>
