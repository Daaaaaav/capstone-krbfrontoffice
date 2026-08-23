<div
    class="min-h-screen bg-[#f5f7f2]"
    @if($autoRefresh) wire:poll.30s="pollRefresh" @endif
>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-8">

        {{-- ================================================================
             HEADER
        ================================================================ --}}
        <x-page-header
            title="{{ __('app.security_reports_title') }}"
            subtitle="{{ __('app.security_reports_sub') }}"
        >
            <x-slot:actions>
                {{-- Auto-refresh toggle --}}
                <button
                    type="button"
                    wire:click="toggleAutoRefresh"
                    class="px-4 py-2 rounded-xl text-sm font-medium transition
                        {{ $autoRefresh
                            ? 'bg-green-500/20 text-green-700 border border-green-400/40 hover:bg-green-500/30'
                            : 'bg-[#CDDEA7]/20 text-[#4E653D] border border-[#CDDEA7]/40 hover:bg-[#CDDEA7]/30' }}"
                    title="{{ $autoRefresh ? 'Click to pause auto-refresh' : 'Click to enable auto-refresh' }}"
                >
                    {{ $autoRefresh ? __('app.live') : __('app.paused_btn') }}
                </button>

                {{-- Manual refresh --}}
                <button
                    type="button"
                    wire:click="refreshAlerts"
                    wire:loading.attr="disabled"
                    wire:target="refreshAlerts,pollRefresh"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium
                           bg-white border border-[#d4dfc8] text-[#4E653D]
                           hover:bg-[#f0f4eb] transition disabled:opacity-60"
                >
                    <svg
                        wire:loading
                        wire:target="refreshAlerts,pollRefresh"
                        class="w-4 h-4 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8v8z"
                        />
                    </svg>

                    <svg
                        wire:loading.remove
                        wire:target="refreshAlerts,pollRefresh"
                        class="w-4 h-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                        />
                    </svg>

                    <span
                        wire:loading.remove
                        wire:target="refreshAlerts,pollRefresh"
                    >
                        {{ __('app.refresh') }}
                    </span>

                    <span
                        wire:loading
                        wire:target="refreshAlerts,pollRefresh"
                    >
                        {{ __('app.loading') }}
                    </span>
                </button>
            </x-slot:actions>
        </x-page-header>


        {{-- ================================================================
             CONNECTION STATUS BANNER
        ================================================================ --}}
        @if(!$wazuhAvailable)
            <div
                class="flex items-start gap-3 px-5 py-4 bg-amber-50
                       border border-amber-300 rounded-2xl shadow-sm"
            >
                <svg
                    class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>

                <div>
                    <p class="text-sm font-semibold text-amber-800">
                        Security monitoring is temporarily unavailable.
                    </p>

                    <p class="text-xs text-amber-700 mt-0.5">
                        Could not reach the Wazuh Indexer. The page will automatically retry every 30 seconds.

                        @if($lastUpdated)
                            Last attempted: {{ $lastUpdated }}
                        @endif
                    </p>
                </div>
            </div>
        @endif


        {{-- ================================================================
             SUMMARY CARDS
        ================================================================ --}}
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">

            {{-- Total --}}
            <div class="bg-white border border-[#d4dfc8] rounded-2xl p-5 shadow-sm hover:shadow-md transition">
                <p class="text-xs font-medium text-[#7a8f6a] uppercase tracking-wide">
                    Total Alerts
                </p>

                <h2 class="text-3xl font-bold text-blue-600 mt-2">
                    {{ $summary['total'] ?? 0 }}
                </h2>

                <p class="text-[10px] text-[#9aaa8a] mt-1">
                    {{ __('app.cumulative_from_log') }}
                </p>
            </div>

            {{-- Critical --}}
            <div class="bg-white border border-red-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition">
                <p class="text-xs font-medium text-red-500 uppercase tracking-wide">
                    Critical
                </p>

                <h2 class="text-3xl font-bold text-red-600 mt-2">
                    {{ $summary['critical'] ?? 0 }}
                </h2>

                <p class="text-[10px] text-[#9aaa8a] mt-1">
                    Level &ge; 12
                </p>
            </div>

            {{-- High --}}
            <div class="bg-white border border-orange-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition">
                <p class="text-xs font-medium text-orange-500 uppercase tracking-wide">
                    {{ __('app.severity_high') }}
                </p>

                <h2 class="text-3xl font-bold text-orange-500 mt-2">
                    {{ $summary['high'] ?? 0 }}
                </h2>

                <p class="text-[10px] text-[#9aaa8a] mt-1">
                    Level 9 – 11
                </p>
            </div>

            {{-- Medium --}}
            <div class="bg-white border border-yellow-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition">
                <p class="text-xs font-medium text-yellow-600 uppercase tracking-wide">
                    {{ __('app.severity_medium') }}
                </p>

                <h2 class="text-3xl font-bold text-yellow-500 mt-2">
                    {{ $summary['medium'] ?? 0 }}
                </h2>

                <p class="text-[10px] text-[#9aaa8a] mt-1">
                    Level 6 – 8
                </p>
            </div>

            {{-- Low --}}
            <div class="bg-white border border-green-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition">
                <p class="text-xs font-medium text-green-600 uppercase tracking-wide">
                    {{ __('app.severity_low') }}
                </p>

                <h2 class="text-3xl font-bold text-green-600 mt-2">
                    {{ $summary['low'] ?? 0 }}
                </h2>

                <p class="text-[10px] text-[#9aaa8a] mt-1">
                    Level 1 – 5
                </p>
            </div>
        </section>


        {{-- ================================================================
             CONNECTION STATUS
        ================================================================ --}}
        <section
            class="bg-white border border-[#d4dfc8] rounded-2xl px-5 py-4
                   flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-sm"
        >
            <div class="flex items-center gap-3">
                <span
                    class="w-2.5 h-2.5 rounded-full
                    {{ $wazuhAvailable ? 'bg-green-400' : 'bg-red-400' }}"
                ></span>

                <div>
                    <p class="text-sm font-medium text-[#2d3a24]">
                        {{ __('app.siem_connection') }}

                        <span
                            class="ml-2 px-2 py-0.5 rounded-full text-xs font-semibold
                            {{ $wazuhAvailable
                                ? 'bg-green-100 text-green-700'
                                : 'bg-red-100 text-red-700' }}"
                        >
                            {{ $wazuhAvailable ? __('app.connected') : __('app.offline') }}
                        </span>
                    </p>

                    <p class="text-xs text-[#7a8f6a] mt-0.5">
                        Wazuh Indexer &middot; wazuh-alerts-* index
                    </p>
                </div>
            </div>

            @if($lastUpdated)
                <p class="text-xs text-[#9aaa8a] sm:text-right shrink-0">
                    {{ __('app.last_updated') }}<br>

                    <span class="font-medium text-[#5a6e4a]">
                        {{ $lastUpdated }}
                    </span>
                </p>
            @endif
        </section>


        {{-- ================================================================
             SEVERITY FILTERS
        ================================================================ --}}
        <div class="flex flex-wrap gap-2">
            @foreach([
                [
                    'key' => 'all',
                    'label' => __('app.all'),
                    'active' => 'bg-[#4A2F24] text-white',
                    'dot' => '',
                ],
                [
                    'key' => 'critical',
                    'label' => 'Critical',
                    'active' => 'bg-red-600 text-white',
                    'dot' => '🔴 ',
                ],
                [
                    'key' => 'high',
                    'label' => __('app.severity_high'),
                    'active' => 'bg-orange-500 text-white',
                    'dot' => '🟠 ',
                ],
                [
                    'key' => 'medium',
                    'label' => __('app.severity_medium'),
                    'active' => 'bg-yellow-500 text-white',
                    'dot' => '🟡 ',
                ],
                [
                    'key' => 'low',
                    'label' => __('app.severity_low'),
                    'active' => 'bg-green-600 text-white',
                    'dot' => '🟢 ',
                ],
            ] as $btn)
                <button
                    type="button"
                    wire:click="setSeverity('{{ $btn['key'] }}')"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition
                        {{ $selectedSeverity === $btn['key']
                            ? $btn['active']
                            : 'bg-white border border-[#d4dfc8] text-[#4E653D] hover:bg-[#f0f4eb]' }}"
                >
                    {{ $btn['dot'] }}{{ $btn['label'] }}

                    @if($btn['key'] !== 'all' && isset($summary[$btn['key']]))
                        <span class="ml-1 text-xs opacity-75">
                            ({{ $summary[$btn['key']] }})
                        </span>
                    @endif
                </button>
            @endforeach
        </div>


        {{-- ================================================================
             ALERTS TABLE
        ================================================================ --}}
        <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">

            {{-- Loading state --}}
            <div
                wire:loading
                wire:target="refreshAlerts,pollRefresh,setSeverity"
                class="px-5 py-3 bg-blue-50 border-b border-blue-100
                       flex items-center gap-2 text-sm text-blue-700"
            >
                <svg
                    class="w-4 h-4 animate-spin flex-shrink-0"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    />
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8v8z"
                    />
                </svg>

                {{ __('app.loading') }}
            </div>


            @if(count($filteredAlerts) > 0)

                {{-- ========================================================
                     DESKTOP
                ======================================================== --}}
                <div class="hidden lg:block overflow-x-auto">
                    <div class="min-w-[1050px]">

                        <div
                            class="grid gap-x-3 px-5 py-3 bg-[#f0f4eb]
                                   border-b border-[#d4dfc8]
                                   text-xs font-semibold uppercase tracking-wide
                                   text-[#7a8f6a]"
                            style="grid-template-columns: 120px 60px minmax(250px, 1fr) 140px 80px 180px 100px 80px;"
                        >
                            <span>Severity</span>
                            <span>Level</span>
                            <span>Description</span>
                            <span>Agent</span>
                            <span>Rule ID</span>
                            <span>Location</span>
                            <span>Time</span>
                            <span class="text-right">{{ __('app.actions') }}</span>
                        </div>


                        @foreach($filteredAlerts as $index => $alert)
                            @php
                                $alertKey = $alert['id']
                                    ?? $alert['alert_id']
                                    ?? md5(
                                        ($alert['timestamp'] ?? '') . '|' .
                                        ($alert['rule_id'] ?? '') . '|' .
                                        ($alert['agent_id'] ?? '') . '|' .
                                        $index
                                    );

                                $isExpanded = ($expandedIndex === $index);

                                $ts = $alert['timestamp'] ?? '';

                                try {
                                    $formatted = $ts
                                        ? \Carbon\Carbon::parse($ts)->format('d M H:i')
                                        : '-';

                                    $fullTime = $ts
                                        ? \Carbon\Carbon::parse($ts)->toDateTimeString()
                                        : '-';
                                } catch (\Throwable $e) {
                                    $formatted = $ts ?: '-';
                                    $fullTime = $ts ?: '-';
                                }
                            @endphp


                            <div
                                wire:key="wazuh-alert-desktop-{{ $alertKey }}"
                                class="border-b border-[#e8ede2] last:border-b-0
                                    {{ $isExpanded ? 'bg-[#f5f7f2]' : 'hover:bg-[#fafbf8]' }}
                                    transition"
                            >
                                <div class="px-5 py-4">
                                    <div
                                        class="grid gap-x-3 items-center"
                                        style="grid-template-columns: 120px 60px minmax(250px, 1fr) 140px 80px 180px 100px 80px;"
                                    >
                                        <span
                                            class="px-2.5 py-1 border rounded-full text-xs
                                                   font-medium text-center {{ $alert['badge_class'] ?? '' }}"
                                        >
                                            {{ $alert['severity_label'] ?? 'Unknown' }}
                                        </span>

                                        <span class="text-sm font-mono font-semibold text-[#4A2F24]">
                                            {{ $alert['rule_level'] ?? '-' }}
                                        </span>

                                        <span
                                            class="text-sm text-[#2d3a24] truncate"
                                            title="{{ $alert['rule_description'] ?? '' }}"
                                        >
                                            {{ $alert['rule_description'] ?? 'Unknown alert' }}
                                        </span>

                                        <span
                                            class="text-xs text-[#5a6e4a] truncate"
                                            title="{{ $alert['agent_name'] ?? '' }}"
                                        >
                                            {{ $alert['agent_name'] ?? 'Unknown' }}
                                        </span>

                                        <span class="text-xs font-mono text-[#7a8f6a]">
                                            {{ $alert['rule_id'] ?? '-' }}
                                        </span>

                                        <span
                                            class="text-xs text-[#7a8f6a] truncate"
                                            title="{{ $alert['location'] ?? '' }}"
                                        >
                                            {{ $alert['location'] ?? '-' }}
                                        </span>

                                        <span
                                            class="text-xs text-[#9aaa8a]"
                                            title="{{ $fullTime }}"
                                        >
                                            {{ $formatted }}
                                        </span>

                                        <div class="text-right">
                                            <button
                                                type="button"
                                                wire:click="toggleDetail({{ $index }})"
                                                class="px-3 py-1.5 rounded-lg text-xs font-medium transition
                                                    {{ $isExpanded
                                                        ? 'bg-[#4A2F24] text-white border-[#4A2F24]'
                                                        : 'bg-white border border-[#d4dfc8] text-[#4E653D] hover:bg-[#f0f4eb]' }}"
                                            >
                                                {{ $isExpanded ? 'Close' : __('app.detail') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>


                                {{-- Desktop details --}}
                                @if($isExpanded)
                                    <div class="px-5 pb-5 border-t border-[#e8ede2] bg-[#f5f7f2]">
                                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                                            <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                                <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                    Severity
                                                </p>

                                                <span class="px-2.5 py-1 border rounded-full text-xs font-medium {{ $alert['badge_class'] ?? '' }}">
                                                    {{ $alert['severity_label'] ?? 'Unknown' }}
                                                </span>
                                            </div>

                                            <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                                <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                    Rule Level
                                                </p>

                                                <p class="text-2xl font-bold text-[#4A2F24]">
                                                    {{ $alert['rule_level'] ?? '-' }}
                                                </p>
                                            </div>

                                            <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                                <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                    {{ __('app.rule_label') }} ID
                                                </p>

                                                <p class="text-sm font-mono text-[#2d3a24]">
                                                    {{ $alert['rule_id'] ?? '-' }}
                                                </p>
                                            </div>

                                            <div class="bg-white border border-[#d4dfc8] rounded-xl p-3 sm:col-span-2 lg:col-span-3">
                                                <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                    Description
                                                </p>

                                                <p class="text-sm text-[#2d3a24]">
                                                    {{ $alert['rule_description'] ?? 'Unknown alert' }}
                                                </p>
                                            </div>

                                            <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                                <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                    Agent Name
                                                </p>

                                                <p class="text-sm text-[#2d3a24]">
                                                    {{ $alert['agent_name'] ?? 'Unknown' }}
                                                </p>
                                            </div>

                                            <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                                <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                    Agent ID
                                                </p>

                                                <p class="text-sm font-mono text-[#2d3a24]">
                                                    {{ $alert['agent_id'] ?? '-' }}
                                                </p>
                                            </div>

                                            <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                                <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                    Agent IP
                                                </p>

                                                <p class="text-sm font-mono text-[#2d3a24]">
                                                    {{ $alert['agent_ip'] ?? '-' }}
                                                </p>
                                            </div>

                                            <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                                <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                    Manager
                                                </p>

                                                <p class="text-sm text-[#2d3a24]">
                                                    {{ $alert['manager_name'] ?? '-' }}
                                                </p>
                                            </div>

                                            <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                                <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                    Decoder
                                                </p>

                                                <p class="text-sm text-[#2d3a24]">
                                                    {{ $alert['decoder_name'] ?? '-' }}
                                                </p>
                                            </div>

                                            <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                                <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                    Location
                                                </p>

                                                <p class="text-sm text-[#2d3a24] break-all">
                                                    {{ $alert['location'] ?? '-' }}
                                                </p>
                                            </div>

                                            <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                                <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                    Timestamp
                                                </p>

                                                <p class="text-sm font-mono text-[#2d3a24]">
                                                    {{ ($alert['timestamp'] ?? '') ?: '-' }}
                                                </p>
                                            </div>

                                            @if(($alert['full_log'] ?? '') !== '')
                                                <div class="bg-white border border-[#d4dfc8] rounded-xl p-3 sm:col-span-2 lg:col-span-3">
                                                    <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-2">
                                                        {{ __('app.show_raw_log') }}
                                                    </p>

                                                    <pre class="overflow-x-auto rounded-lg bg-[#2d3a24] text-[#CDDEA7] text-xs leading-5 p-4 whitespace-pre-wrap break-all">{{ $alert['full_log'] }}</pre>
                                                </div>
                                            @endif

                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>


                {{-- ========================================================
                     MOBILE
                ======================================================== --}}
                <div class="lg:hidden">
                    @foreach($filteredAlerts as $index => $alert)
                        @php
                            $alertKey = $alert['id']
                                ?? $alert['alert_id']
                                ?? md5(
                                    ($alert['timestamp'] ?? '') . '|' .
                                    ($alert['rule_id'] ?? '') . '|' .
                                    ($alert['agent_id'] ?? '') . '|' .
                                    $index
                                );

                            $isExpanded = ($expandedIndex === $index);

                            $ts = $alert['timestamp'] ?? '';

                            try {
                                $formatted = $ts
                                    ? \Carbon\Carbon::parse($ts)->format('d M H:i')
                                    : '-';
                            } catch (\Throwable $e) {
                                $formatted = $ts ?: '-';
                            }
                        @endphp

                        <div
                            wire:key="wazuh-alert-mobile-{{ $alertKey }}"
                            class="border-b border-[#e8ede2] last:border-b-0
                                {{ $isExpanded ? 'bg-[#f5f7f2]' : 'hover:bg-[#fafbf8]' }}
                                transition"
                        >
                            <div class="px-5 py-4 space-y-2">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="px-2.5 py-1 border rounded-full text-xs font-medium {{ $alert['badge_class'] ?? '' }}">
                                        {{ $alert['severity_label'] ?? 'Unknown' }}
                                    </span>

                                    <span class="text-xs text-[#9aaa8a]">
                                        {{ $formatted }}
                                    </span>
                                </div>

                                <p class="text-sm font-semibold text-[#2d3a24]">
                                    {{ $alert['rule_description'] ?? 'Unknown alert' }}
                                </p>

                                <div class="flex flex-wrap gap-2 text-xs text-[#7a8f6a]">
                                    @if(($alert['agent_name'] ?? 'Unknown') !== 'Unknown')
                                        <span class="px-2 py-0.5 bg-[#eef1e8] rounded-full">
                                            {{ $alert['agent_name'] }}
                                        </span>
                                    @endif

                                    @if(($alert['rule_id'] ?? '-') !== '-')
                                        <span class="px-2 py-0.5 bg-[#eef1e8] rounded-full">
                                            {{ __('app.rule_label') }}
                                            {{ $alert['rule_id'] }}
                                        </span>
                                    @endif

                                    @if(($alert['location'] ?? '-') !== '-')
                                        <span class="px-2 py-0.5 bg-[#eef1e8] rounded-full truncate max-w-[160px]">
                                            {{ $alert['location'] }}
                                        </span>
                                    @endif
                                </div>

                                <button
                                    type="button"
                                    wire:click="toggleDetail({{ $index }})"
                                    class="text-xs text-[#4E653D] hover:text-[#354C2B] font-medium"
                                >
                                    {{ $isExpanded ? '▲ Hide Details' : '▼ Show Details' }}
                                </button>
                            </div>


                            {{-- Mobile details --}}
                            @if($isExpanded)
                                <div class="px-5 pb-5 border-t border-[#e8ede2] bg-[#f5f7f2]">
                                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">

                                        <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                            <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                Severity
                                            </p>

                                            <span class="px-2.5 py-1 border rounded-full text-xs font-medium {{ $alert['badge_class'] ?? '' }}">
                                                {{ $alert['severity_label'] ?? 'Unknown' }}
                                            </span>
                                        </div>

                                        <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                            <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                Rule Level
                                            </p>

                                            <p class="text-2xl font-bold text-[#4A2F24]">
                                                {{ $alert['rule_level'] ?? '-' }}
                                            </p>
                                        </div>

                                        <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                            <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                {{ __('app.rule_label') }} ID
                                            </p>

                                            <p class="text-sm font-mono text-[#2d3a24]">
                                                {{ $alert['rule_id'] ?? '-' }}
                                            </p>
                                        </div>

                                        <div class="bg-white border border-[#d4dfc8] rounded-xl p-3 sm:col-span-2">
                                            <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                Description
                                            </p>

                                            <p class="text-sm text-[#2d3a24]">
                                                {{ $alert['rule_description'] ?? 'Unknown alert' }}
                                            </p>
                                        </div>

                                        <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                            <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                Agent Name
                                            </p>

                                            <p class="text-sm text-[#2d3a24]">
                                                {{ $alert['agent_name'] ?? 'Unknown' }}
                                            </p>
                                        </div>

                                        <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                            <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                Agent ID
                                            </p>

                                            <p class="text-sm font-mono text-[#2d3a24]">
                                                {{ $alert['agent_id'] ?? '-' }}
                                            </p>
                                        </div>

                                        <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                            <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                Agent IP
                                            </p>

                                            <p class="text-sm font-mono text-[#2d3a24]">
                                                {{ $alert['agent_ip'] ?? '-' }}
                                            </p>
                                        </div>

                                        <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                            <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                Manager
                                            </p>

                                            <p class="text-sm text-[#2d3a24]">
                                                {{ $alert['manager_name'] ?? '-' }}
                                            </p>
                                        </div>

                                        <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                            <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                Decoder
                                            </p>

                                            <p class="text-sm text-[#2d3a24]">
                                                {{ $alert['decoder_name'] ?? '-' }}
                                            </p>
                                        </div>

                                        <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                            <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                Location
                                            </p>

                                            <p class="text-sm text-[#2d3a24] break-all">
                                                {{ $alert['location'] ?? '-' }}
                                            </p>
                                        </div>

                                        <div class="bg-white border border-[#d4dfc8] rounded-xl p-3">
                                            <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-1">
                                                Timestamp
                                            </p>

                                            <p class="text-sm font-mono text-[#2d3a24]">
                                                {{ ($alert['timestamp'] ?? '') ?: '-' }}
                                            </p>
                                        </div>

                                        @if(($alert['full_log'] ?? '') !== '')
                                            <div class="bg-white border border-[#d4dfc8] rounded-xl p-3 sm:col-span-2">
                                                <p class="text-xs font-semibold text-[#7a8f6a] uppercase tracking-wide mb-2">
                                                    {{ __('app.show_raw_log') }}
                                                </p>

                                                <pre class="overflow-x-auto rounded-lg bg-[#2d3a24] text-[#CDDEA7] text-xs leading-5 p-4 whitespace-pre-wrap break-all">{{ $alert['full_log'] }}</pre>
                                            </div>
                                        @endif

                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>


            {{-- ============================================================
                 EMPTY STATE
            ============================================================ --}}
            @else
                <div class="p-12 text-center">

                    @if($wazuhAvailable)
                        <svg
                            class="w-12 h-12 text-[#b5c4a5] mx-auto mb-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>

                        <p class="text-[#5a6e4a] font-medium">
                            @if($selectedSeverity === 'all')
                                No security alerts found.
                            @else
                                {{ __('app.no_alerts') }}
                            @endif
                        </p>

                        <p class="text-sm text-[#9aaa8a] mt-1">
                            The Wazuh Indexer is connected. No alerts match the current filter.
                        </p>
                    @else
                        <svg
                            class="w-12 h-12 text-amber-400 mx-auto mb-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>

                        <p class="text-[#5a6e4a] font-medium">
                            Security monitoring is temporarily unavailable. Please try again later.
                        </p>

                        <p class="text-sm text-[#9aaa8a] mt-1">
                            The page will automatically retry. You can also use the Refresh button above.
                        </p>
                    @endif
                </div>
            @endif
        </div>


        {{-- ================================================================
             FOOTER
        ================================================================ --}}
        @if($wazuhAvailable && count($filteredAlerts) > 0)
            <p class="text-xs text-[#9aaa8a] text-center pb-2">
                Showing {{ count($filteredAlerts) }}
                alert{{ count($filteredAlerts) !== 1 ? 's' : '' }}

                @if($selectedSeverity !== 'all')
                    (filtered: {{ $selectedSeverity }})
                @endif

                &middot; Fetched from Wazuh Indexer (last 50 alerts)
                &middot; Counts reflect this page&rsquo;s dataset, not total history.

                @if($autoRefresh)
                    Auto-refreshes every 30 s.
                @endif
            </p>
        @endif

    </main>
</div>