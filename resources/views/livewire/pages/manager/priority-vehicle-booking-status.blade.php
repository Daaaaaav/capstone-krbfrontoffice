<div class="min-h-screen bg-[#f5f7f2]">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        <x-page-header
            title="Priority Vehicle Booking Status"
            subtitle="View and manage pending and approved priority vehicle bookings">
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
                <button wire:click="$set('statusFilter', 'pending')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition
                        {{ $statusFilter === 'pending' ? 'bg-amber-100 text-amber-700 border border-amber-300' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200' }}">
                    Pending
                </button>
                <button wire:click="$set('statusFilter', 'approved')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition
                        {{ $statusFilter === 'approved' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200' }}">
                    Approved
                </button>
                <button wire:click="$set('statusFilter', 'all')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition
                        {{ $statusFilter === 'all' ? 'bg-blue-100 text-blue-700 border border-blue-300' : 'bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200' }}">
                    All Status
                </button>
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
                                            'pending_receipt' => 'bg-amber-100 text-amber-700',
                                            'pending_cancellation' => 'bg-orange-100 text-orange-700',
                                            'approved' => 'bg-green-100 text-green-700',
                                            default => 'bg-gray-100 text-gray-700',
                                        };
                                    @endphp
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $color }}">
                                        {{ $booking->statusLabel() }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <button wire:click="openDetail({{ $booking->id }})"
                                            class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                            View
                                        </button>
                                        @if($booking->isActionable())
                                            <button wire:click="openApprove({{ $booking->id }})"
                                                class="text-green-600 hover:text-green-800 text-xs font-medium">
                                                Approve
                                            </button>
                                            <button wire:click="openReject({{ $booking->id }})"
                                                class="text-red-600 hover:text-red-800 text-xs font-medium">
                                                Reject
                                            </button>
                                        @elseif(in_array($booking->status, ['approved', 'on_progress']) && !$booking->return_photo)
                                            <button wire:click="openDone({{ $booking->id }})"
                                                class="text-purple-600 hover:text-purple-800 text-xs font-medium">
                                                Mark Done
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="text-[#9aaa8a]">
                                        <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                                        </svg>
                                        <p class="text-sm font-medium">No priority vehicle bookings found</p>
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
                    @if($detailBooking->cancels_booking_id)
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <p class="text-xs font-semibold text-amber-700 uppercase">Conflicts With</p>
                            <p class="text-sm text-amber-900">Regular booking #{{ $detailBooking->cancels_booking_id }}</p>
                        </div>
                    @endif
                    @if($detailBooking->rejection_reason)
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <p class="text-xs font-semibold text-red-700 uppercase">Rejection Reason</p>
                            <p class="text-sm text-red-900">{{ $detailBooking->rejection_reason }}</p>
                        </div>
                    @endif
                    
                    {{-- Photo Evidence Display --}}
                    @if($detailBooking->handover_photo || $detailBooking->return_photo)
                        <div>
                            <label class="text-xs font-semibold text-[#7a8f6a] uppercase mb-2 block">Photo Evidence</label>
                            <div class="flex flex-wrap gap-3">
                                @if($detailBooking->handover_photo && Storage::disk('public')->exists($detailBooking->handover_photo))
                                    <button type="button"
                                        @click="$dispatch('open-lightbox', { src: '{{ asset('storage/' . $detailBooking->handover_photo) }}' })"
                                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 text-xs font-semibold transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        Handover Photo
                                    </button>
                                @endif
                                @if($detailBooking->return_photo && Storage::disk('public')->exists($detailBooking->return_photo))
                                    <button type="button"
                                        @click="$dispatch('open-lightbox', { src: '{{ asset('storage/' . $detailBooking->return_photo) }}' })"
                                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 text-xs font-semibold transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        Return Photo
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- APPROVE MODAL WITH CAMERA --}}
    @if($showApproveModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            wire:key="approve-modal-container"
            x-data="{
                show: @entangle('showApproveModal').live,
                stream: null,
                devices: [],
                selectedDeviceId: null,
                async init() {
                    if (this.show) {
                        await this.startCamera();
                    }
                },
                async startCamera() {
                    try {
                        const devices = await navigator.mediaDevices.enumerateDevices();
                        this.devices = devices.filter(d => d.kind === 'videoinput');
                        
                        const constraints = {
                            video: this.selectedDeviceId 
                                ? { deviceId: { exact: this.selectedDeviceId } }
                                : { facingMode: 'environment' }
                        };
                        
                        this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                        this.$refs.video.srcObject = this.stream;
                    } catch (err) {
                        console.error('Camera error:', err);
                        alert('Unable to access camera. Please check permissions.');
                    }
                },
                async switchCamera() {
                    if (this.devices.length <= 1) return;
                    
                    const currentIndex = this.devices.findIndex(d => d.deviceId === this.selectedDeviceId);
                    const nextIndex = (currentIndex + 1) % this.devices.length;
                    this.selectedDeviceId = this.devices[nextIndex].deviceId;
                    
                    this.stopCamera();
                    await this.startCamera();
                },
                stopCamera() {
                    if (this.stream) {
                        this.stream.getTracks().forEach(track => track.stop());
                        this.stream = null;
                    }
                },
                capturePhoto() {
                    const video = this.$refs.video;
                    const canvas = this.$refs.canvas;
                    canvas.width = video.videoWidth || 640;
                    canvas.height = video.videoHeight || 480;
                    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                    $wire.set('photoData', canvas.toDataURL('image/png'));
                },
                handleFile(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        $wire.set('photoData', ev.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            }"
            x-init="$watch('show', value => { if (value) startCamera(); else stopCamera(); })"
            @click.self="$wire.closeApprove(); stopCamera()">
            
            <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10">
                    <div>
                        <h3 class="text-lg font-bold text-[#2d3a24]">Approve Priority Vehicle Booking</h3>
                        <p class="text-xs text-[#9aaa8a] mt-0.5">Capture handover photo evidence</p>
                    </div>
                    <button @click="$wire.closeApprove(); stopCamera()" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4">
                    
                    {{-- Camera Viewport --}}
                    <div x-show="!$wire.photoData" class="relative bg-gray-900 rounded-2xl overflow-hidden shadow-inner flex items-center justify-center aspect-[4/3] w-full">
                        <video x-ref="video" autoplay playsinline class="w-full h-full object-cover"></video>
                        <canvas x-ref="canvas" style="display: none;"></canvas>
                        
                        {{-- Camera controls overlay --}}
                        <div class="absolute bottom-4 left-0 right-0 flex justify-center items-center gap-4">
                            <button type="button" @click="capturePhoto()" 
                                class="w-16 h-16 rounded-full bg-white border-4 border-gray-300 hover:border-gray-400 transition shadow-lg">
                            </button>
                            <button type="button" @click="switchCamera()" x-show="devices.length > 1"
                                class="w-12 h-12 rounded-full bg-black/60 text-white hover:bg-black/80 backdrop-blur-md transition shadow-lg border border-white/10 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </button>
                        </div>
                    </div>
                    
                    {{-- Preview --}}
                    <div x-show="$wire.photoData" style="display: none;" class="relative bg-gray-900 rounded-2xl overflow-hidden shadow-inner flex items-center justify-center aspect-[4/3] w-full">
                        <img :src="$wire.photoData" class="w-full h-full object-cover" />
                        <button type="button" @click="$wire.set('photoData', null)" class="absolute top-3 right-3 px-4 py-2 text-xs font-semibold rounded-full bg-black/60 text-white hover:bg-black/80 backdrop-blur-md transition inline-flex items-center gap-1.5 shadow-lg border border-white/10">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Retake
                        </button>
                    </div>

                    {{-- Or Upload --}}
                    <div class="text-center">
                        <label class="inline-flex items-center gap-2 px-4 py-2 text-xs font-medium text-[#5a6e4a] bg-gray-100 rounded-lg hover:bg-gray-200 cursor-pointer transition border border-gray-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Or Upload Photo
                            <input type="file" accept="image/*" @change="handleFile" class="hidden">
                        </label>
                    </div>

                    @error('photoData')
                        <p class="text-xs text-red-600 text-center">{{ $message }}</p>
                    @enderror
                </div>

                <div class="px-6 py-4 border-t border-gray-200 flex gap-3 justify-end bg-gray-50">
                    <button type="button" @click="$wire.closeApprove(); stopCamera()"
                        class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmApprove" :disabled="!$wire.photoData"
                        class="px-5 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
                        Approve & Start
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showRejectModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            x-data
            @click.self="$wire.closeReject()">
            <div class="bg-white rounded-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-[#2d3a24]">Reject Booking</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <p class="text-sm text-[#5a6e4a]">Please provide a reason for rejection:</p>
                    <textarea wire:model="rejectReason"
                        placeholder="Enter rejection reason..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:outline-none"
                        rows="4"></textarea>
                    @error('rejectReason')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="px-6 py-4 border-t border-gray-200 flex gap-3 justify-end">
                    <button wire:click="closeReject"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                        Cancel
                    </button>
                    <button wire:click="confirmReject"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">
                        Reject
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MARK DONE MODAL WITH CAMERA --}}
    @if($showDoneModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            wire:key="done-modal-container"
            x-data="{
                show: @entangle('showDoneModal').live,
                stream: null,
                devices: [],
                selectedDeviceId: null,
                async init() {
                    if (this.show) {
                        await this.startCamera();
                    }
                },
                async startCamera() {
                    try {
                        const devices = await navigator.mediaDevices.enumerateDevices();
                        this.devices = devices.filter(d => d.kind === 'videoinput');
                        
                        const constraints = {
                            video: this.selectedDeviceId 
                                ? { deviceId: { exact: this.selectedDeviceId } }
                                : { facingMode: 'environment' }
                        };
                        
                        this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                        this.$refs.video.srcObject = this.stream;
                    } catch (err) {
                        console.error('Camera error:', err);
                        alert('Unable to access camera. Please check permissions.');
                    }
                },
                async switchCamera() {
                    if (this.devices.length <= 1) return;
                    
                    const currentIndex = this.devices.findIndex(d => d.deviceId === this.selectedDeviceId);
                    const nextIndex = (currentIndex + 1) % this.devices.length;
                    this.selectedDeviceId = this.devices[nextIndex].deviceId;
                    
                    this.stopCamera();
                    await this.startCamera();
                },
                stopCamera() {
                    if (this.stream) {
                        this.stream.getTracks().forEach(track => track.stop());
                        this.stream = null;
                    }
                },
                capturePhoto() {
                    const video = this.$refs.video;
                    const canvas = this.$refs.canvas;
                    canvas.width = video.videoWidth || 640;
                    canvas.height = video.videoHeight || 480;
                    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                    $wire.set('donePhotoData', canvas.toDataURL('image/png'));
                },
                handleFile(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        $wire.set('donePhotoData', ev.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            }"
            x-init="$watch('show', value => { if (value) startCamera(); else stopCamera(); })"
            @click.self="$wire.closeDone(); stopCamera()">
            
            <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10">
                    <div>
                        <h3 class="text-lg font-bold text-[#2d3a24]">Mark Booking as Done</h3>
                        <p class="text-xs text-[#9aaa8a] mt-0.5">Capture return photo evidence</p>
                    </div>
                    <button @click="$wire.closeDone(); stopCamera()" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-5 space-y-4">
                    
                    {{-- Camera Viewport --}}
                    <div x-show="!$wire.donePhotoData" class="relative bg-gray-900 rounded-2xl overflow-hidden shadow-inner flex items-center justify-center aspect-[4/3] w-full">
                        <video x-ref="video" autoplay playsinline class="w-full h-full object-cover"></video>
                        <canvas x-ref="canvas" style="display: none;"></canvas>
                        
                        {{-- Camera controls overlay --}}
                        <div class="absolute bottom-4 left-0 right-0 flex justify-center items-center gap-4">
                            <button type="button" @click="capturePhoto()" 
                                class="w-16 h-16 rounded-full bg-white border-4 border-gray-300 hover:border-gray-400 transition shadow-lg">
                            </button>
                            <button type="button" @click="switchCamera()" x-show="devices.length > 1"
                                class="w-12 h-12 rounded-full bg-black/60 text-white hover:bg-black/80 backdrop-blur-md transition shadow-lg border border-white/10 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </button>
                        </div>
                    </div>
                    
                    {{-- Preview --}}
                    <div x-show="$wire.donePhotoData" style="display: none;" class="relative bg-gray-900 rounded-2xl overflow-hidden shadow-inner flex items-center justify-center aspect-[4/3] w-full">
                        <img :src="$wire.donePhotoData" class="w-full h-full object-cover" />
                        <button type="button" @click="$wire.set('donePhotoData', null)" class="absolute top-3 right-3 px-4 py-2 text-xs font-semibold rounded-full bg-black/60 text-white hover:bg-black/80 backdrop-blur-md transition inline-flex items-center gap-1.5 shadow-lg border border-white/10">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Retake
                        </button>
                    </div>

                    {{-- Or Upload --}}
                    <div class="text-center">
                        <label class="inline-flex items-center gap-2 px-4 py-2 text-xs font-medium text-[#5a6e4a] bg-gray-100 rounded-lg hover:bg-gray-200 cursor-pointer transition border border-gray-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Or Upload Photo
                            <input type="file" accept="image/*" @change="handleFile" class="hidden">
                        </label>
                    </div>

                    @error('donePhotoData')
                        <p class="text-xs text-red-600 text-center">{{ $message }}</p>
                    @enderror
                </div>

                <div class="px-6 py-4 border-t border-gray-200 flex gap-3 justify-end bg-gray-50">
                    <button type="button" @click="$wire.closeDone(); stopCamera()"
                        class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmDone" :disabled="!$wire.donePhotoData"
                        class="px-5 py-2.5 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
                        Mark as Done
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
