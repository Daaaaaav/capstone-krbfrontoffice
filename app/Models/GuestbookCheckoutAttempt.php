<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestbookCheckoutAttempt extends Model
{
    protected $table = 'guestbook_checkout_attempts';
    public $timestamps = false;

    protected $fillable = [
        'guestbook_id',
        'success',
        'message',
        'visitor_number',
        'error_type',
        'attempted_at',
    ];

    protected $casts = [
        'success'        => 'boolean',
        'visitor_number' => 'integer',
        'attempted_at'   => 'datetime',
    ];

    public function guestbook(): BelongsTo
    {
        return $this->belongsTo(Guestbook::class, 'guestbook_id', 'guestbook_id');
    }
}
