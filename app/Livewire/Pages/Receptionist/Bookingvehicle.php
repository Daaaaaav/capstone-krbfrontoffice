<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\VehicleBooking;
use App\Models\Vehicle;
use App\Models\Department;
use App\Models\User;
use App\Services\SecurityMonitoringService;

#[Layout('layouts.receptionist')]
#[Title('Vehicle Booking')]
class Bookingvehicle extends Component
{
    public bool $showVehicleScheduleModal = false;
    public ?int $selectedVehicleForSchedule = null;
    public array $vehicleScheduleData = [];
    public bool $showBookingDetailModal = false;
    public array $selectedBookingDetail = [];
    public bool $showVehicleRejectModal = false;
    public ?int $vehicleRejectId = null;
    public string $vehicleRejectNote = '';
    public ?int $department_id = null;
    public ?int $borrower_user_id = null;
    public string $borrower_name = '';
    public ?int $vehicle_id = null;
    public ?string $date_from = null;
    public ?string $date_to = null;
    public ?string $start_time = null;
    public ?string $end_time = null;
    public string $purpose = '';
    public ?string $destination = null;
    public string $odd_even_area = 'tidak';      
    public ?string $purpose_type = null;         
    public ?string $purpose_type_other = null;   
    public $departments;
    public $users;
    public $vehicles;
    public bool $hasVehicles = false;
    public array $usersForCombobox = [];
    public string $departmentSearch = '';
    public string $userSearch = '';

    protected string $tz = 'Asia/Jakarta';

    protected function rules(): array
    {
        return [
            'department_id'        => ['required', 'integer', 'exists:departments,department_id'],
            'borrower_user_id'     => ['nullable', 'integer', 'exists:users,user_id'],
            'borrower_name'        => ['required_without:borrower_user_id', 'string', 'max:255'],
            'vehicle_id'           => ['required', 'integer', 'exists:vehicles,vehicle_id'],

            'date_from'            => ['required', 'date'],
            'date_to'              => ['required', 'date', 'after_or_equal:date_from'],
            'start_time'           => ['required'],
            'end_time'             => ['required'],

            'purpose'              => ['required', 'string', 'max:255'],
            'destination'          => ['nullable', 'string', 'max:255'],
            'odd_even_area'        => ['nullable', 'string', 'max:50'],
            'purpose_type'         => ['required', 'string', 'in:dinas,operasional,antar_jemput,lainnya'],
            'purpose_type_other'   => ['required_if:purpose_type,lainnya', 'nullable', 'string', 'max:255'],
        ];
    }

    public function mount()
    {
        $user = Auth::user();
        $companyId = (int) ($user?->company_id ?? 0);

        $this->departments = Department::where('company_id', $companyId)
            ->orderBy('department_name', 'asc')
            ->get();
        $this->users = collect();
        $this->vehicles = Vehicle::where('company_id', $companyId)
            ->where('is_active', 1)
            ->orderBy('name', 'asc')
            ->get(['vehicle_id', 'name', 'plate_number']);
        $this->hasVehicles = $this->vehicles->count() > 0;
        $today = now($this->tz)->toDateString();
        $this->date_from = $today;
        $this->date_to   = $today;
    }

    public function updatedDepartmentId($value): void
    {
        $this->borrower_user_id = null;
        $this->userSearch = '';

        if (!$value) {
            $this->users = collect();
            $this->usersForCombobox = [];
            return;
        }

        $authUser  = Auth::user();
        $companyId = (int) ($authUser?->company_id ?? 0);

        $this->users = User::where('company_id', $companyId)
            ->where('department_id', $value)
            ->orderBy('full_name', 'asc')
            ->get();

        $this->usersForCombobox = $this->users
            ->map(fn($u) => [
                'id'    => $u->user_id,
                'label' => $u->full_name,
                'email' => $u->email,
            ])
            ->values()
            ->all();
    }

