<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestbookScan extends Model
{
    protected $table = 'guestbook_scans';
    protected $primaryKey = 'scan_id';
    public $incrementing = true;
    protected $keyType = 'int';

    // scanned_at is managed via useCurrent() in DB, no Laravel timestamps needed
    public $timestamps = false;

    protected $fillable = [
        'guestbook_id',
        'visitor_name',
        'visitor_id_number',
        'scanned_by_ip',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function guestbook(): BelongsTo
    {
        return $this->belongsTo(Guestbook::class, 'guestbook_id', 'guestbook_id');
    }
}
