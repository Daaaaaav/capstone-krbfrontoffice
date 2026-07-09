<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\Delivery;

#[Layout('layouts.manager')]
#[Title('Delivery Statistics')]
class DeliveryStatistics extends Component
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
            // Delivery statuses: pending | stored | done
            // "done" covers both delivered and taken (direction field distinguishes them)
            $totalDeliveries     = Delivery::where('company_id', $companyId)->where('created_at', '>=', $since)->count();
            $pendingDeliveries   = Delivery::where('company_id', $companyId)->where('created_at', '>=', $since)->where('status', 'pending')->count();
            $storedDeliveries    = Delivery::where('company_id', $companyId)->where('created_at', '>=', $since)->where('status', 'stored')->count();
            $completedDeliveries = Delivery::where('company_id', $companyId)->where('created_at', '>=', $since)->where('status', 'done')->count();

            // ── Daily chart — zero-filled for every day in range ──────────────
            $raw = Delivery::where('company_id', $companyId)
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

            // ── Delivery list ─────────────────────────────────────────────────
            $deliveries = $this->showList
                ? Delivery::where('company_id', $companyId)
                    ->where('created_at', '>=', $since)
                    ->orderBy('created_at', 'desc')
                    ->get()
                : collect();

            $stats = [
                ['label' => __('app.total_deliveries'), 'value' => $totalDeliveries,     'color' => 'blue'],
                ['label' => __('app.pending'),           'value' => $pendingDeliveries,   'color' => 'yellow'],
                ['label' => __('app.stored'),            'value' => $storedDeliveries,    'color' => 'purple'],
                ['label' => __('app.completed'),         'value' => $completedDeliveries, 'color' => 'green'],
            ];

            $this->dispatch('delivery-chart-updated', labels: $labels, data: $data);

            return view('livewire.pages.manager.delivery-statistics', [
                'stats'      => $stats,
                'labels'     => $labels,
                'data'       => $data,
                'deliveries' => $deliveries,
            ]);

        } catch (\Exception $e) {
            $this->dispatch('toast',
                type: 'error', title: 'Error',
                message: 'Failed to retrieve delivery data: ' . $e->getMessage(),
                duration: 4000
            );

            return view('livewire.pages.manager.delivery-statistics', [
                'stats' => [
                    ['label' => __('app.total_deliveries'), 'value' => 0, 'color' => 'blue'],
                    ['label' => __('app.pending'),           'value' => 0, 'color' => 'yellow'],
                    ['label' => __('app.stored'),            'value' => 0, 'color' => 'purple'],
                    ['label' => __('app.completed'),         'value' => 0, 'color' => 'green'],
                ],
                'labels'     => [],
                'data'       => [],
                'deliveries' => collect(),
            ]);
        }
    }
}
