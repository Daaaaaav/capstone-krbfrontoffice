<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriorityVehicleBooking extends Model
{
    protected $table = 'priority_vehicle_bookings';
    protected $primaryKey = 'id';

    protected $fillable = [
        'company_id',
        'manager_id',
        'vehicle_id',
        'department_id',
        'borrower_name',
        'start_at',
        'end_at',
        'purpose',
        'destination',
        'purpose_type',
        'special_notes',
        'status',
        'cancels_booking_id',
        'handled_by',
        'rejection_reason',
        'handover_photo',
        'return_photo',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];

    // Status constants (same pattern as PriorityRoomBooking)
    const STATUS_PENDING_RECEIPT           = 'pending_receipt';
    const STATUS_PENDING_CANCELLATION      = 'pending_cancellation';
    const STATUS_APPROVED                  = 'approved';
    const STATUS_ON_PROGRESS               = 'on_progress';
    const STATUS_REJECTED                  = 'rejected';
    const STATUS_CONFLICT_DENIED           = 'cancelled_conflict_denied';

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id', 'user_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function cancelledBooking(): BelongsTo
    {
        return $this->belongsTo(VehicleBooking::class, 'cancels_booking_id', 'vehiclebooking_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by', 'user_id');
    }

    public function scopeForCompany($query, ?int $companyId)
    {
        return $companyId ? $query->where('company_id', $companyId) : $query;
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING_RECEIPT, self::STATUS_PENDING_CANCELLATION]);
    }

    public static function autoExpirePending(?int $companyId): void
    {
        static::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereIn('status', [self::STATUS_PENDING_RECEIPT, self::STATUS_PENDING_CANCELLATION])
            ->where('end_at', '<', now())
            ->update([
                'status'           => self::STATUS_REJECTED,
                'rejection_reason' => 'Auto-expired: vehicle booking time has passed.',
            ]);
    }

    public function isActionable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_RECEIPT, self::STATUS_PENDING_CANCELLATION], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_RECEIPT      => 'Pending',
            self::STATUS_PENDING_CANCELLATION => 'Awaiting Cancellation Approval',
            self::STATUS_APPROVED             => 'Approved',
            self::STATUS_ON_PROGRESS          => 'On Progress',
            self::STATUS_REJECTED             => 'Rejected',
            self::STATUS_CONFLICT_DENIED      => 'Conflict Denied',
            default                           => ucfirst((string) $this->status),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_RECEIPT      => 'yellow',
            self::STATUS_PENDING_CANCELLATION => 'orange',
            self::STATUS_APPROVED             => 'green',
            self::STATUS_ON_PROGRESS          => 'blue',
            self::STATUS_REJECTED             => 'red',
            self::STATUS_CONFLICT_DENIED      => 'red',
            default                           => 'gray',
        };
    }
}