    public function submit()
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Validation Failed',
                message: 'Please check the form for errors.',
                duration: 5000
            );
            throw $e;
        }

        try {
            SecurityMonitoringService::logFormSubmit('vehicle_booking', [
                'department_id' => $this->department_id,
                'borrower_user_id' => $this->borrower_user_id,
                'borrower_name' => $this->borrower_name,
                'vehicle_id' => $this->vehicle_id,
                'date_from' => $this->date_from,
                'date_to' => $this->date_to,
                'start_time' => $this->start_time,
                'end_time' => $this->end_time,
                'purpose' => $this->purpose,
                'destination' => $this->destination,
                'odd_even_area' => $this->odd_even_area,
                'purpose_type' => $this->purpose_type,
                'purpose_type_other' => $this->purpose_type_other,
            ]);

            $user = Auth::user();
            $companyId = (int) ($user?->company_id ?? 0);

            $startAt = Carbon::parse($this->date_from.' '.$this->start_time, $this->tz);
            $endAt   = Carbon::parse($this->date_to.' '.$this->end_time, $this->tz);

            $borrowerName = $this->borrower_name;
            if ($this->borrower_user_id) {
                $u = \App\Models\User::find($this->borrower_user_id);
                if ($u) {
                    $borrowerName = $u->full_name;
                }
            }

        $maxAdvanceDate = now($this->tz)->addMonths(1);
        if ($startAt->greaterThan($maxAdvanceDate)) {
            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Booking Limit Exceeded',
                message: 'Booking can only be made up to 1 month in advance.',
                duration: 7000
            );
            return;
        }

        $minAdvanceDate = now($this->tz)->addMinutes(30);
        if ($startAt->lessThan($minAdvanceDate)) {
            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Invalid Booking Time',
                message: 'Bookings must be made at least 1 hour in advance.',
                duration: 7000
            );
            return;
        }
        if ($this->purpose_type === 'lainnya' && $this->purpose_type_other) {
            $this->purpose .= ' (Lainnya: '.$this->purpose_type_other.')';
        }

        $blocker = VehicleBooking::findLateReturnBlocker((int) $this->vehicle_id);
        if ($blocker) {
            $tz           = $this->tz;
            $overdueSince = \Carbon\Carbon::parse($blocker->end_at, $tz);
            $diffMins     = (int) $overdueSince->diffInMinutes(now($tz));
            $diffHours    = (int) $overdueSince->diffInHours(now($tz));
            $diffDays     = (int) $overdueSince->diffInDays(now($tz));

            if ($diffDays >= 1) {
                $overdueLabel = $diffDays . ' day' . ($diffDays > 1 ? 's' : '');
            } elseif ($diffHours >= 1) {
                $overdueLabel = $diffHours . ' hour' . ($diffHours > 1 ? 's' : '');
            } else {
                $overdueLabel = max(1, $diffMins) . ' minute' . ($diffMins !== 1 ? 's' : '');
            }

            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Vehicle Unavailable',
                message: 'This vehicle cannot be booked — it has an unresolved late return '
                    . '(Booking #' . $blocker->vehiclebooking_id . ', overdue by ' . $overdueLabel . '). '
                    . 'Please mark it as returned first.',
                duration: 7000,
            );
            return;
        }

        $conflict = VehicleBooking::where('vehicle_id', $this->vehicle_id)
            ->whereIn('status', ['pending', 'approved', 'on_progress'])
            ->where(function($q) use ($startAt, $endAt) {
                $q->where('start_at', '<', $endAt->toDateTimeString())
                  ->whereRaw('DATE_ADD(end_at, INTERVAL 1 HOUR) > ?', [$startAt->toDateTimeString()]);
            })
            ->first();

        if ($conflict) {
            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Vehicle Unavailable',
                message: 'This vehicle is already booked from ' . $conflict->start_at->format('H:i') . ' to ' . $conflict->end_at->format('H:i') . '. There is a mandatory 1-hour buffer before it can be booked again.',
                duration: 7000,
            );
            return;
        }

            VehicleBooking::create([
                'vehicle_id'     => $this->vehicle_id,
                'company_id'     => $companyId,
                'department_id'  => $this->department_id,
                'user_id'        => $this->borrower_user_id,
                'borrower_name'  => $borrowerName,
                'start_at'       => $startAt,
                'end_at'         => $endAt,
                'purpose'        => $this->purpose,
                'destination'    => $this->destination,
                'odd_even_area'  => $this->odd_even_area,
                'purpose_type'   => $this->purpose_type,
                'terms_agreed'   => 1,
                'status'         => 'pending',
                'notes'          => null,
            ]);

            $this->dispatch('$refresh');
            $this->dispatch('toast', type: 'success', title: 'Submitted', message: 'Vehicle booking has been created.', duration: 3000);

            $this->reset([
                'department_id',
                'borrower_user_id',
                'borrower_name',
                'vehicle_id',
                'date_from',
                'date_to',
                'start_time',
                'end_time',
                'purpose',
                'destination',
                'odd_even_area',
                'purpose_type',
                'purpose_type_other',
                'departmentSearch',
                'userSearch',
            ]);
    
            $this->users = collect();
            $this->usersForCombobox = [];
            $today = now($this->tz)->toDateString();
            $this->date_from     = $today;
            $this->date_to       = $today;
            $this->odd_even_area = 'tidak';

        } catch (\Throwable $e) {
            report($e);
            $this->dispatch(
                'toast',
                type: 'error',
                title: 'Submission Failed',
                message: 'An error occurred while saving the booking: ' . $e->getMessage(),
                duration: 7000
            );
        }
    }

    public function openBookingDetail($bookingId): void
    {
        $booking = collect($this->vehicleScheduleData)->first(fn($b) => (string) $b['id'] === (string) $bookingId);
        if ($booking) {
            $this->selectedBookingDetail = $booking;
            $this->showBookingDetailModal = true;
        }
    }

    public function approveVehicleBookingFromDirectory(int $id): void
    {
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
                $b = VehicleBooking::lockForUpdate()->findOrFail($id);
                if ($b->status !== 'pending') {
                    throw new \RuntimeException("Booking #{$b->vehiclebooking_id} is not pending.");
                }
                if (now() < $b->start_at) {
                    $b->status = 'approved';
                } else {
                    $b->status = 'on_progress';
                }
                $b->save();
            });

            if ($this->selectedVehicleForSchedule) {
                $this->openVehicleScheduleModal($this->selectedVehicleForSchedule);
            }
            $this->showBookingDetailModal = false;
            $this->dispatch('toast', type: 'success', title: 'Approved', message: 'Vehicle booking has been approved.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'warning', title: 'Cannot Approve', message: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Failed to approve: ' . $e->getMessage());
        }
    }

    public function openVehicleRejectFromDirectory(int $id): void
    {
        $this->vehicleRejectId        = $id;
        $this->vehicleRejectNote      = '';
        $this->showVehicleRejectModal = true;
        $this->showBookingDetailModal = false;
    }

    public function closeVehicleReject(): void
    {
        $this->showVehicleRejectModal = false;
        $this->vehicleRejectId        = null;
        $this->vehicleRejectNote      = '';
    }

    public function confirmVehicleRejectFromDirectory(): void
    {
        $this->validate([
            'vehicleRejectNote' => 'required|string|min:5|max:2000',
            'vehicleRejectId'   => 'required|integer',
        ]);

        $bookingId = (int) $this->vehicleRejectId;
        $reason    = '[Rejected] ' . trim($this->vehicleRejectNote);

        try {
            $affected = \Illuminate\Support\Facades\DB::table('vehicle_bookings')
                ->where('vehiclebooking_id', $bookingId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'notes'  => \Illuminate\Support\Facades\DB::raw(
                        "TRIM(CONCAT(COALESCE(notes, ''), IF(COALESCE(notes, '') = '', '', '\n'), " .
                        \Illuminate\Support\Facades\DB::getPdo()->quote($reason) . "))"
                    ),
                ]);

            if ($affected === 0) {
                throw new \RuntimeException("Booking #{$bookingId} could not be rejected — it may no longer be pending.");
            }

            $this->showVehicleRejectModal = false;
            if ($this->selectedVehicleForSchedule) {
                $this->openVehicleScheduleModal($this->selectedVehicleForSchedule);
            }
            $this->dispatch('toast', type: 'info', title: 'Rejected', message: 'Vehicle booking has been rejected.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', title: 'Error', message: 'Could not reject: ' . $e->getMessage());
        }
    }

    public function openVehicleScheduleModal($vehicleId): void
    {
        $this->selectedVehicleForSchedule = (int) $vehicleId;
        $now     = now($this->tz);
        $endDate = $now->copy()->addDays(30);
        $regularBookings = VehicleBooking::with(['vehicle', 'user', 'department'])
            ->where('vehicle_id', $vehicleId)
            ->whereBetween('start_at', [$now->copy()->startOfDay(), $endDate->endOfDay()])
            ->whereIn('status', ['pending', 'approved', 'on_progress', 'PENDING', 'APPROVED'])
            ->orderBy('start_at')
            ->get()
            ->map(function ($b) {
                $startAt = Carbon::parse($b->start_at, $this->tz);
                $endAt   = Carbon::parse($b->end_at,   $this->tz);
                return [
                    'id'            => $b->vehiclebooking_id,
                    'title'         => $b->purpose ?? 'Vehicle Booking',
                    'vehicle_name'  => $b->vehicle->name ?? 'Vehicle',
                    'plate_number'  => $b->vehicle->plate_number ?? '-',
                    'borrower'      => $b->user->full_name ?? $b->borrower_name ?? 'Unknown',
                    'department'    => $b->department->department_name ?? 'Unknown',
                    'purpose'       => $b->purpose ?? '-',
                    'destination'   => $b->destination ?? '-',
                    'start_date'    => $startAt->format('l, d M Y'),
                    'end_date'      => $endAt->format('l, d M Y'),
                    'start_time'    => $startAt->format('H:i'),
                    'end_time'      => $endAt->format('H:i'),
                    'start_at_full' => $startAt->format('d M Y H:i'),
                    'end_at_full'   => $endAt->format('d M Y H:i'),
                    'status'        => $b->status,
                    'is_priority'   => false,
                    'requested_by'  => null,
                ];
            });
        $priorityBookings = \App\Models\PriorityVehicleBooking::with(['vehicle', 'manager', 'department'])
            ->where('vehicle_id', $vehicleId)
            ->whereBetween('start_at', [$now->copy()->startOfDay(), $endDate->endOfDay()])
            ->whereIn('status', [
                \App\Models\PriorityVehicleBooking::STATUS_PENDING_RECEIPT,
                \App\Models\PriorityVehicleBooking::STATUS_PENDING_CANCELLATION,
                \App\Models\PriorityVehicleBooking::STATUS_APPROVED,
            ])
            ->orderBy('start_at')
            ->get()
            ->map(function ($pb) {
                $startAt = Carbon::parse($pb->start_at, $this->tz);
                $endAt   = Carbon::parse($pb->end_at,   $this->tz);
                return [
                    'id'            => 'priority-' . $pb->id,
                    'title'         => $pb->purpose ?? 'Priority Vehicle Booking',
                    'vehicle_name'  => $pb->vehicle->name ?? 'Vehicle',
                    'plate_number'  => $pb->vehicle->plate_number ?? '-',
                    'borrower'      => $pb->manager?->full_name ?? '—',
                    'department'    => $pb->department?->department_name ?? '—',
                    'purpose'       => $pb->purpose ?? '-',
                    'destination'   => $pb->destination ?? '-',
                    'start_date'    => $startAt->format('l, d M Y'),
                    'end_date'      => $endAt->format('l, d M Y'),
                    'start_time'    => $startAt->format('H:i'),
                    'end_time'      => $endAt->format('H:i'),
                    'start_at_full' => $startAt->format('d M Y H:i'),
                    'end_at_full'   => $endAt->format('d M Y H:i'),
                    'status'        => $pb->statusLabel(),
                    'is_priority'   => true,
                    'requested_by'  => $pb->manager?->full_name ?? '—',
                ];
            });

        $this->vehicleScheduleData = $regularBookings->concat($priorityBookings)
            ->sortBy(fn($b) => $b['start_date'] . ' ' . $b['start_time'])
            ->values()
            ->toArray();

        $this->showVehicleScheduleModal = true;
    }


    public function render()

    {
        $departments = $this->departments;

        if (trim($this->departmentSearch) !== '') {
            $term = mb_strtolower(trim($this->departmentSearch));

            $departments = $departments->filter(function ($d) use ($term) {
                return str_contains(mb_strtolower($d->department_name ?? ''), $term);
            });
        }

        $departments = $departments
            ->sortBy('department_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $users = $this->users;

        if ($this->department_id) {
            if (trim($this->userSearch) !== '') {
                $term = mb_strtolower(trim($this->userSearch));

                $users = $users->filter(function ($u) use ($term) {
                    return str_contains(mb_strtolower($u->full_name ?? ''), $term)
                        || str_contains(mb_strtolower($u->email ?? ''), $term);
                });
            }

            $users = $users
                ->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
        } else {
            $users = collect();
        }

        return view('livewire.pages.receptionist.bookingvehicle', [
            'departments' => $departments,
            'users'       => $users,
            'vehicles'    => $this->vehicles,
            'hasVehicles' => $this->hasVehicles,
            'vehiclesForDirectory' => Vehicle::where('company_id', (int) (Auth::user()?->company_id ?? 0))
                ->where('is_active', 1)
                ->orderBy('name', 'asc')
                ->get(['vehicle_id', 'name', 'plate_number']),
        ]);
    }
}
