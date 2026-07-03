{{--
    Custom Select Component — uses x-teleport to escape overflow:hidden parents
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
    $uid         = 'cs_' . uniqid();
@endphp

<style>
.cs-wrap { position: relative; display: inline-block; min-width: 130px; }
.cs-trigger {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    width: 100%; height: 40px; padding: 0 12px;
    border-radius: 8px; border: 1px solid #d1d5db;
    background: #ffffff; font-size: 0.875rem; font-weight: 500;
    color: #111827 !important; -webkit-text-fill-color: #111827 !important;
    cursor: pointer; white-space: nowrap;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05); outline: none;
}
.cs-trigger:hover { background: #f9fafb; }
.cs-chevron { flex-shrink:0; width:16px; height:16px; color:#6b7280; transition:transform 0.2s; }
.cs-chevron.cs-open { transform: rotate(180deg); }
.cs-portal {
    position: fixed; z-index: 99999;
    border-radius: 8px; border: 1px solid #e5e7eb;
    background: #ffffff; box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    overflow-y: auto; overflow-x: hidden;
    list-style: none; padding: 2px 0; margin: 0;
    max-height: 260px;
}
.cs-opt {
    padding: 10px 14px; font-size: 0.875rem; cursor: pointer;
    color: #111827 !important; -webkit-text-fill-color: #111827 !important;
}
.cs-opt:hover { background: #f3f4f6; }
.cs-opt.cs-sel {
    background: #4E653D !important;
    color: #ffffff !important; -webkit-text-fill-color: #ffffff !important;
    font-weight: 600;
}
</style>

<div {{ $attributes->only('class') }}
     id="{{ $uid }}"
     x-data="{
         open: false,
         triggerRect: {},
         get selectedLabel() {
             const val = $wire.{{ $livewireKey }};
             const opt = {{ json_encode($options) }}.find(o => String(o.value) === String(val));
             return opt ? opt.label : '{{ addslashes($placeholder ?? ($options[0]['label'] ?? '')) }}';
         },
         toggle() {
             const el = document.getElementById('{{ $uid }}');
             const btn = el.querySelector('.cs-trigger');
             const r = btn.getBoundingClientRect();
             this.triggerRect = { top: r.bottom + window.scrollY + 4, left: r.left + window.scrollX, width: r.width };
             this.open = !this.open;
         },
         close() { this.open = false; }
     }"
     @keydown.escape.window="close()"
     @click.outside="close()"
     class="cs-wrap"
>
    @if($label)
        <span style="display:block;font-size:0.875rem;font-weight:500;color:#374151;margin-bottom:8px;">{{ $label }}</span>
    @endif

    <button type="button" class="cs-trigger" @click.stop="toggle()">
        <span x-text="selectedLabel"></span>
        <svg class="cs-chevron" :class="open ? 'cs-open' : ''" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
        </svg>
    </button>

    <template x-teleport="body">
        <ul class="cs-portal"
            x-show="open"
            x-cloak
            :style="`top:${triggerRect.top}px; left:${triggerRect.left}px; width:${triggerRect.width}px;`"
            @click.outside="close()"
            style="display:none;">
            @foreach($options as $option)
                <li class="cs-opt"
                    :class="String($wire.{{ $livewireKey }}) === '{{ $option['value'] }}' ? 'cs-sel' : ''"
                    @click.stop="$wire.set('{{ $livewireKey }}', '{{ $option['value'] }}'); close();">
                    {{ $option['label'] }}
                </li>
            @endforeach
        </ul>
    </template>
</div>
