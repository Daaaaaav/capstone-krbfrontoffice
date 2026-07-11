<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Models\VehicleBooking;
use App\Models\Vehicle;
use App\Models\VehicleBookingPhoto;

use App\Livewire\Pages\Receptionist\Traits\HasViewMode;

#[Layout('layouts.receptionist')]
#[Title('Vehicle Status')]
class Vehiclestatus extends Component
{
    use WithPagination;
    use HasViewMode;

    protected string $paginationTheme = 'tailwind';
    protected string $tz = 'Asia/Jakarta';

    // Filters/state
    public string $q = '';
    public ?int $vehicleFilter = null;
    public ?string $selectedDate = null;   // YYYY-MM-DD
    public string $statusTab = 'pending';  // pending | approved | on_progress | returned
    public string $sortFilter = 'recent';  // recent | oldest | nearest
    public int $perPage = 10;
    public bool $includeDeleted = false;

    /** cache */
    public $vehicles;
    /** @var array<int,string> */
    public array $vehicleMap = [];
    /** @var array<int,array{before:int,after:int}> */
    public array $photoCounts = [];

    // Reject modal state
    public bool $showRejectModal = false;
    public ?int $rejectId = null;
    public string $rejectNote = '';

    // Late reason modal state
    public bool $showLateReasonModal = false;
    public ?int $lateBookingId = null;
    public string $lateReasonText = '';


    // Reject result popup state
    public bool $showRejectResult = false;
    public string $rejectResultType = 'success'; // 'success' | 'error'
    public string $rejectResultTitle = '';
    public string $rejectResultMessage = '';
    public ?int $rejectResultBookingId = null;

    // *** BARU: Detail modal state ***
    public bool $showDetailModal = false;
    public ?VehicleBooking $selectedBooking = null;
    /** @var array{before: array, after: array} */
    public array $selectedPhotos = ['before' => [], 'after' => []];
    // *** END BARU ***

    // Mobile filter modal
    public bool $showFilterModal = false;

    protected $queryString = [
        'q' => ['except' => ''],
        'vehicleFilter' => ['except' => null],
        'selectedDate' => ['except' => null],
        'statusTab' => ['except' => 'pending'],
        'sortFilter' => ['except' => 'recent'],
        'page' => ['except' => 1],
    ];

    // Reset page on filter change
    public function updatedQ()
    {
        $this->resetPage();
    }
    public function updatedVehicleFilter()
    {
        $this->resetPage();
    }
    public function updatedSelectedDate()
    {
        $this->resetPage();
    }
    public function updatedStatusTab()
    {
        $this->resetPage();
    }
    public function updatedSortFilter()
    {
        $this->resetPage();
    }
    public function updatedIncludeDeleted()
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        // Deduplicate by name: keep only the first vehicle per unique name
        $this->vehicles = Vehicle::orderBy('name')
            ->get()
            ->unique(fn($v) => $v->name ?? $v->plate_number ?? $v->vehicle_id);

