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