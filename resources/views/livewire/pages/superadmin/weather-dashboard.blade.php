<div class="min-h-screen bg-[#f5f7f2]">
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-8">

        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.weather_title') }}"
            subtitle="3-{{ __('app.day') }} forecast for {{ $weather['location']['kotkab'] ?? 'Kota Bogor' }}, {{ $weather['location']['kecamatan'] ?? '' }} — Source: BMKG">
            <x-slot:actions>
                <button wire:click="refreshWeather"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-[#CDDEA7]/20 text-[#CDDEA7] border border-[#CDDEA7]/30 hover:bg-[#CDDEA7]/30 transition">
                    <svg wire:loading.remove wire:target="refreshWeather" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <svg wire:loading wire:target="refreshWeather" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    {{ __('app.refresh') }}
                </button>
            </x-slot:actions>
        </x-page-header>

        @if(!$weather)
            {{-- UNAVAILABLE --}}
            <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-6 text-yellow-800">
                <p class="font-semibold">{{ __('app.weather_unavailable') }}</p>
                <p class="text-sm mt-1">{{ __('app.weather_check_connection') }}</p>
            </div>
        @else

            {{-- 3-DAY FORECAST CARDS --}}
            <section class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @foreach($weather['forecast'] as $i => $day)
                    <div wire:click="selectDay({{ $i }})"
                        class="cursor-pointer bg-white border border-[#d4dfc8] rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-200
                               {{ $selectedDay === $i && $showHourly ? 'ring-2 ring-[#4A2F24]' : '' }}">

                        {{-- Date --}}
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-[#9aaa8a]">{{ $day['date_label'] }}</p>
                                <p class="text-sm text-[#7a8f6a]">{{ date('d M Y', strtotime($day['date'])) }}</p>
                            </div>
                            <span class="text-4xl">{{ $day['weather_icon'] }}</span>
                        </div>

                        {{-- Temp range --}}
                        <div class="mb-3">
                            <span class="text-3xl font-bold text-[#2d3a24]">{{ $day['summary']['temp'] ?? $day['max_temp'] }}°C</span>
                            <span class="text-sm text-[#9aaa8a] ml-2">
                                {{ $day['min_temp'] }}° / {{ $day['max_temp'] }}°
                            </span>
                        </div>

                        {{-- Description --}}
                        <p class="text-sm font-medium text-[#4E653D] mb-4">
                            {{ $day['summary']['weather_desc'] ?? '—' }}
                        </p>

                        {{-- Details grid --}}
                        <div class="grid grid-cols-2 gap-2 text-xs text-[#7a8f6a]">
                            <div class="flex items-center gap-1">
                                💧 <span>{{ $day['avg_humidity'] }}% humidity</span>
                            </div>
                            <div class="flex items-center gap-1">
                                💨 <span>{{ $day['summary']['wind_speed'] ?? '—' }} km/h {{ $day['summary']['wind_dir'] ?? '' }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                🌧️ <span>{{ $day['rain_chance'] }}% rain</span>
                            </div>
                            <div class="flex items-center gap-1">
                                👁️ <span>{{ $day['summary']['visibility'] ?? '—' }}</span>
                            </div>
                        </div>

                        <p class="text-xs text-[#9aaa8a] mt-3 text-center">{{ __('app.click_hourly') }}</p>
                    </div>
                @endforeach
            </section>

            {{-- HOURLY DETAIL PANEL --}}
            @if($showHourly && isset($weather['forecast'][$selectedDay]))
                @php $day = $weather['forecast'][$selectedDay]; @endphp
                <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b bg-[#f0f4eb] flex items-center justify-between">
                        <h3 class="font-semibold text-[#2d3a24]">
                            {{ __('app.hourly_forecast') }} — {{ $day['date_label'] }}, {{ date('d M Y', strtotime($day['date'])) }}
                        </h3>
                        <button wire:click="closeHourly" class="text-[#9aaa8a] hover:text-[#4E653D] transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-[#f0f4eb] text-[#7a8f6a] uppercase text-xs border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left">{{ __('app.time') }}</th>
                                    <th class="px-6 py-3 text-left">{{ __('app.condition') }}</th>
                                    <th class="px-6 py-3 text-right">{{ __('app.temp') }}</th>
                                    <th class="px-6 py-3 text-right">Humidity</th>
                                    <th class="px-6 py-3 text-right">{{ __('app.wind') }}</th>
                                    <th class="px-6 py-3 text-right">{{ __('app.rain') }}</th>
                                    <th class="px-6 py-3 text-right">{{ __('app.visibility') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e4edd8]">
                                @foreach($day['slots'] as $slot)
                                    <tr class="hover:bg-[#f0f4eb]">
                                        <td class="px-6 py-3 font-medium text-[#2d3a24]">
                                            {{ date('H:i', strtotime($slot['local_datetime'])) }}
                                            <span class="text-xs text-[#9aaa8a] ml-1">{{ $slot['time_label'] }}</span>
                                        </td>
                                        <td class="px-6 py-3 text-[#4E653D]">{{ $slot['weather_desc'] }}</td>
                                        <td class="px-6 py-3 text-right font-semibold text-[#2d3a24]">{{ $slot['temp'] }}°C</td>
                                        <td class="px-6 py-3 text-right text-[#5a6e4a]">{{ $slot['humidity'] }}%</td>
                                        <td class="px-6 py-3 text-right text-[#5a6e4a]">{{ $slot['wind_speed'] }} km/h {{ $slot['wind_dir'] }}</td>
                                        <td class="px-6 py-3 text-right text-[#5a6e4a]">{{ $slot['rain_mm'] }} mm</td>
                                        <td class="px-6 py-3 text-right text-[#5a6e4a]">{{ $slot['visibility'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- ATTRIBUTION --}}
            <p class="text-xs text-[#9aaa8a] text-center">
                Weather data provided by
                <a href="https://data.bmkg.go.id" target="_blank" class="underline">BMKG (Badan Meteorologi, Klimatologi, dan Geofisika)</a>.
                Last fetched: {{ $weather['fetched_at'] ?? '—' }} WIB
            </p>

        @endif
    </main>
</div>
