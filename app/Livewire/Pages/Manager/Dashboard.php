<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\BookingRoom;
use App\Models\VehicleBooking;
use App\Models\Delivery;

#[Layout('layouts.manager')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public $activeFilter  = 'all';
    public int $selectedYear;

    public function mount(): void
    {
        // Default to the latest year that actually has data; fall back to current year.
        $companyId = Auth::user()->company_id;

        $roomYears    = BookingRoom::where('company_id', $companyId)->selectRaw('YEAR(created_at) as y')->groupByRaw('YEAR(created_at)')->pluck('y');
        $vehicleYears = VehicleBooking::where('company_id', $companyId)->selectRaw('YEAR(created_at) as y')->groupByRaw('YEAR(created_at)')->pluck('y');
        $latestYear   = $roomYears->merge($vehicleYears)->unique()->sort()->last();

        $this->selectedYear = $latestYear ? (int) $latestYear : (int) date('Y');
    }

    public function setFilter($type): void
    {
        $this->activeFilter = $type;
    }

    public function render()
    {
        try {
            $companyId = Auth::user()->company_id;

            // Year range indicator
            $yearStart = "{$this->selectedYear}-01-01";
            $yearEnd   = "{$this->selectedYear}-12-31 23:59:59";

            $totalRooms    = BookingRoom::where('company_id', $companyId)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
            $totalVehicles = VehicleBooking::where('company_id', $companyId)->whereBetween('created_at', [$yearStart, $yearEnd])->count();
            $totalUsers    = User::where('company_id', $companyId)->whereHas('role', fn($q) => $q->where('name', 'Receptionist'))->count();

            // Trend: compare selected year vs previous year
            $prevStart = ($this->selectedYear - 1) . '-01-01';
            $prevEnd   = ($this->selectedYear - 1) . '-12-31 23:59:59';

            $prevRooms    = BookingRoom::where('company_id', $companyId)->whereBetween('created_at', [$prevStart, $prevEnd])->count();
            $prevVehicles = VehicleBooking::where('company_id', $companyId)->whereBetween('created_at', [$prevStart, $prevEnd])->count();

            $allTrend     = $this->calcTrend($prevRooms + $prevVehicles, $totalRooms + $totalVehicles);
            $roomTrendKpi = $this->calcTrend($prevRooms, $totalRooms);
            $vehTrendKpi  = $this->calcTrend($prevVehicles, $totalVehicles);

            $stats = [
                [
                    'key'       => 'all',
                    'label'     => __('app.all'),
                    'value'     => $totalRooms + $totalVehicles,
                    'trend'     => abs($allTrend),
                    'direction' => $allTrend >= 0 ? 'up' : 'down',
                ],
                [
                    'key'       => 'room',
                    'label'     => __('app.room_bookings_label'),
                    'value'     => $totalRooms,
                    'trend'     => abs($roomTrendKpi),
                    'direction' => $roomTrendKpi >= 0 ? 'up' : 'down',
                ],
                [
                    'key'       => 'vehicle',
                    'label'     => __('app.vehicle_bookings_label'),
                    'value'     => $totalVehicles,
                    'trend'     => abs($vehTrendKpi),
                    'direction' => $vehTrendKpi >= 0 ? 'up' : 'down',
                ],
                [
                    'key'       => 'users',
                    'label'     => __('app.receptionists'),
                    'value'     => $totalUsers,
                    'trend'     => 0,
                    'direction' => 'up',
                ],
            ];

            // Monthly counts for selected year
            $months = collect(range(1, 12));

            $roomByMonth = BookingRoom::where('company_id', $companyId)
                ->whereYear('created_at', $this->selectedYear)
                ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
                ->groupByRaw('MONTH(created_at)')
                ->pluck('count', 'month');

            $vehicleByMonth = VehicleBooking::where('company_id', $companyId)
                ->whereYear('created_at', $this->selectedYear)
                ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
                ->groupByRaw('MONTH(created_at)')
                ->pluck('count', 'month');

            $labels  = $months->map(fn($m) => date('M', mktime(0, 0, 0, $m, 1)))->toArray();
            $room    = $months->map(fn($m) => (int) ($roomByMonth[$m] ?? 0))->toArray();
            $vehicle = $months->map(fn($m) => (int) ($vehicleByMonth[$m] ?? 0))->toArray();

            // Available years for selector
            $roomYears    = BookingRoom::where('company_id', $companyId)->selectRaw('YEAR(created_at) as y')->groupByRaw('YEAR(created_at)')->pluck('y');
            $vehicleYears = VehicleBooking::where('company_id', $companyId)->selectRaw('YEAR(created_at) as y')->groupByRaw('YEAR(created_at)')->pluck('y');
            $availableYears = $roomYears->merge($vehicleYears)->unique()->sort()->values()->toArray();
            if (empty($availableYears)) {
                $availableYears = [(int) date('Y')];
            }

            // Filter logic
            if ($this->activeFilter === 'room') {
                $datasets = [
                    [
                        'type'  => 'room',
                        'label' => __('app.room_bookings_label'),
                        'data'  => $room,
                        'borderColor' => '#2563eb',
                    ]
                ];
            } elseif ($this->activeFilter === 'vehicle') {
                $datasets = [
                    [
                        'type'  => 'vehicle',
                        'label' => __('app.vehicle_bookings_label'),
                        'data'  => $vehicle,
                        'borderColor' => '#059669',
                    ]
                ];
            } else {
                $datasets = [
                    [
                        'type'  => 'room',
                        'label' => __('app.room_bookings_label'),
                        'data'  => $room,
                        'borderColor' => '#2563eb',
                    ],
                    [
                        'type'  => 'vehicle',
                        'label' => __('app.vehicle_bookings_label'),
                        'data'  => $vehicle,
                        'borderColor' => '#059669',
                    ]
                ];
            }

            // Dispatch chart data as a browser event so JS can update without re-rendering the canvas
            $this->dispatch('chart-data-updated', labels: $labels, datasets: $datasets);

            return view('livewire.pages.manager.dashboard', [
                'stats'          => $stats,
                'labels'         => $labels,
                'datasets'       => $datasets,
                'activeFilter'   => $this->activeFilter,
                'selectedYear'   => $this->selectedYear,
                'availableYears' => $availableYears,
            ]);

            // Dispatch chart data as a browser event so JS can update without re-rendering
            $this->dispatch('chart-data-updated', labels: $labels, datasets: $datasets);
        } catch (\Exception $e) {
            $this->dispatch('toast', 
                type: 'error',
                title: 'Error',
                message: 'Failed to retrieve dashboard data: ' . $e->getMessage(),
                duration: 4000
            );

            return view('livewire.pages.manager.dashboard', [
                'stats' => [
                    ['key' => 'all', 'label' => 'All Activity', 'value' => 0, 'trend' => 0, 'direction' => 'up'],
                    ['key' => 'room', 'label' => 'Room Bookings', 'value' => 0, 'trend' => 0, 'direction' => 'up'],
                    ['key' => 'vehicle', 'label' => 'Vehicle Bookings', 'value' => 0, 'trend' => 0, 'direction' => 'up'],
                    ['key' => 'users', 'label' => 'Receptionists', 'value' => 0, 'trend' => 0, 'direction' => 'up'],
                ],
                'labels'         => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
                'datasets'       => [],
                'activeFilter'   => $this->activeFilter,
                'selectedYear'   => $this->selectedYear,
                'availableYears' => [(int) date('Y')],
            ]);
        }
    }

    private function calcTrend(int $prev, int $curr): float
    {
        if ($prev === 0) return $curr > 0 ? 100 : 0;
        return round(($curr - $prev) / $prev * 100, 1);
    }
}
