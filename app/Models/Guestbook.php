<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guestbook extends Model
{
    use SoftDeletes;

    protected $table = 'guestbooks';
    protected $primaryKey = 'guestbook_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'company_id',
        'department_id',
        'user_id',
        'date',
        'jam_in',
        'jam_out',
        'name',
        'email',
        'phone_number',
        'instansi',
        'keperluan',
        'petugas_penjaga',
        'qr_token',
        'qr_status',
        'visitor_count',
        'storage_place',
    ];

    // If column `date` is DATE, this is safe. Times are left as string (TIME cast is not native Carbon).
    protected $casts = [
        'date'          => 'date:Y-m-d',
        'visitor_count' => 'integer',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /** Individual visitor scan records for this guestbook entry */
    public function scans(): HasMany
    {
        return $this->hasMany(GuestbookScan::class, 'guestbook_id', 'guestbook_id');
    }

    /** Individual QR codes (one per visitor in the group) for checkout scanning */
    public function qrCodes(): HasMany
    {
        return $this->hasMany(GuestbookQrCode::class, 'guestbook_id', 'guestbook_id');
    }

    /** Check if all individual visitor QR codes have been scanned out */
    public function allQrScanned(): bool
    {
        $total = $this->qrCodes()->count();
        if ($total === 0) {
            return false;
        }
        return $this->qrCodes()->where('is_scanned', true)->count() >= $total;
    }

    /** Count how many QR codes have been scanned */
    public function scannedQrCount(): int
    {
        return $this->qrCodes()->where('is_scanned', true)->count();
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    /** Scope: by company */
    public function scopeForCompany(Builder $q, $companyId): Builder
    {
        return $q->where('company_id', $companyId);
    }

    /** Scope: fulltext-ish search */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) {
            return $q;
        }
        $like = '%' . $term . '%';

        return $q->where(function ($w) use ($like) {
            $w->where('name', 'like', $like)
                ->orWhere('phone_number', 'like', $like)
                ->orWhere('instansi', 'like', $like)
                ->orWhere('keperluan', 'like', $like)
                ->orWhere('petugas_penjaga', 'like', $like);
        });
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Generate a cryptographically random unique QR token */
    public static function generateQrToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32)); // 64 hex chars
        } while (static::where('qr_token', $token)->exists());

        return $token;
    }

    /** Human-readable QR status label */
    public function qrStatusLabel(): string
    {
        return match ($this->qr_status) {
            'pending'   => 'Menunggu Scan',
            'ongoing'   => 'Sedang Berkunjung',
            'completed' => 'Selesai',
            default     => ucfirst((string) $this->qr_status),
        };
    }
}
