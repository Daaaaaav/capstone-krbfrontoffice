<div class="min-h-screen bg-background" wire:poll.30000ms.keep-alive>
    @php
        $card   = 'bg-card border border-border rounded-2xl shadow-sm overflow-hidden';
        $label  = 'block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5';
        $input  = 'w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all';
        $btnBlk = 'inline-flex items-center justify-center gap-2 px-5 h-10 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:bg-primary/95 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60';
    @endphp

    <div class="px-4 sm:px-6 py-6 space-y-5">
        <x-page-header title="Doc / Pack — New Entry" subtitle="Register an incoming or outgoing document or package." />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            {{-- LEFT: Form --}}
            <div class="lg:col-span-2 {{ $card }}">
                <div class="px-6 py-4 border-b border-border bg-muted/10 flex items-center gap-3">
                    <div class="w-2 h-2 bg-primary rounded-full animate-pulse"></div>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">Add New Doc/Pack</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Fill in the details to register the item.</p>
                    </div>
                </div>
                <form wire:submit.prevent="save" class="p-6 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="{{ $label }}">Flow Direction</label>
                            <select wire:model.live="direction" class="{{ $input }}">
                                <option value="taken">Incoming (Taken)</option>
                                <option value="deliver">Outgoing (Deliver)</option>
                            </select>
                            @error('direction') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Item Type</label>
                            <select wire:model.defer="itemType" class="{{ $input }}">
                                <option value="package">Package</option>
                                <option value="document">Document</option>
                            </select>
                        </div>
                        <div>
                            <label class="{{ $label }}">Storage Location</label>
                            <select wire:model.defer="storageId" class="{{ $input }}">
                                <option value="">— Select Storage —</option>
                                @foreach($storages as $s)
                                    <option value="{{ $s['id'] }}">{{ $s['name'] }}</option>
                                @endforeach
                            </select>
                            @error('storageId') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="{{ $label }}">Item Name</label>
                        <input type="text" wire:model.defer="itemName" class="{{ $input }}" placeholder="e.g. Monthly Report Package">
                        @error('itemName') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="{{ $label }}">{{ $direction === 'taken' ? 'Receiver Department' : 'Sender Department' }}</label>
                            <select wire:model.live="departmentId" class="{{ $input }}">
                                <option value="">— Select Department —</option>
                                @foreach($departments as $d)
                                    <option value="{{ $d['id'] }}">{{ $d['name'] }}</option>
                                @endforeach
                            </select>
                            @error('departmentId') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">{{ $direction === 'taken' ? 'Receiver (Internal User)' : 'Sender (Internal User)' }}</label>
                            <select wire:model.defer="userId" class="{{ $input }}" @disabled(!$departmentId)>
                                <option value="">— Select User —</option>
                                @foreach($users as $uid => $uname)
                                    <option value="{{ $uid }}">{{ $uname }}</option>
                                @endforeach
                            </select>
                            @error('userId') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        @if($direction === 'taken')
                        <div>
                            <label class="{{ $label }}">Sender Name (External)</label>
                            <input type="text" wire:model.defer="senderText" class="{{ $input }}" placeholder="External sender name">
                            @error('senderText') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        @else
                        <div>
                            <label class="{{ $label }}">Receiver Name (External)</label>
                            <input type="text" wire:model.defer="receiverText" class="{{ $input }}" placeholder="External recipient name">
                            @error('receiverText') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                        @endif
                        <div>
                            <label class="{{ $label }}">Photo Evidence</label>
                            <input type="file" wire:model="photo" accept="image/*" class="{{ $input }} h-auto py-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary">
                            @error('photo') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end pt-1">
                        <button type="submit" class="{{ $btnBlk }}" wire:loading.attr="disabled">
                            <svg wire:loading wire:target="save" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                            Save Entry
                        </button>
                    </div>
                </form>
            </div>

            {{-- RIGHT: Status Sidebar --}}
            <aside class="lg:col-span-1 space-y-4">

                {{-- Pending --}}
                <div class="{{ $card }}" x-data="{ open: true }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 border-b border-border bg-muted/30 hover:bg-muted/50 transition">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                            <p class="text-xs font-bold uppercase tracking-wider text-foreground">Pending</p>
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-400 text-white">{{ $sidebarPending->count() }}</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-muted-foreground transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="divide-y divide-border/50 max-h-60 overflow-y-auto">
                        @forelse($sidebarPending as $d)
                        <div class="flex items-start gap-3 px-4 py-2.5 hover:bg-muted/20 transition">
                            <div class="w-7 h-7 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="text-[10px] font-bold text-amber-600">{{ strtoupper(substr($d->item_name ?? 'P', 0, 1)) }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-foreground truncate">{{ $d->item_name }}</p>
                                <p class="text-[11px] text-muted-foreground truncate">{{ $d->nama_pengirim ?? '—' }} → {{ $d->nama_penerima ?? '—' }}</p>
                                <p class="text-[10px] text-muted-foreground/60">{{ $d->created_at?->diffForHumans() }}</p>
                            </div>
                            <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 capitalize">{{ $d->type }}</span>
                        </div>
                        @empty
                        <p class="px-4 py-4 text-xs text-muted-foreground italic text-center">No pending items.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Stored --}}
                <div class="{{ $card }}" x-data="{ open: true }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 border-b border-border bg-muted/30 hover:bg-muted/50 transition">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-sky-400 animate-pulse"></span>
                            <p class="text-xs font-bold uppercase tracking-wider text-foreground">Stored</p>
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-sky-500 text-white">{{ $sidebarStored->count() }}</span>
                        </div>
                        <svg class="w-3.5 h-3.5 text-muted-foreground transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="divide-y divide-border/50 max-h-60 overflow-y-auto">
                        @forelse($sidebarStored as $d)
                        <div class="flex items-start gap-3 px-4 py-2.5 hover:bg-muted/20 transition">
                            <div class="w-7 h-7 rounded-lg bg-sky-500/10 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="text-[10px] font-bold text-sky-600">{{ strtoupper(substr($d->item_name ?? 'P', 0, 1)) }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-foreground truncate">{{ $d->item_name }}</p>
                                <p class="text-[11px] text-muted-foreground truncate">{{ $d->nama_pengirim ?? '—' }} → {{ $d->nama_penerima ?? '—' }}</p>
                                <p class="text-[10px] text-muted-foreground/60">{{ $d->updated_at?->diffForHumans() }}</p>
                            </div>
                            <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-sky-100 text-sky-700 capitalize">{{ $d->type }}</span>
                        </div>
                        @empty
                        <p class="px-4 py-4 text-xs text-muted-foreground italic text-center">No stored items.</p>
                        @endforelse
                    </div>
                </div>

            </aside>
        </div>
    </div>
</div>
