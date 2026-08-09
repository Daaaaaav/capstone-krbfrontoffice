<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\BookingRoom;
use Carbon\Carbon;

#[Layout('layouts.manager')]
#[Title('Room Booking Statistics')]
class RoomBookingStatistics extends Component
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

            $base = BookingRoom::where('company_id', $companyId)->whereBetween('created_at', [$since, $until]);

            $totalBookings = (clone $base)->count();
            $pendingBookings = (clone $base)->where('status', 'pending')->count();
            $approvedBookings = (clone $base)->where('status', 'approved')->count();
            $rejectedBookings = (clone $base)->where('status', 'rejected')->count();
            $completedBookings = (clone $base)->whereIn('status', ['completed', 'done'])->count();

            $raw = BookingRoom::where('company_id', $companyId)
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
                $labels[] = $currentDate->format('M d');
                $data[] = (int) ($raw[$dateKey] ?? 0);
                $currentDate->addDay();
            }

            $kpis = [
                ['label' => __('app.total_bookings'), 'value' => $totalBookings, 'color' => 'blue'],
                ['label' => __('app.pending'), 'value' => $pendingBookings, 'color' => 'yellow'],
                ['label' => __('app.approved'), 'value' => $approvedBookings, 'color' => 'green'],
                ['label' => __('app.rejected'), 'value' => $rejectedBookings, 'color' => 'red'],
                ['label' => __('app.completed'), 'value' => $completedBookings, 'color' => 'gray'],
            ];

            $bookings = $this->showList
                ? BookingRoom::where('company_id', $companyId)
                    ->whereBetween('created_at', [$since, $until])
                    ->with(['room', 'user', 'department'])
                    ->orderBy('created_at', 'desc')
                    ->get()
                : collect();

            $this->dispatch('room-chart-updated', labels: $labels, data: $data);

            return view('livewire.pages.manager.room-booking-statistics', [
                'kpis'     => $kpis,
                'labels'   => $labels,
                'data'     => $data,
                'bookings' => $bookings,
            ]);

        } catch (\Exception $e) {
            $this->dispatch('toast',
                type: 'error', title: 'Error',
                message: 'Failed to retrieve room booking data: ' . $e->getMessage(),
                duration: 4000
            );

            return view('livewire.pages.manager.room-booking-statistics', [
                'kpis' => [
                    ['label' => __('app.total_bookings'), 'value' => 0, 'color' => 'blue'],
                    ['label' => __('app.pending'),        'value' => 0, 'color' => 'yellow'],
                    ['label' => __('app.approved'),       'value' => 0, 'color' => 'green'],
                    ['label' => __('app.rejected'),       'value' => 0, 'color' => 'red'],
                    ['label' => __('app.completed'),      'value' => 0, 'color' => 'gray'],
                ],
                'labels'   => [],
                'data'     => [],
                'bookings' => collect(),
            ]);
        }
    }
}
