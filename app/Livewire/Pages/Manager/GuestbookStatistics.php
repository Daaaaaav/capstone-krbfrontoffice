<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\Guestbook;

#[Layout('layouts.manager')]
#[Title('Guestbook Statistics')]
class GuestbookStatistics extends Component
{
    public $timeRange = '90days';
    public $showList  = false;

    public function setTimeRange($range): void
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
            $totalVisitors = Guestbook::where('company_id', $companyId)
                ->where('created_at', '>=', $since)
                ->count();

            // Currently inside: checked in but not yet checked out
            $checkedIn = Guestbook::where('company_id', $companyId)
                ->where('created_at', '>=', $since)
                ->whereNotNull('jam_in')
                ->whereNull('jam_out')
                ->count();

            // Already left: has a check-out time
            $checkedOut = Guestbook::where('company_id', $companyId)
                ->where('created_at', '>=', $since)
                ->whereNotNull('jam_out')
                ->count();

            // ── Daily chart — zero-filled for every day in range ──────────────
            $raw = Guestbook::where('company_id', $companyId)
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

            // ── Visitor list ──────────────────────────────────────────────────
            $guestbooks = $this->showList
                ? Guestbook::where('company_id', $companyId)
                    ->where('created_at', '>=', $since)
                    ->orderBy('created_at', 'desc')
                    ->get()
                : collect();

            $stats = [
                ['label' => __('app.total_visitors'), 'value' => $totalVisitors,                                           'color' => 'blue'],
                ['label' => __('app.currently_in'),   'value' => $checkedIn,                                               'color' => 'yellow'],
                ['label' => __('app.checked_out'),    'value' => $checkedOut,                                              'color' => 'green'],
                ['label' => __('app.avg_per_day'),    'value' => $days > 0 ? round($totalVisitors / $days, 1) : 0,         'color' => 'purple'],
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
