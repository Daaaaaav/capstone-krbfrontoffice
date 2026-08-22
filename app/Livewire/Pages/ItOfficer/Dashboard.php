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
use App\Services\ApplicationHealthService;

#[Layout('layouts.it-officer')]
#[Title('IT Officer Dashboard')]
class Dashboard extends Component
{
    /**
     * Application Health summary state.
     * Caching handled by ApplicationHealthService to prevent endpoint flooding.
     */
    public array $applicationHealth = [];

    public function mount(): void
    {
        $this->loadHealth(false);
    }

    public function refreshHealth(): void
    {
        $this->loadHealth(true);
    }

    public function pollRefresh(): void
    {
        $this->loadHealth(false);
    }

    protected function loadHealth(bool $force = false): void
    {
        try {
            $healthService = app(ApplicationHealthService::class);
            $this->applicationHealth = $healthService->getHealthSummary($force);
        } catch (\Throwable $e) {
            $this->applicationHealth = [
                'status'        => 'unknown',
                'status_label'  => 'Status Unknown',
                'status_badge'  => 'bg-gray-100 text-gray-800 border-gray-300 dark:bg-gray-800 dark:text-gray-300',
                'healthy_count' => 0,
                'total_count'   => 3,
                'last_checked'  => now()->toIso8601String(),
                'services'      => [],
            ];
        }
    }

    public function render()
    {
        $companyId = Auth::user()->company_id ?? 1;

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

        return view('livewire.pages.it-officer.dashboard', [
            'stats' => $stats,
            'applicationHealth' => $this->applicationHealth,
        ]);
    }
}