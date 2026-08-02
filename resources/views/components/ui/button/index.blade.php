@aware(['icon', 'iconClasses', 'iconVariant', 'iconAfter'])

@props([
    'type' => 'button',
    'size' => 'md',
    'color' => null,
    'disabled'=> false,
    'loading' => false,
    'variant' => 'primary',
    'icon' => null,
    'iconAfter' => null,
    'iconVariant' => null,
    'as' => 'button',
    'iconClasses' => null,
    'squared' => false
])

@php
$squared = $slot->isEmpty();

$sizeClasses = match($size) { 
    'lg' => '[:where(&)]:h-12 text-md' . ' '. ( $squared ? 'w-12': ($icon ? 'ps-4' : 'ps-5') . ' ' . ($iconAfter ? 'pe-4' : 'pe-5')),
    'md' => '[:where(&)]:h-10 text-base' . ' '. ( $squared ? 'w-10': ($icon ? 'ps-3' : 'ps-4') . ' ' . ($iconAfter ? 'pe-3' : 'pe-4')), // default
    'sm' => '[:where(&)]:h-9 text-sm' . ' '. ( $squared ? 'w-9': ($icon ? 'ps-2' : 'ps-3') . ' ' . ($iconAfter ? 'pe-2' : 'pe-3')),
    'xs' => '[:where(&)]:h-6 text-xs' . ' '. ( $squared ? 'w-6': ($icon ? 'ps-1' : 'ps-2') . ' ' . ($iconAfter ? 'pe-1' : 'pe-2')),
    default => '[:where(&)]:h-10 text-sm' . ' '. ( $squared ? 'w-10': ($icon ? 'ps-3' : 'ps-4') . ' ' . ($iconAfter ? 'pe-3' : 'pe-4')),
};

$iconVariant ??= match($size) {
    'xs' => 'micro',
    'sm' => 'mini',
    'md' => $squared ? 'mini' : 'micro',
    'lg' => $squared ? 'mini' : 'micro',
    default => 'micro',
};

$iconClasses = [
    $iconClasses,
    $size !== 'xs' ? 'size-5' : 'size-4',
    '!text-[var(--color-primary-fg)]' => $variant === 'primary',
    '!text-[var(--color-primary)]' => $variant === 'outline'
];
 
$iconAttributes = (new \Illuminate\View\ComponentAttributeBag())->class($iconClasses);

