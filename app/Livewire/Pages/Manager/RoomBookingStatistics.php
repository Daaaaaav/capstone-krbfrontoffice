<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\BookingRoom;

#[Layout('layouts.manager')]
#[Title('Room Booking Statistics')]
class RoomBookingStatistics extends Component
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
            $base = BookingRoom::where('company_id', $companyId)->where('created_at', '>=', $since);

            $totalBookings     = (clone $base)->count();
            $pendingBookings   = (clone $base)->where('status', 'pending')->count();
            $approvedBookings  = (clone $base)->where('status', 'approved')->count();
            $rejectedBookings  = (clone $base)->where('status', 'rejected')->count();
            $completedBookings = (clone $base)->whereIn('status', ['completed', 'done'])->count();

            // ── Daily chart — zero-filled for every day in range ──────────────
            $raw = BookingRoom::where('company_id', $companyId)
                ->where('created_at', '>=', $since)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->orderByRaw('DATE(created_at)')
                ->pluck('count', 'date');

            $labels = [];
            $data   = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date     = now()->subDays($i)->format('Y-m-d');
                $labels[] = now()->subDays($i)->format('M d');
                $data[]   = (int) ($raw[$date] ?? 0);
            }

            $kpis = [
                ['label' => __('app.total_bookings'), 'value' => $totalBookings,     'color' => 'blue'],
                ['label' => __('app.pending'),        'value' => $pendingBookings,   'color' => 'yellow'],
                ['label' => __('app.approved'),       'value' => $approvedBookings,  'color' => 'green'],
                ['label' => __('app.rejected'),       'value' => $rejectedBookings,  'color' => 'red'],
                ['label' => __('app.completed'),      'value' => $completedBookings, 'color' => 'gray'],
            ];

            $bookings = $this->showList
                ? BookingRoom::where('company_id', $companyId)
                    ->where('created_at', '>=', $since)
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
