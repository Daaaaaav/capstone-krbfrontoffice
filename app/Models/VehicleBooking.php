<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleBooking extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vehicle_bookings';
    protected $primaryKey = 'vehiclebooking_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'vehicle_id',
        'company_id',
        'department_id',
        'user_id',
        'borrower_name',
        'start_at',
        'end_at',
        'purpose',
        'destination',
        'odd_even_area',
        'purpose_type',
        'terms_agreed',
        'has_sim_a',   
        'status',
        'notes',
        'handover_photo',
        'return_photo',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'terms_agreed' => 'boolean',
        'has_sim_a' => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(\App\Models\Vehicle::class, 'vehicle_id', 'vehicle_id')->withTrashed();
    }

    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id', 'department_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'user_id');
    }

    public static function findLateReturnBlocker(int $vehicleId, ?int $excludeBookingId = null): ?static
    {
        return static::query()
            ->whereNull('deleted_at')
            ->where('vehicle_id', $vehicleId)
            ->where('status', 'late_return')
            ->when($excludeBookingId, fn($q) => $q->where('vehiclebooking_id', '!=', $excludeBookingId))
            ->orderBy('end_at')
            ->first();
    }

    /**
     * Prevent one user from having multiple overlapping vehicle bookings (across regular and priority).
     */
    public static function findUserBookingConflict(
        ?int $companyId,
        ?int $userId,
        string $borrowerName,
        \Carbon\Carbon $startAt,
        \Carbon\Carbon $endAt,
        ?int $excludeRegularId = null,
        ?int $excludePriorityId = null
    ): ?string {
        $cleanBorrower = trim($borrowerName);
        $userNames = array_filter([$cleanBorrower]);

        if ($userId) {
            $user = \App\Models\User::find($userId);
            if ($user) {
                if ($user->full_name) $userNames[] = trim($user->full_name);
                if ($user->name) $userNames[] = trim($user->name);
            }
        }
        $userNames = array_unique(array_filter($userNames));

        // 1. Check Regular Vehicle Bookings
        $regConflict = static::query()
            ->with(['vehicle'])
            ->whereNull('deleted_at')
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereIn('status', ['pending', 'approved', 'on_progress', 'late_return'])
            ->when($excludeRegularId, fn($q) => $q->where('vehiclebooking_id', '!=', $excludeRegularId))
            ->where('start_at', '<', $endAt->toDateTimeString())
            ->where('end_at', '>', $startAt->toDateTimeString())
            ->where(function($q) use ($userId, $userNames) {
                if ($userId) {
                    $q->where('user_id', $userId);
                }
                foreach ($userNames as $name) {
                    $q->orWhereRaw('LOWER(TRIM(borrower_name)) = ?', [strtolower($name)]);
                }
            })
            ->first();

        if ($regConflict) {
            $veh = $regConflict->vehicle ? ($regConflict->vehicle->name ?? ('Vehicle #' . $regConflict->vehicle_id)) : 'a vehicle';
            $s = $regConflict->start_at ? $regConflict->start_at->format('d M Y H:i') : '—';
            $e = $regConflict->end_at ? $regConflict->end_at->format('d M Y H:i') : '—';
            return "This user already has a regular vehicle booking ({$veh}) scheduled from {$s} to {$e}.";
        }

        // 2. Check Priority Vehicle Bookings
        $priorityConflict = \App\Models\PriorityVehicleBooking::query()
            ->with(['vehicle'])
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereIn('status', [
                \App\Models\PriorityVehicleBooking::STATUS_PENDING_RECEIPT,
                \App\Models\PriorityVehicleBooking::STATUS_PENDING_CANCELLATION,
                \App\Models\PriorityVehicleBooking::STATUS_APPROVED,
                \App\Models\PriorityVehicleBooking::STATUS_ON_PROGRESS,
                \App\Models\PriorityVehicleBooking::STATUS_LATE_RETURN,
            ])
            ->when($excludePriorityId, fn($q) => $q->where('id', '!=', $excludePriorityId))
            ->where('start_at', '<', $endAt->toDateTimeString())
            ->where('end_at', '>', $startAt->toDateTimeString())
            ->where(function($q) use ($userNames) {
                foreach ($userNames as $name) {
                    $q->orWhereRaw('LOWER(TRIM(borrower_name)) = ?', [strtolower($name)]);
                }
            })
            ->first();

        if ($priorityConflict) {
            $veh = $priorityConflict->vehicle ? ($priorityConflict->vehicle->name ?? ('Vehicle #' . $priorityConflict->vehicle_id)) : 'a vehicle';
            $s = $priorityConflict->start_at ? $priorityConflict->start_at->format('d M Y H:i') : '—';
            $e = $priorityConflict->end_at ? $priorityConflict->end_at->format('d M Y H:i') : '—';
            return "This user already has a priority vehicle booking ({$veh}) scheduled from {$s} to {$e}.";
        }

        return null;
    }
}