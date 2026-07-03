<div class="min-h-screen bg-background">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- Page header --}}
        <x-page-header title="{{ __('app.superadmin_analytics') }}" subtitle="{{ __('app.interactive_insights') }} {{ $selectedYear }}">
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
                    <select wire:model.live="selectedYear"
                        class="px-3 py-2 text-sm rounded-md border border-border bg-card text-card-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring transition-colors cursor-pointer">
                        @foreach(array_reverse($availableYears) as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>

        {{-- KPI Cards --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($stats as $s)
                @php
                    $isActive = $activeFilter === $s['key'];
                    $isUp = $s['direction'] === 'up';
                @endphp
                <div wire:click="setFilter('{{ $s['key'] }}')"
                    class="cursor-pointer bg-card border rounded-lg p-5 transition-all duration-150
                           hover:bg-accent/50
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

        {{-- Chart --}}
        <div class="bg-card border border-border p-6 rounded-lg">
            <h3 class="text-sm font-semibold text-card-foreground mb-4">
                {{ __('app.booking_trends') }} — {{ $selectedYear }}
            </h3>
            <div wire:ignore style="position: relative; height: 400px;">
                <canvas id="chart"></canvas>
            </div>
        </div>
    </main>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
