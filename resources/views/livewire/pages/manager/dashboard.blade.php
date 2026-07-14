<div class="min-h-screen bg-background">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- Page header --}}
        <x-page-header title="{{ __('app.manager_analytics') }}" subtitle="{{ __('app.interactive_insights') }} {{ $selectedYear }}">
            <x-slot:actions>
                <button wire:click="setFilter('all')"
                    class="px-4 py-2 text-sm font-medium bg-secondary text-secondary-foreground rounded-md border border-border hover:bg-accent transition-colors">
                    {{ __('app.reset_view') }}
                </button>
            </x-slot:actions>
        </x-page-header>

        {{-- Year Selector --}}
        <div class="bg-card border border-border rounded-lg p-4">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <p class="text-sm font-medium text-muted-foreground">{{ __('app.select_year') }}</p>
                    <p class="text-xs text-muted-foreground/70">{{ __('app.viewing_data_for') }} {{ $selectedYear }}</p>
                </div>
                @if(empty($availableYears))
                    <span class="text-sm text-muted-foreground">{{ __('app.no_data_available') }}</span>
                @else
                    @php
                        $yearOptions = array_map(
                            fn($y) => ['value' => (string) $y, 'label' => (string) $y],
                            array_reverse($availableYears)
                        );
                    @endphp
                    <x-custom-select wire:model.live="selectedYear" :options="$yearOptions" />
                @endif
            </div>
        </div>

        {{-- KPI Cards --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($stats as $s)
                @php $isActive = $activeFilter === $s['key']; $isUp = $s['direction'] === 'up'; @endphp
                <div wire:click="setFilter('{{ $s['key'] }}')"
                    class="cursor-pointer bg-card border rounded-lg p-5 transition-all duration-150 hover:bg-accent/50
                           {{ $isActive ? 'border-foreground ring-1 ring-foreground' : 'border-border' }}">
                    <div class="flex justify-between items-start">
                        <p class="text-sm font-medium text-muted-foreground">{{ $s['label'] }}</p>
                        <span class="text-xs px-2 py-0.5 rounded-md font-medium
                            {{ $isUp ? 'text-success bg-success/10' : 'text-destructive bg-destructive/10' }}">
                            {{ $isUp ? '+' : '' }}{{ $s['trend'] }}%
                        </span>
                    </div>
                    <h2 class="text-2xl font-semibold mt-3 text-card-foreground tracking-tight">{{ number_format($s['value']) }}</h2>
                    <p class="mt-2 text-xs text-muted-foreground/60">{{ __('app.click_filter_chart') }}</p>
                </div>
            @endforeach
        </section>

        {{-- Two-column layout: Chart (left) + Status Sidebar (right) --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">

            {{-- LEFT: Chart --}}
            <div class="xl:col-span-2 bg-card border border-border p-6 rounded-lg">
                <h3 class="text-sm font-semibold text-card-foreground mb-4">
                    {{ __('app.booking_trends') }} — {{ $selectedYear }}
                </h3>
                <div wire:ignore style="position: relative; height: 380px;">
                    <canvas id="chart"></canvas>
                </div>
            </div>

            {{-- RIGHT: Status Sidebar --}}
            <aside class="xl:col-span-1 space-y-4">

                {{-- Room Bookings Status --}}
                <div class="bg-card border border-border rounded-lg overflow-hidden"
                     x-data="{ pendingOpen: true, ongoingOpen: true }">
                    <div class="px-4 py-3 border-b border-border bg-muted/30">
                        <p class="text-xs font-bold uppercase tracking-wider text-foreground">Room Bookings Status</p>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Offline bookings today</p>
                    </div>

                    {{-- Pending --}}
                    <div class="border-b border-border/60">
                        <button @click="pendingOpen = !pendingOpen"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-amber-700 bg-amber-50/60 hover:bg-amber-50 transition">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                Pending
                                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-400 text-white">
                                    {{ $pendingRoomBookings->count() }}
                                </span>
                            </span>
                            <svg class="w-3.5 h-3.5 transition-transform" :class="pendingOpen ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="pendingOpen" x-collapse class="divide-y divide-border/40">
                            @forelse($pendingRoomBookings as $b)
                            <div class="px-4 py-2.5 flex items-start gap-3 hover:bg-muted/30 transition">
                                <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21V11.5a1.5 1.5 0 013 0V21"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-foreground truncate">{{ $b->meeting_title }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">
                                        {{ $b->room?->room_name ?? '—' }} &bull;
                                        {{ \Carbon\Carbon::parse($b->date)->format('d M') }}
                                        {{ is_string($b->start_time) ? substr($b->start_time,0,5) : '' }}–{{ is_string($b->end_time) ? substr($b->end_time,0,5) : '' }}
                                    </p>
                                </div>
                            </div>
                            @empty
                            <p class="px-4 py-3 text-xs text-muted-foreground italic">No pending room bookings.</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Ongoing --}}
                    <div>
                        <button @click="ongoingOpen = !ongoingOpen"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-emerald-700 bg-emerald-50/60 hover:bg-emerald-50 transition">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Ongoing
                                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500 text-white">
                                    {{ $ongoingRoomBookings->count() }}
                                </span>
                            </span>
                            <svg class="w-3.5 h-3.5 transition-transform" :class="ongoingOpen ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="ongoingOpen" x-collapse class="divide-y divide-border/40">
                            @forelse($ongoingRoomBookings as $b)
                            <div class="px-4 py-2.5 flex items-start gap-3 hover:bg-muted/30 transition">
                                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21V11.5a1.5 1.5 0 013 0V21"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-foreground truncate">{{ $b->meeting_title }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">
                                        {{ $b->room?->room_name ?? '—' }} &bull;
                                        {{ \Carbon\Carbon::parse($b->date)->format('d M') }}
                                        {{ is_string($b->start_time) ? substr($b->start_time,0,5) : '' }}–{{ is_string($b->end_time) ? substr($b->end_time,0,5) : '' }}
                                    </p>
                                </div>
                            </div>
                            @empty
                            <p class="px-4 py-3 text-xs text-muted-foreground italic">No ongoing room bookings.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Vehicle Bookings Status --}}
                <div class="bg-card border border-border rounded-lg overflow-hidden"
                     x-data="{ pendingOpen: true, ongoingOpen: true }">
                    <div class="px-4 py-3 border-b border-border bg-muted/30">
                        <p class="text-xs font-bold uppercase tracking-wider text-foreground">Vehicle Bookings Status</p>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Active vehicle requests</p>
                    </div>

                    {{-- Pending --}}
                    <div class="border-b border-border/60">
                        <button @click="pendingOpen = !pendingOpen"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-amber-700 bg-amber-50/60 hover:bg-amber-50 transition">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                Pending
                                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-400 text-white">
                                    {{ $pendingVehicleBookings->count() }}
                                </span>
                            </span>
                            <svg class="w-3.5 h-3.5 transition-transform" :class="pendingOpen ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="pendingOpen" x-collapse class="divide-y divide-border/40">
                            @forelse($pendingVehicleBookings as $b)
                            <div class="px-4 py-2.5 flex items-start gap-3 hover:bg-muted/30 transition">
                                <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/>
                                        <circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-foreground truncate">{{ $b->borrower_name }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">
                                        {{ $b->vehicle?->name ?? '—' }} &bull;
                                        {{ $b->start_at?->format('d M H:i') }}
                                    </p>
                                </div>
                            </div>
                            @empty
                            <p class="px-4 py-3 text-xs text-muted-foreground italic">No pending vehicle bookings.</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Ongoing --}}
                    <div>
                        <button @click="ongoingOpen = !ongoingOpen"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-blue-700 bg-blue-50/60 hover:bg-blue-50 transition">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                                On Road / Approved
                                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-500 text-white">
                                    {{ $ongoingVehicleBookings->count() }}
                                </span>
                            </span>
                            <svg class="w-3.5 h-3.5 transition-transform" :class="ongoingOpen ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="ongoingOpen" x-collapse class="divide-y divide-border/40">
                            @forelse($ongoingVehicleBookings as $b)
                            <div class="px-4 py-2.5 flex items-start gap-3 hover:bg-muted/30 transition">
                                <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/>
                                        <circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-foreground truncate">{{ $b->borrower_name }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">
                                        {{ $b->vehicle?->name ?? '—' }} &bull;
                                        {{ $b->start_at?->format('d M H:i') }}–{{ $b->end_at?->format('H:i') }}
                                    </p>
                                </div>
                            </div>
                            @empty
                            <p class="px-4 py-3 text-xs text-muted-foreground italic">No ongoing vehicle bookings.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Priority Bookings Status --}}
                @if($pendingPriorityRoom->isNotEmpty() || $pendingPriorityVehicle->isNotEmpty())
                <div class="bg-card border border-amber-300 rounded-lg overflow-hidden"
                     x-data="{ roomOpen: true, vehOpen: true }">
                    <div class="px-4 py-3 border-b border-amber-200 bg-amber-50/80">
                        <div class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                            <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Priority Bookings Pending</p>
                        </div>
                        <p class="text-[11px] text-amber-600/80 mt-0.5">Awaiting receptionist action</p>
                    </div>

                    @if($pendingPriorityRoom->isNotEmpty())
                    <div class="{{ $pendingPriorityVehicle->isNotEmpty() ? 'border-b border-border/60' : '' }}">
                        <button @click="roomOpen = !roomOpen"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-amber-700 bg-amber-50/40 hover:bg-amber-50 transition">
                            <span class="flex items-center gap-2">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2"/></svg>
                                Room Priority
                                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white">
                                    {{ $pendingPriorityRoom->count() }}
                                </span>
                            </span>
                            <svg class="w-3.5 h-3.5 transition-transform" :class="roomOpen ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="roomOpen" x-collapse class="divide-y divide-border/40">
                            @foreach($pendingPriorityRoom as $pb)
                            <div class="px-4 py-2.5 flex items-start gap-3 hover:bg-muted/30 transition">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-foreground truncate">{{ $pb->meeting_title }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">
                                        {{ $pb->room?->room_name ?? '—' }} &bull;
                                        {{ \Carbon\Carbon::parse($pb->date)->format('d M') }}
                                        {{ $pb->start_time }}–{{ $pb->end_time }}
                                    </p>
                                    @if($pb->status === 'pending_cancellation')
                                    <span class="text-[10px] text-orange-600 font-medium">Cancel request pending</span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if($pendingPriorityVehicle->isNotEmpty())
                    <div>
                        <button @click="vehOpen = !vehOpen"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-xs font-semibold text-blue-700 bg-blue-50/40 hover:bg-blue-50 transition">
                            <span class="flex items-center gap-2">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 002 12v4c0 .6.4 1 1 1h2"/>
                                    <circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>
                                </svg>
                                Vehicle Priority
                                <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-500 text-white">
                                    {{ $pendingPriorityVehicle->count() }}
                                </span>
                            </span>
                            <svg class="w-3.5 h-3.5 transition-transform" :class="vehOpen ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="vehOpen" x-collapse class="divide-y divide-border/40">
                            @foreach($pendingPriorityVehicle as $pb)
                            <div class="px-4 py-2.5 flex items-start gap-3 hover:bg-muted/30 transition">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-foreground truncate">{{ $pb->borrower_name }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">
                                        {{ $pb->vehicle?->name ?? '—' }} &bull;
                                        {{ $pb->start_at?->format('d M H:i') }}
                                    </p>
                                    @if($pb->status === 'pending_cancellation')
                                    <span class="text-[10px] text-orange-600 font-medium">Cancel request pending</span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                @endif

            </aside>
        </div>{{-- end two-column grid --}}
    </main>
