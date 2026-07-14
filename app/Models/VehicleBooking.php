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

    /**
     * Properti $fillable DI-UPDATE.
     * - 'is_approve' dihapus.
     * - 'has_sim_a' ditambahkan (dan tidak dikomentari).
     */
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
        'has_sim_a',    // <-- PERBAIKAN: Baris ini sekarang aktif
        'status',
        'notes',
    ];

    /**
     * Casts untuk memastikan tipe data benar.
     */
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'terms_agreed' => 'boolean',
        'has_sim_a' => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(\App\Models\Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id', 'department_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'user_id');
    }

    public function photos()
    {
        return $this->hasMany(\App\Models\VehicleBookingPhoto::class, 'vehiclebooking_id', 'vehiclebooking_id');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Check whether a vehicle is blocked by an unresolved late_return booking.
     *
     * A vehicle is blocked when it has at least one booking with status
     * 'late_return'. The block applies regardless of the requested booking
     * window — the receptionist must mark the overdue booking as returned
     * first before the vehicle can be booked again.
     *
     * @param  int       $vehicleId
     * @param  int|null  $excludeBookingId  Exclude this ID (useful when editing).
     * @return static|null  The blocking booking, or null if none.
     */
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
}