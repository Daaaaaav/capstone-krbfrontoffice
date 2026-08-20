<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Services\WazuhService;

#[Layout('layouts.manager')]
#[Title('Wazuh Security Reports')]
class AISecurityReports extends Component
{
    // -------------------------------------------------------------------------
    // Public state – these are serialised between Livewire requests.
    //
    // SECURITY: None of these properties contain Wazuh credentials.
    // Credentials live exclusively in config/services.php → .env and are
    // accessed only inside WazuhService (server-side).
    // -------------------------------------------------------------------------

    /** Which severity filter is active: all | critical | high | medium | low */
    public string $selectedSeverity = 'all';

    /** Whether automatic 30-second polling is enabled */
    public bool $autoRefresh = true;

    /** Whether the Wazuh Indexer responded successfully on the last fetch */
    public bool $wazuhAvailable = false;

    /** Normalised alert rows (plain arrays, NOT Eloquent models) */
    public array $alerts = [];

    /** Severity summary counts: total, critical, high, medium, low */
    public array $summary = [
        'total'    => 0,
        'critical' => 0,
        'high'     => 0,
        'medium'   => 0,
        'low'      => 0,
    ];

    /** ISO 8601 string of when data was last fetched */
    public string $lastUpdated = '';

    /** Which alert index is expanded in the detail panel (null = none) */
    public ?int $expandedIndex = null;

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function mount(): void
    {
        $this->loadAlerts();
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    /**
     * Manual refresh – called by the "Refresh" button.
     * Uses the same loading logic as auto-poll.
     */
    public function refreshAlerts(): void
    {
        $this->loadAlerts();
    }

    /**
     * Called by wire:poll.30s on the Blade template.
     * Delegates to the same method as manual refresh to avoid duplication.
     */
    public function pollRefresh(): void
    {
        $this->loadAlerts();
    }

    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    public function setSeverity(string $level): void
    {
        $this->selectedSeverity = $level;
        $this->expandedIndex    = null;
        // No re-fetch needed; filtering is done in the view over already-loaded $alerts
    }

    public function toggleDetail(int $index): void
    {
        $this->expandedIndex = ($this->expandedIndex === $index) ? null : $index;
    }

    // -------------------------------------------------------------------------
    // Data loading
    // -------------------------------------------------------------------------

    /**
     * Fetch fresh data from the Wazuh Indexer via the centralised WazuhService.
     *
     * This is the ONLY place that touches WazuhService in both the Manager and
     * IT Officer pages. The IT Officer component inherits this method.
     *
     * Outcome states:
     *   wazuhAvailable=true  + alerts non-empty → display alerts
     *   wazuhAvailable=true  + alerts empty     → "No security alerts found."
     *   wazuhAvailable=false                    → "Security monitoring temporarily unavailable."
     */
    protected function loadAlerts(): void
    {
        try {
            /** @var WazuhService $service */
            $service = app(WazuhService::class);

            $result = $service->getSecuritySummary(50);

            $this->wazuhAvailable = (bool) ($result['available'] ?? false);
            $this->alerts         = (array) ($result['alerts']   ?? []);
            $this->summary        = (array) ($result['summary']  ?? $this->summary);
            $this->lastUpdated    = (string) ($result['last_updated'] ?? now()->toIso8601String());

        } catch (\Throwable $e) {
            // Defensive catch – WazuhService already logs internally.
            // This ensures the component never crashes the dashboard.
            \Illuminate\Support\Facades\Log::error(
                'AISecurityReports failed to load alerts: ' . $e->getMessage()
            );

            $this->wazuhAvailable = false;
            $this->alerts         = [];
            $this->lastUpdated    = now()->toIso8601String();
        }
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    public function render()
    {
        // Apply severity filter to the already-loaded alerts (no extra HTTP request)
        $filtered = collect($this->alerts);

        if ($this->selectedSeverity !== 'all') {
            $filtered = $filtered->filter(
                fn ($alert) => ($alert['severity'] ?? '') === $this->selectedSeverity
            );
        }

        return view('livewire.pages.manager.a-i-security-reports', [
            'filteredAlerts' => $filtered->values()->all(),
        ]);
    }
}
