<?php

namespace App\Livewire\Pages\ItOfficer;

use App\Services\WazuhAlertService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Pages\Manager\AISecurityReports as ManagerAISecurityReports;

#[Layout('layouts.it-officer')]
#[Title('Wazuh Security Reports')]
class AISecurityReports extends ManagerAISecurityReports
{
    public function render(WazuhAlertService $wazuh)
    {
        // Delegate all data-fetching to the parent, then swap the layout.
        return parent::render($wazuh)->layout('layouts.it-officer');
    }
}
