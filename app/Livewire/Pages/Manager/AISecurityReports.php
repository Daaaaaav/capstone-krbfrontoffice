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
            $report = app(WazuhAlertService::class)
                ->getRecentAlerts(25, $this->selectedSeverity);

            return view(
                'livewire.pages.manager.a-i-security-reports',
                [
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
                    'total_count'      => 0,
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
