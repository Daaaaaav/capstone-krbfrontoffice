<div class="min-h-screen bg-[#f5f7f2]" @if($autoRefresh) wire:poll.5s @endif>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-8">
        {{-- HEADER --}}
        <x-page-header
            title="{{ __('app.security_reports_title') }}"
            subtitle="{{ __('app.security_reports_sub') }}">
            <x-slot:actions>
                <button wire:click="toggleAutoRefresh"
                    class="px-4 py-2 rounded-xl text-sm font-medium transition {{ $autoRefresh ? 'bg-green-500/20 text-green-300 border border-green-400/30 hover:bg-green-500/30' : 'bg-[#CDDEA7]/20 text-[#CDDEA7] border border-[#CDDEA7]/30 hover:bg-[#CDDEA7]/30' }}">
                    {{ $autoRefresh ? __('app.live') : __('app.paused_btn') }}
                </button>
            </x-slot:actions>
        </x-page-header>

        {{-- STATS --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($stats as $stat)
                <div class="bg-white border border-[#d4dfc8] rounded-2xl p-5 shadow-sm hover:shadow-lg transition">
                    <p class="text-sm font-medium text-[#7a8f6a]">{{ $stat['label'] }}</p>
                    <h2 class="text-3xl font-bold mt-2 {{ [
                        'blue' => 'text-blue-600',
                        'red' => 'text-red-600',
                        'yellow' => 'text-yellow-600',
                        'green' => 'text-green-600',
                    ][$stat['color']] }}">{{ $stat['value'] }}</h2>
                </div>
            @endforeach
        </section>

        {{-- SOURCE STATUS --}}
        <section class="bg-white border border-[#d4dfc8] rounded-2xl p-5 shadow-sm flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-[#7a8f6a]">{{ __('app.data_source') }}</p>
                <p class="text-sm text-[#2d3a24]">
                    {{ $source_label }}
                    @if(isset($source) && $source)
                        <span class="text-[#7a8f6a]">• {{ $source }}</span>
                    @endif
                </p>
            </div>
            <div class="text-sm text-[#7a8f6a]">
                @if(isset($last_updated) && $last_updated)
                    {{ __('app.last_updated') }} {{ $last_updated }}
                @else
                    {{ __('app.source_unavailable') }}
                @endif
            </div>
        </section>

        {{-- SIEM CONNECTION --}}
        <section class="bg-white border border-[#d4dfc8] rounded-2xl p-5 shadow-sm">

            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-[#7a8f6a]">
                        {{ __('app.siem_connection') }}
                    </p>

                    <p class="text-sm text-[#2d3a24] mt-1">
                        {{ $source_label }}

                        @if($source_host)
                            <span class="text-[#7a8f6a]">
                                • {{ $source_host }}
                            </span>
                        @endif
                    </p>
                </div>

                <div>
                    @if($available)
                        <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">
                            {{ __('app.connected') }}
                        </span>
                    @else
                        <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">
                            {{ __('app.offline') }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- DEBUG ENDPOINTS --}}
            @if(!empty($api_endpoints))
                <div class="mt-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#9aaa8a] mb-2">
                        {{ __('app.available_endpoints') }}
                    </p>

                    <div class="flex flex-wrap gap-2">
                        @foreach($api_endpoints as $endpoint)
                            <span class="px-3 py-1 rounded-lg bg-[#eef1e8] text-[#4E653D] text-xs font-mono">
                                {{ $endpoint }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

        </section>

        {{-- FILTER BUTTONS --}}
        <div class="flex flex-wrap gap-3">
            <button wire:click="setSeverity('all')"
                class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $selectedSeverity === 'all' ? 'bg-[#4A2F24] text-white' : 'bg-white border border-[#d4dfc8] text-[#4E653D] hover:bg-[#f0f4eb]' }}">
                {{ __('app.all') }}
            </button>
            <button wire:click="setSeverity('high')"
                class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $selectedSeverity === 'high' ? 'bg-red-600 text-white' : 'bg-white border border-[#d4dfc8] text-[#4E653D] hover:bg-[#f0f4eb]' }}">
                🔴 {{ __('app.severity_high') }}
            </button>
            <button wire:click="setSeverity('medium')"
                class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $selectedSeverity === 'medium' ? 'bg-yellow-500 text-white' : 'bg-white border border-[#d4dfc8] text-[#4E653D] hover:bg-[#f0f4eb]' }}">
                🟡 {{ __('app.severity_medium') }}
            </button>
            <button wire:click="setSeverity('low')"
                class="px-4 py-2 rounded-lg text-sm font-medium transition {{ $selectedSeverity === 'low' ? 'bg-green-600 text-white' : 'bg-white border border-[#d4dfc8] text-[#4E653D] hover:bg-[#f0f4eb]' }}">
                🟢 {{ __('app.severity_low') }}
            </button>
        </div>

        {{-- ALERTS LIST --}}
        <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">
            @forelse($alerts as $alert)
                <div class="border-b border-[#d4dfc8] last:border-b-0 p-5 hover:bg-[#f0f4eb] transition">
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <span class="px-3 py-1 rounded-full text-xs font-medium {{ [
                                'high' => 'bg-red-100 text-red-700',
                                'medium' => 'bg-yellow-100 text-yellow-700',
                                'low' => 'bg-green-100 text-green-700',
                            ][$alert['severity']] }}">
                                {{ $alert['severity_label'] }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-[#2d3a24]">{{ $alert['title'] }}</p>
                                    @if($alert['rule_id'])
                                        <span class="text-xs px-2 py-1 rounded-full bg-[#eef1e8] text-[#5a6e4a]">{{ __('app.rule_label') }} {{ $alert['rule_id'] }}</span>
                                    @endif
                                </div>
                                <p class="text-sm text-[#5a6e4a] mt-1 break-words">{{ $alert['message'] }}</p>
                                <div class="mt-3 flex flex-wrap gap-2 text-xs text-[#7a8f6a]">
                                    @foreach($alert['details'] as $detail)
                                        <span class="px-2 py-1 rounded-full bg-[#eef1e8]">{{ $detail }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="text-sm text-[#7a8f6a] lg:text-right">
                            <div>{{ $alert['timestamp'] ?? __('app.live_entry') }}</div>
                            <div class="text-xs mt-1 uppercase tracking-wide">{{ $alert['severity'] }} {{ __('app.severity_label') }}</div>
                        </div>
                    </div>
                    <details class="mt-4">
                        <summary class="cursor-pointer text-sm text-[#4E653D] hover:text-[#354C2B]">{{ __('app.show_raw_log') }}</summary>
                        <pre class="mt-3 overflow-x-auto rounded-xl bg-[#2d3a24] text-[#CDDEA7] text-xs leading-6 p-4 whitespace-pre-wrap">{{ $alert['raw'] }}</pre>
                    </details>
                </div>
            @empty
                <div class="p-8 text-center text-[#7a8f6a]">
                    @if($available)
                        {{ __('app.no_alerts') }}
                    @else
                        {{ __('app.no_wazuh_source') }}
                    @endif
                </div>
            @endforelse
        </div>
    </main>
</div>
