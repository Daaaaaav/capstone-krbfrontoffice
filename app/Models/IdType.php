<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdType extends Model
{
    protected $fillable = [
        'id_type_name',
        'company_id',
    ];

    public $timestamps = true;

    /**
     * Get the company that owns the ID type.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Get all guestbook entries using this ID type.
     */
    public function guestbooks(): HasMany
    {
        return $this->hasMany(Guestbook::class, 'id_type_id');
    }

    /**
     * Check if this ID type is currently referenced by any guestbook entries.
     */
    public function isReferenced(): bool
    {
        return $this->guestbooks()->exists();
    }

    /**
     * Scope a query to only include ID types for a specific company.
     */
    public function scopeForCompany($query, ?int $companyId)
    {
        return $companyId ? $query->where('company_id', $companyId) : $query;
    }
}
