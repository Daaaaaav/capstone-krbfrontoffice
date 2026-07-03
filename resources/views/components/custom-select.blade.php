{{--
    Custom Select Component
    Props:
      - wire:model / model: Livewire property name to bind (passed as attribute)
      - options: array of ['value' => ..., 'label' => ...] (required)
      - label: optional label above the dropdown
      - placeholder: optional default display text when nothing matched
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
     style="position:relative; width:fit-content; min-width:140px;"
>
    @if($label)
        <p style="font-size:0.875rem; font-weight:500; color:#374151; margin-bottom:0.5rem;">{{ $label }}</p>
    @endif

    {{-- Trigger button --}}
    <button
        type="button"
        @click="open = !open"
        @click.outside="open = false"
        style="display:flex; align-items:center; justify-content:space-between; gap:0.75rem; width:100%; height:2.5rem; padding:0 0.75rem; border-radius:0.5rem; border:1px solid #d1d5db; background:#ffffff; font-size:0.875rem; font-weight:500; color:#1f2937; box-shadow:0 1px 2px rgba(0,0,0,0.05); cursor:pointer; outline:none; white-space:nowrap;"
        onmouseover="this.style.background='#f9fafb'"
        onmouseout="this.style.background='#ffffff'"
    >
        <span x-text="selectedLabel" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"></span>
        <svg style="width:1rem; height:1rem; color:#9ca3af; flex-shrink:0; transition:transform 0.2s;"
             :style="open ? 'transform:rotate(180deg)' : ''"
             viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
        </svg>
    </button>

    {{-- Dropdown panel --}}
    <ul
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0; transform:translateY(-4px)"
        x-transition:enter-end="opacity-100; transform:translateY(0)"
        @click.outside="open = false"
        style="display:none; position:absolute; right:0; z-index:50; margin-top:0.25rem; width:100%; max-height:13rem; overflow-y:auto; border-radius:0.5rem; border:1px solid #e5e7eb; background:#ffffff; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1); list-style:none; padding:0; margin-left:0;"
    >
        @foreach($options as $option)
            <li
                @click="$wire.set('{{ $livewireKey }}', '{{ $option['value'] }}'); open = false;"
                style="padding:0.625rem 0.875rem; cursor:pointer; font-size:0.875rem; transition:background 0.15s;"
                :style="String($wire.{{ $livewireKey }}) === '{{ $option['value'] }}'
                    ? 'background:#4E653D; color:#ffffff; font-weight:600;'
                    : 'color:#374151;'"
                onmouseover="if(String($wire?.{{ $livewireKey }}) !== '{{ $option['value'] }}') this.style.background='#f3f4f6'"
                onmouseout="if(String($wire?.{{ $livewireKey }}) !== '{{ $option['value'] }}') this.style.background=''"
            >
                {{ $option['label'] }}
            </li>
        @endforeach
    </ul>
</div>
