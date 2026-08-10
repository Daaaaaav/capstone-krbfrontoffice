<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\PriorityRoomBooking;

#[Layout('layouts.manager')]
#[Title('Priority Room Booking History')]
class PriorityRoomBookingHistory extends Component
{
    use WithPagination;
    
    protected string $paginationTheme = 'tailwind';
    protected string $tz = 'Asia/Jakarta';
    
    // Filters
    public string $q = '';
    public string $statusFilter = 'all'; // all | completed | rejected
    public ?string $selectedDate = null; // YYYY-MM-DD
    public string $dateMode = 'recent'; // recent | oldest
    public int $perPage = 10;
    
    // Detail modal
    public bool $showDetailModal = false;
    public ?int $detailId = null;
    
    protected $queryString = [
        'q' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'selectedDate' => ['except' => null],
        'dateMode' => ['except' => 'recent'],
        'page' => ['except' => 1],
    ];
    
    public function updatedQ(): void
    {
        $this->resetPage();
    }
    
    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }
    
    public function updatedSelectedDate(): void
    {
        $this->resetPage();
    }
    
    public function updatedDateMode(): void
    {
        $this->resetPage();
    }
    
    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->showDetailModal = true;
    }
    
    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->detailId = null;
    }
    
    public function getDetailBookingProperty(): ?PriorityRoomBooking
    {
        if (!$this->detailId) return null;
        
        $companyId = Auth::user()->company_id ?? null;
        
        return PriorityRoomBooking::with(['room', 'manager', 'cancelledBooking', 'handledBy'])
            ->forCompany($companyId)
            ->find($this->detailId);
    }
    
    public function render()
    {
        $companyId = Auth::user()->company_id ?? null;
        
        $query = PriorityRoomBooking::with(['room', 'manager', 'cancelledBooking', 'handledBy'])
            ->forCompany($companyId);
        
        // Only show completed/rejected bookings in history
        if ($this->statusFilter === 'completed') {
            $query->where('status', PriorityRoomBooking::STATUS_COMPLETED);
        } elseif ($this->statusFilter === 'rejected') {
            $query->whereIn('status', [
                PriorityRoomBooking::STATUS_REJECTED,
                PriorityRoomBooking::STATUS_CONFLICT_DENIED,
            ]);
        } else {
            // all: show completed + rejected
            $query->whereIn('status', [
                PriorityRoomBooking::STATUS_COMPLETED,
                PriorityRoomBooking::STATUS_REJECTED,
                PriorityRoomBooking::STATUS_CONFLICT_DENIED,
            ]);
        }
        
        // Apply date filter
        if ($this->selectedDate) {
            $query->whereDate('date', $this->selectedDate);
        }
        
        // Apply search filter
        if ($this->q !== '') {
            $like = '%' . $this->q . '%';
            $query->where(function ($q) use ($like) {
                $q->where('meeting_title', 'like', $like)
                  ->orWhereHas('manager', function ($qm) use ($like) {
                      $qm->where('full_name', 'like', $like)
                         ->orWhere('name', 'like', $like);
                  })
                  ->orWhereHas('room', function ($qr) use ($like) {
                      $qr->where('room_name', 'like', $like);
                  });
            });
        }
        
        // Apply date sorting
        if ($this->dateMode === 'oldest') {
            $query->orderBy('date')->orderBy('start_time');
        } else {
            // recent (default)
            $query->orderByDesc('date')->orderByDesc('start_time');
        }
        
        $bookings = $query->paginate($this->perPage);
        
        return view('livewire.pages.manager.priority-room-booking-history', [
            'bookings' => $bookings,
            'detailBooking' => $this->detailBooking,
        ]);
    }
}
