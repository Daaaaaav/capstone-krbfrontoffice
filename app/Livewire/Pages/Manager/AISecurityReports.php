<?php

namespace App\Livewire\Pages\Manager;

use App\Services\WazuhAlertService;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.manager')]
#[Title('Wazuh Security Reports')]
class AISecurityReports extends Component
{
    use WithPagination;

    public string $selectedSeverity = 'all';

    public bool $autoRefresh = true;

    public function setSeverity(string $level): void
    {
        $this->resetPage();
        $this->selectedSeverity = $level;
    }

    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    public function render(WazuhAlertService $wazuh)
    {
        try {
            $report = $wazuh->fetchAlerts(
                $this->selectedSeverity,
                $this->getPage(),
                perPage: 10
            );

            return view(
                'livewire.pages.manager.a-i-security-reports',
                array_merge(['selectedSeverity' => $this->selectedSeverity], $report)
            );

        } catch (\Throwable $e) {
            Log::error('Failed to fetch Wazuh alerts', ['error' => $e->getMessage()]);

            return view(
                'livewire.pages.manager.a-i-security-reports',
                [
                    'alerts'           => [],
                    'stats'            => $wazuh->buildEmptyStats(),
                    'total_count'      => 0,
                    'source_label'     => 'Wazuh Indexer (Error)',
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
