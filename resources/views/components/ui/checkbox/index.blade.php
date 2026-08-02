@aware([
    'variant' => 'default'
])

@props([
    'name' => null,
    'label' => null,
    'description' => null,
    'value' => null,
    'checked' => false,
    'indeterminate' => false,
    'disabled' => false,
    'invalid' => false,
    'size' => 'md',
    'variant' => 'default',
    'indicator' => true,
])

@php
    $modelAttrs = collect($attributes->getAttributes())->keys()->first(fn($key) => str_starts_with($key, 'wire:model'));

    $model = $modelAttrs ? $attributes->get($modelAttrs) : null;

    $isLive = $modelAttrs && str_contains($modelAttrs, '.live');
@endphp
<div 
    data-slot="checkbox-wrapper"
    x-data="
        function(){
            const $entangle = (prop, live) => {
                const binding = $wire.$entangle(prop);
                return live ? binding.live : binding;
            };

            const $initState = (prop, live, checked) => {
                if (!prop) return checked;
                return $entangle(prop, live);
            };

            return {
            _checked: $initState(@js($model), @js($isLive), @js($checked)) ,
            value: @js($value),
            _indeterminate: @js($indeterminate),
            
            toggle() {
                if (this._indeterminate) {
                    this._indeterminate = false;
                }

                this._checked = !this._checked;
                this.syncHiddenInput();
                this.dispatchChangeEvent();
            },
            
            init() {
                this.$nextTick(()=>{
                    if (this.inInGroup()) {
                        this._checked = this._state.includes(this.value);
                    }
                    else {
                        if(!this._checked) {
                            this._checked = this.$root._x_model?.get() ?? false;
                        }
                    }
                });

                this.$watch('_checked', (isChecked) => {
                    if (this.inInGroup()) {
                        this.syncWithGroupState(isChecked);
                    }
                    else{
                        // Sync with Alpine.js model binding
                        this.$root?._x_model?.set(isChecked);                
                    }
                });           
            },

            inInGroup() {
                return ![undefined, null].includes(this._state);
            },

            syncWithGroupState(isChecked) {
                if (isChecked) {
                    if (!this._state.includes(this.value)) {
                        this._state.push(this.value);
                    }

                } else {
                    if (this._state.includes(this.value)) {
                        this._state = this._state.filter((item) => item !== this.value);
                    }
                }
            },
            syncHiddenInput() {
                const hiddenInput = this.$refs.hiddenInput;
                if (hiddenInput) {
                    hiddenInput.checked = this._checked;
                }
            },
            dispatchChangeEvent() {
                this.$refs.hiddenInput?.dispatchEvent(
                    new Event('change', { bubbles: true })
                );
            },
            setIndeterminate(isIndeterminate) {
                this._indeterminate = isIndeterminate;
                if (isIndeterminate) {
                    this._checked = false;
                }
            }
        }
    }"
    {{ $attributes }}
>

    <input
        x-ref="hiddenInput"
        type="checkbox"
        @if($name) name="{{ $name }}" @endif
        @if($value !== null) value="{{ $value }}" @endif
        @if($checked) checked @endif
        @if($disabled) disabled @endif
        hidden
        tabindex="-1"
        {{ $attributes->whereStartsWith(['wire:model', 'x-model']) }}
    />
    
    @switch($variant)
        @case('pills')
            <x-ui.checkbox.variant.pill>
                {{ $slot }}
            </x-ui.checkbox.variant.pill>
            @break
            
        @case('cards')
            <x-ui.checkbox.variant.card>
                {{ $slot }}    
            </x-ui.checkbox.variant.card>
            @break
            
        @default
            <x-ui.checkbox.variant.default>                    
                {{ $slot }}
            </x-ui.checkbox.variant.default>                    
    @endswitch
</div>