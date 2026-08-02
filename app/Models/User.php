<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $primaryKey = 'user_id';
    public $incrementing = true;
    protected $keyType = 'int';
    protected $fillable = [
        'company_id',
        'department_id',
        'role_id',
        'full_name',
        'email',
        'phone_number',
        'password',
        'is_agent',
        'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'deleted_at' => 'datetime',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn($value) => is_null($value) ? null : strtolower($value),
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->full_name,
            set: fn($value) => ['full_name' => $value],
        );
    }

    public function getAuthIdentifierName()
    {
        return 'user_id';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,   
            'user_departments',  
            'user_id',           
            'department_id'       
        );
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function commentReads(): HasMany
    {
        return $this->hasMany(TicketCommentRead::class, 'user_id', 'user_id');
    }

    public function isInDepartment(int $departmentId): bool
    {
        return $this->departments()->where('departments.department_id', $departmentId)->exists();
    }

    public function rooms()
    {
        return $this->hasMany(\App\Models\BookingRoom::class, 'user_id', 'user_id');
    }

    public function vehicles()
    {
        return $this->hasMany(\App\Models\VehicleBooking::class, 'user_id', 'user_id');
    }
}
