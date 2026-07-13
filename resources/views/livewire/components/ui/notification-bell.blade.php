<div class="relative" x-data="{ open: @entangle('open').live }" @click.outside="open = false">

    {{-- Bell button --}}
    <button @click="$wire.toggle()"
        class="relative flex items-center justify-center w-9 h-9 rounded-xl border border-border bg-card text-muted-foreground hover:text-foreground hover:bg-muted transition focus:outline-none focus:ring-2 focus:ring-primary/20"
        title="Notifications">
        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if($unreadCount > 0)
        <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 flex items-center justify-center rounded-full bg-destructive text-destructive-foreground text-[10px] font-bold leading-none shadow">
            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
        </span>
        @endif
    </button>

    {{-- Dropdown panel --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
         class="absolute right-0 top-full mt-2 w-80 sm:w-96 bg-card border border-border rounded-2xl shadow-xl z-[200] overflow-hidden"
         style="display:none;">

        {{-- Header --}}
        <div class="px-4 py-3 border-b border-border flex items-center justify-between bg-muted/30">
            <div class="flex items-center gap-2">
                <p class="text-sm font-semibold text-foreground">Notifications</p>
                @if($unreadCount > 0)
                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-destructive text-destructive-foreground">{{ $unreadCount }}</span>
                @endif
            </div>
            @if($unreadCount > 0)
            <button wire:click="markAllRead" class="text-xs text-primary hover:underline font-medium">Mark all read</button>
            @endif
        </div>

        {{-- Notification list --}}
        <div class="max-h-[28rem] overflow-y-auto divide-y divide-border/60">
            @forelse($notifs as $n)
            <div class="px-4 py-3.5 hover:bg-muted/30 transition {{ !$n->is_read ? 'bg-primary/5' : '' }}"
                 wire:key="notif-{{ $n->id }}">
                <div class="flex items-start gap-3">
                    {{-- Type icon --}}
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5
                        {{ str_contains($n->type, 'room') ? 'bg-amber-500/10 text-amber-500' : 'bg-blue-500/10 text-blue-500' }}">
                        @if(str_contains($n->type, 'room'))
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21V11.5a1.5 1.5 0 013 0V21"/></svg>
                        @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        {{-- Title row --}}
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-xs font-semibold text-foreground leading-snug">{{ $n->title }}</p>
                            @if(!$n->is_read)
                            <span class="w-2 h-2 rounded-full bg-primary shrink-0 mt-1 flex-none"></span>
                            @endif
                        </div>

                        {{-- Message --}}
                        <p class="text-[11px] text-muted-foreground mt-0.5 leading-relaxed line-clamp-2">{{ $n->message }}</p>
                        <p class="text-[10px] text-muted-foreground/60 mt-1">{{ $n->created_at->diffForHumans() }}</p>

                        {{-- Action buttons — shown only for pending-action notifications --}}
                        @if($n->isPendingAction())
                        <div class="flex items-center gap-2 mt-2.5">
                            {{-- Approve button --}}
                            <button wire:click="approveDirectly({{ $n->id }})"
                                wire:loading.attr="disabled"
                                wire:target="approveDirectly({{ $n->id }})"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 transition disabled:opacity-60 shadow-sm">
                                <svg wire:loading wire:target="approveDirectly({{ $n->id }})" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                <svg wire:loading.remove wire:target="approveDirectly({{ $n->id }})" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                Approve &amp; Cancel Conflict
                            </button>

                            {{-- Deny button --}}
                            <button wire:click="denyDirectly({{ $n->id }})"
                                wire:loading.attr="disabled"
                                wire:target="denyDirectly({{ $n->id }})"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold rounded-lg bg-destructive/10 text-destructive hover:bg-destructive/20 active:scale-95 transition disabled:opacity-60">
                                <svg wire:loading wire:target="denyDirectly({{ $n->id }})" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                <svg wire:loading.remove wire:target="denyDirectly({{ $n->id }})" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Deny
                            </button>
                        </div>

                        @elseif($n->action_taken)
                        {{-- Already actioned — show result badge --}}
                        <div class="mt-1.5">
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full
                                {{ $n->action_taken === 'approved' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-red-500/10 text-red-500' }}">
                                @if($n->action_taken === 'approved')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                @else
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                @endif
                                {{ ucfirst($n->action_taken) }}
                            </span>
                        </div>
                        @endif

                    </div>
                </div>
            </div>
            @empty
            <div class="px-4 py-10 text-center text-muted-foreground">
                <svg class="w-8 h-8 mx-auto mb-2 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <p class="text-xs">No notifications yet.</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
