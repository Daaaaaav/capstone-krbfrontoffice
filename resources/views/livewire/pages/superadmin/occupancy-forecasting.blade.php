<div class="min-h-screen bg-[#f5f7f2]">
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-8">

        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.occupancy_title') }}"
            subtitle="{{ __('app.occupancy_subtitle') }}">
            <x-slot:actions>
                <span class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium
                    {{ $isLSTMAvailable ? 'bg-green-500/20 text-green-300 border border-green-400/30' : 'bg-[#CDDEA7]/15 text-[#CDDEA7] border border-[#CDDEA7]/25' }}">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    {{ $isLSTMAvailable ? __('app.lstm_model') : __('app.statistical_model') }}
                </span>
            </x-slot:actions>
        </x-page-header>

        {{-- CONTROLS --}}
        <div class="bg-white border border-[#d4dfc8] rounded-2xl p-6 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                {{-- Forecast Type --}}
                <div>
                    <label class="block text-sm font-medium text-[#4E653D] mb-2">{{ __('app.forecast_type') }}</label>
                    <div class="flex gap-2">
                        <button wire:click="setForecastType('room')"
                            class="flex-1 px-4 py-2 rounded-lg text-sm font-medium transition {{ $forecastType === 'room' ? 'bg-[#4A2F24] text-white' : 'bg-[#eef1e8] text-[#4E653D] hover:bg-[#dde4d4]' }}">
                            {{ __('app.rooms') }}
                        </button>
                        <button wire:click="setForecastType('vehicle')"
                            class="flex-1 px-4 py-2 rounded-lg text-sm font-medium transition {{ $forecastType === 'vehicle' ? 'bg-[#4A2F24] text-white' : 'bg-[#eef1e8] text-[#4E653D] hover:bg-[#dde4d4]' }}">
                            {{ __('app.vehicles') }}
                        </button>
                        <button wire:click="setForecastType('combined')"
                            class="flex-1 px-4 py-2 rounded-lg text-sm font-medium transition {{ $forecastType === 'combined' ? 'bg-[#4A2F24] text-white' : 'bg-[#eef1e8] text-[#4E653D] hover:bg-[#dde4d4]' }}">
                            {{ __('app.both') }}
                        </button>
                    </div>
                </div>

                {{-- Forecast Period --}}
                <div>
                    <label class="block text-sm font-medium text-[#4E653D] mb-2">{{ __('app.forecast_period') }}</label>
                    <div class="flex gap-2">
                        <button wire:click="setForecastDays(7)"
                            class="flex-1 px-4 py-2 rounded-lg text-sm font-medium transition {{ $forecastDays === 7 ? 'bg-[#4A2F24] text-white' : 'bg-[#eef1e8] text-[#4E653D] hover:bg-[#dde4d4]' }}">
                            {{ __('app.7_days') }}
                        </button>
                        <button wire:click="setForecastDays(14)"
                            class="flex-1 px-4 py-2 rounded-lg text-sm font-medium transition {{ $forecastDays === 14 ? 'bg-[#4A2F24] text-white' : 'bg-[#eef1e8] text-[#4E653D] hover:bg-[#dde4d4]' }}">
                            {{ __('app.14_days') }}
                        </button>
                        <button wire:click="setForecastDays(21)"
                            class="flex-1 px-4 py-2 rounded-lg text-sm font-medium transition {{ $forecastDays === 21 ? 'bg-[#4A2F24] text-white' : 'bg-[#eef1e8] text-[#4E653D] hover:bg-[#dde4d4]' }}">
                            {{ __('app.21_days') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- STATS CARDS --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white border border-[#d4dfc8] rounded-2xl p-5 shadow-sm hover:shadow-lg transition">
                <p class="text-sm font-medium text-[#7a8f6a] mb-2">{{ __('app.avg_room_occupancy') }}</p>
                <h2 class="text-3xl font-bold text-[#2d3a24]">{{ $stats['avg_room_fc'] }}</h2>
                <p class="text-xs text-[#9aaa8a] mt-1">
                    {{ __('app.historical') }}: {{ $stats['avg_room_hist'] }}
                    @if($stats['room_trend'] != 0)
                        <span class="{{ $stats['room_trend'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                            ({{ $stats['room_trend'] > 0 ? '+' : '' }}{{ $stats['room_trend'] }}%)
                        </span>
                    @endif
                </p>
            </div>

            <div class="bg-white border border-[#d4dfc8] rounded-2xl p-5 shadow-sm hover:shadow-lg transition">
                <p class="text-sm font-medium text-[#7a8f6a] mb-2">{{ __('app.avg_vehicle_bookings') }}</p>
                <h2 class="text-3xl font-bold text-[#2d3a24]">{{ $stats['avg_vehicle_fc'] }}</h2>
                <p class="text-xs text-[#9aaa8a] mt-1">
                    {{ __('app.historical') }}: {{ $stats['avg_vehicle_hist'] }}
                    @if($stats['vehicle_trend'] != 0)
                        <span class="{{ $stats['vehicle_trend'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                            ({{ $stats['vehicle_trend'] > 0 ? '+' : '' }}{{ $stats['vehicle_trend'] }}%)
                        </span>
                    @endif
                </p>
            </div>

            <div class="bg-white border border-[#d4dfc8] rounded-2xl p-5 shadow-sm hover:shadow-lg transition">
                <p class="text-sm font-medium text-[#7a8f6a] mb-2">{{ __('app.peak_day') }}</p>
                <h2 class="text-2xl font-bold text-[#2d3a24]">{{ $stats['peak_day'] }}</h2>
                <p class="text-xs text-[#9aaa8a] mt-1">{{ __('app.highest_predicted') }}</p>
            </div>

            <div class="bg-white border border-[#d4dfc8] rounded-2xl p-5 shadow-sm hover:shadow-lg transition">
                <p class="text-sm font-medium text-[#7a8f6a] mb-2">{{ __('app.total_forecast') }}</p>
                <h2 class="text-3xl font-bold text-[#2d3a24]">{{ $stats['total_room_fc'] }}</h2>
                <p class="text-xs text-[#9aaa8a] mt-1">{{ __('app.room_bookings') }} ({{ $forecastDays }} {{ __('app.day') }})</p>
            </div>
        </section>

        {{-- WEATHER INSIGHTS (only shown when weather data is available) --}}
        @if(!empty($weatherInsight))
            <div class="bg-white border border-[#d4dfc8] rounded-2xl p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[#2d3a24] mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ __('app.weather_impact') }}
                </h3>
                <div class="space-y-3">
                    @foreach($weatherInsight as $insight)
                        <div class="flex items-start gap-3 p-4 rounded-lg {{ $insight['type'] === 'warning' ? 'bg-yellow-50 border border-yellow-200' : 'bg-green-50 border border-green-200' }}">
                            <span class="text-2xl">{{ $insight['icon'] }}</span>
                            <p class="text-sm {{ $insight['type'] === 'warning' ? 'text-yellow-800' : 'text-green-800' }} flex-1">
                                {{ $insight['message'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- FORECAST CHART --}}
        @if($roomForecast || $vehicleForecast)
            <div class="bg-white border border-[#d4dfc8] p-6 rounded-2xl shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-[#2d3a24]">{{ __('app.occupancy_forecast_chart') }}</h3>
                    <p class="text-sm text-[#7a8f6a] mt-1">
                        {{ $forecastDays }}-{{ __('app.day') }} {{ __('app.prediction_based_on_history') }}
                        ({{ $isLSTMAvailable ? __('app.lstm_model') : __('app.statistical_model') }})
                    </p>
                </div>
                <div wire:ignore style="position: relative; height: 400px;">
                    <canvas id="occupancyChart"></canvas>
                </div>
            </div>
        @endif

        {{-- WEATHER MINI CARDS (only shown when weather data is available) --}}
        @if(!empty($weather['forecast']))
            <div class="bg-white border border-[#d4dfc8] rounded-2xl p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[#2d3a24] mb-4">{{ __('app.3day_weather') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @foreach($weather['forecast'] as $day)
                        <div class="flex items-center gap-4 p-4 bg-[#f0f4eb] rounded-lg">
                            <span class="text-4xl">{{ $day['weather_icon'] }}</span>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-[#2d3a24]">{{ $day['date_label'] }}</p>
                                <p class="text-xs text-[#7a8f6a]">{{ $day['summary']['weather_desc'] ?? '—' }}</p>
                                <p class="text-lg font-bold text-[#2d3a24] mt-1">{{ $day['max_temp'] }}°C</p>
                                <p class="text-xs text-[#9aaa8a]">🌧️ {{ $day['rain_chance'] }}% {{ __('app.rain') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-[#9aaa8a] mt-4 text-center">
                    Data from <a href="https://data.bmkg.go.id" target="_blank" class="underline">BMKG</a>
                </p>
            </div>
        @endif

    </main>
</div>

@if($roomForecast || $vehicleForecast)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() { initChart(); });

    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', ({ el, component }) => {
            setTimeout(() => initChart(), 150);
        });
    });

    function initChart() {
        const ctx = document.getElementById('occupancyChart');
        if (!ctx) return;

        if (window.occupancyChart && typeof window.occupancyChart.destroy === 'function') {
            window.occupancyChart.destroy();
        }

        const datasets = [];
        const roomData = @json($chartData['roomData']);
        const vehicleData = @json($chartData['vehicleData']);

        if (roomData && roomData.some(v => v !== null)) {
            datasets.push({
                label: '{{ __('app.room_bookings') }}',
                data: roomData,
                borderColor: '#4E653D',
                backgroundColor: 'rgba(78, 101, 61, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6,
            });
        }

        if (vehicleData && vehicleData.length > 0) {
            datasets.push({
                label: '{{ __('app.vehicle_bookings') }}',
                data: vehicleData,
                borderColor: '#4A2F24',
                backgroundColor: 'rgba(74, 47, 36, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6,
            });
        }

        window.occupancyChart = new Chart(ctx, {
            type: 'line',
            data: { labels: @json($chartData['labels']), datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(1)
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: '{{ __('app.reservations') }}' } },
                    x: { title: { display: true, text: '{{ __('app.date_label') }}' } }
                }
            }
        });
    }
</script>
@endpush
@endif
