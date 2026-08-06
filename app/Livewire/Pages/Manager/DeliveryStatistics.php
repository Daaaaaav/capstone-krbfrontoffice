<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\Delivery;
use Carbon\Carbon;

#[Layout('layouts.manager')]
#[Title('Delivery Statistics')]
class DeliveryStatistics extends Component
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

            $totalDeliveries = Delivery::where('company_id', $companyId)->whereBetween('created_at', [$since, $until])->count();
            $pendingDeliveries = Delivery::where('company_id', $companyId)->whereBetween('created_at', [$since, $until])->where('status', 'pending')->count();
            $storedDeliveries = Delivery::where('company_id', $companyId)->whereBetween('created_at', [$since, $until])->where('status', 'stored')->count();
            $completedDeliveries = Delivery::where('company_id', $companyId)->whereBetween('created_at', [$since, $until])->where('status', 'done')->count();

            $raw = Delivery::where('company_id', $companyId)
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

            $deliveries = $this->showList
                ? Delivery::where('company_id', $companyId)
                    ->whereBetween('created_at', [$since, $until])
                    ->orderBy('created_at', 'desc')
                    ->get()
                : collect();

            $stats = [
                ['label' => __('app.total_deliveries'), 'value' => $totalDeliveries, 'color' => 'blue'],
                ['label' => __('app.pending'), 'value' => $pendingDeliveries, 'color' => 'yellow'],
                ['label' => __('app.stored'), 'value' => $storedDeliveries, 'color' => 'purple'],
                ['label' => __('app.completed'), 'value' => $completedDeliveries, 'color' => 'green'],
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
