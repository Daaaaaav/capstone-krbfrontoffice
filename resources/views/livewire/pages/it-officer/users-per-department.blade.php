<div class="min-h-screen bg-[#f5f7f2]"
    x-data="{ confirmDeleteId: null, confirmDeleteName: '' }"
    @keydown.escape.window="confirmDeleteId = null">
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-8">

        {{-- HEADER --}}
        <x-page-header
            title="Manage Users per Department"
            subtitle="View and manage users organized by their assigned department">
            <x-slot name="actions">
                <button wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition shadow-sm"
                    style="background:#CDDEA7; color:#2d3a24;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    <span>Create New User</span>
                </button>
            </x-slot>
        </x-page-header>

        {{-- ================= SEARCH ================= --}}
        <div class="bg-white border border-[#d4dfc8] rounded-2xl p-4 shadow-sm">
            <div class="relative flex items-center">
                {{-- ICON --}}
                <div class="absolute left-3 text-[#9aaa8a]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35m1.6-5.65a7.25 7.25 0 11-14.5 0 7.25 7.25 0 0114.5 0z" />
                    </svg>
                </div>

                {{-- INPUT --}}
                <input type="text"
                    wire:model.live.debounce.500ms="search"
                    placeholder="Search users across all departments..."
                    class="w-full pl-10 pr-20 py-2 rounded-lg border border-[#c4d4b4]
                        text-[#2d3a24] placeholder-[#9aaa8a]
                        focus:ring-2 focus:ring-[#4E653D] focus:outline-none transition">

                {{-- CLEAR BUTTON --}}
                @if($search)
                    <button wire:click="$set('search', '')"
                        class="absolute right-12 text-[#9aaa8a] hover:text-[#4E653D] transition">
                        ✕
                    </button>
                @endif

                {{-- LOADING SPINNER --}}
                <div wire:loading wire:target="search"
                    class="absolute right-3">
                    <svg class="animate-spin h-5 w-5 text-[#4E653D]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>

            {{-- LOADING BAR --}}
            <div wire:loading wire:target="search" class="mt-2">
                <div class="w-full bg-[#dde4d4] rounded-full h-1 overflow-hidden">
                    <div class="bg-[#4E653D] h-1 rounded-full animate-loading-bar"></div>
                </div>
            </div>
        </div>

        {{-- ================= DEPARTMENTS ================= --}}
        <div class="space-y-4">
            @foreach($departments as $department)
                @php
                    $isExpanded = $this->expandedDepartments[$department->department_id] ?? false;
                    $deptData = $departmentUsers[$department->department_id] ?? null;
                    $userCount = $deptData['total'] ?? 0;
                @endphp

                <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">
                    {{-- Department Header --}}
                    <button 
                        wire:click="toggleDepartment({{ $department->department_id }})"
                        class="w-full px-6 py-4 flex items-center justify-between hover:bg-[#f0f4eb] transition-colors">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-[#4E653D] transition-transform duration-200 {{ $isExpanded ? 'rotate-90' : '' }}"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <h3 class="text-lg font-bold text-[#2d3a24]">{{ $department->department_name }}</h3>
                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-[#dde4d4] text-[#4E653D]">
                                {{ $userCount }} {{ $userCount === 1 ? 'user' : 'users' }}
                            </span>
                        </div>
                    </button>

                    {{-- Department Users Table --}}
                    @if($isExpanded && $deptData)
                        <div class="border-t border-[#d4dfc8]">
                            @if($deptData['users']->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm min-w-[600px]">
                                        <thead class="bg-[#f0f4eb] text-[#7a8f6a] uppercase text-xs">
                                            <tr>
                                                <th class="px-6 py-3 text-left">{{ __('app.name') }}</th>
                                                <th class="px-6 py-3 text-left">{{ __('app.email') }}</th>
                                                <th class="px-6 py-3 text-left">Role</th>
                                                <th class="px-6 py-3 text-left">{{ __('app.status') }}</th>
                                                <th class="px-6 py-3 text-left">{{ __('app.actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-[#d4dfc8]">
                                            @foreach($deptData['users'] as $user)
                                                <tr class="hover:bg-[#f0f4eb] transition">
                                                    {{-- NAME --}}
                                                    <td class="px-6 py-4">
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-10 h-10 rounded-full bg-[#dde4d4] flex items-center justify-center text-[#4E653D] font-semibold">
                                                                {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                                                            </div>
                                                            <span class="font-medium text-[#2d3a24]">
                                                                {{ $user->name ?? 'Unknown' }}
                                                            </span>
                                                        </div>
                                                    </td>

                                                    {{-- EMAIL --}}
                                                    <td class="px-6 py-4 text-[#5a6e4a]">
                                                        {{ $user->email }}
                                                    </td>

                                                    {{-- ROLE --}}
                                                    <td class="px-6 py-4">
                                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-[#eef1e8] text-[#5a6e4a]">
                                                            {{ $user->role->name ?? 'N/A' }}
                                                        </span>
                                                    </td>

                                                    {{-- STATUS --}}
                                                    <td class="px-6 py-4">
                                                        <span class="px-3 py-1 rounded-full text-xs font-medium
                                                            {{ $user->status === 'active'
                                                                ? 'bg-green-100 text-green-700'
                                                                : 'bg-[#eef1e8] text-[#5a6e4a]' }}">
                                                            {{ ucfirst($user->status ?? 'active') }}
                                                        </span>
                                                    </td>

                                                    {{-- ACTIONS --}}
                                                    <td class="px-6 py-4">
                                                        <div class="flex gap-2">
                                                            <button wire:click="openEditModal({{ $user->user_id }})"
                                                                class="px-3 py-1 bg-[#dde4d4] text-[#4E653D] rounded-md hover:bg-[#c4d4b4] text-sm">
                                                                {{ __('app.edit') }}
                                                            </button>

                                                            <button type="button"
                                                                @click="confirmDeleteId = {{ $user->user_id }}; confirmDeleteName = '{{ addslashes($user->full_name) }}'"
                                                                class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200 text-sm">
                                                                {{ __('app.delete') }}
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Pagination for this department --}}
                                @if($deptData['last_page'] > 1)
                                    <div class="px-6 py-4 border-t border-[#d4dfc8] flex items-center justify-between">
                                        <div class="text-sm text-[#7a8f6a]">
                                            Showing {{ (($deptData['current_page'] - 1) * $deptData['per_page']) + 1 }} 
                                            to {{ min($deptData['current_page'] * $deptData['per_page'], $deptData['total']) }} 
                                            of {{ $deptData['total'] }} users
                                        </div>
                                        <div class="flex gap-1">
                                            <button 
                                                wire:click="setDepartmentPage({{ $department->department_id }}, {{ $deptData['current_page'] - 1 }})"
                                                @if($deptData['current_page'] <= 1) disabled @endif
                                                class="px-3 py-1 rounded-lg border border-[#d4dfc8] text-sm text-[#5a6e4a] hover:bg-[#f0f4eb] transition disabled:opacity-50 disabled:cursor-not-allowed">
                                                Previous
                                            </button>
                                            
                                            @for($i = 1; $i <= $deptData['last_page']; $i++)
                                                <button 
                                                    wire:click="setDepartmentPage({{ $department->department_id }}, {{ $i }})"
                                                    class="px-3 py-1 rounded-lg text-sm transition {{ $deptData['current_page'] === $i ? 'bg-[#4E653D] text-white' : 'border border-[#d4dfc8] text-[#5a6e4a] hover:bg-[#f0f4eb]' }}">
                                                    {{ $i }}
                                                </button>
                                            @endfor
                                            
                                            <button 
                                                wire:click="setDepartmentPage({{ $department->department_id }}, {{ $deptData['current_page'] + 1 }})"
                                                @if($deptData['current_page'] >= $deptData['last_page']) disabled @endif
                                                class="px-3 py-1 rounded-lg border border-[#d4dfc8] text-sm text-[#5a6e4a] hover:bg-[#f0f4eb] transition disabled:opacity-50 disabled:cursor-not-allowed">
                                                Next
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            @else
                                <div class="px-6 py-10 text-center text-[#7a8f6a]">
                                    No users found in this department
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Unassigned Users Section --}}
            @if($unassignedData['total'] > 0 || !empty($search))
                @php
                    $isUnassignedExpanded = $this->expandedDepartments[0] ?? true;
                    $unassignedCount = $unassignedData['total'];
                @endphp

                <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">
                    {{-- Unassigned Header --}}
                    <button 
                        wire:click="toggleDepartment(0)"
                        class="w-full px-6 py-4 flex items-center justify-between hover:bg-[#f0f4eb] transition-colors">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-[#9aaa8a] transition-transform duration-200 {{ $isUnassignedExpanded ? 'rotate-90' : '' }}"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <h3 class="text-lg font-bold text-[#2d3a24]">Unassigned</h3>
                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-[#f0f4eb] text-[#7a8f6a]">
                                {{ $unassignedCount }} {{ $unassignedCount === 1 ? 'user' : 'users' }}
                            </span>
                        </div>
                    </button>

                    {{-- Unassigned Users Table --}}
                    @if($isUnassignedExpanded)
                        <div class="border-t border-[#d4dfc8]">
                            @if($unassignedData['users']->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm min-w-[600px]">
                                        <thead class="bg-[#f0f4eb] text-[#7a8f6a] uppercase text-xs">
                                            <tr>
                                                <th class="px-6 py-3 text-left">{{ __('app.name') }}</th>
                                                <th class="px-6 py-3 text-left">{{ __('app.email') }}</th>
                                                <th class="px-6 py-3 text-left">Role</th>
                                                <th class="px-6 py-3 text-left">{{ __('app.status') }}</th>
                                                <th class="px-6 py-3 text-left">{{ __('app.actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-[#d4dfc8]">
                                            @foreach($unassignedData['users'] as $user)
                                                <tr class="hover:bg-[#f0f4eb] transition">
                                                    <td class="px-6 py-4">
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-10 h-10 rounded-full bg-[#dde4d4] flex items-center justify-center text-[#4E653D] font-semibold">
                                                                {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                                                            </div>
                                                            <span class="font-medium text-[#2d3a24]">
                                                                {{ $user->name ?? 'Unknown' }}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 text-[#5a6e4a]">
                                                        {{ $user->email }}
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-[#eef1e8] text-[#5a6e4a]">
                                                            {{ $user->role->name ?? 'N/A' }}
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <span class="px-3 py-1 rounded-full text-xs font-medium
                                                            {{ $user->status === 'active'
                                                                ? 'bg-green-100 text-green-700'
                                                                : 'bg-[#eef1e8] text-[#5a6e4a]' }}">
                                                            {{ ucfirst($user->status ?? 'active') }}
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="flex gap-2">
                                                            <button wire:click="openEditModal({{ $user->user_id }})"
                                                                class="px-3 py-1 bg-[#dde4d4] text-[#4E653D] rounded-md hover:bg-[#c4d4b4] text-sm">
                                                                {{ __('app.edit') }}
                                                            </button>
                                                            <button type="button"
                                                                @click="confirmDeleteId = {{ $user->user_id }}; confirmDeleteName = '{{ addslashes($user->full_name) }}'"
                                                                class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200 text-sm">
                                                                {{ __('app.delete') }}
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Pagination for unassigned users --}}
                                @if($unassignedData['last_page'] > 1)
                                    <div class="px-6 py-4 border-t border-[#d4dfc8] flex items-center justify-between">
                                        <div class="text-sm text-[#7a8f6a]">
                                            Showing {{ (($unassignedData['current_page'] - 1) * $unassignedData['per_page']) + 1 }} 
                                            to {{ min($unassignedData['current_page'] * $unassignedData['per_page'], $unassignedData['total']) }} 
                                            of {{ $unassignedData['total'] }} users
                                        </div>
                                        <div class="flex gap-1">
                                            <button 
                                                wire:click="setDepartmentPage(0, {{ $unassignedData['current_page'] - 1 }})"
                                                @if($unassignedData['current_page'] <= 1) disabled @endif
                                                class="px-3 py-1 rounded-lg border border-[#d4dfc8] text-sm text-[#5a6e4a] hover:bg-[#f0f4eb] transition disabled:opacity-50 disabled:cursor-not-allowed">
                                                Previous
                                            </button>
                                            
                                            @for($i = 1; $i <= $unassignedData['last_page']; $i++)
                                                <button 
                                                    wire:click="setDepartmentPage(0, {{ $i }})"
                                                    class="px-3 py-1 rounded-lg text-sm transition {{ $unassignedData['current_page'] === $i ? 'bg-[#4E653D] text-white' : 'border border-[#d4dfc8] text-[#5a6e4a] hover:bg-[#f0f4eb]' }}">
                                                    {{ $i }}
                                                </button>
                                            @endfor
                                            
                                            <button 
                                                wire:click="setDepartmentPage(0, {{ $unassignedData['current_page'] + 1 }})"
                                                @if($unassignedData['current_page'] >= $unassignedData['last_page']) disabled @endif
                                                class="px-3 py-1 rounded-lg border border-[#d4dfc8] text-sm text-[#5a6e4a] hover:bg-[#f0f4eb] transition disabled:opacity-50 disabled:cursor-not-allowed">
                                                Next
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            @else
                                <div class="px-6 py-10 text-center text-[#7a8f6a]">
                                    No unassigned users found
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            @if($departments->count() === 0 && $unassignedData['total'] === 0)
                <div class="bg-white border border-[#d4dfc8] rounded-2xl p-12 text-center">
                    <div class="max-w-sm mx-auto">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[#dde4d4] flex items-center justify-center">
                            <svg class="w-8 h-8 text-[#7a8f6a]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-[#2d3a24] mb-2">No Users Found</h3>
                        <p class="text-sm text-[#7a8f6a]">
                            There are no users to display. Try adjusting your search criteria.
                        </p>
                    </div>
                </div>
            @endif
        </div>

    </main>

    {{-- ================= DELETE CONFIRM MODAL ================= --}}
    <template x-teleport="body">
        <div x-show="confirmDeleteId !== null"
            x-transition.opacity
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
            style="display: none;">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/60 backdrop-blur-md"
                @click="confirmDeleteId = null"></div>

            {{-- Dialog --}}
            <div class="relative w-full max-w-sm bg-white rounded-2xl border border-[#d4dfc8] shadow-2xl overflow-hidden"
                @click.stop>

                {{-- Header --}}
                <div class="px-6 pt-6 pb-4 text-center">
                    <div class="mx-auto w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-[#2d3a24]">{{ __('app.delete_confirm_title') ?? 'Delete User' }}</h3>
                    <p class="mt-1 text-sm text-[#7a8f6a]">
                        {{ __('app.delete_confirm') ?? 'Are you sure you want to delete' }}
                        <br>
                        <span class="font-medium text-[#2d3a24]" x-text="confirmDeleteName"></span>
                    </p>
                </div>

                {{-- Buttons --}}
                <div class="px-6 pb-6 flex gap-3">
                    <button type="button"
                        @click="confirmDeleteId = null"
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

    {{-- ================= CREATE / EDIT MODAL ================= --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/60 backdrop-blur-md transition-opacity duration-300" wire:click="closeModal"></div>

            {{-- Modal Content --}}
            <div class="relative w-full max-w-md bg-card rounded-2xl border border-border shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                {{-- Header --}}
                <div class="px-6 py-5 border-b border-border bg-muted/10 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <h3 class="font-bold text-foreground text-base tracking-tight">
                            {{ $editMode ? (__('app.edit_user') ?? 'Edit User') : (__('app.create_user') ?? 'Create New User') }}
                        </h3>
                    </div>
                    <button type="button" class="w-8 h-8 flex items-center justify-center rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition" wire:click="closeModal">✕</button>
                </div>

                <form wire:submit.prevent="save" class="flex flex-col overflow-hidden">
                    {{-- Body --}}
                    <div class="p-6 space-y-4 overflow-y-auto">
                        {{-- NAME --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                {{ __('app.name') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" wire:model="name"
                                class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            @error('name') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                        </div>

                        {{-- EMAIL --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                {{ __('app.email') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="email" wire:model="email"
                                class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            @error('email') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                        </div>

                        {{-- PHONE --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.phone_optional') ?? 'Phone (Optional)' }}</label>
                            <input type="text" wire:model="phone" placeholder="e.g. 08123456789"
                                class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            @error('phone') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                        </div>

                        {{-- COMPANY --}}
                        @if(!$editMode || $selectedCompanyId)
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                    Company <span class="text-red-500">*</span>
                                </label>
                                <select wire:model.live="selectedCompanyId"
                                    class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                    <option value="">Select Company</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->company_id }}">{{ $company->company_name }}</option>
                                    @endforeach
                                </select>
                                @error('selectedCompanyId') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        {{-- DEPARTMENT --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                Department <span class="text-muted-foreground text-xs normal-case">(Optional)</span>
                            </label>
                            <select wire:model="selectedDepartmentId"
                                class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                                @if(!$selectedCompanyId) disabled @endif>
                                <option value="">No Department (Unassigned)</option>
                                @foreach($this->availableDepartments as $dept)
                                    <option value="{{ $dept->department_id }}">{{ $dept->department_name }}</option>
                                @endforeach
                            </select>
                            @if(!$selectedCompanyId)
                                <p class="text-xs text-muted-foreground mt-1.5">Select a company first</p>
                            @endif
                            @error('selectedDepartmentId') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                        </div>

                        {{-- ROLE --}}
                        @if(!$editMode || $selectedRoleId)
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                    Role <span class="text-red-500">*</span>
                                </label>
                                <select wire:model="selectedRoleId"
                                    class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                    <option value="">Select Role</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->role_id }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                @error('selectedRoleId') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        {{-- PASSWORD --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">
                                {{ __('app.password') }} 
                                @if($editMode)
                                    <span class="text-muted-foreground text-xs normal-case">({{ __('app.optional') }} - leave blank to keep current)</span>
                                @else
                                    <span class="text-red-500">*</span>
                                @endif
                            </label>
                            <input type="password" wire:model="password"
                                placeholder="{{ $editMode ? 'Leave blank to keep current password' : 'Enter password' }}"
                                class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            @error('password') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                        </div>

                        {{-- STATUS --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('app.status') }}</label>
                            <select wire:model="status"
                                class="w-full h-10 px-3.5 rounded-lg border border-input bg-background text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                <option value="active">{{ __('app.active') }}</option>
                                <option value="inactive">{{ __('app.inactive') }}</option>
                            </select>
                            @error('status') <p class="text-xs text-destructive mt-1.5 font-medium">{{ $message }}</p> @enderror
                        </div>

                        {{-- Info box for create mode --}}
                        @if(!$editMode)
                            <div class="p-3 rounded-lg border border-input bg-muted/5">
                                <p class="text-xs text-muted-foreground">
                                    <svg class="w-3.5 h-3.5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    The user will be created with these credentials. Make sure to provide a secure password.
                                </p>
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="border-t border-border px-6 py-4 flex items-center justify-end gap-3 bg-muted/5 shrink-0">
                        <button type="button" wire:click="closeModal"
                            class="h-9 px-4 rounded-lg bg-secondary text-secondary-foreground text-xs font-semibold hover:bg-secondary/80 border border-border transition inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span>{{ __('app.cancel') }}</span>
                        </button>
                        <button type="submit"
                            wire:loading.attr="disabled"
                            class="h-9 px-4 rounded-lg bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/95 transition shadow-sm inline-flex items-center gap-1.5 disabled:opacity-60">
                            <svg wire:loading wire:target="save" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <svg wire:loading.remove wire:target="save" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>{{ $editMode ? __('app.update') : __('app.create') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <style>
        @keyframes loading-bar {
            0% {
                width: 0%;
            }
            50% {
                width: 70%;
            }
            100% {
                width: 100%;
            }
        }

        .animate-loading-bar {
            animation: loading-bar 1s ease-in-out infinite;
        }
    </style>
</div>
