{{--
    Manager DocPack Form — identical logic to receptionist version, just under manager layout.
    Extends the receptionist blade via @include to avoid duplication.
--}}
<div class="min-h-screen bg-background" wire:poll.1000ms.keep-alive>
    @php
        $card   = 'bg-card border border-border rounded-2xl shadow-xl overflow-hidden';
        $label  = 'block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5';
        $input  = 'w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all';
        $btnBlk = 'inline-flex items-center justify-center gap-2 px-5 h-10 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:bg-primary/95 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60';
    @endphp

    @push('styles')
    <style>
        :root { color-scheme: light; }
        select, option { color: var(--foreground) !important; background: var(--background) !important; }
        option:checked { background: var(--muted) !important; }
    </style>
    @endpush

    <div class="px-4 sm:px-6 py-6 space-y-6">
        <x-page-header title="Doc / Pack — New Entry" subtitle="Register an incoming or outgoing document or package." />

        <div class="{{ $card }}">
            <div class="px-6 py-5 border-b border-border bg-muted/10">
                <div class="flex items-center gap-3">
                    <div class="w-2.5 h-2.5 bg-primary rounded-full animate-pulse"></div>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">Add New Doc/Pack</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Fill in the details below to register the item.</p>
                    </div>
                </div>
            </div>

            <form wire:submit.prevent="save" class="p-6 space-y-6">
                {{-- Direction, Item Type, Storage --}}
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
                        @error('itemType') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
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

                {{-- Item Name --}}
                <div>
                    <label class="{{ $label }}">Item Name</label>
                    <input type="text" wire:model.defer="itemName" class="{{ $input }}" placeholder="e.g. Monthly Report Package">
                    @error('itemName') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {{-- Department --}}
                    <div>
                        <label class="{{ $label }}">
                            {{ $direction === 'taken' ? 'Receiver Department' : 'Sender Department' }}
                        </label>
                        <select wire:model.live="departmentId" class="{{ $input }}">
                            <option value="">— Select Department —</option>
                            @foreach($departments as $d)
                                <option value="{{ $d['id'] }}">{{ $d['name'] }}</option>
                            @endforeach
                        </select>
                        @error('departmentId') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    {{-- User --}}
                    <div>
                        <label class="{{ $label }}">
                            {{ $direction === 'taken' ? 'Receiver (Internal User)' : 'Sender (Internal User)' }}
                        </label>
                        <select wire:model.defer="userId" class="{{ $input }}" @disabled(!$departmentId)>
                            <option value="">— Select User —</option>
                            @foreach($users as $uid => $uname)
                                <option value="{{ $uid }}">{{ $uname }}</option>
                            @endforeach
                        </select>
                        @error('userId') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>

                    {{-- External party --}}
                    @if($direction === 'taken')
                    <div>
                        <label class="{{ $label }}">Sender Name (External)</label>
                        <input type="text" wire:model.defer="senderText" class="{{ $input }}" placeholder="Name of the external sender">
                        @error('senderText') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                    @else
                    <div>
                        <label class="{{ $label }}">Receiver Name (External)</label>
                        <input type="text" wire:model.defer="receiverText" class="{{ $input }}" placeholder="Name of the external recipient">
                        @error('receiverText') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    {{-- Photo --}}
                    <div>
                        <label class="{{ $label }}">Photo Evidence (optional)</label>
                        <input type="file" wire:model="photo" accept="image/*" class="{{ $input }} h-auto py-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary">
                        @error('photo') <p class="mt-1 text-xs text-destructive">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="{{ $btnBlk }}" wire:loading.attr="disabled">
                        <svg wire:loading wire:target="save" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                        Save Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
