@props([
    'variant' => 'default',
    'model' => $attributes->whereStartsWith(['wire:model', 'x-model'])
])

@php
$classes = match($variant) {
        'pills' => 'flex gap-2 flex-wrap',
        'cards' => 'flex flex-col gap-2',
        default => [
            '[&>[data-slot=checkbox-wrapper]:not(:first-child)]:mt-3',
            '[&>[data-slot=checkbox-wrapper]:has([data-slot=checkbox-description])+[data-slot=checkbox-wrapper]]:mt-4'
        ]
    };
    $modelAttrs = collect($attributes->getAttributes())->keys()->first(fn($key) => str_starts_with($key, 'wire:model'));
    $model = $modelAttrs ? $attributes->get($modelAttrs) : null;
    $isLive = $modelAttrs && str_contains($modelAttrs, '.live');

@endphp

<div
    x-data="
    function(){
        const $entangle = (prop, live) => {
            const binding = $wire.$entangle(prop);
            return live ? binding.live : binding;
        };

        const $initState = (prop, live) => {
            if (!prop) return undefined;
            return $entangle(prop, live);
        };

        return {
            _state: $initState(@js($model), @js($isLive)),

            init() {
                this.$nextTick(() => {

                    if (this._state == undefined) {
                        this._state = this.$root?._x_model?.get() ?? undefined;
                    }

                });
                
                this.$watch('_state', (values) => {
                    
                    if (values === undefined) return;

                    this.$root?._x_model?.set(values);
                });
            },
        }
    }"
    {{ $attributes->class($classes) }}
    data-slot="checkbox-group"
    {{ $slot }}
</div>