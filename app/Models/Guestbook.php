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
        'scheduled_by_manager',
        'receptionist_notified_at',
        'id_type_id',
        'visitor_lanyard_id',
    ];

    protected $casts = [
        'date'                     => 'date:Y-m-d',
        'visitor_count'            => 'integer',
        'scheduled_by_manager'     => 'boolean',
        'receptionist_notified_at' => 'datetime',
    ];

    public function scans(): HasMany
    {
        return $this->hasMany(GuestbookScan::class, 'guestbook_id', 'guestbook_id');
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(GuestbookQrCode::class, 'guestbook_id', 'guestbook_id');
    }

    public function allQrScanned(): bool
    {
        $total = $this->qrCodes()->count();
        if ($total === 0) {
            return false;
        }
        return $this->qrCodes()->where('is_scanned', true)->count() >= $total;
    }

    public function scannedQrCount(): int
    {
        return $this->qrCodes()->where('is_scanned', true)->count();
    }

    public function scopeForCompany(Builder $q, $companyId): Builder
    {
        return $q->where('company_id', $companyId);
    }

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

    public static function generateQrToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32)); // 64 hex chars
        } while (static::where('qr_token', $token)->exists());

        return $token;
    }

    public function qrStatusLabel(): string
    {
        return match ($this->qr_status) {
            'pending'   => 'Menunggu Scan',
            'ongoing'   => 'Sedang Berkunjung',
            'completed' => 'Selesai',
            default     => ucfirst((string) $this->qr_status),
        };
    }

    public function idType()
    {
        return $this->belongsTo(IdType::class, 'id_type_id');
    }

    public function visitorLanyard()
    {
        return $this->belongsTo(VisitorLanyard::class, 'visitor_lanyard_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
