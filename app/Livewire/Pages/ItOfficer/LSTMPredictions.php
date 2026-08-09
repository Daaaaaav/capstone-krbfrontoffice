<?php

namespace App\Livewire\Pages\ItOfficer;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Livewire\Pages\Manager\LSTMPredictions as ManagerLSTMPredictions;

#[Layout('layouts.it-officer')]
#[Title('LSTM Predictions')]
class LSTMPredictions extends ManagerLSTMPredictions
{
    public function render()
    {
        $result = parent::render();
        
        // Override the view to use the IT Officer layout
        return $result->layout('layouts.it-officer');
    }
}
