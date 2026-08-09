<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{

    use HasFactory;

    protected $table = 'companies';
    protected $primaryKey = 'company_id';
    public $incrementing = true;        
    protected $keyType = 'int';          
    public $timestamps = true;

    protected $fillable = ['company_name', 'company_address', 'company_email']; 

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id', 'company_id');
    }
}
