<?php

namespace App\Livewire\Pages\ItOfficer;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Pages\Manager\OccupancyForecasting as ManagerOccupancyForecasting;

#[Layout('layouts.it-officer')]
#[Title('Occupancy Forecasting')]
class OccupancyForecasting extends ManagerOccupancyForecasting
{
    public function render()
    {
        $result = parent::render();
        
        // Override the view to use the IT Officer layout
        return $result->layout('layouts.it-officer');
    }
}
