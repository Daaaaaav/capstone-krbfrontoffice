<?php

namespace App\Livewire\Pages\ItOfficer;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Room;
use App\Models\Vehicle;
use App\Models\Storage;

#[Layout('layouts.it-officer')]
#[Title('IT Officer Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $companyId = Auth::user()->company_id;

        $stats = [
            'receptionists' => User::where('company_id', $companyId)
                ->whereHas('role', fn($q) => $q->where('name', 'Receptionist'))
                ->count(),
            'managers' => User::where('company_id', $companyId)
                ->whereHas('role', fn($q) => $q->where('name', 'Manager'))
                ->count(),
            'rooms' => Room::where('company_id', $companyId)->count(),
            'vehicles' => Vehicle::where('company_id', $companyId)->count(),
            'storages' => Storage::where('company_id', $companyId)->count(),
        ];

        return view('livewire.pages.it-officer.dashboard', compact('stats'));
    }
}
