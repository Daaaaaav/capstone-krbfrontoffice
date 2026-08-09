<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use SoftDeletes;

    protected $table = 'deliveries';
    protected $primaryKey = 'delivery_id'; 

    protected $fillable = [
        'company_id',
        'department_id',
        'receptionist_id',
        'type',             
        'item_name',
        'nama_pengirim',
        'nama_penerima',
        'catatan',
        'status',          
        'direction',      
        'pengiriman',       
        'pengambilan',  
        'image', 
    ];

    protected $casts = [
        'pengiriman' => 'datetime',
        'pengambilan' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function receptionist()
    {
        return $this->belongsTo(User::class, 'receptionist_id', 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function scopeByCompany($q, ?int $companyId)
    {
        if ($companyId)
            $q->where('company_id', $companyId);
        return $q;
    }

    public function getFinishStatusAttribute(): string
    {
        if (($this->status ?? '') !== 'done') {
            return (string) ($this->status ?? '');
        }

        return match ($this->direction) {
            'deliver' => 'delivered',
            'taken' => 'taken',
            default => 'done',
        };
    }

    public function getFinishAtAttribute(): ?\Illuminate\Support\Carbon
    {
        return match ($this->direction) {
            'deliver' => $this->pengiriman,
            'taken' => $this->pengambilan,
            default => $this->created_at,
        };
    }
}
