<div class="min-h-screen bg-[#f5f7f2]"
    x-data="{ confirmDeleteId: null, confirmDeleteName: '', usageCount: 0, isAssigned: false }"
    @keydown.escape.window="confirmDeleteId = null">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.visitor_lanyards_title') }}"
            subtitle="{{ __('app.visitor_lanyards_subtitle') }}">
            <x-slot:actions>
                <button wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-[#CDDEA7]/20 text-[#CDDEA7] border border-[#CDDEA7]/30 hover:bg-[#CDDEA7]/30 transition">
                    {{ __('app.add_lanyard') }}
                </button>
            </x-slot:actions>
        </x-page-header>

        {{-- SEARCH --}}
        <div class="bg-white border border-[#d4dfc8] rounded-2xl p-4 shadow-sm">
            <div class="relative flex items-center">
                <div class="absolute left-3 text-[#9aaa8a]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35m1.6-5.65a7.25 7.25 0 11-14.5 0 7.25 7.25 0 0114.5 0z" />
                    </svg>
                </div>
                <input type="text"
                    wire:model.live.debounce.500ms="search"
                    placeholder="{{ __('app.search_lanyards') }}"
                    class="w-full pl-10 pr-20 py-2 rounded-lg border border-[#c4d4b4] text-[#2d3a24] placeholder-[#9aaa8a]
                           focus:ring-2 focus:ring-[#4E653D] focus:outline-none transition">
                @if($search)
                    <button wire:click="$set('search', '')"
                        class="absolute right-12 text-[#9aaa8a] hover:text-[#4E653D] transition">✕</button>
                @endif
                <div wire:loading wire:target="search" class="absolute right-3">
                    <svg class="animate-spin h-5 w-5 text-[#4E653D]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
            <div wire:loading wire:target="search" class="mt-2">
                <div class="w-full bg-[#dde4d4] rounded-full h-1 overflow-hidden">
                    <div class="bg-[#4E653D] h-1 rounded-full animate-loading-bar"></div>
                </div>
            </div>
        </div>

        {{-- TABLE --}}
        <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[700px]">
                    <thead class="bg-[#f0f4eb] text-[#7a8f6a] uppercase text-xs border-b">
                        <tr>
                            <th class="px-6 py-3 text-left">#</th>
                            <th class="px-6 py-3 text-left">{{ __('app.lanyard_name_col') }}</th>
                            <th class="px-6 py-3 text-left">{{ __('app.status') }}</th>
                            <th class="px-6 py-3 text-left">{{ __('app.usage_history_col') }}</th>
                            <th class="px-6 py-3 text-left">{{ __('app.created_col') }}</th>
                            <th class="px-6 py-3 text-left">{{ __('app.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#d4dfc8]">
                        @forelse($lanyards as $lanyard)
                            <tr class="hover:bg-[#f0f4eb] transition">
                                <td class="px-6 py-4 text-[#9aaa8a] text-xs">{{ $lanyards->firstItem() + $loop->index }}</td>

                                {{-- LANYARD NAME --}}
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-[#dde4d4] flex items-center justify-center text-[#4E653D]">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                            </svg>
                                        </div>
                                        <span class="font-medium text-[#2d3a24]">{{ $lanyard->lanyard_name }}</span>
                                    </div>
                                </td>

                                {{-- STATUS --}}
                                <td class="px-6 py-4">
                                    <button wire:click="toggleStatus({{ $lanyard->id }})"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium transition-colors
                                            {{ $lanyard->status
                                                ? 'bg-green-50 text-green-700 hover:bg-green-100'
                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                        <span class="w-2 h-2 rounded-full {{ $lanyard->status ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                        {{ $lanyard->status ? __('app.lanyard_available') : __('app.lanyard_unavailable') }}
                                    </button>
                                </td>

                                {{-- USAGE HISTORY --}}
                                <td class="px-6 py-4">
                                    @if($lanyard->guestbooks_count > 0)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-medium">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            {{ __('app.lanyard_used_count', ['count' => $lanyard->guestbooks_count]) }}
                                        </span>
                                    @else
                                        <span class="text-[#9aaa8a] text-xs">{{ __('app.lanyard_never_used') }}</span>
                                    @endif
                                </td>

                                {{-- CREATED DATE --}}
                                <td class="px-6 py-4 text-[#5a6e4a] text-xs">
                                    {{ $lanyard->created_at->format('d M Y') }}
                                </td>

                                {{-- ACTIONS --}}
                                <td class="px-6 py-4">
                                    <div class="flex gap-2">
                                        <button wire:click="openEditModal({{ $lanyard->id }})"
                                            class="px-3 py-1 bg-[#dde4d4] text-[#4E653D] rounded-md hover:bg-[#c4d4b4] text-sm transition">
                                            {{ __('app.edit') }}
                                        </button>
                                        <button type="button"
                                            @click="confirmDeleteId = {{ $lanyard->id }};
                                                    confirmDeleteName = '{{ addslashes($lanyard->lanyard_name) }}';
                                                    usageCount = {{ $lanyard->guestbooks_count }};
                                                    isAssigned = {{ $lanyard->status ? 'false' : 'true' }}"
                                            class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200 text-sm transition">
                                            {{ __('app.delete') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2 text-[#9aaa8a]">
                                        <svg class="w-10 h-10 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                        </svg>
                                        <span class="text-sm">{{ __('app.no_lanyards_found') }}{{ $search ? ' ' . __('app.no_results_for') . ' "' . $search . '"' : '' }}.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($lanyards->hasPages())
                <div class="px-6 py-4 border-t border-[#d4dfc8]">
                    {{ $lanyards->links() }}
                </div>
            @endif
        </div>

    </main>

    {{-- DELETE CONFIRM MODAL --}}
    <template x-teleport="body">
        <div x-show="confirmDeleteId !== null"
            x-transition.opacity
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
            style="display: none;">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="confirmDeleteId = null"></div>
            <div class="relative w-full max-w-sm bg-white rounded-2xl border border-[#d4dfc8] shadow-2xl overflow-hidden" @click.stop>
                <div class="px-6 pt-6 pb-4 text-center">
                    <div class="mx-auto w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-[#2d3a24]">{{ __('app.delete_lanyard_title') }}</h3>
                    <p class="mt-1 text-sm text-[#7a8f6a]">
                        {{ __('app.are_you_sure_delete') }}<br>
                        <span class="font-medium text-[#2d3a24]" x-text="confirmDeleteName"></span>?
                    </p>
                    <template x-if="usageCount > 0">
                        <p class="mt-2 text-xs text-red-600 font-medium"
                            x-text="'⚠️ {{ addslashes(__('app.lanyard_in_use_warning', ['count' => ''])) }}' + usageCount">
                        </p>
                    </template>
                    <template x-if="!isAssigned && usageCount === 0">
                        <p class="mt-2 text-xs text-green-600">
                            {{ __('app.lanyard_safe_to_delete') }}
                        </p>
                    </template>
                </div>
                <div class="px-6 pb-6 flex gap-3">
                    <button type="button" @click="confirmDeleteId = null"
                        class="flex-1 px-4 py-2 rounded-lg border border-[#d4dfc8] text-sm text-[#5a6e4a] hover:bg-[#f0f4eb] transition">
                        {{ __('app.cancel') }}
                    </button>
                    <button type="button"
                        @click="$wire.delete(confirmDeleteId); confirmDeleteId = null"
                        class="flex-1 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition">
                        {{ __('app.delete') }}
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- CREATE / EDIT MODAL --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300"
                wire:click="closeModal"></div>

            <div class="relative w-full max-w-lg bg-card rounded-2xl border border-border shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                {{-- Header --}}
                <div class="px-6 py-5 border-b border-border bg-muted/10 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                        </div>
                        <h3 class="font-bold text-foreground text-base tracking-tight">
                            {{ $editMode ? __('app.edit_visitor_lanyard') : __('app.add_new_lanyard') }}
                        </h3>
                    </div>
                    <button type="button"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition"
                        wire:click="closeModal"
                        aria-label="{{ __('app.close') }}">✕</button>
                </div>

                <form wire:submit.prevent="save" class="flex flex-col overflow-hidden">
                    <div class="p-6 space-y-4 overflow-y-auto">

                        {{-- LANYARD NAME --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                {{ __('app.lanyard_name_label') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                wire:model="{{ $editMode ? 'edit_lanyard_name' : 'lanyard_name' }}"
                                placeholder="{{ __('app.lanyard_name_placeholder') }}"
                                class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground
                                       focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            @if($editMode)
                                @error('edit_lanyard_name') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                            @else
                                @error('lanyard_name') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                            @endif
                        </div>

                        {{-- STATUS --}}
                        <div class="flex items-center justify-between p-3 rounded-lg border border-input bg-background">
                            <div>
                                <p class="text-sm font-medium text-foreground">{{ __('app.lanyard_status_label') }}</p>
                                <p class="text-xs text-muted-foreground">{{ __('app.lanyard_status_description') }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox"
                                    wire:model="{{ $editMode ? 'edit_status' : 'status' }}"
                                    class="sr-only peer">
                                <div class="w-11 h-6 bg-muted peer-focus:ring-2 peer-focus:ring-primary/20 rounded-full peer
                                            peer-checked:after:translate-x-full peer-checked:after:border-white
                                            after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                            after:bg-white after:border-gray-300 after:border after:rounded-full
                                            after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>

                        <div class="p-3 rounded-lg border border-input bg-muted/5">
                            <p class="text-xs text-muted-foreground">
                                <svg class="w-3.5 h-3.5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ __('app.lanyard_auto_hint') }}
                            </p>
                        </div>

                    </div>

                    {{-- Footer --}}
                    <div class="border-t border-border px-6 py-4 flex items-center justify-end gap-3 bg-muted/5 shrink-0">
                        <button type="button" wire:click="closeModal"
                            class="h-9 px-4 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold
                                   hover:bg-secondary/80 border border-border transition inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span>{{ __('app.cancel') }}</span>
                        </button>
                        <button type="submit"
                            wire:loading.attr="disabled"
                            class="h-9 px-4 rounded-lg bg-primary text-primary-foreground text-xs font-semibold
                                   hover:bg-primary/95 transition shadow-sm inline-flex items-center gap-1.5 disabled:opacity-60">
                            <svg wire:loading wire:target="save" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <svg wire:loading.remove wire:target="save" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>{{ $editMode ? __('app.update_lanyard_btn') : __('app.create_lanyard_btn') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <style>
        @keyframes loading-bar { 0% { width: 0%; } 50% { width: 70%; } 100% { width: 100%; } }
        .animate-loading-bar { animation: loading-bar 1s ease-in-out infinite; }
    </style>
</div>
