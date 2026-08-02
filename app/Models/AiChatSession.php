<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiChatSession extends Model
{
    protected $table      = 'ai_chat_sessions';
    protected $fillable   = ['user_id', 'role', 'title', 'started_at', 'ended_at'];
    protected $casts      = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'session_id')
                    ->orderBy('sent_at');
    }

    public function close(): void
    {
        $this->update(['ended_at' => now()]);
    }
    
    public static function titleFromMessage(string $text): string
    {
        $clean = preg_replace('/\s+/', ' ', trim($text));
        return mb_strlen($clean) > 60
            ? mb_substr($clean, 0, 57) . '…'
            : $clean;
    }
}
