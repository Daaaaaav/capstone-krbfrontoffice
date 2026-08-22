<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Computed;
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
     * Only loads when auto-refresh is enabled; avoids wasted requests when paused.
     */
    public function pollRefresh(): void
    {
        if ($this->autoRefresh) {
            $this->loadAlerts();
        }
    }

    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    public function setSeverity(string $level): void
    {
        $this->selectedSeverity = $level;
        $this->expandedIndex    = null;
        // No re-fetch needed; the filteredAlerts computed property re-evaluates automatically.
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

            // Log a brief summary only when the Indexer transitions to unavailable,
            // to avoid filling logs during normal polling.
            if (!$this->wazuhAvailable) {
                \Illuminate\Support\Facades\Log::warning('WazuhSecurityReports: Indexer unavailable', [
                    'component' => static::class,
                ]);
            }

        } catch (\Throwable $e) {
            // Defensive catch – WazuhService already logs the full error internally.
            // This ensures the component never crashes the dashboard.
            \Illuminate\Support\Facades\Log::error(
                'WazuhSecurityReports: unexpected exception in component: ' . $e->getMessage(),
                ['component' => static::class, 'exception_class' => get_class($e)]
            );

            $this->wazuhAvailable = false;
            $this->alerts         = [];
            $this->lastUpdated    = now()->toIso8601String();
        }
    }

    // -------------------------------------------------------------------------
    // Computed properties
    // -------------------------------------------------------------------------

    /**
     * Severity-filtered view of the loaded alerts.
     *
     * Livewire v3 #[Computed] properties are evaluated lazily on each render
     * request and are available in Blade as $this->filteredAlerts.  Because
     * they derive from public properties ($alerts, $selectedSeverity) that are
     * already serialised on every request, the Blade always sees the correct
     * filtered data – even after setSeverity(), refreshAlerts(), and
     * pollRefresh() updates.
     *
     * The cache is intentionally NOT persisted across requests (persist: false
     * is the default) so that every render always reflects the current state.
     */
    #[Computed]
    public function filteredAlerts(): array
    {
        $collection = collect($this->alerts);

        if ($this->selectedSeverity !== 'all') {
            $collection = $collection->filter(
                fn ($alert) => ($alert['severity'] ?? '') === $this->selectedSeverity
            );
        }

        return $collection->values()->all();
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    public function render()
    {
        // filteredAlerts is now a #[Computed] property; no need to pass it as
        // a view variable.  The Blade accesses it via $this->filteredAlerts.
        return view('livewire.pages.manager.a-i-security-reports');
    }
}
