<div class="min-h-screen bg-[#f5f7f2]">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        <x-page-header
            title="Priority Vehicle Booking History"
            subtitle="View completed and rejected priority vehicle bookings">
        </x-page-header>

        <div class="bg-white border border-[#d4dfc8] rounded-2xl p-4 shadow-sm space-y-4">
            <div class="relative flex items-center">
                <div class="absolute left-3 text-[#9aaa8a]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35m1.6-5.65a7.25 7.25 0 11-14.5 0 7.25 7.25 0 0114.5 0z" />
                    </svg>
                </div>
                <input type="text"
                    wire:model.live.debounce.500ms="q"
                    placeholder="Search by purpose, borrower, vehicle, or manager..."
                    class="w-full pl-10 pr-10 py-2 rounded-lg border border-[#c4d4b4] text-[#2d3a24] placeholder-[#9aaa8a]
                           focus:ring-2 focus:ring-[#4E653D] focus:outline-none transition">
                @if($q)
                    <button wire:click="$set('q', '')"
                        class="absolute right-3 text-[#9aaa8a] hover:text-[#4E653D] transition">✕</button>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                <button wire:click="$set('statusFilter', 'all')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition
                        {{ $statusFilter === 'all' ? 'bg-blue-100 text-blue-700 border border-blue-300' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200' }}">
                    All
                </button>
                <button wire:click="$set('statusFilter', 'approved')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition
                        {{ $statusFilter === 'approved' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200' }}">
                    Approved
                </button>
                <button wire:click="$set('statusFilter', 'rejected')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition
                        {{ $statusFilter === 'rejected' ? 'bg-red-100 text-red-700 border border-red-300' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200' }}">
                    Rejected
                </button>

                <div class="border-l border-gray-300 mx-1"></div>

                <button wire:click="$set('dateMode', 'recent')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition
                        {{ $dateMode === 'recent' ? 'bg-purple-100 text-purple-700 border border-purple-300' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200' }}">
                    Most Recent
                </button>
                <button wire:click="$set('dateMode', 'oldest')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition
                        {{ $dateMode === 'oldest' ? 'bg-purple-100 text-purple-700 border border-purple-300' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200' }}">
                    Oldest First
                </button>

                <input type="date"
                    wire:model.live="selectedDate"
                    class="px-4 py-2 text-sm rounded-lg border border-gray-300 focus:ring-2 focus:ring-[#4E653D] focus:outline-none">
                @if($selectedDate)
                    <button wire:click="$set('selectedDate', null)"
                        class="px-3 py-2 text-sm text-gray-600 hover:text-red-600">
                        Clear Date
                    </button>
                @endif
            </div>
        </div>

        <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[800px]">
                    <thead class="bg-[#f0f4eb] text-[#7a8f6a] uppercase text-xs border-b">
                        <tr>
                            <th class="px-6 py-3 text-left">Purpose</th>
                            <th class="px-6 py-3 text-left">Vehicle</th>
                            <th class="px-6 py-3 text-left">Borrower</th>
                            <th class="px-6 py-3 text-left">Period</th>
                            <th class="px-6 py-3 text-left">Manager</th>
                            <th class="px-6 py-3 text-left">Status</th>
                            <th class="px-6 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#d4dfc8]">
                        @forelse($bookings as $booking)
                            <tr class="hover:bg-[#f0f4eb] transition">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-[#2d3a24]">{{ $booking->purpose }}</div>
                                    @if($booking->destination)
                                        <div class="text-xs text-[#9aaa8a]">To: {{ $booking->destination }}</div>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-[#5a6e4a]">
                                    {{ $booking->vehicle->name ?? 'N/A' }}
                                    @if($booking->vehicle->plate_number)
                                        <div class="text-xs text-[#9aaa8a]">{{ $booking->vehicle->plate_number }}</div>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-[#5a6e4a]">
                                    {{ $booking->borrower_name }}
                                </td>

                                <td class="px-6 py-4 text-[#5a6e4a]">
                                    <div>{{ \Carbon\Carbon::parse($booking->start_at)->format('d M Y H:i') }}</div>
                                    <div class="text-xs text-[#9aaa8a]">
                                        to {{ \Carbon\Carbon::parse($booking->end_at)->format('d M Y H:i') }}
                                    </div>
                                </td>

                                <td class="px-6 py-4 text-[#5a6e4a]">
                                    {{ $booking->manager->full_name ?? $booking->manager->name ?? 'N/A' }}
                                </td>

                                <td class="px-6 py-4">
                                    @php
                                        $color = match($booking->status) {
                                            'approved' => 'bg-green-100 text-green-700',
                                            'rejected', 'cancelled_conflict_denied' => 'bg-red-100 text-red-700',
                                            default => 'bg-gray-100 text-gray-700',
                                        };
                                    @endphp
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $color }}">
                                        {{ $booking->statusLabel() }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <button wire:click="openDetail({{ $booking->id }})"
                                        class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="text-[#9aaa8a]">
                                        <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <p class="text-sm font-medium">No priority vehicle booking history found</p>
                                        <p class="text-xs mt-1">Try adjusting your filters</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($bookings->hasPages())
                <div class="px-6 py-4 border-t border-[#d4dfc8]">
                    {{ $bookings->links() }}
                </div>
            @endif
        </div>

    </main>

    {{-- DETAIL MODAL --}}
    @if($showDetailModal && $detailBooking)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            x-data
            @click.self="$wire.closeDetail()">
            <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-[#2d3a24]">Priority Vehicle Booking Details</h3>
                    <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="text-xs font-semibold text-[#7a8f6a] uppercase">Purpose</label>
                        <p class="text-sm text-[#2d3a24]">{{ $detailBooking->purpose }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-semibold text-[#7a8f6a] uppercase">Vehicle</label>
                            <p class="text-sm text-[#2d3a24]">{{ $detailBooking->vehicle->name ?? 'N/A' }}</p>
                            @if($detailBooking->vehicle->plate_number)
                                <p class="text-xs text-[#9aaa8a]">{{ $detailBooking->vehicle->plate_number }}</p>
                            @endif
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-[#7a8f6a] uppercase">Borrower</label>
                            <p class="text-sm text-[#2d3a24]">{{ $detailBooking->borrower_name }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-semibold text-[#7a8f6a] uppercase">Start</label>
                            <p class="text-sm text-[#2d3a24]">{{ \Carbon\Carbon::parse($detailBooking->start_at)->format('d M Y, H:i') }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-[#7a8f6a] uppercase">End</label>
                            <p class="text-sm text-[#2d3a24]">{{ \Carbon\Carbon::parse($detailBooking->end_at)->format('d M Y, H:i') }}</p>
                        </div>
                    </div>
                    @if($detailBooking->destination)
                        <div>
                            <label class="text-xs font-semibold text-[#7a8f6a] uppercase">Destination</label>
                            <p class="text-sm text-[#2d3a24]">{{ $detailBooking->destination }}</p>
                        </div>
                    @endif
                    <div>
                        <label class="text-xs font-semibold text-[#7a8f6a] uppercase">Requested By</label>
                        <p class="text-sm text-[#2d3a24]">{{ $detailBooking->manager->full_name ?? $detailBooking->manager->name ?? 'N/A' }}</p>
                    </div>
                    @if($detailBooking->special_notes)
                        <div>
                            <label class="text-xs font-semibold text-[#7a8f6a] uppercase">Special Notes</label>
                            <p class="text-sm text-[#2d3a24]">{{ $detailBooking->special_notes }}</p>
                        </div>
                    @endif
                    <div>
                        <label class="text-xs font-semibold text-[#7a8f6a] uppercase">Status</label>
                        <p class="text-sm text-[#2d3a24]">{{ $detailBooking->statusLabel() }}</p>
                    </div>
                    @if($detailBooking->handledBy)
                        <div>
                            <label class="text-xs font-semibold text-[#7a8f6a] uppercase">Handled By</label>
                            <p class="text-sm text-[#2d3a24]">{{ $detailBooking->handledBy->full_name ?? $detailBooking->handledBy->name ?? 'N/A' }}</p>
                        </div>
                    @endif
                    @if($detailBooking->cancels_booking_id)
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <p class="text-xs font-semibold text-amber-700 uppercase">Conflicted With</p>
                            <p class="text-sm text-amber-900">Regular booking #{{ $detailBooking->cancels_booking_id }}</p>
                        </div>
                    @endif
                    @if($detailBooking->rejection_reason)
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <p class="text-xs font-semibold text-red-700 uppercase">Rejection Reason</p>
                            <p class="text-sm text-red-900">{{ $detailBooking->rejection_reason }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

</div>
