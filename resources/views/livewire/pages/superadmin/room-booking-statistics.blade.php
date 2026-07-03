<div class="min-h-screen bg-background">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.room_booking_stats_title') }}"
            subtitle="{{ __('app.room_booking_stats_sub') }}">
            <x-slot:actions>
                <x-custom-select
                    wire:model.live="timeRange"
                    :options="[
                        ['value' => '7days',  'label' => __('app.7_days')],
                        ['value' => '30days', 'label' => __('app.30_days')],
                        ['value' => '90days', 'label' => __('app.90_days')],
                    ]"
                />
            </x-slot:actions>
        </x-page-header>

        {{-- KPIs --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach($kpis as $kpi)
                @php
                    $colors = [
                        'blue'   => 'text-foreground',
                        'yellow' => 'text-yellow-600',
                        'green'  => 'text-success',
                        'red'    => 'text-destructive',
                        'gray'   => 'text-muted-foreground',
                    ];
                @endphp
                <div class="bg-card border border-border rounded-lg p-5 shadow-sm hover:shadow-md transition">
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
                <canvas id="roomChart"></canvas>
            </div>
        </div>

        {{-- BOOKING LIST --}}
        @if($showList)
            <div class="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-border bg-muted/50">
                    <h3 class="font-semibold text-card-foreground">{{ __('app.room_booking_items') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[700px]">
                        <thead class="bg-muted text-muted-foreground uppercase text-xs border-b border-border">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium">ID</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.room') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.name') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.meeting_title_col') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.date') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.time') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($bookings as $booking)
                                <tr class="hover:bg-muted/50 transition-colors">
                                    <td class="px-6 py-4 text-card-foreground">{{ $booking->bookingroom_id }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $booking->room->room_name ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $booking->user->name ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $booking->meeting_title }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $booking->date->format('M d, Y') }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $booking->start_time }} - {{ $booking->end_time }}</td>
                                    <td class="px-6 py-4">
                                        @php
                                            $statusValue = is_numeric($booking->status)
                                                ? (['pending', 'approved', 'rejected', 'done'][$booking->status] ?? 'pending')
                                                : strtolower($booking->status);
                                            $statusColors = [
                                                'pending'   => 'bg-yellow-100 text-yellow-800',
                                                'approved'  => 'bg-green-100 text-green-800',
                                                'rejected'  => 'bg-red-100 text-red-800',
                                                'completed' => 'bg-muted text-muted-foreground',
                                                'done'      => 'bg-muted text-muted-foreground',
                                            ];
                                        @endphp
                                        <span class="px-3 py-1 text-xs rounded-full font-medium {{ $statusColors[$statusValue] ?? 'bg-muted text-muted-foreground' }}">
                                            {{ __('app.' . $statusValue) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-muted-foreground">{{ __('app.no_bookings_found') }}</td>
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
    function buildRoomChart(labels, data) {
        const ctx = document.getElementById('roomChart');
        if (!ctx) return;

        if (window.roomChart && typeof window.roomChart.destroy === 'function') {
            window.roomChart.destroy();
        }

        window.roomChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '{{ __('app.room_bookings_label') }}',
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
                    y: { beginAtZero: true, ticks: { stepSize: 1 }, title: { display: true, text: '{{ __('app.bookings_axis') }}' } },
                    x: { title: { display: true, text: '{{ __('app.date') }}' } }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        buildRoomChart(@json($labels), @json($data));
    });

    document.addEventListener('livewire:init', () => {
        Livewire.on('room-chart-updated', ({ labels, data }) => {
            buildRoomChart(labels, data);
        });
    });
</script>
