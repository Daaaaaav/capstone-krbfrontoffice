<div class="min-h-screen bg-[#f5f7f2]">
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-8">
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

        <div class="bg-white border border-[#d4dfc8] rounded-2xl p-6 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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

                <div>
                    <label class="block text-sm font-medium text-[#4E653D] mb-2">{{ __('app.forecast_period') }}</label>
                    <div class="flex flex-col sm:flex-row gap-2 items-start">
                        <div class="flex-1 w-full">
                            <x-forecast-date-picker
                                start-key="forecastStartDate"
                                end-key="forecastEndDate"
                                :start-value="$forecastStartDate"
                                :end-value="$forecastEndDate"
                                class="w-full"
                            />
                        </div>
                        <div class="flex gap-2">
                            <button wire:click="setForecastDays(7)"
                                type="button"
                                class="px-3 py-2 rounded-lg text-xs font-medium transition {{ $forecastDays === 7 ? 'bg-[#4A2F24] text-white' : 'bg-[#eef1e8] text-[#4E653D] hover:bg-[#dde4d4]' }}">
                                7d
                            </button>
                            <button wire:click="setForecastDays(14)"
                                type="button"
                                class="px-3 py-2 rounded-lg text-xs font-medium transition {{ $forecastDays === 14 ? 'bg-[#4A2F24] text-white' : 'bg-[#eef1e8] text-[#4E653D] hover:bg-[#dde4d4]' }}">
                                14d
                            </button>
                            <button wire:click="setForecastDays(21)"
                                type="button"
                                class="px-3 py-2 rounded-lg text-xs font-medium transition {{ $forecastDays === 21 ? 'bg-[#4A2F24] text-white' : 'bg-[#eef1e8] text-[#4E653D] hover:bg-[#dde4d4]' }}">
                                21d
                            </button>
                        </div>
                    </div>
                    @if($forecastStartDate && $forecastEndDate)
                        <p class="text-xs text-[#7a8f6a] mt-2">
                            {{ __('app.forecasting') ?? 'Forecasting' }} {{ $forecastDays }} {{ $forecastDays === 1 ? __('app.day') : __('app.days') }}
                            ({{ \Carbon\Carbon::parse($forecastStartDate)->format('M d') }} - {{ \Carbon\Carbon::parse($forecastEndDate)->format('M d, Y') }})
                        </p>
                    @endif
                </div>
            </div>

            <div class="border-t border-[#e8ede2] mt-6"></div>

            <div class="mt-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                    <div>
                        <p class="text-sm font-medium text-[#4E653D]">{{ __('app.training_data_source') }}</p>
                        <p class="text-xs text-[#7a8f6a] mt-0.5">{{ __('app.training_data_source_sub') }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <button wire:click="setTrainingSource('csv_server')"
                        class="flex items-start gap-3 p-4 rounded-xl border-2 text-left transition
                            {{ $trainingSource === 'csv_server'
                                ? 'border-[#4E653D] bg-[#eef1e8]'
                                : 'border-[#d4dfc8] bg-white hover:border-[#9ab08a] hover:bg-[#f5f7f2]' }}">
                        <div class="mt-0.5 flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center
                            {{ $trainingSource === 'csv_server' ? 'bg-[#4E653D] text-white' : 'bg-[#eef1e8] text-[#4E653D]' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-[#2d3a24] flex items-center gap-1.5">
                                {{ __('app.source_csv_server') }}
                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded bg-[#4E653D] text-white leading-none">
                                    {{ __('app.default') }}
                                </span>
                            </p>
                            <p class="text-xs text-[#7a8f6a] mt-0.5 leading-snug">
                                @if(($csvInfo['rows'] ?? 0) > 0)
                                    {{ $csvInfo['rows'] }} {{ __('app.rows') }}
                                    &middot; {{ $csvInfo['start'] }} → {{ $csvInfo['end'] }}
                                @else
                                    {{ __('app.csv_server_desc') }}
                                @endif
                            </p>
                        </div>
                    </button>

                    <button wire:click="setTrainingSource('csv_upload')"
                        class="flex items-start gap-3 p-4 rounded-xl border-2 text-left transition
                            {{ $trainingSource === 'csv_upload'
                                ? 'border-[#4E653D] bg-[#eef1e8]'
                                : 'border-[#d4dfc8] bg-white hover:border-[#9ab08a] hover:bg-[#f5f7f2]' }}">
                        <div class="mt-0.5 flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center
                            {{ $trainingSource === 'csv_upload' ? 'bg-[#4E653D] text-white' : 'bg-[#eef1e8] text-[#4E653D]' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-[#2d3a24]">{{ __('app.source_csv_upload') }}</p>
                            <p class="text-xs text-[#7a8f6a] mt-0.5 leading-snug truncate">
                                {{ $uploadedCsvName ?? __('app.csv_upload_desc') }}
                            </p>
                        </div>
                    </button>

                    <button wire:click="setTrainingSource('live_db')"
                        class="flex items-start gap-3 p-4 rounded-xl border-2 text-left transition
                            {{ $trainingSource === 'live_db'
                                ? 'border-[#4E653D] bg-[#eef1e8]'
                                : 'border-[#d4dfc8] bg-white hover:border-[#9ab08a] hover:bg-[#f5f7f2]' }}">
                        <div class="mt-0.5 flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center
                            {{ $trainingSource === 'live_db' ? 'bg-[#4E653D] text-white' : 'bg-[#eef1e8] text-[#4E653D]' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 7v10c0 2 1.5 3 4 3h8c2.5 0 4-1 4-3V7M4 7c0-2 1.5-3 4-3h8c2.5 0 4 1 4 3M4 7h16M9 11h6m-6 4h6"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-[#2d3a24]">{{ __('app.source_live_db') }}</p>
                            <p class="text-xs text-[#7a8f6a] mt-0.5 leading-snug">{{ __('app.source_live_db_desc') }}</p>
                        </div>
                    </button>
                </div>

                @if($trainingSource === 'csv_upload')
                    <div class="mt-4 p-4 bg-[#f5f7f2] border border-[#d4dfc8] rounded-xl space-y-3">

                        {{-- CSV Format Guidance --}}
                        <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg space-y-2">
                            <p class="text-xs font-semibold text-blue-800 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                                {{ __('app.csv_format_guide_title') }}
                            </p>
                            <ul class="text-xs text-blue-700 space-y-1 pl-5 list-disc">
                                <li>{{ __('app.csv_guide_header_required') }}</li>
                                <li>{{ __('app.csv_guide_columns') }}:
                                    <code class="px-1 py-0.5 bg-blue-100 rounded text-blue-900 font-mono text-[10px]">
                                        date, visitors, docs_packages_received, docs_packages_sent, offline_room_bookings, online_room_bookings, vehicle_bookings
                                    </code>
                                </li>
                                <li>{{ __('app.csv_guide_date_format') }}: <code class="px-1 py-0.5 bg-blue-100 rounded text-blue-900 font-mono text-[10px]">YYYY-MM-DD</code> ({{ __('app.csv_guide_date_example') }}: <code class="font-mono text-[10px]">2024-01-15</code>)</li>
                                <li>{{ __('app.csv_guide_numeric') }}</li>
                                <li>{{ __('app.csv_guide_order') }}</li>
                                <li>{{ __('app.csv_guide_file_type') }}: <code class="font-mono text-[10px]">.csv</code> {{ __('app.csv_guide_or') }} <code class="font-mono text-[10px]">.txt</code>, {{ __('app.csv_guide_max_size') }}: 10 MB</li>
                            </ul>
                            <div class="mt-2 pt-2 border-t border-blue-200">
                                <p class="text-[10px] font-semibold text-blue-700 mb-1">{{ __('app.csv_guide_example') }}:</p>
                                <pre class="text-[10px] font-mono text-blue-800 bg-blue-100 rounded px-2 py-1.5 overflow-x-auto whitespace-pre">date,visitors,docs_packages_received,docs_packages_sent,offline_room_bookings,online_room_bookings,vehicle_bookings
2024-01-01,45,3,2,4,2,1
2024-01-02,52,5,1,6,3,2
2024-01-03,38,2,4,3,1,0</pre>
                            </div>
                        </div>

                        {{-- Error --}}
                        @if($uploadError)
                            <div class="flex items-start gap-2 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-9v4a1 1 0 102 0V9a1 1 0 10-2 0zm0-4a1 1 0 112 0 1 1 0 01-2 0z" clip-rule="evenodd"/>
                                </svg>
                                {{ $uploadError }}
                            </div>
                        @endif

                        {{-- Success --}}
                        @if($uploadSuccess)
                            <div class="flex items-start gap-2 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                {{ $uploadSuccess }}
                            </div>
                        @endif

                        {{-- File input + upload button --}}
                        <form wire:submit.prevent="uploadCsv" class="flex flex-col sm:flex-row gap-2">
                            <div class="flex-1">
                                <input type="file" wire:model="uploadedCsv" accept=".csv,.txt"
                                    class="block w-full text-sm text-[#4E653D]
                                        file:mr-3 file:py-2 file:px-4
                                        file:rounded-lg file:border-0
                                        file:text-sm file:font-medium
                                        file:bg-[#eef1e8] file:text-[#4E653D]
                                        hover:file:bg-[#dde4d4] cursor-pointer"/>
                                @error('uploadedCsv')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                                <div wire:loading wire:target="uploadedCsv"
                                    class="text-xs text-[#7a8f6a] mt-1">{{ __('app.loading') }}</div>
                            </div>
                            <button type="submit"
                                wire:loading.attr="disabled" wire:target="uploadCsv"
                                class="px-5 py-2 rounded-lg text-sm font-medium bg-[#4A2F24] text-white hover:bg-[#3a231b] transition disabled:opacity-60 whitespace-nowrap">
                                <span wire:loading.remove wire:target="uploadCsv">{{ __('app.upload_and_use') }}</span>
                                <span wire:loading wire:target="uploadCsv">{{ __('app.uploading') }}</span>
                            </button>
                        </form>
                    </div>
                @endif

                <div class="mt-3 flex items-center gap-2 text-xs text-[#7a8f6a]">
                    <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span>
                    {{ __('app.currently_using') }}:
                    <span class="font-medium text-[#2d3a24]">
                        @if($trainingSource === 'csv_server')   {{ __('app.source_csv_server') }}
                        @elseif($trainingSource === 'csv_upload') {{ __('app.source_csv_upload') }}{{ $uploadedCsvName ? ' — ' . $uploadedCsvName : '' }}
                        @else {{ __('app.source_live_db') }}
                        @endif
                    </span>
                </div>

            </div>
        </div>

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

        <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-[#f0f4eb] border-b border-[#d4dfc8] flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-[#4E653D] text-white flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-[#2d3a24]">{{ __('app.model_performance_title') }}</h3>
                    <p class="text-xs text-[#7a8f6a] mt-0.5">{{ __('app.model_performance_subtitle') }}</p>
                </div>
            </div>

            @php $m = $modelMetrics ?? []; $hasMetrics = !empty($m) && ($m['available'] ?? false); @endphp

            @if($hasMetrics)
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div class="bg-[#f5f7f2] rounded-xl p-4 border border-[#e2e8da]">
                            <p class="text-xs font-medium text-[#7a8f6a] mb-1">{{ __('app.mp_last_trained') }}</p>
                            <p class="text-sm font-bold text-[#2d3a24] leading-snug">
                                @if(!empty($m['trained_at']))
                                    {{ \Carbon\Carbon::parse($m['trained_at'])->format('d M Y') }}<br>
                                    <span class="font-normal text-[#5a6e4a]">{{ \Carbon\Carbon::parse($m['trained_at'])->format('H:i') }}</span>
                                @else —
                                @endif
                            </p>
                        </div>

                        <div class="bg-[#f5f7f2] rounded-xl p-4 border border-[#e2e8da]">
                            <p class="text-xs font-medium text-[#7a8f6a] mb-1">{{ __('app.mp_epochs') }}</p>
                            <p class="text-2xl font-bold text-[#2d3a24]">{{ $m['epochs_run'] ?? '—' }}</p>
                        </div>

                        <div class="bg-[#f5f7f2] rounded-xl p-4 border border-[#e2e8da]">
                            <p class="text-xs font-medium text-[#7a8f6a] mb-1">{{ __('app.mp_training_loss') }}</p>
                            <p class="text-2xl font-bold text-[#2d3a24]">
                                {{ isset($m['training_loss']) && $m['training_loss'] !== null ? number_format((float)$m['training_loss'], 4) : '—' }}
                            </p>
                        </div>

                        <div class="bg-[#f5f7f2] rounded-xl p-4 border border-[#e2e8da]">
                            <p class="text-xs font-medium text-[#7a8f6a] mb-1">{{ __('app.mp_validation_loss') }}</p>
                            <p class="text-2xl font-bold text-[#2d3a24]">
                                {{ isset($m['validation_loss']) && $m['validation_loss'] !== null ? number_format((float)$m['validation_loss'], 4) : '—' }}
                            </p>
                        </div>

                        <div class="bg-[#f5f7f2] rounded-xl p-4 border border-[#e2e8da]">
                            <p class="text-xs font-medium text-[#7a8f6a] mb-1">{{ __('app.mp_mae') }}</p>
                            <p class="text-2xl font-bold text-[#2d3a24]">
                                {{ isset($m['mae']) && $m['mae'] !== null ? number_format((float)$m['mae'], 4) : '—' }}
                            </p>
                            <p class="text-[10px] text-[#9aaa8a] mt-0.5">{{ __('app.mp_bookings_unit') }}</p>
                        </div>

                        <div class="bg-[#f5f7f2] rounded-xl p-4 border border-[#e2e8da]">
                            <p class="text-xs font-medium text-[#7a8f6a] mb-1">{{ __('app.mp_rmse') }}</p>
                            <p class="text-2xl font-bold text-[#2d3a24]">
                                {{ isset($m['rmse']) && $m['rmse'] !== null ? number_format((float)$m['rmse'], 4) : '—' }}
                            </p>
                            <p class="text-[10px] text-[#9aaa8a] mt-0.5">{{ __('app.mp_bookings_unit') }}</p>
                        </div>

                        @if(isset($m['smape']) && $m['smape'] !== null)
                        <div class="bg-[#f5f7f2] rounded-xl p-4 border border-[#e2e8da]">
                            <p class="text-xs font-medium text-[#7a8f6a] mb-1">{{ __('app.mp_smape') }}</p>
                            <p class="text-2xl font-bold text-[#2d3a24]">{{ number_format((float)$m['smape'], 2) }}%</p>
                            <p class="text-[10px] text-[#9aaa8a] mt-0.5">{{ __('app.mp_smape_hint') }}</p>
                        </div>
                        @elseif(isset($m['mape']) && $m['mape'] !== null)
                        <div class="bg-[#f5f7f2] rounded-xl p-4 border border-[#e2e8da]">
                            <p class="text-xs font-medium text-[#7a8f6a] mb-1">{{ __('app.mp_mape') }}</p>
                            <p class="text-2xl font-bold text-[#2d3a24]">{{ number_format((float)$m['mape'], 2) }}%</p>
                        </div>
                        @endif

                        @if(isset($m['wape']) && $m['wape'] !== null)
                        <div class="bg-[#f5f7f2] rounded-xl p-4 border border-[#e2e8da]">
                            <p class="text-xs font-medium text-[#7a8f6a] mb-1">{{ __('app.mp_wape') }}</p>
                            <p class="text-2xl font-bold text-[#2d3a24]">{{ number_format((float)$m['wape'], 2) }}%</p>
                            <p class="text-[10px] text-[#9aaa8a] mt-0.5">{{ __('app.mp_wape_hint') }}</p>
                        </div>
                        @endif

                        @if(isset($m['r2']) && $m['r2'] !== null)
                        <div class="bg-[#f5f7f2] rounded-xl p-4 border border-[#e2e8da]">
                            <p class="text-xs font-medium text-[#7a8f6a] mb-1">{{ __('app.mp_r2') }}</p>
                            <p class="text-2xl font-bold text-[#2d3a24]">{{ number_format((float)$m['r2'], 4) }}</p>
                        </div>
                        @endif

                        @if(isset($m['best_val_loss']) && $m['best_val_loss'] !== null)
                        <div class="bg-[#f5f7f2] rounded-xl p-4 border border-[#e2e8da]">
                            <p class="text-xs font-medium text-[#7a8f6a] mb-1">{{ __('app.mp_best_val_loss') }}</p>
                            <p class="text-2xl font-bold text-[#2d3a24]">{{ number_format((float)$m['best_val_loss'], 6) }}</p>
                        </div>
                        @endif

                        @if(isset($m['early_stop_epoch']) && $m['early_stop_epoch'] !== null)
                        <div class="bg-[#f5f7f2] rounded-xl p-4 border border-[#e2e8da]">
                            <p class="text-xs font-medium text-[#7a8f6a] mb-1">{{ __('app.mp_early_stop_epoch') }}</p>
                            <p class="text-2xl font-bold text-[#2d3a24]">{{ $m['early_stop_epoch'] }}</p>
                            <p class="text-[10px] text-[#9aaa8a] mt-0.5">{{ __('app.mp_best_epoch_hint') }}</p>
                        </div>
                        @endif

                        @if(isset($m['trainable_params']) && $m['trainable_params'] !== null)
                        <div class="bg-[#f5f7f2] rounded-xl p-4 border border-[#e2e8da]">
                            <p class="text-xs font-medium text-[#7a8f6a] mb-1">{{ __('app.mp_trainable_params') }}</p>
                            <p class="text-2xl font-bold text-[#2d3a24]">{{ number_format($m['trainable_params']) }}</p>
                        </div>
                        @endif

                    </div>

                    @php
                        $hasSecondaryOcc = (isset($m['training_time']) && $m['training_time'] !== null)
                            || (isset($m['training_samples']) && $m['training_samples'] !== null)
                            || (isset($m['validation_samples']) && $m['validation_samples'] !== null)
                            || (isset($m['test_samples']) && $m['test_samples'] !== null);
                    @endphp
                    @if($hasSecondaryOcc)
                    <div class="border-t border-[#e8ede2] pt-4">
                        <div class="flex flex-wrap gap-6">
                            @if(isset($m['training_time']) && $m['training_time'] !== null)
                            <div>
                                <p class="text-xs text-[#7a8f6a]">{{ __('app.mp_training_time') }}</p>
                                <p class="text-sm font-semibold text-[#2d3a24] mt-0.5">{{ $m['training_time'] }}s</p>
                            </div>
                            @endif
                            @if(isset($m['training_samples']) && $m['training_samples'] !== null)
                            <div>
                                <p class="text-xs text-[#7a8f6a]">{{ __('app.mp_training_samples') }}</p>
                                <p class="text-sm font-semibold text-[#2d3a24] mt-0.5">{{ number_format($m['training_samples']) }}</p>
                            </div>
                            @endif
                            @if(isset($m['validation_samples']) && $m['validation_samples'] !== null)
                            <div>
                                <p class="text-xs text-[#7a8f6a]">{{ __('app.mp_validation_samples') }}</p>
                                <p class="text-sm font-semibold text-[#2d3a24] mt-0.5">{{ number_format($m['validation_samples']) }}</p>
                            </div>
                            @endif
                            @if(isset($m['test_samples']) && $m['test_samples'] !== null)
                            <div>
                                <p class="text-xs text-[#7a8f6a]">{{ __('app.mp_test_samples') }}</p>
                                <p class="text-sm font-semibold text-[#2d3a24] mt-0.5">{{ number_format($m['test_samples']) }}</p>
                            </div>
                            @endif
                            @if(!empty($m['from_cache']))
                            <div>
                                <p class="text-xs text-[#7a8f6a]">{{ __('app.mp_cache_status') }}</p>
                                <span class="inline-flex items-center gap-1 mt-0.5 text-xs font-medium px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">
                                    {{ __('app.mp_loaded_from_cache') }}
                                </span>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if(!empty($m['loss_history']) && !empty($m['val_loss_history']))
                    <div class="border-t border-[#e8ede2] pt-4">
                        <h4 class="text-sm font-semibold text-[#2d3a24] mb-3">{{ __('app.mp_loss_curve') }}</h4>
                        <div wire:ignore style="position: relative; height: 220px;">
                            <canvas id="lossCurveChartOcc"></canvas>
                        </div>
                    </div>
                    @endif

                    @if(!empty($m['hyperparameters']))
                    <div class="border-t border-[#e8ede2] pt-4">
                        <h4 class="text-sm font-semibold text-[#2d3a24] mb-3">{{ __('app.mp_hyperparameters') }}</h4>
                        @php $hp = $m['hyperparameters']; @endphp
                        <div class="flex flex-wrap gap-x-6 gap-y-3">
                            @if(isset($hp['lstm_units']))
                            <div><p class="text-xs text-[#7a8f6a]">{{ __('app.mp_hp_lstm_units') }}</p><p class="text-sm font-semibold text-[#2d3a24] mt-0.5">{{ $hp['lstm_units'] }}</p></div>
                            @endif
                            @if(isset($hp['sequence_window']))
                            <div><p class="text-xs text-[#7a8f6a]">{{ __('app.mp_hp_seq_window') }}</p><p class="text-sm font-semibold text-[#2d3a24] mt-0.5">{{ $hp['sequence_window'] }}</p></div>
                            @endif
                            @if(isset($hp['dropout_rate']))
                            <div><p class="text-xs text-[#7a8f6a]">{{ __('app.mp_hp_dropout') }}</p><p class="text-sm font-semibold text-[#2d3a24] mt-0.5">{{ $hp['dropout_rate'] }}</p></div>
                            @endif
                            @if(isset($hp['batch_size']))
                            <div><p class="text-xs text-[#7a8f6a]">{{ __('app.mp_hp_batch_size') }}</p><p class="text-sm font-semibold text-[#2d3a24] mt-0.5">{{ $hp['batch_size'] }}</p></div>
                            @endif
                            @if(isset($hp['validation_split']))
                            <div><p class="text-xs text-[#7a8f6a]">{{ __('app.mp_hp_val_split') }}</p><p class="text-sm font-semibold text-[#2d3a24] mt-0.5">{{ $hp['validation_split'] * 100 }}%</p></div>
                            @endif
                            @if(isset($hp['early_stop_patience']))
                            <div><p class="text-xs text-[#7a8f6a]">{{ __('app.mp_hp_patience') }}</p><p class="text-sm font-semibold text-[#2d3a24] mt-0.5">{{ $hp['early_stop_patience'] }}</p></div>
                            @endif
                            @if(isset($hp['optimizer']))
                            <div><p class="text-xs text-[#7a8f6a]">{{ __('app.mp_hp_optimizer') }}</p><p class="text-sm font-semibold text-[#2d3a24] mt-0.5">{{ $hp['optimizer'] }}</p></div>
                            @endif
                            @if(isset($hp['loss_fn']))
                            <div><p class="text-xs text-[#7a8f6a]">{{ __('app.mp_hp_loss_fn') }}</p><p class="text-sm font-semibold text-[#2d3a24] mt-0.5">{{ $hp['loss_fn'] }}</p></div>
                            @endif
                        </div>
                    </div>
                    @endif

                </div>
            @else
                <div class="p-10 text-center">
                    <svg class="w-10 h-10 text-[#b5c4a5] mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm font-medium text-[#5a6e4a]">{{ __('app.mp_no_metrics') }}</p>
                    <p class="text-xs text-[#9aaa8a] mt-1">{{ __('app.mp_no_metrics_hint') }}</p>
                </div>
            @endif
        </div>

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

        const labels      = @json($chartData['labels']);
        const roomData    = @json($chartData['roomData']);
        const vehicleData = @json($chartData['vehicleData']);

        console.group('[OccupancyForecasting Debug] Chart vs Prediction data');
        console.log('Label count   :', labels.length);
        console.log('First label   :', labels[0] ?? 'none');
        console.log('Last label    :', labels[labels.length - 1] ?? 'none');
        console.log('Labels sample :', labels.slice(0, 5));
        console.log('Room data pts :', roomData ? roomData.filter(v => v !== null).length : 0);
        console.log('Veh data pts  :', vehicleData ? vehicleData.filter(v => v !== null).length : 0);
        console.groupEnd();

        const datasets = [];

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
            data: { labels: labels, datasets },
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

@php $occLossHistory = $modelMetrics['loss_history'] ?? []; $occValLossHistory = $modelMetrics['val_loss_history'] ?? []; @endphp
@if(!empty($occLossHistory) && !empty($occValLossHistory))
@push('scripts')
<script>
(function () {
    function buildOccLossCurve() {
        const ctx = document.getElementById('lossCurveChartOcc');
        if (!ctx) return;
        if (window.lossCurveChartOcc && typeof window.lossCurveChartOcc.destroy === 'function') {
            window.lossCurveChartOcc.destroy();
        }
        const epochs = Array.from({ length: @json(count($occLossHistory)) }, (_, i) => i + 1);
        window.lossCurveChartOcc = new Chart(ctx, {
            type: 'line',
            data: {
                labels: epochs,
                datasets: [
                    {
                        label: '{{ __('app.mp_training_loss') }}',
                        data: @json($occLossHistory),
                        borderColor: '#4E653D',
                        backgroundColor: 'rgba(78,101,61,0.08)',
                        borderWidth: 2, fill: true, tension: 0.3, pointRadius: 0, pointHoverRadius: 4,
                    },
                    {
                        label: '{{ __('app.mp_validation_loss') }}',
                        data: @json($occValLossHistory),
                        borderColor: '#4A2F24',
                        backgroundColor: 'rgba(74,47,36,0.05)',
                        borderWidth: 2, fill: false, tension: 0.3, pointRadius: 0, pointHoverRadius: 4, borderDash: [4,3],
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: { callbacks: { label: c => c.dataset.label + ': ' + c.parsed.y.toFixed(6) } }
                },
                scales: {
                    x: { title: { display: true, text: '{{ __('app.mp_epoch') }}', font: { size: 11 } }, ticks: { maxTicksLimit: 10 } },
                    y: { title: { display: true, text: 'Loss (MSE)', font: { size: 11 } }, beginAtZero: false }
                }
            }
        });
    }
    document.addEventListener('DOMContentLoaded', buildOccLossCurve);
    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', ({ el, component }) => setTimeout(buildOccLossCurve, 150));
    });
})();
</script>
@endpush
@endif
