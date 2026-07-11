<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\VehicleBooking;

#[Layout('layouts.manager')]
#[Title('Vehicle Booking Statistics')]
class VehicleBookingStatistics extends Component
{
    public string $timeRange = '90days';
    public bool   $showList  = false;

    public function setTimeRange(string $range): void
    {
        $this->timeRange = $range;
    }

    public function toggleList(): void
    {
        $this->showList = !$this->showList;
    }

    public function render()
    {
        try {
            $companyId = Auth::user()->company_id;

            $days = match($this->timeRange) {
                '30days' => 30,
                '90days' => 90,
                default  => 7,
            };

            $since = now()->subDays($days)->startOfDay();

            // ── KPI counts ────────────────────────────────────────────────────
            // vehicle_bookings statuses: pending | approved | on_progress | completed | cancelled | rejected | returned
            $totalBookings      = VehicleBooking::where('company_id', $companyId)->where('created_at', '>=', $since)->count();
            $pendingBookings    = VehicleBooking::where('company_id', $companyId)->where('created_at', '>=', $since)->where('status', 'pending')->count();
            $approvedBookings   = VehicleBooking::where('company_id', $companyId)->where('created_at', '>=', $since)->where('status', 'approved')->count();
            $onProgressBookings = VehicleBooking::where('company_id', $companyId)->where('created_at', '>=', $since)->whereIn('status', ['on_progress', 'late_return'])->count();
            $completedBookings  = VehicleBooking::where('company_id', $companyId)->where('created_at', '>=', $since)->whereIn('status', ['completed', 'returned'])->count();
            $rejectedBookings   = VehicleBooking::where('company_id', $companyId)->where('created_at', '>=', $since)->whereIn('status', ['rejected', 'cancelled'])->count();

            // ── Daily chart — zero-filled for every day in range ──────────────
            $raw = VehicleBooking::where('company_id', $companyId)
                ->where('created_at', '>=', $since)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->orderByRaw('DATE(created_at)')
                ->pluck('count', 'date');

            $labels = [];
            $data   = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date     = now()->subDays($i)->format('Y-m-d');
                $labels[] = now()->subDays($i)->format('d/m');
                $data[]   = (int) ($raw[$date] ?? 0);
            }

            $kpis = [
                ['label' => __('app.total_bookings'), 'value' => $totalBookings,      'color' => 'blue',   'icon' => 'truck'],
                ['label' => __('app.pending'),         'value' => $pendingBookings,    'color' => 'yellow', 'icon' => 'clock'],
                ['label' => __('app.approved'),        'value' => $approvedBookings,   'color' => 'green',  'icon' => 'check-circle'],
                ['label' => __('app.in_progress'),     'value' => $onProgressBookings, 'color' => 'purple', 'icon' => 'arrow-path'],
                ['label' => __('app.completed'),       'value' => $completedBookings,  'color' => 'gray',   'icon' => 'check-badge'],
                ['label' => __('app.rejected'),        'value' => $rejectedBookings,   'color' => 'red',    'icon' => 'x-circle'],
            ];

            $bookings = $this->showList
                ? VehicleBooking::where('company_id', $companyId)
                    ->where('created_at', '>=', $since)
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
