<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestbookQrCode extends Model
{
    protected $table = 'guestbook_qr_codes';
    public $timestamps = false;

    protected $fillable = [
        'guestbook_id',
        'qr_token',
        'visitor_number',
        'is_scanned',
        'scanned_at',
        'created_at',
    ];

    protected $casts = [
        'visitor_number' => 'integer',
        'is_scanned'     => 'boolean',
        'scanned_at'     => 'datetime',
        'created_at'     => 'datetime',
    ];

    public function guestbook(): BelongsTo
    {
        return $this->belongsTo(Guestbook::class, 'guestbook_id', 'guestbook_id');
    }

    /**
     * Generate a batch of unique QR tokens.
     *
     * @param  int  $count  Number of tokens to generate
     * @return string[]  Array of unique hex tokens
     */
    public static function generateTokenBatch(int $count): array
    {
        $tokens = [];
        while (count($tokens) < $count) {
            $token = bin2hex(random_bytes(32)); // 64 hex chars
            // Ensure uniqueness within the batch and the DB
            if (!in_array($token, $tokens, true) && !static::where('qr_token', $token)->exists()) {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }
}
