<div class="min-h-screen bg-[#f5f7f2]">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        <x-page-header
            title="Priority Vehicle Booking Status"
            subtitle="View and manage pending and approved priority vehicle bookings">
        </x-page-header>

        {{-- SEARCH BAR --}}
        <div class="bg-white border border-[#d4dfc8] rounded-2xl p-4 shadow-sm">
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
        </div>

        {{-- PENDING BOOKINGS --}}
        @if($pendingBookings->isNotEmpty())
        <div class="bg-white border border-amber-300 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-amber-50 border-b border-amber-200">
                <h3 class="text-base font-bold text-amber-900">Pending Approval ({{ $pendingBookings->count() }})</h3>
                <p class="text-xs text-amber-700 mt-0.5">Requires your action to approve or reject</p>
            </div>
            <div class="divide-y divide-amber-100">
                @foreach($pendingBookings as $booking)
                <div class="px-6 py-4 hover:bg-amber-50/50 transition">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center text-amber-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-[#2d3a24]">{{ $booking->purpose }}</p>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[#5a6e4a] mt-1">
                                        <span>{{ $booking->vehicle->name ?? 'N/A' }}{{ $booking->vehicle->plate_number ? ' — ' . $booking->vehicle->plate_number : '' }}</span>
                                        <span>Borrower: {{ $booking->borrower_name }}</span>
                                        <span>{{ \Carbon\Carbon::parse($booking->start_at)->format('d M Y, H:i') }}</span>
                                    </div>
                                    @if($booking->status === 'pending_cancellation' && $booking->cancels_booking_id)
                                        <div class="mt-2 text-xs bg-orange-100 text-orange-700 px-2 py-1 rounded inline-block">
                                            ⚠ Conflicts with regular booking #{{ $booking->cancels_booking_id }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button wire:click="openDetail({{ $booking->id }})" class="px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition">View</button>
                            <button wire:click="openApprove({{ $booking->id }})" class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition">Approve</button>
                            <button wire:click="openReject({{ $booking->id }})" class="px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition">Reject</button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- APPROVED / UPCOMING BOOKINGS --}}
        @if($approvedBookings->isNotEmpty())
        <div class="bg-white border border-green-300 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-green-50 border-b border-green-200">
                <h3 class="text-base font-bold text-green-900">Approved / Upcoming ({{ $approvedBookings->count() }})</h3>
                <p class="text-xs text-green-700 mt-0.5">Scheduled to start soon</p>
            </div>
            <div class="divide-y divide-green-100">
                @foreach($approvedBookings as $booking)
                <div class="px-6 py-4 hover:bg-green-50/50 transition">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center text-green-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-[#2d3a24]">{{ $booking->purpose }}</p>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[#5a6e4a] mt-1">
                                        <span>{{ $booking->vehicle->name ?? 'N/A' }}{{ $booking->vehicle->plate_number ? ' — ' . $booking->vehicle->plate_number : '' }}</span>
                                        <span>Borrower: {{ $booking->borrower_name }}</span>
                                        <span>Starts: {{ \Carbon\Carbon::parse($booking->start_at)->format('d M Y, H:i') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button wire:click="openDetail({{ $booking->id }})" class="px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition">View Details</button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ON THE ROAD (ON_PROGRESS + LATE_RETURN) BOOKINGS --}}
        @if($onProgressBookings->isNotEmpty())
        <div class="bg-white border border-blue-300 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-blue-50 border-b border-blue-200">
                <h3 class="text-base font-bold text-blue-900">On the Road ({{ $onProgressBookings->count() }})</h3>
                <p class="text-xs text-blue-700 mt-0.5">Vehicles currently in use</p>
            </div>
            <div class="divide-y divide-blue-100">
                @foreach($onProgressBookings as $booking)
                @php $isLate = $booking->status === \App\Models\PriorityVehicleBooking::STATUS_LATE_RETURN; @endphp
                <div class="px-6 py-4 hover:bg-blue-50/50 transition {{ $isLate ? 'border-l-4 border-red-400' : '' }}">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center
                                    {{ $isLate ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="font-semibold text-[#2d3a24]">{{ $booking->purpose }}</p>
                                        @if($isLate)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-700 rounded-full">
                                                ⚠ Late Return
                                                @php $dur = $this->overdueDuration($booking); @endphp
                                                @if($dur) · {{ $dur }} overdue @endif
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[#5a6e4a] mt-1">
                                        <span>{{ $booking->vehicle->name ?? 'N/A' }}{{ $booking->vehicle->plate_number ? ' — ' . $booking->vehicle->plate_number : '' }}</span>
                                        <span>Borrower: {{ $booking->borrower_name }}</span>
                                        <span>Until: {{ \Carbon\Carbon::parse($booking->end_at)->format('d M Y, H:i') }}</span>
                                    </div>
                                    @if($booking->destination)
                                        <div class="mt-1 text-xs text-[#9aaa8a]">
                                            → {{ $booking->destination }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button wire:click="openDetail({{ $booking->id }})" class="px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition">View</button>
                            <button wire:click="openDone({{ $booking->id }})"
                                class="px-3 py-1.5 text-xs font-medium text-white rounded-lg transition
                                    {{ $isLate ? 'bg-red-600 hover:bg-red-700' : 'bg-purple-600 hover:bg-purple-700' }}">
                                Mark Done
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- EMPTY STATE --}}
        @if($pendingBookings->isEmpty() && $approvedBookings->isEmpty() && $onProgressBookings->isEmpty())
        <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm p-12">
            <div class="text-center text-[#9aaa8a]">
                <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/>
                </svg>
                <p class="text-base font-medium text-[#5a6e4a]">No Priority Vehicle Bookings</p>
                <p class="text-sm mt-1">{{ $q ? 'Try adjusting your search' : 'Priority vehicle bookings will appear here' }}</p>
            </div>
        </div>
        @endif

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
                photoData: @entangle('donePhotoData').live,
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
                    this.photoData = canvas.toDataURL('image/png');
                },
                handleFile(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        this.photoData = ev.target.result;
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
                    <div x-show="!photoData" class="relative bg-gray-900 rounded-2xl overflow-hidden shadow-inner flex items-center justify-center aspect-[4/3] w-full">
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
                    <div x-show="photoData" style="display: none;" class="relative bg-gray-900 rounded-2xl overflow-hidden shadow-inner flex items-center justify-center aspect-[4/3] w-full">
                        <img :src="photoData" class="w-full h-full object-cover" />
                        <button type="button" @click="photoData = null" class="absolute top-3 right-3 px-4 py-2 text-xs font-semibold rounded-full bg-black/60 text-white hover:bg-black/80 backdrop-blur-md transition inline-flex items-center gap-1.5 shadow-lg border border-white/10">
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
                    <button type="button" wire:click="confirmDone" :disabled="!photoData"
                        class="px-5 py-2.5 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
                        Mark as Done
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
