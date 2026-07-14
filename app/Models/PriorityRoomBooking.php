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

    // Status constants
    const STATUS_PENDING_RECEIPT           = 'pending_receipt';           // Created, no conflict
    const STATUS_PENDING_CANCELLATION      = 'pending_cancellation';      // Waiting receptionist approval to cancel conflict
    const STATUS_APPROVED                  = 'approved';                  // Receptionist approved (incl. cancellation)
    const STATUS_REJECTED                  = 'rejected';                  // Receptionist rejected
    const STATUS_CONFLICT_DENIED           = 'cancelled_conflict_denied'; // Receptionist denied the cancellation request

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
     * Auto-expire any pending priority room bookings whose scheduled time has already passed.
     * Marks them as rejected so they don't clog the queue.
     */
    public static function autoExpirePending(?int $companyId): void
    {
        static::query()
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->whereIn('status', [self::STATUS_PENDING_RECEIPT, self::STATUS_PENDING_CANCELLATION])
            ->where(function ($q) {
                $q->where('date', '<', now()->toDateString())
                  ->orWhere(function ($q2) {
                      $q2->where('date', now()->toDateString())
                         ->where('end_time', '<', now()->format('H:i:s'));
                  });
            })
            ->update([
                'status'           => self::STATUS_REJECTED,
                'rejection_reason' => 'Auto-expired: meeting time has passed.',
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
            self::STATUS_REJECTED             => 'red',
            self::STATUS_CONFLICT_DENIED      => 'red',
            default                           => 'gray',
        };
    }
}
