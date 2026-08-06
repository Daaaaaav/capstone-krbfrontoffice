<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\Guestbook;
use Carbon\Carbon;

#[Layout('layouts.manager')]
#[Title('Guestbook Statistics')]
class GuestbookStatistics extends Component
{
    public $startDate;
    public $endDate;
    public $showList = false;

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

            $totalVisitors = Guestbook::where('company_id', $companyId)
                ->whereBetween('created_at', [$since, $until])
                ->count();

            $checkedIn = Guestbook::where('company_id', $companyId)
                ->whereBetween('created_at', [$since, $until])
                ->whereNotNull('jam_in')
                ->whereNull('jam_out')
                ->count();

            $checkedOut = Guestbook::where('company_id', $companyId)
                ->whereBetween('created_at', [$since, $until])
                ->whereNotNull('jam_out')
                ->count();

            $raw = Guestbook::where('company_id', $companyId)
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

            $days = $since->diffInDays($until) + 1;

            $guestbooks = $this->showList
                ? Guestbook::where('company_id', $companyId)
                    ->whereBetween('created_at', [$since, $until])
                    ->orderBy('created_at', 'desc')
                    ->get()
                : collect();

            $stats = [
                ['label' => __('app.total_visitors'), 'value' => $totalVisitors, 'color' => 'blue'],
                ['label' => __('app.currently_in'), 'value' => $checkedIn, 'color' => 'yellow'],
                ['label' => __('app.checked_out'), 'value' => $checkedOut, 'color' => 'green'],
                ['label' => __('app.avg_per_day'), 'value' => $days > 0 ? round($totalVisitors / $days, 1) : 0, 'color' => 'purple'],
            ];

            $this->dispatch('guestbook-chart-updated', labels: $labels, data: $data);

            return view('livewire.pages.manager.guestbook-statistics', [
                'stats'      => $stats,
                'labels'     => $labels,
                'data'       => $data,
                'guestbooks' => $guestbooks,
            ]);

        } catch (\Exception $e) {
            $this->dispatch('toast',
                type: 'error', title: 'Error',
                message: 'Failed to retrieve guestbook data: ' . $e->getMessage(),
                duration: 4000
            );

            return view('livewire.pages.manager.guestbook-statistics', [
                'stats' => [
                    ['label' => __('app.total_visitors'), 'value' => 0, 'color' => 'blue'],
                    ['label' => __('app.currently_in'),   'value' => 0, 'color' => 'yellow'],
                    ['label' => __('app.checked_out'),    'value' => 0, 'color' => 'green'],
                    ['label' => __('app.avg_per_day'),    'value' => 0, 'color' => 'purple'],
                ],
                'labels'     => [],
                'data'       => [],
                'guestbooks' => collect(),
            ]);
        }
    }
}