</div>

@push('scripts')
{{-- Chart.js is bundled via resources/js/app.js (chart.js npm package) and exposed as window.Chart --}}
<script>
    const CHART_COLORS = {
        'room':    { border: '#4E653D', bg: 'rgba(78, 101, 61, 0.1)' },
        'vehicle': { border: '#4A2F24', bg: 'rgba(74, 47, 36, 0.1)' },
    };
    const FALLBACK_COLORS = ['#354C2B', '#CDDEA7'];

    function applyDatasetStyles(datasets) {
        return datasets.map((ds, i) => {
            const { type: dsType, ...rest } = ds;
            const c = CHART_COLORS[dsType] ?? {
                border: FALLBACK_COLORS[i] ?? '#374151',
                bg:     (FALLBACK_COLORS[i] ?? '#374151') + '14',
            };
            return {
                ...rest,
                borderColor:      c.border,
                backgroundColor:  c.bg,
                borderWidth:      2,
                tension:          0.35,
                fill:             false,
                pointRadius:      3,
                pointHoverRadius: 5,
            };
        });
    }

    function buildChart(labels, datasets) {
        const ctx = document.getElementById('chart');
        if (!ctx) return;

        if (window.dashChart && typeof window.dashChart.destroy === 'function') {
            window.dashChart.destroy();
        }

        window.dashChart = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets: applyDatasetStyles(datasets) },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 300 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 20,
                            font: { family: 'Inter', size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'hsl(0 0% 9%)',
                        titleFont: { family: 'Inter', size: 12 },
                        bodyFont: { family: 'Inter', size: 12 },
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { family: 'Inter', size: 11 } },
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        title: { display: true, text: @json(__('app.bookings_axis')), font: { family: 'Inter', size: 12 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 11 } },
                        title: { display: true, text: @json(__('app.month_axis')), font: { family: 'Inter', size: 12 } }
                    }
                }
            }
        });
    }

    function updateChart(labels, datasets) {
        if (!window.dashChart) {
            buildChart(labels, datasets);
            return;
        }
        window.dashChart.data.labels = labels;
        window.dashChart.data.datasets = applyDatasetStyles(datasets);
        window.dashChart.update('active');
    }

    document.addEventListener('DOMContentLoaded', () => {
        buildChart(@json($labels), @json($datasets));
    });

    document.addEventListener('livewire:init', () => {
        Livewire.on('chart-data-updated', ({ labels, datasets }) => {
            updateChart(labels, datasets);
        });
    });
</script>
@endpush
