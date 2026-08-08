<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'departments';
    protected $primaryKey = 'department_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = ['company_id', 'department_name'];

    public function getNameAttribute()
    {
        return $this->attributes['name']
            ?? $this->attributes['department_name']
            ?? null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id', 'department_id');
    }

    public function allUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,         
            'user_departments',   
            'department_id',      
            'user_id'             
        );
    }
}