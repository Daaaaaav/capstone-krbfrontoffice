@php
    use Carbon\Carbon;
    $card   = 'bg-card border border-border rounded-2xl shadow-sm overflow-hidden';
    $input  = 'w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all';
    $btnSm  = 'inline-flex items-center gap-1.5 px-3 h-8 text-xs font-semibold rounded-lg transition focus:outline-none';
@endphp

<div class="min-h-screen bg-background" wire:poll.30000ms.keep-alive>
<div class="px-4 sm:px-6 py-6 space-y-6">

    <x-page-header title="Doc / Pack — Status" subtitle="Track and advance pending items through storage to final delivery." />

    {{-- Controls row --}}
    <div class="flex flex-wrap items-center gap-3">
        {{-- Stage tabs --}}
        <div class="inline-flex items-center bg-muted rounded-xl p-1 text-xs font-medium">
            <button wire:click="setTab('pending')" class="px-4 py-1.5 rounded-lg transition {{ $activeTab === 'pending' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">Pending</button>
            <button wire:click="setTab('stored')"  class="px-4 py-1.5 rounded-lg transition {{ $activeTab === 'stored'  ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">Stored</button>
        </div>
        {{-- Type tabs --}}
        <div class="inline-flex items-center bg-muted rounded-xl p-1 text-xs font-medium">
            <button wire:click="$set('type','all')"      class="px-3.5 py-1.5 rounded-lg transition {{ $type === 'all'      ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">All</button>
            <button wire:click="$set('type','document')" class="px-3.5 py-1.5 rounded-lg transition {{ $type === 'document' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">Document</button>
            <button wire:click="$set('type','package')"  class="px-3.5 py-1.5 rounded-lg transition {{ $type === 'package'  ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">Package</button>
        </div>
        {{-- Search --}}
        <div class="relative flex-1 max-w-xs">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" wire:model.live.debounce.300ms="q" placeholder="Search items…" class="w-full h-9 pl-9 pr-4 rounded-lg border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
        </div>
        <input type="date" wire:model.live="selectedDate" class="h-9 px-3 rounded-lg border border-input bg-background text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
    </div>

    {{-- Item cards --}}
    @if($activeTab === 'pending')
        @forelse($pending as $row)
        @php $typeColor = $row->type === 'document' ? 'text-blue-500 bg-blue-500/10' : 'text-violet-500 bg-violet-500/10'; @endphp
        <div class="{{ $card }} p-5" wire:key="pend-{{ $row->delivery_id }}">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl {{ $typeColor }} flex items-center justify-center shrink-0 font-bold text-base">
                        {{ strtoupper(substr($row->item_name ?? 'P', 0, 1)) }}
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <p class="text-sm font-semibold text-foreground truncate">{{ $row->item_name }}</p>
                        <p class="text-xs text-muted-foreground">From: {{ $row->nama_pengirim ?? '—' }} → To: {{ $row->nama_penerima ?? '—' }}</p>
                        <p class="text-xs text-muted-foreground">{{ $row->created_at?->format('d M Y H:i') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="inline-flex px-2.5 py-1 text-[11px] font-semibold rounded-full bg-amber-500/10 text-amber-600 border border-amber-500/30">Pending</span>
                    <button wire:click="openEdit({{ $row->delivery_id }})" class="{{ $btnSm }} border border-border hover:bg-muted">Edit</button>
                    <button wire:click="storeItem({{ $row->delivery_id }})" class="{{ $btnSm }} bg-primary text-primary-foreground hover:bg-primary/90">Mark Stored</button>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-12 text-muted-foreground">
            <svg class="w-10 h-10 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <p class="text-sm">No pending items.</p>
        </div>
        @endforelse
        <div>{{ $pending->links() }}</div>

    @else
        @forelse($stored as $row)
        @php
            $dir = $storedDirections[$row->delivery_id ?? $row->id] ?? 'taken';
            $dirLabel = $dir === 'deliver' ? 'Deliver Out' : 'Give to Receiver';
            $typeColor = $row->type === 'document' ? 'text-blue-500 bg-blue-500/10' : 'text-violet-500 bg-violet-500/10';
        @endphp
        <div class="{{ $card }} p-5" wire:key="stored-{{ $row->delivery_id }}">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl {{ $typeColor }} flex items-center justify-center shrink-0 font-bold text-base">
                        {{ strtoupper(substr($row->item_name ?? 'P', 0, 1)) }}
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <p class="text-sm font-semibold text-foreground truncate">{{ $row->item_name }}</p>
                        <p class="text-xs text-muted-foreground">From: {{ $row->nama_pengirim ?? '—' }} → To: {{ $row->nama_penerima ?? '—' }}</p>
                        <p class="text-xs text-muted-foreground">Stored {{ $row->updated_at?->format('d M Y H:i') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="inline-flex px-2.5 py-1 text-[11px] font-semibold rounded-full bg-sky-500/10 text-sky-600 border border-sky-500/30">Stored</span>
                    <button wire:click="openEdit({{ $row->delivery_id }})" class="{{ $btnSm }} border border-border hover:bg-muted">Edit</button>
                    <button wire:click="finalizeItem({{ $row->delivery_id }})" class="{{ $btnSm }} bg-emerald-600 text-white hover:bg-emerald-700">{{ $dirLabel }}</button>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-12 text-muted-foreground">
            <svg class="w-10 h-10 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <p class="text-sm">No stored items.</p>
        </div>
        @endforelse
        <div>{{ $stored->links() }}</div>
    @endif

</div>

{{-- ── EDIT MODAL ── --}}
@if($showEdit)
<div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-card border border-border rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <div class="flex items-center justify-between">
            <p class="font-semibold text-foreground">Edit Item</p>
            <button wire:click="$set('showEdit',false)" class="text-muted-foreground hover:text-foreground transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Item Name</label>
                <input type="text" wire:model.defer="edit.item_name" class="{{ $input }}">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Sender</label>
                <input type="text" wire:model.defer="edit.nama_pengirim" class="{{ $input }}">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Receiver</label>
                <input type="text" wire:model.defer="edit.nama_penerima" class="{{ $input }}">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">Replace Photo</label>
                <input type="file" wire:model="editPhoto" accept="image/*" class="{{ $input }} h-auto py-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary">
            </div>
        </div>
        <div class="flex gap-2 pt-2">
            <button wire:click="saveEdit" class="flex-1 inline-flex items-center justify-center h-10 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 transition">Save</button>
            <button wire:click="$set('showEdit',false)" class="flex-1 inline-flex items-center justify-center h-10 text-xs font-semibold rounded-lg border border-border bg-card text-foreground hover:bg-muted transition">Cancel</button>
        </div>
    </div>
</div>
@endif

</div>
