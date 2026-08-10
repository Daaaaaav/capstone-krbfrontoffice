<div class="min-h-screen bg-background">
    <main class="px-4 sm:px-6 py-6 space-y-6">

        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.guestbook_stats_title') }}"
            subtitle="{{ __('app.guestbook_stats_sub') }}">
            <x-slot:actions>
                <x-date-range-picker wire:model.live="startDate" />
            </x-slot:actions>
        </x-page-header>

        {{-- STATS --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($stats as $stat)
                @php
                    $colors = [
                        'blue'   => 'text-foreground',
                        'yellow' => 'text-yellow-600',
                        'green'  => 'text-success',
                        'purple' => 'text-primary',
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
                <h3 class="text-sm font-semibold text-card-foreground">{{ __('app.daily_visitor_trend') }}</h3>
                <button wire:click="toggleList"
                    class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:opacity-90 text-sm font-medium transition">
                    {{ $showList ? __('app.hide_list') : __('app.show_list') }}
                </button>
            </div>
            <div wire:ignore style="position: relative; height: 400px;">
                <canvas id="guestbookChart"></canvas>
            </div>
        </div>

        {{-- GUESTBOOK LIST --}}
        @if($showList)
            <div class="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-border bg-muted/50">
                    <h3 class="font-semibold text-card-foreground">{{ __('app.recent_visitors') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[900px]">
                        <thead class="bg-muted text-muted-foreground uppercase text-xs border-b border-border">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.name') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.institution') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.visit_purpose') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.id_card_type') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.date_label') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.check_in') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.check_out') }}</th>
                                <th class="px-6 py-3 text-left font-medium">{{ __('app.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($guestbooks as $guest)
                                <tr class="hover:bg-muted/50 transition-colors">
                                    <td class="px-6 py-4 text-card-foreground font-medium">{{ $guest->name }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $guest->instansi ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ Str::limit($guest->keperluan, 30) }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $guest->idType->id_type_name ?? 'N/A' }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $guest->date ? $guest->date->format('d/m/Y') : '-' }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $guest->jam_in ?? '-' }}</td>
                                    <td class="px-6 py-4 text-card-foreground">{{ $guest->jam_out ?? '-' }}</td>
                                    <td class="px-6 py-4">
                                        @if($guest->jam_out)
                                            <span class="px-3 py-1 text-xs rounded-full font-medium bg-green-100 text-green-700">
                                                {{ __('app.checked_out') }}
                                            </span>
                                        @elseif($guest->jam_in)
                                            <span class="px-3 py-1 text-xs rounded-full font-medium bg-yellow-100 text-yellow-700">
                                                {{ __('app.in_building') }}
                                            </span>
                                        @else
                                            <span class="px-3 py-1 text-xs rounded-full font-medium bg-muted text-muted-foreground">
                                                {{ __('app.registered_status') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-muted-foreground">{{ __('app.no_visitors_found') }}</td>
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
    function buildGuestbookChart(labels, data) {
        const ctx = document.getElementById('guestbookChart');
        if (!ctx) return;

        if (window.guestbookChart && typeof window.guestbookChart.destroy === 'function') {
            window.guestbookChart.destroy();
        }

        window.guestbookChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '{{ __('app.visitors') }}',
                    data: data,
                    backgroundColor: '#4E653DCC',
                    borderColor: '#4E653D',
                    borderWidth: 1,
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
                    y: { beginAtZero: true, ticks: { stepSize: 1 }, title: { display: true, text: '{{ __('app.visitors') }}' } },
                    x: { title: { display: true, text: '{{ __('app.date_label') }}' } }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        buildGuestbookChart(@json($labels), @json($data));
    });

    document.addEventListener('livewire:init', () => {
        Livewire.on('guestbook-chart-updated', ({ labels, data }) => {
            buildGuestbookChart(labels, data);
        });
    });
</script>
