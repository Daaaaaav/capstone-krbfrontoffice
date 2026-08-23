<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KrbKnowledgeSource extends Model
{
    use HasFactory;

    protected $table = 'krb_knowledge_sources';
    protected $primaryKey = 'source_id';

    protected $fillable = [
        'name',
        'type',
        'trust_level',
        'approval_status',
        'source_reference',
        'publication_date',
        'verified_at',
        'is_active',
    ];

    protected $casts = [
        'publication_date' => 'date',
        'verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(KrbKnowledgeDocument::class, 'source_id', 'source_id');
    }
}

