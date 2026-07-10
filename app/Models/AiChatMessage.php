<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessage extends Model
{
    public $timestamps  = false;          // only has sent_at, not created_at/updated_at
    protected $table    = 'ai_chat_messages';
    protected $fillable = ['session_id', 'role', 'text', 'booking_prefill', 'sent_at'];
    protected $casts    = [
        'booking_prefill' => 'array',
        'sent_at'         => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'session_id');
    }
}
