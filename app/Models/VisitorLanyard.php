<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class VisitorLanyard extends Model
{
    protected $fillable = [
        'company_id',
        'lanyard_name',
        'status',
    ];

    public $timestamps = true;

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Get the company that owns the lanyard.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Get all guestbook entries using this lanyard.
     */
    public function guestbooks(): HasMany
    {
        return $this->hasMany(Guestbook::class, 'visitor_lanyard_id');
    }

    /**
     * Check if this lanyard is currently assigned to an active visitor.
     * A lanyard is considered "in use" if it's assigned to a guestbook entry
     * that doesn't have a checkout time (jam_out is null).
     */
    public function isCurrentlyAssigned(): bool
    {
        return $this->guestbooks()
            ->whereNull('jam_out')
            ->exists();
    }

    /**
     * Check if this lanyard has ever been used (historical reference).
     */
    public function isReferenced(): bool
    {
        return $this->guestbooks()->exists();
    }

    /**
     * Scope a query to only include lanyards for a specific company.
     */
    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        return $companyId ? $query->where('company_id', $companyId) : $query;
    }

    /**
     * Scope a query to only include available lanyards (status = 1).
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * Scope a query to only include unavailable lanyards (status = 0).
     */
    public function scopeUnavailable(Builder $query): Builder
    {
        return $query->where('status', 0);
    }
}
