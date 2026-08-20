<div
    class="w-full min-h-full bg-[#f5f7f2]"
    @if($autoRefresh) wire:poll.30s="pollRefresh" @endif
>
    <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-8">

        {{-- Header --}}
        <x-page-header
            title="{{ __('app.security_reports_title') }}"
            subtitle="{{ __('app.security_reports_sub') }}"
        >
            {{-- actions --}}
        </x-page-header>

        {{-- Status banner --}}
        @if(!$wazuhAvailable)
            {{-- existing banner --}}
        @endif

        {{-- Summary cards --}}
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            {{-- existing cards --}}
        </section>

        {{-- Data source --}}
        <section class="bg-white border border-[#d4dfc8] rounded-2xl p-4 shadow-sm
                        flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            {{-- existing status --}}
        </section>

        {{-- Filters --}}
        <div class="flex flex-wrap gap-2">
            {{-- existing filters --}}
        </div>

        {{-- Alerts --}}
        <div class="bg-white border border-[#d4dfc8] rounded-2xl shadow-sm overflow-hidden">

            <div
                wire:loading
                wire:target="refreshAlerts,pollRefresh,setSeverity"
                class="px-5 py-3 bg-blue-50 border-b border-blue-100 flex items-center gap-2 text-sm text-blue-700"
            >
                {{-- spinner --}}
                {{ __('app.loading') }}
            </div>

            @if(count($this->filteredAlerts) > 0)

                {{-- Desktop --}}
                <div class="hidden lg:block overflow-x-auto">
                    <div class="min-w-[1050px]">

                        <div
                            class="grid
                                grid-cols-[120px_60px_minmax(250px,1fr)_140px_80px_180px_100px_80px]
                                gap-x-3 px-5 py-3 bg-[#f0f4eb] border-b border-[#d4dfc8]
                                text-xs font-semibold uppercase tracking-wide text-[#7a8f6a]"
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

                        @foreach($this->filteredAlerts as $index => $alert)
                            @php
                                $alertKey = $alert['id']
                                    ?? $alert['alert_id']
                                    ?? md5(
                                        ($alert['timestamp'] ?? '') . '|' .
                                        ($alert['rule_id'] ?? '') . '|' .
                                        ($alert['agent_id'] ?? '') . '|' .
                                        $index
                                    );

                                $isExpanded = $expandedIndex === $index;
                            @endphp

                            <div
                                wire:key="wazuh-alert-{{ $alertKey }}"
                                class="border-b border-[#e8ede2] last:border-b-0
                                    {{ $isExpanded ? 'bg-[#f5f7f2]' : 'hover:bg-[#fafbf8]' }}"
                            >
                                <div class="px-5 py-4">

                                    <div
                                        class="grid
                                            grid-cols-[120px_60px_minmax(250px,1fr)_140px_80px_180px_100px_80px]
                                            gap-x-3 items-center"
                                    >
                                        {{-- desktop row content --}}
                                    </div>

                                </div>

                                @if($isExpanded)
                                    {{-- existing detail panel --}}
                                @endif
                            </div>
                        @endforeach

                    </div>
                </div>

                {{-- Mobile --}}
                <div class="lg:hidden">
                    @foreach($this->filteredAlerts as $index => $alert)
                        @php
                            $alertKey = $alert['id']
                                ?? $alert['alert_id']
                                ?? md5(
                                    ($alert['timestamp'] ?? '') . '|' .
                                    ($alert['rule_id'] ?? '') . '|' .
                                    ($alert['agent_id'] ?? '') . '|' .
                                    $index
                                );
                        @endphp

                        <div
                            wire:key="wazuh-alert-mobile-{{ $alertKey }}"
                            class="border-b border-[#e8ede2] last:border-b-0"
                        >
                            {{-- mobile content --}}
                        </div>
                    @endforeach
                </div>

            @else

                {{-- existing empty state --}}

            @endif

        </div>

        @if($wazuhAvailable && count($this->filteredAlerts) > 0)
            {{-- footer --}}
        @endif

    </div>
</div>