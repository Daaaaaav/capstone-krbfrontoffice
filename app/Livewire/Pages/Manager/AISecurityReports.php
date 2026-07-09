<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Services\WazuhAlertService;

#[Layout('layouts.manager')]
#[Title('Wazuh Security Reports')]
class AISecurityReports extends Component
{
    public string $selectedSeverity = 'all';

    public bool $autoRefresh = true;

    public function setSeverity(string $level): void
    {
        $this->selectedSeverity = $level;
    }

    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    public function render()
    {
        try {

            // Fetch all alerts unfiltered so stats always reflect the true totals
            $report = app(WazuhAlertService::class)
                ->getRecentAlerts(25, 'all');

            // Apply severity filter for the displayed list only
            $alerts = collect($report['alerts']);

            if ($this->selectedSeverity !== 'all') {
                $alerts = $alerts->filter(
                    fn (array $alert) => $alert['severity'] === $this->selectedSeverity
                );
            }

            return view(
                'livewire.pages.manager.a-i-security-reports',
                [
                    'alerts'           => $alerts->values()->toArray(),
                    'selectedSeverity' => $this->selectedSeverity,
                    ...$report,
                ]
            );

        } catch (\Throwable $e) {

            report($e);

            return view(
                'livewire.pages.manager.a-i-security-reports',
                [
                    'alerts'           => [],
                    'stats'            => [],
                    'source_label'     => 'Unavailable',
                    'source_host'      => null,
                    'api_endpoints'    => [],
                    'last_updated'     => null,
                    'available'        => false,
                    'selectedSeverity' => $this->selectedSeverity,
                ]
            );
        }
    }
}
