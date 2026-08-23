<?php

namespace App\Livewire\Pages\Manager;

use App\Services\WazuhService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.manager')]
#[Title('Wazuh Security Reports')]
class AISecurityReports extends Component
{
    // -------------------------------------------------------------------------
    // Public state
    //
    // SECURITY: None of these properties contain Wazuh credentials.
    // Credentials remain exclusively in config/services.php and .env and are
    // accessed only by WazuhService on the server.
    // -------------------------------------------------------------------------

    /**
     * Active severity filter:
     * all | critical | high | medium | low
     */
    public string $selectedSeverity = 'all';

    /**
     * Whether automatic 30-second polling is enabled.
     */
    public bool $autoRefresh = true;

    /**
     * Whether the Wazuh Indexer responded successfully on the last fetch.
     */
    public bool $wazuhAvailable = false;

    /**
     * Normalised alert rows.
     *
     * These are plain arrays, not Eloquent models.
     */
    public array $alerts = [];

    /**
     * Severity summary counts for the current bounded Wazuh dataset.
     */
    public array $summary = [
        'total' => 0,
        'critical' => 0,
        'high' => 0,
        'medium' => 0,
        'low' => 0,
    ];

    /**
     * ISO 8601 timestamp of the most recent fetch.
     */
    public string $lastUpdated = '';

    /**
     * Index within the CURRENT FILTERED LIST that has its details expanded.
     *
     * null means no alert is expanded.
     */
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
     * Manual refresh.
     */
    public function refreshAlerts(): void
    {
        $this->loadAlerts();

        // Alert ordering or filtering may have changed after a fresh fetch.
        $this->expandedIndex = null;
    }

    /**
     * Called by wire:poll.30s.
     */
    public function pollRefresh(): void
    {
        if (!$this->autoRefresh) {
            return;
        }

        $this->loadAlerts();

        // The latest Wazuh results may have a different ordering.
        $this->expandedIndex = null;
    }

    /**
     * Enable or disable automatic refresh.
     */
    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    /**
     * Change the severity filter.
     *
     * No Wazuh request is needed because filtering happens locally against the
     * already-loaded alerts.
     */
    public function setSeverity(string $level): void
    {
        $allowedLevels = [
            'all',
            'critical',
            'high',
            'medium',
            'low',
        ];

        // Defensive validation so an unexpected client value cannot leave the
        // component in an invalid filtering state.
        if (!in_array($level, $allowedLevels, true)) {
            $level = 'all';
        }

        $this->selectedSeverity = $level;
        $this->expandedIndex = null;
    }

    /**
     * Expand or collapse an alert's detail panel.
     *
     * The index belongs to the currently filtered alert list.
     */
    public function toggleDetail(int $index): void
    {
        $this->expandedIndex = (
            $this->expandedIndex === $index
        ) ? null : $index;
    }

    // -------------------------------------------------------------------------
    // Data loading
    // -------------------------------------------------------------------------

    /**
     * Fetch fresh data from Wazuh through the centralised WazuhService.
     *
     * This method is inherited by the IT Officer component.
     *
     * States:
     * - available=true, alerts non-empty:
     *   Wazuh is connected and alerts are displayed.
     *
     * - available=true, alerts empty:
     *   Wazuh is connected but there are no matching recent alerts.
     *
     * - available=false:
     *   Wazuh is unavailable or the request failed.
     */
    protected function loadAlerts(): void
    {
        try {
            /** @var WazuhService $service */
            $service = app(WazuhService::class);

            $result = $service->getSecuritySummary(50);

            $this->wazuhAvailable = (bool) (
                $result['available'] ?? false
            );

            $this->alerts = is_array($result['alerts'] ?? null)
                ? $result['alerts']
                : [];

            $this->summary = is_array($result['summary'] ?? null)
                ? $result['summary']
                : [
                    'total' => 0,
                    'critical' => 0,
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0,
                ];

            $this->lastUpdated = (string) (
                $result['last_updated'] ?? now()->toIso8601String()
            );

            if (!$this->wazuhAvailable) {
                Log::warning(
                    'WazuhSecurityReports: Indexer unavailable',
                    [
                        'component' => static::class,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // WazuhService already handles and logs its own connection errors.
            // This additional catch ensures the Livewire component itself never
            // crashes the dashboard because of an unexpected exception.
            Log::error(
                'WazuhSecurityReports: unexpected exception in component: '
                    . $e->getMessage(),
                [
                    'component' => static::class,
                    'exception_class' => get_class($e),
                ]
            );

            $this->wazuhAvailable = false;
            $this->alerts = [];

            $this->summary = [
                'total' => 0,
                'critical' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ];

            $this->lastUpdated = now()->toIso8601String();
        }
    }

    // -------------------------------------------------------------------------
    // Filtering
    // -------------------------------------------------------------------------

    /**
     * Return alerts filtered by the currently selected severity.
     *
     * This deliberately uses a normal method rather than Livewire's
     * #[Computed] property mechanism. The result is explicitly passed to the
     * Blade view from render(), avoiding computed-property resolution issues.
     */
    public function getFilteredAlerts(): array
    {
        $collection = collect($this->alerts);

        if ($this->selectedSeverity !== 'all') {
            $collection = $collection->filter(
                fn (array $alert): bool =>
                    ($alert['severity'] ?? '') === $this->selectedSeverity
            );
        }

        return $collection
            ->values()
            ->all();
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    /**
     * Render the Manager Security Reports page.
     *
     * filteredAlerts is explicitly supplied as a normal Blade view variable.
     * Do not access it as $this->filteredAlerts in the Blade.
     */
    public function render()
    {
        return view(
            'livewire.pages.manager.a-i-security-reports',
            [
                'filteredAlerts' => $this->getFilteredAlerts(),
            ]
        );
    }
}