<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriorityRoomBooking extends Model
{
    protected $table = 'priority_room_bookings';
    protected $primaryKey = 'id';

    protected $fillable = [
        'company_id',
        'manager_id',
        'room_id',
        'meeting_title',
        'date',
        'start_time',
        'end_time',
        'number_of_attendees',
        'special_notes',
        'status',
        'cancels_booking_id',
        'handled_by',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'number_of_attendees' => 'integer',
    ];

    const STATUS_PENDING_RECEIPT           = 'pending_receipt';           
    const STATUS_PENDING_CANCELLATION      = 'pending_cancellation';      
    const STATUS_APPROVED                  = 'approved';                  
    const STATUS_COMPLETED                 = 'completed';                 
    const STATUS_REJECTED                  = 'rejected';                  
    const STATUS_CONFLICT_DENIED           = 'cancelled_conflict_denied'; 

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id', 'user_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id', 'room_id');
    }

    public function cancelledBooking(): BelongsTo
    {
        return $this->belongsTo(BookingRoom::class, 'cancels_booking_id', 'bookingroom_id');
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

    /**
     * Canonical expiry entry-point used by the dashboard and any other caller
     * that follows the PriorityVehicleBooking naming convention.
     * Delegates to autoRejectExpiredPending() so all expiry logic lives in
     * one place and both methods stay in sync.
     */
    public static function autoExpirePending(?int $companyId): void
    {
        static::autoRejectExpiredPending($companyId);
    }

    /**
     * Auto-reject pending bookings whose END time has already passed
     * (meaning the meeting window is over and they were never approved).
     *
     * IMPORTANT: This must be called AFTER autoApproveNonClashing() so that
     * a booking reaching its start time is first promoted to approved before
     * any expiry check runs. Mirrors the ordinary BookingRoom behaviour in
     * AutoCompleteBookings where pending bookings with an expired end_time
     * are auto-rejected.
     */
    public static function autoRejectExpiredPending(?int $companyId): void
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = now($tz);

        static::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereIn('status', [self::STATUS_PENDING_RECEIPT, self::STATUS_PENDING_CANCELLATION])
            ->where(function ($q) use ($now) {
                $q->where('date', '<', $now->toDateString())
                  ->orWhere(function ($q2) use ($now) {
                      $q2->where('date', $now->toDateString())
                         ->where('end_time', '<', $now->format('H:i:s'));
                  });
            })
            ->update([
                'status'           => self::STATUS_REJECTED,
                'rejection_reason' => 'Auto-rejected: booking window expired without approval.',
            ]);
    }

    public static function autoApproveNonClashing(?int $companyId): void
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = now($tz);

        static::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereIn('status', [self::STATUS_PENDING_RECEIPT, self::STATUS_PENDING_CANCELLATION])
            ->where(function ($q) use ($now) {
                $q->where('date', '<', $now->toDateString())
                  ->orWhere(function ($q2) use ($now) {
                      $q2->where('date', $now->toDateString())
                         ->where('start_time', '<=', $now->format('H:i:s'));
                  });
            })
            ->where(function ($q) use ($now) {
                $q->where('date', '>', $now->toDateString())
                  ->orWhere(function ($q2) use ($now) {
                      $q2->where('date', $now->toDateString())
                         ->where('end_time', '>', $now->format('H:i:s'));
                  });
            })
            ->update([
                'status'     => self::STATUS_APPROVED,
                'updated_at' => $now->toDateTimeString(),
            ]);
    }

    public static function autoCompleteApproved(?int $companyId): void
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $now = now($tz);

        static::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->where('status', self::STATUS_APPROVED)
            ->where(function ($q) use ($now) {
                $q->where('date', '<', $now->toDateString())
                  ->orWhere(function ($q2) use ($now) {
                      $q2->where('date', $now->toDateString())
                         ->where('end_time', '<=', $now->format('H:i:s'));
                  });
            })
            ->update([
                'status'     => self::STATUS_COMPLETED,
                'updated_at' => $now->toDateTimeString(),
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
            self::STATUS_COMPLETED            => 'blue',
            self::STATUS_REJECTED             => 'red',
            self::STATUS_CONFLICT_DENIED      => 'red',
            default                           => 'gray',
        };
    }
}
