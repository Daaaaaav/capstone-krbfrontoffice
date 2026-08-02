<?php

namespace App\Livewire\Pages\Receptionist\Traits;

trait HasViewMode
{
    public string $viewMode = 'card';

    public function bootHasViewMode(): void
    {
        $this->viewMode = session('viewMode', 'card');
        $this->updatePerPageLimits();
    }

    public function mountHasViewMode(): void
    {
        $this->viewMode = session('viewMode', 'card');
        $this->updatePerPageLimits();
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['card', 'table'])) {
            $this->viewMode = $mode;
            session(['viewMode' => $mode]);
            $this->updatePerPageLimits();

            if (method_exists($this, 'resetPage')) {
                $pages = [
                    'page', 'pageDone', 'pageRejected', 'pendingPage', 
                    'storedPage', 'ongoingPage', 'latestPage', 'entriesPage', 
                    'ongoing', 'done'
                ];
                foreach ($pages as $p) {
                    try {
                        $this->resetPage($p);
                    } catch (\Throwable $e) {
                        // ignore if paginator is not registered on this specific component
                    }
                }
            }
        }
    }

    protected function updatePerPageLimits(): void
    {
        $limit = $this->viewMode === 'card' ? 6 : 10;

        $props = [
            'perPage', 'perDone', 'perRejected', 'perPending', 
            'perStored', 'perOngoing', 'perLatest', 'perEntries', 'selectedPerPage'
        ];

        foreach ($props as $prop) {
            if (property_exists($this, $prop)) {
                $this->$prop = $limit;
            }
        }
    }
}
