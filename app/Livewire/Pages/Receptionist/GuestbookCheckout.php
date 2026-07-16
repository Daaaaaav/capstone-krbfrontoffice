<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Guestbook as GuestbookModel;
use App\Models\GuestbookQrCode;
use Carbon\Carbon;

#[Layout('layouts.receptionist')]
#[Title('Guestbook Checkout Scanner')]
class GuestbookCheckout extends Component
{
    public int $guestbookId;

    // Passed to the view
    public ?string $guestName = null;
    public ?string $instansi = null;
    public ?string $keperluan = null;
    public ?string $jamIn = null;
    public ?string $date = null;
    public int $totalVisitors = 0;
    public int $scannedCount = 0;
    public string $qrStatus = 'pending';

    /** Pre-existing scan history loaded from DB, serialized into the Alpine component. */
    public array $initialScanLog = [];

    public function mount(int $guestbookId): void
    {
        $entry = GuestbookModel::where('guestbook_id', $guestbookId)
            ->where('company_id', Auth::user()?->company_id)
            ->firstOrFail();

        $this->guestbookId   = $entry->guestbook_id;
        $this->guestName     = $entry->name;
        $this->instansi      = $entry->instansi;
        $this->keperluan     = $entry->keperluan;
        $this->jamIn         = $entry->jam_in;
        $this->date          = $entry->date ? $entry->date->format('d M Y') : null;
        $this->totalVisitors = (int) $entry->visitor_count;
        $this->scannedCount  = $entry->scannedQrCount();
        $this->qrStatus      = $entry->qr_status ?? 'pending';

        // Build the initial scan log from DB records so any device resuming
        // the session sees the same history.
        $tz = config('app.timezone', 'Asia/Jakarta');
        $this->initialScanLog = GuestbookQrCode::where('guestbook_id', $entry->guestbook_id)
            ->orderByDesc('scanned_at')
            ->get()
            ->map(function (GuestbookQrCode $qr) use ($tz): ?array {
                $scannedAt = $qr->scanned_at
                    ? $qr->scanned_at->setTimezone($tz)->format('H:i:s')
                    : null;

                if ($qr->is_scanned) {
                    return [
                        'success'        => true,
                        'message'        => 'Pengunjung ' . $qr->visitor_number . ' berhasil checkout',
                        'visitorNumber'  => $qr->visitor_number,
                        'time'           => $scannedAt ?? '--:--:--',
                    ];
                }

                return null; // not yet scanned — exclude
            })
            ->filter()
            ->values()
            ->toArray();
    }

    public function processScan(string $qrContent): array
    {
        $prefix = 'GUESTBOOK-CHECKOUT:';
        if (!str_starts_with($qrContent, $prefix)) {
            // For backward compatibility with older QR formats (without prefix)
            // check if length is 64 hex characters
            if (strlen($qrContent) === 64 && ctype_xdigit($qrContent)) {
                $token = $qrContent;
            } else {
                return [
                    'success' => false,
                    'message' => 'Format QR code tidak valid untuk Guestbook.',
                ];
            }
        } else {
            $token = substr($qrContent, strlen($prefix));
        }

        $qrCode = GuestbookQrCode::where('guestbook_id', $this->guestbookId)
            ->where('qr_token', $token)
            ->first();

        if (!$qrCode) {
            return [
                'success' => false,
                'message' => 'QR Code tidak ditemukan atau milik rombongan lain.',
            ];
        }

        if ($qrCode->is_scanned) {
            return [
                'success' => false,
                'message' => 'QR Code ini sudah di-scan sebelumnya.',
                'visitor_number' => $qrCode->visitor_number,
            ];
        }

        $qrCode->update([
            'is_scanned' => true,
            'scanned_at' => Carbon::now(config('app.timezone', 'Asia/Jakarta')),
        ]);

        $this->scannedCount++;
        $allDone = ($this->scannedCount >= $this->totalVisitors);

        if ($allDone) {
            $this->qrStatus = 'completed';
            GuestbookModel::where('guestbook_id', $this->guestbookId)->update([
                'qr_status' => 'completed',
                'jam_out'   => Carbon::now(config('app.timezone', 'Asia/Jakarta'))->format('H:i'),
            ]);
        }

        return [
            'success'        => true,
            'message'        => 'Pengunjung ' . $qrCode->visitor_number . ' berhasil checkout',
            'visitor_number' => $qrCode->visitor_number,
            'scanned_count'  => $this->scannedCount,
            'all_done'       => $allDone,
        ];
    }

    public function render()
    {
        return view('livewire.pages.receptionist.guestbook-checkout');
    }
}
