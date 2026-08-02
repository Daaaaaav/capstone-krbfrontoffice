<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Documents extends Model
{
    protected $table = 'documents';
    protected $primaryKey = 'document_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'company_id',
        'receptionist_id',
        'document_name',
        'nama_pengirim',
        'nama_penerima',
        'type',
        'penyimpanan',
        'pengambilan',
        'pengiriman',
        'status',
    ];

    protected $casts = [
        'pengambilan' => 'datetime',
        'pengiriman' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeForCompany(Builder $q, $companyId): Builder
    {
        return $q->when($companyId, fn($qq) => $qq->where('company_id', $companyId));
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term)
            return $q;
        $like = '%' . trim($term) . '%';
        return $q->where(function ($qq) use ($like) {
            $qq->where('document_name', 'like', $like)
                ->orWhere('nama_pengirim', 'like', $like)
                ->orWhere('nama_penerima', 'like', $like)
                ->orWhere('type', 'like', $like)
                ->orWhere('penyimpanan', 'like', $like)
                ->orWhere('status', 'like', $like);
        });
    }
}
