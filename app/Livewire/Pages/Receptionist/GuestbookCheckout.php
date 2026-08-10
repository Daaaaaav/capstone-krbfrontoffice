<?php

namespace App\Livewire\Pages\Receptionist;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Guestbook as GuestbookModel;
use App\Models\GuestbookQrCode;
use App\Models\GuestbookCheckoutAttempt;
use App\Models\VisitorLanyard;
use Carbon\Carbon;

#[Layout('layouts.receptionist')]
#[Title('Guestbook Checkout Scanner')]
class GuestbookCheckout extends Component
{
    public int $guestbookId;
    public ?string $guestName = null;
    public ?string $instansi = null;
    public ?string $keperluan = null;
    public ?string $jamIn = null;
    public ?string $date = null;
    public int $totalVisitors = 0;
    public int $scannedCount = 0;
    public string $qrStatus = 'pending';
    public array $initialScanLog = [];
    public string $latestAttemptAt = '';

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

        $tz = config('app.timezone', 'Asia/Jakarta');

        $hasAttempts = GuestbookCheckoutAttempt::where('guestbook_id', $entry->guestbook_id)->exists();
        if (!$hasAttempts) {
            $legacy = GuestbookQrCode::where('guestbook_id', $entry->guestbook_id)
                ->where('is_scanned', true)
                ->orderBy('scanned_at')
                ->get();

            foreach ($legacy as $qr) {
                GuestbookCheckoutAttempt::create([
                    'guestbook_id'   => $entry->guestbook_id,
                    'success'        => true,
                    'message'        => 'Pengunjung ' . $qr->visitor_number . ' berhasil checkout',
                    'visitor_number' => $qr->visitor_number,
                    'error_type'     => null,
                    'attempted_at'   => $qr->scanned_at ?? $entry->updated_at ?? now(),
                ]);
            }
        }

        $this->initialScanLog = GuestbookCheckoutAttempt::where('guestbook_id', $entry->guestbook_id)
            ->orderByDesc('attempted_at')
            ->get()
            ->map(fn (GuestbookCheckoutAttempt $a) => [
                'success'       => $a->success,
                'message'       => $a->message,
                'visitorNumber' => $a->visitor_number,
                'time'          => $a->attempted_at
                    ? $a->attempted_at->setTimezone($tz)->format('H:i:s')
                    : '--:--:--',
            ])
            ->values()
            ->toArray();

        $newest = GuestbookCheckoutAttempt::where('guestbook_id', $entry->guestbook_id)
            ->orderByDesc('attempted_at')
            ->value('attempted_at');
        $this->latestAttemptAt = $newest
            ? Carbon::parse($newest)->toISOString()
            : Carbon::now($tz)->subSeconds(5)->toISOString();
    }

    public function processScan(string $qrContent): array
    {
        $tz     = config('app.timezone', 'Asia/Jakarta');
        $now    = Carbon::now($tz);
        $prefix = 'GUESTBOOK-CHECKOUT:';

        if (!str_starts_with($qrContent, $prefix)) {
            if (strlen($qrContent) === 64 && ctype_xdigit($qrContent)) {
                $token = $qrContent;
            } else {
                $result = [
                    'success' => false,
                    'message' => 'Format QR code tidak valid untuk Guestbook.',
                ];
                $this->recordAttempt($result, 'invalid', null, $now);
                return $result;
            }
        } else {
            $token = substr($qrContent, strlen($prefix));
        }

        $qrCode = GuestbookQrCode::where('guestbook_id', $this->guestbookId)
            ->where('qr_token', $token)
            ->first();

        if (!$qrCode) {
            $result = [
                'success' => false,
                'message' => 'QR Code tidak ditemukan atau milik rombongan lain.',
            ];
            $this->recordAttempt($result, 'invalid', null, $now);
            return $result;
        }

        if ($qrCode->is_scanned) {
            $result = [
                'success'        => false,
                'message'        => 'QR Code ini sudah di-scan sebelumnya.',
                'visitor_number' => $qrCode->visitor_number,
            ];
            $this->recordAttempt($result, 'already_scanned', $qrCode->visitor_number, $now);
            return $result;
        }

        $qrCode->update([
            'is_scanned' => true,
            'scanned_at' => $now,
        ]);

        $this->scannedCount++;
        $allDone = ($this->scannedCount >= $this->totalVisitors);

        if ($allDone) {
            $this->qrStatus = 'completed';
            $entry = GuestbookModel::where('guestbook_id', $this->guestbookId)->first();
            
            // Store lanyard ID before updating
            $lanyardId = $entry ? $entry->visitor_lanyard_id : null;
            
            GuestbookModel::where('guestbook_id', $this->guestbookId)->update([
                'qr_status' => 'completed',
                'jam_out'   => $now->format('H:i'),
            ]);
            
            // Return the lanyard to available status
            if ($lanyardId) {
                $lanyard = \App\Models\VisitorLanyard::find($lanyardId);
                if ($lanyard) {
                    $lanyard->update(['status' => 1]);
                }
            }
        }

        $result = [
            'success'        => true,
            'message'        => 'Pengunjung ' . $qrCode->visitor_number . ' berhasil checkout',
            'visitor_number' => $qrCode->visitor_number,
            'scanned_count'  => $this->scannedCount,
            'all_done'       => $allDone,
        ];
        $this->recordAttempt($result, null, $qrCode->visitor_number, $now);
        return $result;
    }

    private function recordAttempt(array $result, ?string $errorType, ?int $visitorNumber, Carbon $at): void
    {
        GuestbookCheckoutAttempt::create([
            'guestbook_id'   => $this->guestbookId,
            'success'        => $result['success'],
            'message'        => $result['message'],
            'visitor_number' => $visitorNumber,
            'error_type'     => $errorType,
            'attempted_at'   => $at,
        ]);
    }

    public function fetchNewAttempts(string $afterTimestamp): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');

        return GuestbookCheckoutAttempt::where('guestbook_id', $this->guestbookId)
            ->where('attempted_at', '>', $afterTimestamp)
            ->orderBy('attempted_at')
            ->get()
            ->map(fn (GuestbookCheckoutAttempt $a) => [
                'success'       => $a->success,
                'message'       => $a->message,
                'visitorNumber' => $a->visitor_number,
                'time'          => $a->attempted_at
                    ? $a->attempted_at->setTimezone($tz)->format('H:i:s')
                    : '--:--:--',
                'attempted_at'  => $a->attempted_at->toISOString(),
            ])
            ->values()
            ->toArray();
    }

    public function getProgress(): array
    {
        $entry = GuestbookModel::where('guestbook_id', $this->guestbookId)->first();
        $scanned = $entry ? $entry->scannedQrCount() : $this->scannedCount;
        $allDone = $scanned >= $this->totalVisitors;

        return [
            'scanned_count' => $scanned,
            'all_done'      => $allDone,
        ];
    }

    public function render()
    {
        return view('livewire.pages.receptionist.guestbook-checkout');
    }
}
