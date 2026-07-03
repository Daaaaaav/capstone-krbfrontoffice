<div class="min-h-screen bg-background">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.delivery_stats_title') }}"
            subtitle="{{ __('app.delivery_stats_sub') }}">
            <x-slot:actions>
                <select wire:model.live="timeRange"
                    class="px-3 py-2 text-sm rounded-md border border-border bg-card text-card-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring transition-colors cursor-pointer">
                    <option value="7days">{{ __('app.7_days') }}</option>
                    <option value="30days">{{ __('app.30_days') }}</option>
                    <option value="90days">{{ __('app.90_days') }}</option>
                </select>
            </x-slot:actions>
        </x-page-header>

        {{-- STATS --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($stats as $stat)
                @php
                    $colors = [
                        'blue'   => 'text-foreground',
                        'yellow' => 'text-yellow-600',
                        'purple' => 'text-primary',
                        'green'  => 'text-success',
                    ];
                @endphp
                <div class="bg-card border border-border rounded-lg p-5 shadow-sm hover:shadow-md transition">
                    <p class="text-sm font-medium text-muted-foreground">{{ $stat['label'] }}</p>
                    <h2 class="text-3xl font-bold mt-2 {{ $colors[$stat['color']] ?? 'text-card-foreground' }}">{{ $stat['value'] }}</h2>
                </div>
            @endforeach
        </section>

        {{-- CHART --}}
        <div class="bg-card border border-border p-6 rounded-lg shadow-sm">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-4">
                <h3 class="text-sm font-semibold text-card-foreground">{{ __('app.daily_delivery_trend') }}</h3>
                <button wire:click="toggleList"
                    class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:opacity-90 text-sm font-medium transition">
                    {{ $showList ? __('app.hide_list') : __('app.show_list') }}
                </button>
            </div>
            <div wire:ignore style="position: relative; height: 400px;">
                <canvas id="deliveryChart"></canvas>
            </div>
        </div>

        {{-- DELIVERY LIST --}}
        @if($showList)
            <div class="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-border bg-muted/50">
                    <h3 class="font-semibold text-card-foreground">{{ __('app.recent_deliveries') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[500px]">
                        <thead class="bg-muted text-muted-foreground uppercase text-xs border-b border-border">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium">ID</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.recipient') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.status') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.date') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($deliveries as $delivery)
                                <tr class="hover:bg-muted/50 transition-colors">
                                    <td class="px-6 py-4 text-card-foreground">#{{ $delivery->delivery_id }}</td>
                                    <td class="px-6 py-4 text-card-foreground font-medium">{{ $delivery->nama_penerima ?? 'N/A' }}</td>
                                    <td class="px-6 py-4">
                                        @php
                                            $s = $delivery->status ?? 'pending';
                                            $statusColors = [
                                                'pending' => 'bg-yellow-100 text-yellow-700',
                                                'stored'  => 'bg-muted text-card-foreground',
                                                'done'    => 'bg-green-100 text-green-700',
                                            ];
                                            $statusLabels = [
                                                'pending' => __('app.pending'),
                                                'stored'  => __('app.stored'),
                                                'done'    => __('app.done'),
                                            ];
                                        @endphp
                                        <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$s] ?? 'bg-muted text-muted-foreground' }}">
                                            {{ $statusLabels[$s] ?? $s }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-muted-foreground">{{ $delivery->created_at?->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-muted-foreground">{{ __('app.no_deliveries_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    function buildDeliveryChart(labels, data) {
        const ctx = document.getElementById('deliveryChart');
        if (!ctx) return;

        if (window.deliveryChart && typeof window.deliveryChart.destroy === 'function') {
            window.deliveryChart.destroy();
        }

        window.deliveryChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '{{ __('app.deliveries') }}',
                    data: data,
                    backgroundColor: 'hsl(var(--primary) / 0.8)',
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 400 },
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 }, title: { display: true, text: '{{ __('app.deliveries') }}' } },
                    x: { title: { display: true, text: '{{ __('app.date_label') }}' } }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        buildDeliveryChart(@json($labels), @json($data));
    });

    document.addEventListener('livewire:init', () => {
        Livewire.on('delivery-chart-updated', ({ labels, data }) => {
            buildDeliveryChart(labels, data);
        });
    });
</script>