$colors = match($color) {
    'slate' => '[--color-primary:var(--color-slate-800)] [--color-primary-content:var(--color-slate-800)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-white)] dark:[--color-primary-content:var(--color-white)] dark:[--color-primary-fg:var(--color-slate-800)]',
    'neutral' => '[--color-primary:var(--color-neutral-800)] [--color-primary-content:var(--color-neutral-800)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-white)] dark:[--color-primary-content:var(--color-white)] dark:[--color-primary-fg:var(--color-neutral-800)]',
    'zinc' => '[--color-primary:var(--color-zinc-800)] [--color-primary-content:var(--color-zinc-800)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-white)] dark:[--color-primary-content:var(--color-white)] dark:[--color-primary-fg:var(--color-zinc-800)]',
    'stone' => '[--color-primary:var(--color-stone-800)] [--color-primary-content:var(--color-stone-800)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-white)] dark:[--color-primary-content:var(--color-white)] dark:[--color-primary-fg:var(--color-stone-800)]',
    'red' => '[--color-primary:var(--color-red-500)] [--color-primary-content:var(--color-red-600)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-red-500)] dark:[--color-primary-content:var(--color-red-400)] dark:[--color-primary-fg:var(--color-neutral-50)]',
    'orange' => '[--color-primary:var(--color-orange-500)] [--color-primary-content:var(--color-orange-600)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-orange-400)] dark:[--color-primary-content:var(--color-orange-400)] dark:[--color-primary-fg:var(--color-orange-950)]',
    'amber' => '[--color-primary:var(--color-amber-400)] [--color-primary-content:var(--color-amber-600)] [--color-primary-fg:var(--color-amber-950)] dark:[--color-primary:var(--color-amber-400)] dark:[--color-primary-content:var(--color-amber-400)] dark:[--color-primary-fg:var(--color-amber-950)]',
    'yellow' => '[--color-primary:var(--color-yellow-400)] [--color-primary-content:var(--color-yellow-600)] [--color-primary-fg:var(--color-yellow-950)] dark:[--color-primary:var(--color-yellow-400)] dark:[--color-primary-content:var(--color-yellow-400)] dark:[--color-primary-fg:var(--color-yellow-950)]',
    'lime' => '[--color-primary:var(--color-lime-400)] [--color-primary-content:var(--color-lime-600)] [--color-primary-fg:var(--color-lime-900)] dark:[--color-primary:var(--color-lime-400)] dark:[--color-primary-content:var(--color-lime-400)] dark:[--color-primary-fg:var(--color-lime-950)]',
    'green' => '[--color-primary:var(--color-green-600)] [--color-primary-content:var(--color-green-600)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-green-600)] dark:[--color-primary-content:var(--color-green-400)] dark:[--color-primary-fg:var(--color-neutral-50)]',
    'emerald' => '[--color-primary:var(--color-emerald-600)] [--color-primary-content:var(--color-emerald-600)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-emerald-600)] dark:[--color-primary-content:var(--color-emerald-400)] dark:[--color-primary-fg:var(--color-neutral-50)]',
    'teal' => '[--color-primary:var(--color-teal-600)] [--color-primary-content:var(--color-teal-600)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-teal-600)] dark:[--color-primary-content:var(--color-teal-400)] dark:[--color-primary-fg:var(--color-neutral-50)]',
    'cyan' => '[--color-primary:var(--color-cyan-600)] [--color-primary-content:var(--color-cyan-600)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-cyan-600)] dark:[--color-primary-content:var(--color-cyan-400)] dark:[--color-primary-fg:var(--color-neutral-50)]',
    'sky' => '[--color-primary:var(--color-sky-600)] [--color-primary-content:var(--color-sky-600)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-sky-600)] dark:[--color-primary-content:var(--color-sky-400)] dark:[--color-primary-fg:var(--color-neutral-50)]',
    'blue' => '[--color-primary:var(--color-blue-500)] [--color-primary-content:var(--color-blue-600)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-blue-500)] dark:[--color-primary-content:var(--color-blue-400)] dark:[--color-primary-fg:var(--color-neutral-50)]',
    'indigo' => '[--color-primary:var(--color-indigo-500)] [--color-primary-content:var(--color-indigo-600)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-indigo-500)] dark:[--color-primary-content:var(--color-indigo-300)] dark:[--color-primary-fg:var(--color-neutral-50)]',
    'violet' => '[--color-primary:var(--color-violet-500)] [--color-primary-content:var(--color-violet-600)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-violet-500)] dark:[--color-primary-content:var(--color-violet-400)] dark:[--color-primary-fg:var(--color-neutral-50)]',
    'purple' => '[--color-primary:var(--color-purple-500)] [--color-primary-content:var(--color-purple-600)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-purple-500)] dark:[--color-primary-content:var(--color-purple-300)] dark:[--color-primary-fg:var(--color-neutral-50)]',
    'fuchsia' => '[--color-primary:var(--color-fuchsia-600)] [--color-primary-content:var(--color-fuchsia-600)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-fuchsia-600)] dark:[--color-primary-content:var(--color-fuchsia-400)] dark:[--color-primary-fg:var(--color-neutral-50)]',
    'pink' => '[--color-primary:var(--color-pink-600)] [--color-primary-content:var(--color-pink-600)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-pink-600)] dark:[--color-primary-content:var(--color-pink-400)] dark:[--color-primary-fg:var(--color-neutral-50)]',
    'rose' => '[--color-primary:var(--color-rose-500)] [--color-primary-content:var(--color-rose-500)] [--color-primary-fg:var(--color-neutral-50)] dark:[--color-primary:var(--color-rose-500)] dark:[--color-primary-content:var(--color-rose-400)] dark:[--color-primary-fg:var(--color-neutral-50)]',
    default => '',
};

