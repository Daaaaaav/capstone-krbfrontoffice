<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KrbKnowledgeDocument extends Model
{
    use HasFactory;

    protected $table = 'krb_knowledge_documents';
    protected $primaryKey = 'document_id';

    protected $fillable = [
        'source_id',
        'category',
        'title',
        'slug',
        'summary',
        'content',
        'keywords',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'keywords' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(KrbKnowledgeSource::class, 'source_id', 'source_id');
    }
}

