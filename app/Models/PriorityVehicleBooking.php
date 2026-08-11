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

    // Status constants — mirror VehicleBooking lifecycle exactly
    const STATUS_PENDING_RECEIPT           = 'pending_receipt';
    const STATUS_PENDING_CANCELLATION      = 'pending_cancellation';
    const STATUS_APPROVED                  = 'approved';
    const STATUS_ON_PROGRESS               = 'on_progress';
    const STATUS_LATE_RETURN               = 'late_return';   // on_progress past end_at + 1 hour
    const STATUS_COMPLETED                 = 'completed';
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
        // Only reject pending bookings whose END time has fully passed.
        // This must be called AFTER autoApproveNonClashing (or equivalent) so
        // that bookings reaching their start time are first approved.
        static::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereIn('status', [self::STATUS_PENDING_RECEIPT, self::STATUS_PENDING_CANCELLATION])
            ->where('end_at', '<', now())
            ->update([
                'status'           => self::STATUS_REJECTED,
                'rejection_reason' => 'Auto-rejected: vehicle booking window expired without approval.',
            ]);
    }

    /**
     * Transition approved bookings to on_progress when start_at arrives.
     * Mirrors VehicleBooking auto-start logic in Vehiclestatus::render().
     */
    public static function autoProgressToOnProgress(?int $companyId): void
    {
        static::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->where('status', self::STATUS_APPROVED)
            ->where('start_at', '<=', now())
            ->update(['status' => self::STATUS_ON_PROGRESS]);
    }

    /**
     * Flag on_progress/approved bookings as late_return when end_at + 1 hour passes.
     * Mirrors the late_return logic in AutoApproveBookings for VehicleBooking.
     */
    public static function autoFlagLateReturns(?int $companyId): void
    {
        static::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereIn('status', [self::STATUS_APPROVED, self::STATUS_ON_PROGRESS])
            ->whereNotNull('end_at')
            ->whereRaw('DATE_ADD(end_at, INTERVAL 1 HOUR) < ?', [now()->toDateTimeString()])
            ->update(['status' => self::STATUS_LATE_RETURN]);
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
            self::STATUS_ON_PROGRESS          => 'On the Road',
            self::STATUS_LATE_RETURN          => 'Late Return',
            self::STATUS_COMPLETED            => 'Completed',
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
            self::STATUS_LATE_RETURN          => 'red',
            self::STATUS_COMPLETED            => 'blue',
            self::STATUS_REJECTED             => 'red',
            self::STATUS_CONFLICT_DENIED      => 'red',
            default                           => 'gray',
        };
    }
}
