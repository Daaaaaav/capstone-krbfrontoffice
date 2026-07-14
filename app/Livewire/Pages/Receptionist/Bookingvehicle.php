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
    // form fields
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
    public string $odd_even_area = 'tidak';      // sesuaikan enum di DB
    public ?string $purpose_type = null;         // jenis keperluan
    public ?string $purpose_type_other = null;   // untuk opsi "lainnya"


    /** @var \Illuminate\Support\Collection */
    public $departments;
    public $users;
    public $vehicles;
    public bool $hasVehicles = false;

    /** Plain array for the Alpine combobox — updated whenever department changes */
    public array $usersForCombobox = [];
    public array $vehiclesForCombobox = [];

    // search box seperti di MeetingSchedule
    public string $departmentSearch = '';
    public string $userSearch = '';
    public string $vehicleSearch = '';

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

        // semua departemen di company ini, urut A-Z
        $this->departments = Department::where('company_id', $companyId)
            ->orderBy('department_name', 'asc')
            ->get();

        // user awal kosong, baru diisi ketika departemen dipilih
        $this->users = collect();

        $this->loadVehicles();

        $this->hasVehicles = !empty($this->vehiclesForCombobox);

        // default tanggal = hari ini
        $today = now($this->tz)->toDateString();
        $this->date_from = $today;
        $this->date_to   = $today;
    }

    protected function loadVehicles(): void
    {
        $user = Auth::user();
        $companyId = (int) ($user?->company_id ?? 0);

        $query = Vehicle::where('company_id', $companyId)
            ->where('is_active', 1)
            ->orderBy('name', 'asc')
            ->get(['vehicle_id', 'name', 'plate_number']);

        if ($this->odd_even_area === 'ganjil' || $this->odd_even_area === 'genap') {
            $query = $query->filter(function ($v) {
                if (!$v->plate_number) return true; // keep if no plate number
                $number = preg_replace('/[^0-9]/', '', $v->plate_number);
                if ($number === '') return true;

                $lastDigit = (int) substr($number, -1);
                $isEven = ($lastDigit % 2 === 0);

                if ($this->odd_even_area === 'ganjil') {
                    return !$isEven;
                }
                return $isEven;
            });
        }

        $this->vehiclesForCombobox = $query->map(function ($v) {
            $vehicleLabel = $v->name ?? __('app.vehicle');
            $plate = $v->plate_number ? ' — ' . $v->plate_number : '';
            return [
                'id' => $v->vehicle_id,
                'label' => $vehicleLabel . $plate,
            ];
        })->values()->toArray();
    }

    /**
     * Auto-called saat departemen diganti.
     */
    public function updatedDepartmentId($value): void
    {
        // reset pilihan + search user
        $this->borrower_user_id = null;
        $this->userSearch = '';

        if (!$value) {
            // kalau dikosongkan, list user dikosongkan
            $this->users = collect();
            $this->usersForCombobox = [];
            return;
        }

        $authUser  = Auth::user();
        $companyId = (int) ($authUser?->company_id ?? 0);

        // load user hanya dari departemen terpilih, urut A-Z
        $this->users = User::where('company_id', $companyId)
            ->where('department_id', $value)
            ->orderBy('full_name', 'asc')
            ->get();

        // keep a plain array for the Alpine combobox
        $this->usersForCombobox = $this->users
            ->map(fn($u) => [
                'id'    => $u->user_id,
                'label' => $u->full_name,
                'email' => $u->email,
            ])
            ->values()
            ->all();
    }

    /**
     * Auto-called saat filter ganjil/genap diganti.
     */
    public function updatedOddEvenArea($value): void
    {
        $this->vehicle_id = null;
        $this->vehicleSearch = '';
        $this->loadVehicles();
    }


    public function submit()
    {
        $this->validate();

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

        // nama borrower dari user yang dipilih, atau dari input text
        $borrowerName = $this->borrower_name;
        if ($this->borrower_user_id) {
            $u = $this->users->firstWhere('user_id', $this->borrower_user_id);
            if ($u) {
                $borrowerName = $u->full_name;
            }
        }

        // jika keperluan_type = lainnya + ada detail lainnya, boleh digabung ke purpose
        if ($this->purpose_type === 'lainnya' && $this->purpose_type_other) {
            $this->purpose .= ' (Lainnya: '.$this->purpose_type_other.')';
        }

        // ── Late-return block ──────────────────────────────────────────────
        // Refuse any new booking for a vehicle that currently has an
        // unresolved late_return booking. The receptionist must mark that
        // overdue booking as returned before the vehicle can be booked again.
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
        // ── end late-return block ──────────────────────────────────────────

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
            'is_approve'     => 0,
            'status'         => 'pending',
            'notes'          => null,
        ]);

        $this->dispatch('$refresh');
        $this->dispatch('toast', type: 'success', title: 'Ditambah', message: 'Terkirim..', duration: 3000);

        // reset form (list departemen/vehicles tetap)
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
            'vehicleSearch',
        ]);

        // list user dikosongkan lagi
        $this->users = collect();
        $this->usersForCombobox = [];

        $today = now($this->tz)->toDateString();
        $this->date_from     = $today;
        $this->date_to       = $today;
        $this->odd_even_area = 'tidak';
    }

    public function render()
    {
        // === FILTER + SORT DEPARTEMEN BERDASARKAN SEARCH ===
        $departments = $this->departments;

        if (trim($this->departmentSearch) !== '') {
            $term = mb_strtolower(trim($this->departmentSearch));

            $departments = $departments->filter(function ($d) use ($term) {
                return str_contains(mb_strtolower($d->department_name ?? ''), $term);
            });
        }

        // pastikan tetap urut A-Z setelah filter
        $departments = $departments
            ->sortBy('department_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        // === FILTER + SORT USERS (KALAU SUDAH ADA DEPARTEMEN) ===
        $users = $this->users;

        if ($this->department_id) {
            if (trim($this->userSearch) !== '') {
                $term = mb_strtolower(trim($this->userSearch));

                $users = $users->filter(function ($u) use ($term) {
                    return str_contains(mb_strtolower($u->full_name ?? ''), $term)
                        || str_contains(mb_strtolower($u->email ?? ''), $term);
                });
            }

            // pastikan user juga tetap urut A-Z setelah filter
            $users = $users
                ->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
        } else {
            // kalau belum pilih departemen, kosongin saja
            $users = collect();
        }

        return view('livewire.pages.receptionist.bookingvehicle', [
            'departments' => $departments,
            'users'       => $users,
            'hasVehicles' => $this->hasVehicles,
        ]);
    }
}