        $this->vehicleMap = $this->vehicles
            ->mapWithKeys(fn($v) => [(int) $v->vehicle_id => (string) ($v->name ?? $v->plate_number ?? ('#' . $v->vehicle_id))])
            ->toArray();
    }

    public function render()
    {
        // Dynamic check: Automatically move 'pending' to 'rejected' if start_at has passed
        VehicleBooking::where('status', 'pending')
            ->where('start_at', '<=', DB::raw('NOW()'))
            ->update([
                'status' => 'rejected',
                'notes' => DB::raw("TRIM(CONCAT(COALESCE(notes, ''), IF(COALESCE(notes, '') = '', '', '\n'), '[System Auto-Rejected] Not approved before start time.'))")
            ]);

        // Dynamic check: Automatically move 'approved' to 'on_progress' if start_at has passed
        VehicleBooking::where('status', 'approved')
            ->where('start_at', '<=', DB::raw('NOW()'))
            ->update(['status' => 'on_progress']);

        $bookings = VehicleBooking::query()
            ->when(!$this->includeDeleted, fn(Builder $q) => $q->whereNull('deleted_at'))
            ->when($this->includeDeleted, fn(Builder $q) => $q->withTrashed())
            ->when($this->vehicleFilter, fn(Builder $q) => $q->where('vehicle_id', $this->vehicleFilter))
            ->when($this->q !== '', function (Builder $q) {
                $like = '%' . $this->q . '%';
                $q->where(function (Builder $qq) use ($like) {
                    $qq->where('purpose', 'like', $like)
                        ->orWhere('destination', 'like', $like)
                        ->orWhere('borrower_name', 'like', $like);
                });
            })
            ->when($this->selectedDate, fn(Builder $q) => $q->whereDate('start_at', $this->selectedDate))
            ->when($this->statusTab, fn(Builder $q) => $q->where('status', $this->statusTab))
            ->when($this->sortFilter === 'recent', fn(Builder $q) => $q->orderByDesc('vehiclebooking_id'))
            ->when($this->sortFilter === 'oldest', fn(Builder $q) => $q->orderBy('vehiclebooking_id'))
            ->when($this->sortFilter === 'nearest', fn(Builder $q) => $q->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, NOW(), start_at))'))
            ->paginate($this->perPage);

        $ids = $bookings->pluck('vehiclebooking_id')->all();
        $this->photoCounts = $this->buildPhotoCounts($ids);

        return view('livewire.pages.receptionist.vehiclestatus', [
            'bookings' => $bookings,
        ]);
    }

    /**
     * @param  array<int> $bookingIds
     * @return array<int,array{before:int,after:int}>
     */
    protected function buildPhotoCounts(array $bookingIds): array
    {
        if (empty($bookingIds))
            return [];
        $rows = VehicleBookingPhoto::selectRaw('vehiclebooking_id, photo_type, COUNT(*) as c')
            ->whereIn('vehiclebooking_id', $bookingIds)
            ->groupBy('vehiclebooking_id', 'photo_type')
            ->get();

        $out = [];
        foreach ($bookingIds as $id)
            $out[$id] = ['before' => 0, 'after' => 0];
        foreach ($rows as $r) {
            $vb = (int) $r->vehiclebooking_id;
            $type = $r->photo_type === 'after' ? 'after' : 'before';
            $out[$vb][$type] = (int) $r->c;
        }
        return $out;
    }

    /* =========================
     * Actions
     * ========================= */

    public function approve(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                /** @var VehicleBooking $b */
                $b = VehicleBooking::lockForUpdate()
                    ->when($this->includeDeleted, fn($q) => $q->withTrashed())
                    ->findOrFail($id);

                if ($b->status !== 'pending') {
                    throw new \RuntimeException("Booking #{$b->vehiclebooking_id} is not in pending status.");
                }
                $b->status = 'approved';
                $b->save();
            });

            $this->dispatch('toast', type: 'success', title: 'Approved', message: 'Booking has been approved.');
            $this->resetPage();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Approve', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to approve: ' . $e->getMessage());
        }
    } 

    /** Open modal to ask for reject reason */
    public function confirmReject(int $id): void
    {
        $this->rejectId = $id;
        $this->rejectNote = '';
        $this->showRejectModal = true;
    }

    /** Close/cancel modal */
    public function cancelReject(): void
    {
        $this->showRejectModal = false;
        $this->rejectId = null;
        $this->rejectNote = '';
    }

    /** Validate + perform rejection with required note */
    public function submitReject(): void
    {
        $this->validate([
            'rejectNote' => 'required|string|min:5|max:2000',
            'rejectId'   => 'required|integer',
        ]);

        $bookingId = (int) $this->rejectId;
        $reason    = trim($this->rejectNote);
        $prefix    = '[Rejected] ';

        try {
            $fullNote = $prefix . $reason;

            // Single atomic UPDATE — no SELECT needed, checks status in the WHERE clause.
            // Uses a parameterized expression to safely append the rejection note.
            $affected = DB::table('vehicle_bookings')
                ->where('vehiclebooking_id', $bookingId)
                ->where('status', 'pending')
                ->when(!$this->includeDeleted, fn($q) => $q->whereNull('deleted_at'))
                ->update([
                    'status' => 'rejected',
                    'notes'  => DB::raw(
                        "TRIM(CONCAT(COALESCE(notes, ''), IF(COALESCE(notes, '') = '', '', '\n'), " .
                        DB::getPdo()->quote($fullNote) . "))"
                    ),
                ]);

            if ($affected === 0) {
                throw new \RuntimeException("Booking #{$bookingId} could not be rejected — it may no longer be in pending status.");
            }

            $this->showRejectModal   = false;
            $this->rejectResultType  = 'success';
            $this->rejectResultTitle = 'Booking Rejected';
            $this->rejectResultMessage   = "Booking #{$bookingId} has been successfully rejected with a reason.";
            $this->rejectResultBookingId = $bookingId;
            $this->showRejectResult  = true;

            // Optimistically remove the card from the current list without a full re-render
            $this->dispatch('booking-rejected', id: $bookingId);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            $this->showRejectModal        = false;
            $this->rejectResultType       = 'error';
            $this->rejectResultTitle      = 'Cannot Reject';
            $this->rejectResultMessage    = $e->getMessage();
            $this->rejectResultBookingId  = $bookingId;
            $this->showRejectResult       = true;
        } catch (\Throwable $e) {
            report($e);
            $this->showRejectModal        = false;
            $this->rejectResultType       = 'error';
            $this->rejectResultTitle      = 'Error';
            $this->rejectResultMessage    = 'Failed to reject: ' . $e->getMessage();
            $this->rejectResultBookingId  = $bookingId;
            $this->showRejectResult       = true;
        }
    }

    public function closeRejectResult(): void
    {
        $this->showRejectResult = false;
        $this->rejectResultTitle = '';
        $this->rejectResultMessage = '';
        $this->rejectResultBookingId = null;
        $this->rejectId = null;
        $this->rejectNote = '';
    }

    public function confirmMarkReturned(int $id): void
    {
        try {
            $b = VehicleBooking::findOrFail($id);
            if (!in_array($b->status, ['approved', 'on_progress', 'late_return'], true)) {
                $this->dispatch('toast', type: 'warning', title: 'Cannot Update', message: "Booking #{$b->vehiclebooking_id} cannot be completed from status '{$b->status}'.");
                return;
            }

            if ($b->end_at) {
                $end = \Carbon\Carbon::parse($b->end_at, $this->tz);
                $now = \Carbon\Carbon::now($this->tz);
                if ($now->greaterThan($end) && $end->diffInHours($now) >= 3) {
                    $this->lateBookingId = $id;
                    $this->lateReasonText = '';
                    $this->showLateReasonModal = true;
                    return;
                }
            }

            // If not > 3 hours late, mark done directly
            $this->markReturned($id);

        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to process: ' . $e->getMessage());
        }
    }

    public function closeLateReasonModal(): void
    {
        $this->showLateReasonModal = false;
        $this->lateBookingId = null;
        $this->lateReasonText = '';
    }

    public function submitLateReason(): void
    {
        $this->validate([
            'lateReasonText' => 'required|string|min:5|max:2000',
            'lateBookingId'  => 'required|integer',
        ]);

        try {
            DB::transaction(function () {
                $b = VehicleBooking::lockForUpdate()->findOrFail($this->lateBookingId);
                $b->status = 'completed';
                $prefix = '[Late Return Reason] ';
                $fullNote = $prefix . trim($this->lateReasonText);
                $b->notes = trim($b->notes . "\n" . $fullNote);
                $b->save();
            });

            $this->showLateReasonModal = false;
            $this->dispatch('toast', type: 'success', title: 'Completed', message: 'Booking marked as completed with reason.');
            $this->resetPage();

        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to update: ' . $e->getMessage());
        }
    }

    public function markReturned(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $b = VehicleBooking::lockForUpdate()
                    ->when($this->includeDeleted, fn($q) => $q->withTrashed())
                    ->findOrFail($id);
                if (!in_array($b->status, ['approved', 'on_progress'], true)) {
                    throw new \RuntimeException("Booking #{$b->vehiclebooking_id} is not yet on progress.");
                }

                $b->status = 'completed';

                if ($b->end_at) {
                    $end = \Carbon\Carbon::parse($b->end_at, $this->tz);
                    $now = \Carbon\Carbon::now($this->tz);
                    if ($now->greaterThan($end)) {
                        $diffInHours = $end->diffInHours($now);
                        if ($diffInHours >= 1 && $diffInHours < 3) {
                            $prefix = '[Late Return] ';
                            $b->notes = trim($b->notes . "\n" . $prefix);
                        }
                    }
                }

                $b->save();
            });

            $this->dispatch('toast', type: 'success', title: 'Returned', message: 'Status updated to Returned.');
            $this->resetPage();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Update', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to update: ' . $e->getMessage());
        }
    }

    public function markDone(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $b = VehicleBooking::lockForUpdate()
                    ->when($this->includeDeleted, fn($q) => $q->withTrashed())
                    ->findOrFail($id);
                if ($b->status !== 'returned') {
                    throw new \RuntimeException("Booking #{$b->vehiclebooking_id} has not been returned yet.");
                }
                $afterCount = VehicleBookingPhoto::where('vehiclebooking_id', $b->vehiclebooking_id)
                    ->where('photo_type', 'after')
                    ->count();
                if ($afterCount < 1) {
                    throw new \RuntimeException('Please upload at least 1 AFTER photo first.');
                }
                $b->status = 'completed';
                $b->save();
            });

            $this->dispatch('toast', type: 'success', title: 'Completed', message: 'Booking marked as completed.');
            $this->resetPage();
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Complete', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to update: ' . $e->getMessage());
        }
    }

    // *** BARU: Metode untuk Detail Modal ***
    public function showDetails(int $id): void
    {
        try {
            $booking = VehicleBooking::when($this->includeDeleted, fn($q) => $q->withTrashed())
                ->findOrFail($id);

            $photos = VehicleBookingPhoto::where('vehiclebooking_id', $id)
                ->with('user') // Pastikan relasi user ada di model VehicleBookingPhoto
                ->orderBy('created_at')
                ->get();

            $this->selectedBooking = $booking;

            // Sort photos
            $before = [];
            $after = [];
            foreach ($photos as $photo) {
                if ($photo->photo_type === 'after') {
                    $after[] = $photo;
                } else {
                    $before[] = $photo;
                }
            }
            $this->selectedPhotos = ['before' => $before, 'after' => $after];

            $this->showDetailModal = true;
            $this->resetErrorBag();

        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to load details: ' . $e->getMessage());
        }
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedBooking = null;
        $this->selectedPhotos = ['before' => [], 'after' => []];
        $this->resetErrorBag();
    }
    // *** END BARU ***

    // ───────── Mobile Filter Modal ─────────
    public function openFilterModal(): void
    {
        $this->showFilterModal = true;
    }

    public function closeFilterModal(): void
    {
        $this->showFilterModal = false;
    }

    public function selectVehicle(int $vehicleId): void
    {
        $this->vehicleFilter = $vehicleId;
        $this->resetPage();
        $this->showFilterModal = false;
    }

    public function clearVehicleFilter(): void
    {
        $this->vehicleFilter = null;
        $this->resetPage();
        $this->showFilterModal = false;
    }
}