<?php

namespace App\Livewire\Booking;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Vehicle;
use App\Models\VehicleBooking;
use App\Services\SecurityMonitoringService;

class QuickVehicleBookModal extends Component
{
    public bool   $show = false;
    public string $mode = 'create'; 

    public ?int    $vehicle_id    = null;
    public string  $borrower_name = '';
    public ?string $date_from     = null;
    public ?string $date_to       = null;
    public ?string $start_time    = null;
    public ?string $end_time      = null;
    public string  $purpose       = '';
    public ?string $destination   = null;
    public ?string $purpose_type  = null;  
    public string  $odd_even_area = 'tidak';

    public ?string $ai_department     = null;
    public ?string $ai_historical_user = null;

    public array $vehicles = [];

    protected string $tz = 'Asia/Jakarta';

    public function mount(): void
    {
        $companyId = Auth::user()->company_id ?? null;

        $this->vehicles = Vehicle::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['vehicle_id', 'name', 'plate_number'])
            ->map(fn($v) => [
                'id'    => $v->vehicle_id,
                'label' => trim(($v->name ?? '') . ($v->plate_number ? ' — ' . $v->plate_number : '')),
            ])
            ->values()
            ->all();
    }

    #[On('open-quick-vehicle-book')]
    public function open(array $payload = []): void
    {
        $this->resetForm();

        $now = Carbon::now($this->tz);

        $this->vehicle_id           = isset($payload['vehicleId']) && $payload['vehicleId']
                                          ? (int) $payload['vehicleId'] : null;
        $this->borrower_name        = $payload['borrowerName']   ?? '';
        $this->date_from            = ($payload['dateFrom']      ?? '') ?: $now->toDateString();
        $this->date_to              = ($payload['dateTo']        ?? '') ?: $now->toDateString();
        $this->start_time           = ($payload['startTime']     ?? '') ?: null;
        $this->end_time             = ($payload['endTime']       ?? '') ?: null;
        $this->purpose              = $payload['purpose']        ?? '';
        $this->destination          = ($payload['destination']   ?? '') ?: null;
        $this->purpose_type         = ($payload['purposeType']   ?? '') ?: null;
        $this->odd_even_area        = $payload['oddEvenArea']    ?? 'tidak';
        $this->ai_department        = ($payload['department']    ?? '') ?: null;
        $this->ai_historical_user   = ($payload['historicalUser'] ?? '') ?: null;
        $this->mode                 = in_array($payload['mode'] ?? '', ['create', 'rebook'], true)
                                          ? $payload['mode'] : 'create';

        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
    }


    public function submit(): void
    {
        $this->validate([
            'vehicle_id'   => 'required|integer|exists:vehicles,vehicle_id',
            'borrower_name'=> 'required|string|max:255',
            'date_from'    => 'required|date',
            'date_to'      => 'required|date|after_or_equal:date_from',
            'start_time'   => 'required',
            'end_time'     => 'required',
            'purpose'      => 'required|string|max:255',
            'destination'  => 'nullable|string|max:255',
            'purpose_type' => 'required|in:dinas,operasional,antar_jemput,lainnya',
            'odd_even_area'=> 'nullable|string|max:50',
        ]);

        $now     = Carbon::now($this->tz);
        $startAt = Carbon::parse($this->date_from . ' ' . $this->start_time, $this->tz);
        $endAt   = Carbon::parse($this->date_to   . ' ' . $this->end_time,   $this->tz);

        if ($startAt->lt($now)) {
            $this->dispatch('toast', type: 'error', message: 'Start time cannot be in the past.');
            return;
        }

        if ($startAt->greaterThanOrEqualTo($endAt)) {
            $this->dispatch('toast', type: 'error', message: 'The start time must be before the end time.');
            return;
        }

        $blocker = VehicleBooking::findLateReturnBlocker((int) $this->vehicle_id);
        if ($blocker) {
            $this->dispatch('toast', type: 'error',
                message: 'Vehicle unavailable — unresolved late return (Booking #' . $blocker->vehiclebooking_id . ').');
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
            $this->dispatch('toast', type: 'error',
                message: 'This vehicle is already booked from ' . $conflict->start_at->format('H:i') . ' to ' . $conflict->end_at->format('H:i') . '. (1-hour buffer required)');
            return;
        }

        SecurityMonitoringService::logFormSubmit('quick_vehicle_booking', [
            'vehicle_id'   => $this->vehicle_id,
            'borrower_name'=> $this->borrower_name,
            'date_from'    => $this->date_from,
            'date_to'      => $this->date_to,
            'purpose'      => $this->purpose,
        ]);

        DB::transaction(function () use ($startAt, $endAt) {
            VehicleBooking::create([
                'vehicle_id'    => (int) $this->vehicle_id,
                'company_id'    => Auth::user()->company_id ?? 1,
                'user_id'       => Auth::id(),
                'department_id' => Auth::user()->department_id ?? null,
                'borrower_name' => $this->borrower_name,
                'start_at'      => $startAt,
                'end_at'        => $endAt,
                'purpose'       => $this->purpose,
                'destination'   => $this->destination,
                'odd_even_area' => $this->odd_even_area,
                'purpose_type'  => $this->purpose_type,
                'terms_agreed'  => 1,
                'is_approve'    => 0,
                'status'        => 'pending',
                'notes'         => null,
            ]);
        });

        $msg = $this->mode === 'rebook'
            ? 'Vehicle rebook saved (pending approval).'
            : 'Vehicle booking saved (pending approval).';

        $this->dispatch('toast', type: 'success', message: $msg);
        $this->dispatch('vehicle-booking-created');
        $this->close();
    }

    protected function resetForm(): void
    {
        $this->vehicle_id         = null;
        $this->borrower_name      = '';
        $this->date_from          = null;
        $this->date_to            = null;
        $this->start_time         = null;
        $this->end_time           = null;
        $this->purpose            = '';
        $this->destination        = null;
        $this->purpose_type       = null;
        $this->odd_even_area      = 'tidak';
        $this->ai_department      = null;
        $this->ai_historical_user = null;
        $this->mode               = 'create';
    }

    public function render()
    {
        $now      = Carbon::now($this->tz);
        $minDate  = $now->toDateString();
        $minStart = ($this->date_from === $minDate) ? $now->format('H:i') : '00:00';

        $vehicleLabel = '';
        if ($this->vehicle_id) {
            $v = collect($this->vehicles)->firstWhere('id', $this->vehicle_id);
            $vehicleLabel = $v['label'] ?? '';
        }

        return view('livewire.booking.quick-vehicle-book-modal', [
            'minDate'      => $minDate,
            'minStart'     => $minStart,
            'vehicleLabel' => $vehicleLabel,
        ]);
    }
}
