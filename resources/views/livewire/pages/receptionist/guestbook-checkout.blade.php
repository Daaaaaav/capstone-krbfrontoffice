<div class="min-h-screen bg-gray-50" style="overflow-y: auto; -webkit-overflow-scrolling: touch; touch-action: pan-y;">
    @php
        $card = 'bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden';
    @endphp

    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- Hero Banner --}}
        <div class="relative overflow-hidden rounded-2xl bg-[#4A2F24] text-[#CDDEA7] shadow-2xl">
            <div class="pointer-events-none absolute inset-0 opacity-10">
                <div class="absolute top-0 -right-4 w-24 h-24 bg-[#CDDEA7] rounded-full blur-xl"></div>
                <div class="absolute bottom-0 -left-4 w-16 h-16 bg-[#CDDEA7] rounded-full blur-lg"></div>
            </div>
            <div class="relative z-10 p-6 sm:p-8">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-[#CDDEA7]/10 rounded-xl flex items-center justify-center backdrop-blur-sm border border-[#CDDEA7]/20">
                            <x-heroicon-o-qr-code class="w-6 h-6 text-[#CDDEA7]"/>
                        </div>
                        <div>
                            <h2 class="text-lg sm:text-xl font-semibold">Checkout Scanner</h2>
                            <p class="text-xs text-[#CDDEA7]/75 mt-0.5">Scan QR code pengunjung untuk checkout</p>
                        </div>
                    </div>
                    <a href="{{ route('receptionist.guestbookstatus') }}"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-[#CDDEA7]/10 border border-[#CDDEA7]/20 text-xs font-semibold text-[#CDDEA7] hover:bg-[#CDDEA7]/20 transition">
                        <x-heroicon-o-arrow-left class="w-3.5 h-3.5"/>
                        Kembali
                    </a>
                </div>
            </div>
        </div>

        {{-- Guest Info Card --}}
        <div class="{{ $card }}">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50/50 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#4A2F24] flex items-center justify-center text-white font-bold text-sm">
                    {{ strtoupper(substr($guestName ?? 'G', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-semibold text-gray-900 truncate">{{ $guestName }}</h3>
                    @if($instansi)
                        <p class="text-xs text-gray-500 truncate">{{ $instansi }}</p>
                    @endif
                </div>
                <div class="text-right shrink-0">
                    <p class="text-xs text-gray-500">{{ $date }}</p>
                    <p class="text-xs font-semibold text-[#4E653D]">Masuk: {{ $jamIn }}</p>
                </div>
            </div>
            @if($keperluan)
                <div class="px-6 py-2.5 border-b border-gray-100 text-xs text-gray-600">
                    <span class="text-gray-400">Keperluan:</span>
                    <span class="font-medium text-gray-800 ml-1">{{ $keperluan }}</span>
                </div>
            @endif
        </div>

        {{-- Scanner Area --}}
        <div
            wire:ignore
            x-data="checkoutScanner()"
            x-init="init()"
            class="space-y-5"
        >
            {{-- Progress Bar --}}
            <div class="{{ $card }} p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-user-group class="w-4.5 h-4.5 text-[#4E653D]"/>
                        <span class="text-sm font-semibold text-gray-900">Progress Checkout</span>
                    </div>
                    <span class="text-sm font-bold text-[#4A2F24]">
                        <span x-text="scannedCount"></span> / <span x-text="totalCount"></span>
                    </span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-5 overflow-hidden border border-gray-200 shadow-inner">
                    <div class="h-5 rounded-full transition-all duration-500 ease-out flex items-center justify-end pr-2 min-w-[2rem] bg-[#4E653D]"
                         :style="'width: ' + Math.max(progressPercent, 8) + '%'">
                        <span class="text-[10px] font-bold text-white leading-none tracking-wide" x-text="progressPercent + '%'"></span>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2 text-center" x-show="!allDone">
                    Arahkan kamera ke QR code pengunjung untuk checkout
                </p>
                <div x-show="allDone" class="mt-3 text-center" style="display: none;">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-[#4E653D]/10 border border-[#4E653D]/20 rounded-xl">
                        <svg class="w-4.5 h-4.5 text-[#4E653D]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-sm font-bold text-[#4E653D]">Semua pengunjung sudah checkout!</span>
                    </div>
                </div>
            </div>

            {{-- Camera + Feedback --}}
            <div class="{{ $card }}" x-show="!allDone">
                {{-- Card Header --}}
                <div class="px-5 py-3.5 border-b border-gray-200 bg-gray-50/50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-[#4E653D]/10 flex items-center justify-center border border-[#4E653D]/20">
                                <x-heroicon-o-camera class="w-4.5 h-4.5 text-[#4E653D]"/>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">QR Scanner</h3>
                                <p class="text-[11px] text-gray-500 mt-0.5">Kamera akan otomatis mendeteksi QR code</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            {{-- Camera switch button - always visible when cameras exist --}}
                            <button x-show="cameras.length > 0"
                                    @click="showCameraSelect = !showCameraSelect"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold transition border"
                                    :class="showCameraSelect ? 'bg-[#4E653D] text-white border-[#4E653D]' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Ganti
                            </button>
                            <span class="relative flex h-3 w-3" x-show="scanning">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#CDDEA7] opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-[#4E653D]"></span>
                            </span>
                            <span class="text-xs font-medium" :class="scanning ? 'text-[#4E653D]' : 'text-gray-400'" x-text="scanning ? 'Aktif' : 'Mati'"></span>
                        </div>
                    </div>

                    {{-- Camera Selector Dropdown --}}
                    <div class="mt-3" x-show="showCameraSelect && cameras.length > 0" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <select x-model="selectedCameraId"
                                    class="flex-1 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#4E653D]/30 focus:border-[#4E653D] cursor-pointer appearance-none"
                                    style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236b7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27M6 8l4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 8px center; background-repeat: no-repeat; background-size: 16px; padding-right: 28px;">
                                <template x-for="cam in cameras" :key="cam.deviceId">
                                    <option :value="cam.deviceId" x-text="cam.label || ('Camera ' + (cameras.indexOf(cam) + 1))" :selected="cam.deviceId === selectedCameraId"></option>
                                </template>
                            </select>
                            <button @click="showCameraSelect = false; switchCamera()"
                                    class="shrink-0 px-3 py-2 bg-[#4E653D] text-white rounded-lg text-xs font-semibold hover:bg-[#3d5130] transition">
                                Terapkan
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Camera Viewport --}}
                <div class="relative bg-black" style="touch-action: none;">
                    {{-- Video element - single clean camera feed --}}
                    <video x-ref="cameraVideo"
                           playsinline muted
                           class="w-full block"
                           style="aspect-ratio: 4/3; object-fit: cover;"></video>

                    {{-- Hidden canvas for QR decoding --}}
                    <canvas x-ref="scanCanvas" class="hidden"></canvas>

                    {{-- Scan Viewfinder Overlay --}}
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none" x-show="scanning">
                        {{-- Dim border around the scan area --}}
                        <div class="absolute inset-0 bg-black/30"></div>

                        {{-- Clear window in center - responsive size --}}
                        <div x-ref="viewfinder" class="relative qr-viewfinder">
                            {{-- Transparent center cutout --}}
                            <div class="absolute inset-0 rounded-2xl" style="box-shadow: 0 0 0 2000px rgba(0,0,0,0.35);"></div>

                            {{-- Corner brackets --}}
                            <div class="absolute top-0 left-0 w-8 h-8 border-t-[3px] border-l-[3px] border-[#CDDEA7] rounded-tl-xl"></div>
                            <div class="absolute top-0 right-0 w-8 h-8 border-t-[3px] border-r-[3px] border-[#CDDEA7] rounded-tr-xl"></div>
                            <div class="absolute bottom-0 left-0 w-8 h-8 border-b-[3px] border-l-[3px] border-[#CDDEA7] rounded-bl-xl"></div>
                            <div class="absolute bottom-0 right-0 w-8 h-8 border-b-[3px] border-r-[3px] border-[#CDDEA7] rounded-br-xl"></div>

                            {{-- Animated scan line --}}
                            <div class="absolute left-2 right-2 h-0.5 bg-gradient-to-r from-transparent via-[#CDDEA7] to-transparent animate-scan-line"></div>

                            {{-- Hint text --}}
                            <div class="absolute -bottom-8 left-0 right-0 text-center">
                                <span class="text-[11px] text-white/70 font-medium">Posisikan QR di dalam kotak</span>
                            </div>
                        </div>
                    </div>

                    {{-- Scan Feedback Overlay --}}
                    <div x-show="showFeedback"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                         class="absolute inset-0 flex items-center justify-center pointer-events-none z-20"
                         style="display: none;">
                        <div class="rounded-xl px-6 py-4 shadow-[0_8px_30px_rgb(0,0,0,0.12)] backdrop-blur-md border border-white/20 max-w-xs text-center transform transition-all"
                             :class="feedbackSuccess
                                 ? 'bg-[#4E653D]/95 text-white'
                                 : 'bg-rose-600/95 text-white'">
                            <div class="flex items-center justify-center gap-2 mb-1.5">
                                <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                                    <svg x-show="feedbackSuccess" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <svg x-show="!feedbackSuccess" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </div>
                                <span class="text-base font-bold tracking-wide" x-text="feedbackTitle"></span>
                            </div>
                            <p class="text-[13px] font-medium opacity-90 leading-relaxed" x-text="feedbackMessage"></p>
                        </div>
                    </div>

                    {{-- Border flash on scan --}}
                    <div x-show="showFeedback"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-500"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="absolute inset-0 pointer-events-none z-10 rounded-none border-[6px]"
                         :class="feedbackSuccess ? 'border-[#4E653D]/80' : 'border-rose-500/80'"
                         style="display: none;">
                    </div>
                </div>

                {{-- Camera Error with Retry --}}
                <div x-show="cameraError" class="px-6 py-5 bg-rose-50 border-t border-rose-200" style="display: none;">
                    <div class="flex items-start gap-3 mb-3">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-rose-500 shrink-0 mt-0.5"/>
                        <div>
                            <p class="text-sm font-semibold text-rose-800">Kamera tidak tersedia</p>
                            <p class="text-xs text-rose-600 mt-0.5" x-text="cameraError"></p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button @click="cameraError = null; requestCameraAccess()" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-[#4E653D] text-white rounded-lg text-xs font-semibold hover:bg-[#3d5130] transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            Izinkan Kamera
                        </button>
                        <button x-show="cameras.length > 0" @click="showCameraSelect = !showCameraSelect" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-xs font-semibold hover:bg-gray-50 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Ganti Kamera
                        </button>
                    </div>
                    <div x-show="showCameraSelect && cameras.length > 0" class="mt-3" x-cloak>
                        <select x-model="selectedCameraId" class="w-full text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#4E653D]/30 focus:border-[#4E653D]">
                            <template x-for="cam in cameras" :key="cam.deviceId">
                                <option :value="cam.deviceId" x-text="cam.label || ('Camera ' + (cameras.indexOf(cam) + 1))"></option>
                            </template>
                        </select>
                        <button @click="cameraError = null; switchCamera()" class="mt-2 w-full px-4 py-2.5 bg-[#4A2F24] text-white rounded-lg text-xs font-semibold hover:bg-[#3a2419] transition">Gunakan Kamera Ini</button>
                    </div>
                </div>
            </div>

            {{-- Completion Card --}}
            <div x-show="allDone" class="{{ $card }} border-[#4E653D]/20" style="display: none;">
                <div class="p-8 text-center">
                    <div class="w-20 h-20 mx-auto mb-5 bg-[#4E653D]/10 rounded-full flex items-center justify-center border border-[#4E653D]/20">
                        <svg class="w-10 h-10 text-[#4E653D]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Checkout Selesai</h3>
                    <p class="text-sm text-gray-600 mb-6 leading-relaxed">
                        Semua <span class="font-bold text-[#4E653D]" x-text="totalCount"></span> pengunjung telah berhasil checkout.
                        Kunjungan dari <strong>{{ $guestName }}</strong> telah dicatat selesai.
                    </p>
                    <a href="{{ route('receptionist.guestbookstatus') }}"
                       class="inline-flex items-center gap-2 px-6 py-3 bg-[#4E653D] text-white rounded-xl font-semibold text-sm hover:bg-[#354C2B] transition shadow-sm">
                        <x-heroicon-o-arrow-left class="w-4 h-4"/>
                        Kembali ke Status Tamu
                    </a>
                </div>
            </div>

            {{-- Scan Log --}}
            <div class="{{ $card }}" x-show="scanLog.length > 0" style="display: none;">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50/50 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#4A2F24]/10 flex items-center justify-center border border-[#4A2F24]/20">
                        <x-heroicon-o-clipboard-document-list class="w-4.5 h-4.5 text-[#4A2F24]"/>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Riwayat Scan</h3>
                        <p class="text-xs text-gray-500 mt-0.5">QR code yang sudah di-scan</p>
                    </div>
                </div>
                <div class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
                    <template x-for="(log, idx) in scanLog" :key="idx">
                        <div class="px-6 py-3.5 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 border"
                                 :class="log.success ? 'bg-[#4E653D]/10 text-[#4E653D] border-[#4E653D]/20' : 'bg-rose-50 text-rose-600 border-rose-200'">
                                <svg x-show="log.success" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                <svg x-show="!log.success" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate" x-text="log.message"></p>
                                <p class="text-[11px] font-medium text-gray-500 mt-0.5" x-text="log.time"></p>
                            </div>
                            <span x-show="log.visitorNumber"
                                  class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-lg"
                                  :class="log.success ? 'bg-[#4E653D]/5 text-[#4E653D] border border-[#4E653D]/20' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                                  x-text="'#' + log.visitorNumber">
                            </span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

    </main>

    {{-- jsQR library for lightweight QR decoding (no extra UI) --}}
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

    <script>
        function checkoutScanner() {
            return {
                scanning: false,
                scannedCount: {{ $scannedCount }},
                totalCount: {{ $totalVisitors }},
                guestbookId: {{ $guestbookId }},
                allDone: {{ $qrStatus === 'completed' ? 'true' : 'false' }},

                showFeedback: false,
                feedbackSuccess: false,
                feedbackTitle: '',
                feedbackMessage: '',

                cameraError: null,
                showCameraSelect: false,
                scanLog: [],
                processing: false,
                lastScannedToken: null,
                cooldownMs: 2000,
                _lastScanTime: 0,
                _stream: null,
                _animFrameId: null,

                // Camera selection
                cameras: [],
                selectedCameraId: '',

                get progressPercent() {
                    if (this.totalCount === 0) return 0;
                    return Math.round((this.scannedCount / this.totalCount) * 100);
                },

                async init() {
                    if (this.allDone) return;
                    await this.requestCameraAccess();
                },

                async requestCameraAccess() {
                    try {
                        await this.enumerateCameras();
                        await this.startCamera();
                    } catch (err) {
                        console.error('Camera access error:', err);
                        this.cameraError = 'Izin kamera ditolak. Klik tombol di bawah untuk mencoba lagi.';
                    }
                },

                async enumerateCameras() {
                    try {
                        // Need a temporary stream first to get permission (labels are empty without permission)
                        const tempStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                        tempStream.getTracks().forEach(t => t.stop());

                        const devices = await navigator.mediaDevices.enumerateDevices();
                        this.cameras = devices.filter(d => d.kind === 'videoinput');

                        if (this.cameras.length > 0) {
                            // Try to find back camera by default on mobile
                            const backCam = this.cameras.find(c =>
                                c.label.toLowerCase().includes('back') ||
                                c.label.toLowerCase().includes('rear') ||
                                c.label.toLowerCase().includes('environment') ||
                                c.label.toLowerCase().includes('belakang')
                            );
                            this.selectedCameraId = backCam ? backCam.deviceId : this.cameras[0].deviceId;
                        }
                    } catch (err) {
                        console.warn('Could not enumerate cameras:', err);
                    }
                },

                async switchCamera() {
                    // Stop current camera first
                    this.stopCamera();
                    // Small delay to ensure tracks fully stop
                    await new Promise(r => setTimeout(r, 200));
                    // Start with the newly selected camera
                    this.cameraError = null;
                    await this.startCamera();
                },

                async startCamera() {
                    try {
                        // Build video constraints
                        const videoConstraints = {
                            width: { ideal: 1280 },
                            height: { ideal: 960 },
                        };

                        // Use specific device if selected, otherwise prefer back camera
                        if (this.selectedCameraId) {
                            videoConstraints.deviceId = { exact: this.selectedCameraId };
                        } else {
                            videoConstraints.facingMode = 'environment';
                        }

                        this._stream = await navigator.mediaDevices.getUserMedia({
                            video: videoConstraints,
                            audio: false,
                        });

                        // Update selectedCameraId from the actual track if it wasn't set
                        if (!this.selectedCameraId && this._stream.getVideoTracks().length > 0) {
                            const settings = this._stream.getVideoTracks()[0].getSettings();
                            if (settings.deviceId) {
                                this.selectedCameraId = settings.deviceId;
                            }
                        }

                        const video = this.$refs.cameraVideo;

                        // Wait for metadata to load before playing
                        video.srcObject = this._stream;
                        await new Promise((resolve, reject) => {
                            video.onloadedmetadata = () => resolve();
                            video.onerror = (e) => reject(e);
                            setTimeout(() => resolve(), 3000);
                        });

                        await video.play().catch(() => {
                            return new Promise(r => setTimeout(r, 300)).then(() => video.play());
                        });

                        this.scanning = true;

                        // Start scanning frames
                        this.scanFrame();
                    } catch (err) {
                        console.error('Camera error:', err);
                        this.cameraError = err.message || 'Tidak dapat mengakses kamera. Pastikan izin kamera diaktifkan.';
                    }
                },

                _scanInterval: null,

                scanFrame() {
                    if (this.allDone) {
                        this.stopCamera();
                        return;
                    }

                    const video = this.$refs.cameraVideo;
                    const canvas = this.$refs.scanCanvas;

                    if (video.readyState === video.HAVE_ENOUGH_DATA) {
                        const vw = video.videoWidth;
                        const vh = video.videoHeight;

                        // Calculate ROI: map the viewfinder square to video coordinates
                        const videoEl = this.$refs.cameraVideo;
                        const viewfinder = this.$refs.viewfinder;
                        const displayW = videoEl.clientWidth;
                        const displayH = videoEl.clientHeight;

                        // Scale factors from display to actual video resolution
                        const scaleX = vw / displayW;
                        const scaleY = vh / displayH;

                        let roiX = 0, roiY = 0, roiW = vw, roiH = vh;

                        if (viewfinder && displayW > 0 && displayH > 0) {
                            const vfRect = viewfinder.getBoundingClientRect();
                            const vidRect = videoEl.getBoundingClientRect();

                            // Viewfinder position relative to the video element
                            const relX = vfRect.left - vidRect.left;
                            const relY = vfRect.top - vidRect.top;

                            // Add some padding (15%) for tolerance
                            const pad = vfRect.width * 0.15;
                            roiX = Math.max(0, Math.floor((relX - pad) * scaleX));
                            roiY = Math.max(0, Math.floor((relY - pad) * scaleY));
                            roiW = Math.min(vw - roiX, Math.ceil((vfRect.width + pad * 2) * scaleX));
                            roiH = Math.min(vh - roiY, Math.ceil((vfRect.height + pad * 2) * scaleY));
                        }

                        // Guard against zero-size ROI
                        if (roiW < 10 || roiH < 10) {
                            this._scanInterval = setTimeout(() => {
                                this._animFrameId = requestAnimationFrame(() => this.scanFrame());
                            }, 150);
                            return;
                        }

                        // Draw only the ROI region to canvas for faster decoding
                        canvas.width = roiW;
                        canvas.height = roiH;
                        const ctx = canvas.getContext('2d', { willReadFrequently: true });
                        ctx.drawImage(video, roiX, roiY, roiW, roiH, 0, 0, roiW, roiH);
                        const imageData = ctx.getImageData(0, 0, roiW, roiH);

                        // Decode QR from ROI with better detection settings
                        const code = jsQR(imageData.data, imageData.width, imageData.height, {
                            inversionAttempts: 'attemptBoth',
                        });

                        if (code && code.data) {
                            this.onScanSuccess(code.data);
                        }
                    }

                    // Use throttled interval instead of rAF to reduce CPU usage
                    this._scanInterval = setTimeout(() => {
                        this._animFrameId = requestAnimationFrame(() => this.scanFrame());
                    }, 150);
                },

                stopCamera() {
                    if (this._scanInterval) {
                        clearTimeout(this._scanInterval);
                        this._scanInterval = null;
                    }
                    if (this._animFrameId) {
                        cancelAnimationFrame(this._animFrameId);
                        this._animFrameId = null;
                    }
                    if (this._stream) {
                        this._stream.getTracks().forEach(t => t.stop());
                        this._stream = null;
                    }
                    this.scanning = false;
                },

                async onScanSuccess(decodedText) {
                    // Prevent duplicate processing
                    if (this.processing) return;
                    if (this.lastScannedToken === decodedText && Date.now() - this._lastScanTime < this.cooldownMs) return;

                    this.processing = true;
                    this.lastScannedToken = decodedText;
                    this._lastScanTime = Date.now();

                    try {
                        const response = await fetch('{{ route("guestbook.checkout.scan") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                qr_content: decodedText,
                                guestbook_id: this.guestbookId,
                            }),
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.scannedCount = data.scanned_count;
                            this.showScanFeedback(true, '✓ Berhasil', data.message, data.visitor_number);

                            if (data.all_done) {
                                this.allDone = true;
                                this.stopCamera();
                            }
                        } else {
                            this.showScanFeedback(false, '✗ Gagal', data.message, data.visitor_number || null);
                        }
                    } catch (err) {
                        console.error('Scan API error:', err);
                        this.showScanFeedback(false, '✗ Error', 'Gagal menghubungi server.', null);
                    }

                    // Allow next scan after cooldown
                    setTimeout(() => {
                        this.processing = false;
                    }, this.cooldownMs);
                },

                showScanFeedback(success, title, message, visitorNumber) {
                    this.feedbackSuccess = success;
                    this.feedbackTitle = title;
                    this.feedbackMessage = message;
                    this.showFeedback = true;

                    // Add to log
                    this.scanLog.unshift({
                        success: success,
                        message: message,
                        visitorNumber: visitorNumber,
                        time: new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
                    });

                    // Auto-hide feedback after 1.5s
                    setTimeout(() => {
                        this.showFeedback = false;
                    }, 1500);
                },

                // Clean up when component is destroyed
                destroy() {
                    this.stopCamera();
                },
            };
        }
    </script>

    <style>
        /* Scan line animation */
        @keyframes scanLineMove {
            0%, 100% { top: 10%; }
            50% { top: 88%; }
        }
        .animate-scan-line {
            animation: scanLineMove 2.5s ease-in-out infinite;
            position: absolute;
        }

        /* Responsive viewfinder square */
        .qr-viewfinder {
            width: 200px;
            height: 200px;
        }
        @media (min-width: 400px) {
            .qr-viewfinder {
                width: 240px;
                height: 240px;
            }
        }
        @media (min-width: 768px) {
            .qr-viewfinder {
                width: 280px;
                height: 280px;
            }
        }
        @media (min-width: 1024px) {
            .qr-viewfinder {
                width: 320px;
                height: 320px;
            }
        }
    </style>
</div>
