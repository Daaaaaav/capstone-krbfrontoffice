<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\VehicleBooking;
use Carbon\Carbon;

#[Layout('layouts.manager')]
#[Title('Vehicle Booking Statistics')]
class VehicleBookingStatistics extends Component
{
    public string $startDate;
    public string $endDate;
    public bool $showList = false;

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['startDate', 'endDate'])) {
            $this->validateDateRange();
        }
    }

    protected function validateDateRange(): void
    {
        if ($this->startDate && $this->endDate) {
            $start = Carbon::parse($this->startDate);
            $end = Carbon::parse($this->endDate);
            
            if ($start->greaterThan($end)) {
                $this->endDate = $this->startDate;
            }
        }
    }

    public function toggleList(): void
    {
        $this->showList = !$this->showList;
    }

    public function render()
    {
        try {
            $companyId = Auth::user()->company_id;

            $since = Carbon::parse($this->startDate)->startOfDay();
            $until = Carbon::parse($this->endDate)->endOfDay();

            $totalBookings = VehicleBooking::where('company_id', $companyId)->whereBetween('created_at', [$since, $until])->count();
            $pendingBookings = VehicleBooking::where('company_id', $companyId)->whereBetween('created_at', [$since, $until])->where('status', 'pending')->count();
            $approvedBookings = VehicleBooking::where('company_id', $companyId)->whereBetween('created_at', [$since, $until])->where('status', 'approved')->count();
            $onProgressBookings = VehicleBooking::where('company_id', $companyId)->whereBetween('created_at', [$since, $until])->whereIn('status', ['on_progress', 'late_return'])->count();
            $completedBookings = VehicleBooking::where('company_id', $companyId)->whereBetween('created_at', [$since, $until])->whereIn('status', ['completed', 'returned'])->count();
            $rejectedBookings = VehicleBooking::where('company_id', $companyId)->whereBetween('created_at', [$since, $until])->whereIn('status', ['rejected', 'cancelled'])->count();

            $raw = VehicleBooking::where('company_id', $companyId)
                ->whereBetween('created_at', [$since, $until])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->orderByRaw('DATE(created_at)')
                ->pluck('count', 'date');

            $labels = [];
            $data = [];
            $currentDate = $since->copy();
            
            while ($currentDate->lessThanOrEqualTo($until)) {
                $dateKey = $currentDate->format('Y-m-d');
                $labels[] = $currentDate->format('d/m');
                $data[] = (int) ($raw[$dateKey] ?? 0);
                $currentDate->addDay();
            }

            $kpis = [
                ['label' => __('app.total_bookings'), 'value' => $totalBookings, 'color' => 'blue', 'icon' => 'truck'],
                ['label' => __('app.pending'), 'value' => $pendingBookings, 'color' => 'yellow', 'icon' => 'clock'],
                ['label' => __('app.approved'), 'value' => $approvedBookings, 'color' => 'green', 'icon' => 'check-circle'],
                ['label' => __('app.in_progress'), 'value' => $onProgressBookings, 'color' => 'purple', 'icon' => 'arrow-path'],
                ['label' => __('app.completed'), 'value' => $completedBookings, 'color' => 'gray', 'icon' => 'check-badge'],
                ['label' => __('app.rejected'), 'value' => $rejectedBookings, 'color' => 'red', 'icon' => 'x-circle'],
            ];

            $bookings = $this->showList
                ? VehicleBooking::where('company_id', $companyId)
                    ->whereBetween('created_at', [$since, $until])
                    ->with(['vehicle', 'user', 'department'])
                    ->orderBy('created_at', 'desc')
                    ->get()
                : collect();

            $this->dispatch('vehicle-chart-updated', labels: $labels, data: $data);

            return view('livewire.pages.manager.vehicle-booking-statistics', [
                'kpis'     => $kpis,
                'labels'   => $labels,
                'data'     => $data,
                'bookings' => $bookings,
            ]);

        } catch (\Exception $e) {
            $this->dispatch('toast',
                type: 'error', title: 'Error',
                message: 'Failed to retrieve vehicle booking data: ' . $e->getMessage(),
                duration: 4000
            );

            return view('livewire.pages.manager.vehicle-booking-statistics', [
                'kpis' => [
                    ['label' => __('app.total_bookings'), 'value' => 0, 'color' => 'blue',   'icon' => 'truck'],
                    ['label' => __('app.pending'),         'value' => 0, 'color' => 'yellow', 'icon' => 'clock'],
                    ['label' => __('app.approved'),        'value' => 0, 'color' => 'green',  'icon' => 'check-circle'],
                    ['label' => __('app.in_progress'),     'value' => 0, 'color' => 'purple', 'icon' => 'arrow-path'],
                    ['label' => __('app.completed'),       'value' => 0, 'color' => 'gray',   'icon' => 'check-badge'],
                    ['label' => __('app.rejected'),        'value' => 0, 'color' => 'red',    'icon' => 'x-circle'],
                ],
                'labels'   => [],
                'data'     => [],
                'bookings' => collect(),
            ]);
        }
    }
}