$variantClasses = match($variant){
    'primary' => [
        'bg-[var(--color-primary)] hover:bg-[var(--color-primary)]/90', 
        'text-[var(--color-primary-fg)]', 
        'border border-black/10 dark:border-0',
        $colors => filled($color)
    ],
    'solid' => [
        'bg-neutral-800/5 hover:bg-neutral-800/10 dark:bg-white/10 dark:hover:bg-white/20',
        'text-neutral-800 dark:text-white'
    ],
    'soft' => [
        'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-white',
        ' bg-transparent'
    ],
    'outline' => [
        'border border-[--alpha(var(--color-primary)/20%)] hover:border-[--alpha(var(--color-primary)/25%)]', 
        'bg-[--alpha(var(--color-primary)/5%)] hover:bg-[--alpha(var(--color-primary)/7%)]', 
        'text-[var(--color-primary)]', 
        $colors => filled($color),
    ],
    'ghost' => [
        'bg-transparent hover:bg-[--alpha(var(--color-neutral-900)/5%)] dark:hover:bg-[--alpha(var(--color-white)/5%)]', 
        'text-neutral-800 dark:text-white' 
    ],
    'danger' =>[
        ' dark:shadow-none', 
        'bg-red-500 hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-500', 
        'text-white' 
    ],
    'none' => [],
    default => []
};

$classes = [
    'relative [:where(&)]:inline-flex items-center font-medium justify-center gap-x-2 whitespace-nowrap transition-colors duration-200',
    'disabled:opacity-55 dark:disabled:opacity-55 disabled:cursor-default disabled:pointer-events-none cursor-pointer',
    '[&_a]:no-underline [&_a]:decoration-none [&_a:hover]:no-underline' => $variant !== 'none' ,
    '[:where(&)]:rounded-field' => $variant !== 'none' , 
    '[&>[data-loading=true]:first-child]:flex', 
    '[&>[data-loading=true]:first-child~*]:opacity-0', 
    $sizeClasses,
    ...$variantClasses,
];

$hasWireLoading = filled($attributes->whereStartsWith('wire:loading')->first());

$loadingAttributes = new \Illuminate\View\ComponentAttributeBag();
$loadingAttributes = $loadingAttributes->merge($hasWireLoading || $type === 'submit' ? [
    'wire:loading.attr' => 'data-loading',
    'wire:target' => $attributes->has('wire:target') ? $attributes->get('wire:target') : ($attributes->whereStartsWith('wire:click')->first() ?? null),
] : []);

$loadingAttributes = $loadingAttributes->merge($loading ? [
    'data-loading' => 'true', // thats 'true' is crucial, boolean true will break the work
] : []);

@endphp

<x-ui.button.abstract 
    :$as 
    :$type 
    :attributes="$attributes->class($classes)->merge([
        'role' => $as === 'a' && !$attributes->has('href') ? 'button' : null,
        'aria-busy' => $loading ? 'true' : 'false',
        'aria-disabled' => $attributes->has('disabled') ? 'true' : 'false',
        // I know it basic, but you can create mapping labels for popular icons like ['x-mark' => 'Close']... 
        'aria-label' => $squared && blank($slot) ? Str::title($icon ?? $iconAfter ?? 'Button') : null,
    ])"  
    data-slot="button"
>
        {{-- This is a special icon component (ui/icon/loading.blade.php) required for the loading indicator --}}
        <div
            @class([
                'absolute inset-0 hidden items-center justify-center '
            ])
            {{-- the is just adding here data-loading="true" to shows loading icon,  you can add it manually, using php, js ... --}}
            {{ $loadingAttributes }}
        >
            <x-ui.icon.loading :variant="$iconVariant" data-slot="loading-indicator" :attributes="$iconAttributes"/>
        </div> 

    @if(filled($icon))
        <x-ui.icon :name="$icon" :variant="$iconVariant" :attributes="$iconAttributes"  data-slot="right-icon"/>
    @endif

    @if($slot->isNotEmpty())
        <span data-text>
            {{ $slot }}
        </span>
    @endif

    @if(filled($iconAfter))
        <x-ui.icon :name="$iconAfter" :variant="$iconVariant" :attributes="$iconAttributes" data-slot="left-icon"/>
    @endif
</x-ui.button.abstract>