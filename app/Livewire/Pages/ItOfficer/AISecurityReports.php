<?php

namespace App\Livewire\Pages\ItOfficer;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Pages\Manager\AISecurityReports as ManagerAISecurityReports;

#[Layout('layouts.it-officer')]
#[Title('Wazuh Security Reports')]
class AISecurityReports extends ManagerAISecurityReports
{
    public function render()
    {
        $result = parent::render();
        
        // Override the view to use the IT Officer layout
        return $result->layout('layouts.it-officer');
    }
}
