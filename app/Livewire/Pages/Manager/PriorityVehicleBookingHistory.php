<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\PriorityVehicleBooking;
use App\Models\VehicleBooking;

#[Layout('layouts.manager')]
#[Title('Priority Vehicle Booking History')]
class PriorityVehicleBookingHistory extends Component
{
    use WithPagination;
    
    protected string $paginationTheme = 'tailwind';
    protected string $tz = 'Asia/Jakarta';
    
    // Filters
    public string $q = '';
    public string $statusFilter = 'all'; // all | approved | rejected
    public ?string $selectedDate = null; // YYYY-MM-DD
    public string $dateMode = 'recent'; // recent | oldest
    public int $perPage = 10;
    
    // Detail modal
    public bool $showDetailModal = false;
    public ?int $detailId = null;
    
    // Photo modal (for viewing before/after photos if vehicle booking was created)
    public bool $showPhotoModal = false;
    public array $photoUrls = ['before' => null, 'after' => null];
    
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
    
    public function getDetailBookingProperty(): ?PriorityVehicleBooking
    {
        if (!$this->detailId) return null;
        
        $companyId = Auth::user()->company_id ?? null;
        
        return PriorityVehicleBooking::with(['vehicle', 'manager', 'department', 'cancelledBooking', 'handledBy'])
            ->forCompany($companyId)
            ->find($this->detailId);
    }
    
    public function viewPhotos(int $priorityBookingId): void
    {
        // Try to find the corresponding VehicleBooking created from this priority booking
        // This is a best-effort lookup based on timing and vehicle_id
        $priority = PriorityVehicleBooking::find($priorityBookingId);
        
        if (!$priority) {
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Priority booking not found.');
            return;
        }
        
        // Look for a vehicle booking that matches the priority booking criteria
        $vehicleBooking = VehicleBooking::where('vehicle_id', $priority->vehicle_id)
            ->where('borrower_name', $priority->borrower_name)
            ->whereBetween('start_at', [
                Carbon::parse($priority->start_at)->subMinutes(5),
                Carbon::parse($priority->start_at)->addMinutes(5)
            ])
            ->first();
        
        if ($vehicleBooking) {
            $this->photoUrls = [
                'before' => $vehicleBooking->handover_photo 
                    ? asset('storage/' . $vehicleBooking->handover_photo) 
                    : null,
                'after' => $vehicleBooking->return_photo 
                    ? asset('storage/' . $vehicleBooking->return_photo) 
                    : null,
            ];
            $this->showPhotoModal = true;
        } else {
            $this->dispatch('toast', type: 'info', title: 'No Photos', message: 'No photo evidence found for this booking.');
        }
    }
    
    public function closePhotoModal(): void
    {
        $this->showPhotoModal = false;
        $this->photoUrls = ['before' => null, 'after' => null];
    }
    
    public function render()
    {
        $companyId = Auth::user()->company_id ?? null;
        
        $query = PriorityVehicleBooking::with(['vehicle', 'manager', 'department', 'cancelledBooking', 'handledBy'])
            ->forCompany($companyId);
        
        // Only show approved/rejected bookings in history
        // Note: Approved bookings that have completed their time period belong in history
        if ($this->statusFilter === 'approved') {
            // Show approved bookings whose end time has passed
            $query->where('status', PriorityVehicleBooking::STATUS_APPROVED)
                ->where('end_at', '<', Carbon::now($this->tz));
        } elseif ($this->statusFilter === 'rejected') {
            $query->whereIn('status', [
                PriorityVehicleBooking::STATUS_REJECTED,
                PriorityVehicleBooking::STATUS_CONFLICT_DENIED,
            ]);
        } else {
            // all: show approved (past end time) + rejected
            $query->where(function ($q) {
                $q->where(function ($q1) {
                    $q1->where('status', PriorityVehicleBooking::STATUS_APPROVED)
                       ->where('end_at', '<', Carbon::now($this->tz));
                })
                ->orWhereIn('status', [
                    PriorityVehicleBooking::STATUS_REJECTED,
                    PriorityVehicleBooking::STATUS_CONFLICT_DENIED,
                ]);
            });
        }
        
        // Apply date filter
        if ($this->selectedDate) {
            $query->whereDate('start_at', $this->selectedDate);
        }
        
        // Apply search filter
        if ($this->q !== '') {
            $like = '%' . $this->q . '%';
            $query->where(function ($q) use ($like) {
                $q->where('purpose', 'like', $like)
                  ->orWhere('borrower_name', 'like', $like)
                  ->orWhere('destination', 'like', $like)
                  ->orWhereHas('manager', function ($qm) use ($like) {
                      $qm->where('full_name', 'like', $like)
                         ->orWhere('name', 'like', $like);
                  })
                  ->orWhereHas('vehicle', function ($qv) use ($like) {
                      $qv->where('name', 'like', $like)
                         ->orWhere('plate_number', 'like', $like);
                  });
            });
        }
        
        // Apply date sorting
        if ($this->dateMode === 'oldest') {
            $query->orderBy('start_at');
        } else {
            // recent (default)
            $query->orderByDesc('start_at');
        }
        
        $bookings = $query->paginate($this->perPage);
        
        return view('livewire.pages.manager.priority-vehicle-booking-history', [
            'bookings' => $bookings,
            'detailBooking' => $this->detailBooking,
        ]);
    }
}
