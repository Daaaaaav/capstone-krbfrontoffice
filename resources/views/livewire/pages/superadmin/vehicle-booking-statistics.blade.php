<div class="min-h-screen bg-background">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.vehicle_booking_stats_title') }}"
            subtitle="{{ __('app.vehicle_booking_stats_sub') }}">
            <x-slot:actions>
                <select wire:model.live="timeRange"
                    class="px-3 py-2 text-sm rounded-md border border-border bg-card text-card-foreground shadow-sm focus:outline-none focus:ring-1 focus:ring-ring transition-colors cursor-pointer">
                    <option value="7days">{{ __('app.7_days') }}</option>
                    <option value="30days">{{ __('app.30_days') }}</option>
                    <option value="90days">{{ __('app.90_days') }}</option>
                </select>
            </x-slot:actions>
        </x-page-header>

        {{-- KPIs --}}
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach($kpis as $kpi)
                @php
                    $colors = [
                        'blue'   => 'text-foreground',
                        'yellow' => 'text-yellow-600',
                        'green'  => 'text-success',
                        'purple' => 'text-primary',
                        'gray'   => 'text-muted-foreground',
                        'red'    => 'text-destructive',
                    ];
                @endphp
                <div class="bg-card border border-border rounded-lg p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition">
                    <p class="text-sm font-medium text-muted-foreground">{{ $kpi['label'] }}</p>
                    <h2 class="text-3xl font-bold mt-2 {{ $colors[$kpi['color']] ?? 'text-card-foreground' }}">{{ $kpi['value'] }}</h2>
                </div>
            @endforeach
        </section>

        {{-- CHART --}}
        <div class="bg-card border border-border p-6 rounded-lg shadow-sm">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-4">
                <h3 class="text-sm font-semibold text-card-foreground">{{ __('app.daily_booking_trend') }}</h3>
                <button wire:click="toggleList"
                    class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:opacity-90 text-sm font-medium transition">
                    {{ $showList ? __('app.hide_list') : __('app.show_list') }}
                </button>
            </div>
            <div wire:ignore style="position: relative; height: 400px;">
                <canvas id="vehicleChart"></canvas>
            </div>
        </div>

        {{-- BOOKING LIST --}}
        @if($showList)
            <div class="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-border bg-muted/50">
                    <h3 class="font-semibold text-card-foreground">{{ __('app.vehicle_booking_items') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[800px]">
                        <thead class="bg-muted text-muted-foreground uppercase text-xs border-b border-border">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium">ID</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.vehicle') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.borrower') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.purpose') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.destination') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.start') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.end') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($bookings as $booking)
                                <tr class="hover:bg-muted/50 transition-colors">
                                    <td class="px-6 py-4 text-card-foreground">{{ $booking->vehiclebooking_id }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $booking->vehicle->vehicle_name ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $booking->borrower_name }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ Str::limit($booking->purpose, 30) }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $booking->destination }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $booking->start_at->format('d/m H:i') }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $booking->end_at->format('d/m H:i') }}</td>
                                    <td class="px-6 py-4">
                                        @php
                                            $statusValue = strtolower($booking->status ?? 'pending');
                                            $statusColors = [
                                                'pending'     => 'bg-yellow-100 text-yellow-800',
                                                'approved'    => 'bg-green-100 text-green-800',
                                                'on_progress' => 'bg-muted text-card-foreground',
                                                'completed'   => 'bg-muted text-muted-foreground',
                                                'returned'    => 'bg-muted text-muted-foreground',
                                                'rejected'    => 'bg-red-100 text-red-800',
                                                'cancelled'   => 'bg-red-50 text-red-600',
                                            ];
                                            $statusLabels = [
                                                'pending'     => __('app.pending'),
                                                'approved'    => __('app.approved'),
                                                'on_progress' => __('app.in_progress'),
                                                'completed'   => __('app.completed'),
                                                'returned'    => __('app.returned'),
                                                'rejected'    => __('app.rejected'),
                                                'cancelled'   => __('app.cancelled'),
                                            ];
                                        @endphp
                                        <span class="px-3 py-1 text-xs rounded-full font-medium {{ $statusColors[$statusValue] ?? 'bg-muted text-muted-foreground' }}">
                                            {{ $statusLabels[$statusValue] ?? $statusValue }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-muted-foreground">{{ __('app.no_bookings_found') }}</td>
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
    function buildVehicleChart(labels, data) {
        const ctx = document.getElementById('vehicleChart');
        if (!ctx) return;

        if (window.vehicleChart && typeof window.vehicleChart.destroy === 'function') {
            window.vehicleChart.destroy();
        }

        window.vehicleChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '{{ __('app.total_bookings') }}',
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
                    y: { beginAtZero: true, ticks: { stepSize: 1 }, title: { display: true, text: '{{ __('app.total_bookings') }}' } },
                    x: { title: { display: true, text: '{{ __('app.date_label') }}' } }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        buildVehicleChart(@json($labels), @json($data));
    });

    document.addEventListener('livewire:init', () => {
        Livewire.on('vehicle-chart-updated', ({ labels, data }) => {
            buildVehicleChart(labels, data);
        });
    });
</script>
