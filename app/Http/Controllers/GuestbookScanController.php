<?php

namespace App\Http\Controllers;

use App\Models\Guestbook;
use App\Models\GuestbookQrCode;
use App\Models\GuestbookScan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GuestbookScanController extends Controller
{

    public function show(string $token)
    {
        $entry = Guestbook::where('qr_token', $token)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $scans = $entry->scans()
            ->orderByDesc('scanned_at')
            ->limit(20)
            ->get();

        return view('guestbook.scan', compact('entry', 'scans'));
    }

    public function qrImage(string $token)
    {
        $entry = Guestbook::where('qr_token', $token)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $scanUrl = route('guestbook.scan', ['token' => $token]);

        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(300),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        $svg = (new \BaconQrCode\Writer($renderer))->writeString($scanUrl);

        return response($svg, 200, [
            'Content-Type'  => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function submit(Request $request, string $token)
    {
        $entry = Guestbook::where('qr_token', $token)
            ->whereNull('deleted_at')
            ->firstOrFail();

        // no more scans allowed after toggled to already out
        if ($entry->jam_out) {
            return redirect()
                ->route('guestbook.scan', ['token' => $token])
                ->with('error', 'Kunjungan ini sudah selesai.');
        }

        $data = $request->validate([
            'visitor_name'      => ['required', 'string', 'max:255'],
            'visitor_id_number' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($entry, $data, $request) {
            // Record the individual scan
            GuestbookScan::create([
                'guestbook_id'      => $entry->guestbook_id,
                'visitor_name'      => $data['visitor_name'],
                'visitor_id_number' => $data['visitor_id_number'] ?? null,
                'scanned_by_ip'     => $request->ip(),
                'scanned_at'        => now(),
            ]);

            $entry->increment('visitor_count');
            if ($entry->qr_status === 'pending') {
                $entry->update(['qr_status' => 'ongoing']);
            }
        });

        return redirect()
            ->route('guestbook.scan', ['token' => $token])
            ->with('scan_success', "Selamat datang, {$data['visitor_name']}!");
    }

    public function checkoutScan(Request $request)
    {
        $request->validate([
            'qr_content' => ['required', 'string', 'max:500'],
        ]);

        $rawContent = $request->input('qr_content');

        $token = $rawContent;
        if (str_starts_with($rawContent, 'GUESTBOOK-CHECKOUT:')) {
            $token = substr($rawContent, strlen('GUESTBOOK-CHECKOUT:'));
        }

        $qrCode = GuestbookQrCode::where('qr_token', $token)->first();

        if (!$qrCode) {
            return response()->json([
                'success' => false,
                'error'   => 'invalid',
                'message' => 'QR code tidak dikenali.',
            ], 200); 
        }

        if ($qrCode->is_scanned) {
            return response()->json([
                'success' => false,
                'error'   => 'already_scanned',
                'message' => 'QR code ini sudah di-scan sebelumnya (Pengunjung ' . $qrCode->visitor_number . ').',
                'visitor_number' => $qrCode->visitor_number,
            ], 200);
        }
        $entry = $qrCode->guestbook;

        if (!$entry || $entry->deleted_at) {
            return response()->json([
                'success' => false,
                'error'   => 'invalid',
                'message' => 'Data kunjungan tidak ditemukan.',
            ], 200);
        }

        if ($entry->qr_status === 'completed') {
            return response()->json([
                'success' => false,
                'error'   => 'completed',
                'message' => 'Kunjungan ini sudah selesai.',
            ], 200);
        }

        $expectedGuestbookId = $request->input('guestbook_id');
        if ($expectedGuestbookId && (int) $qrCode->guestbook_id !== (int) $expectedGuestbookId) {
            return response()->json([
                'success' => false,
                'error'   => 'wrong_entry',
                'message' => 'QR code ini milik kunjungan yang berbeda.',
            ], 200);
        }

        $qrCode->update([
            'is_scanned' => true,
            'scanned_at' => now(),
        ]);

        if ($entry->qr_status === 'pending') {
            $entry->update(['qr_status' => 'ongoing']);
        }

        $totalQr   = $entry->qrCodes()->count();
        $scannedQr = $entry->qrCodes()->where('is_scanned', true)->count();
        $allDone   = $scannedQr >= $totalQr;

        if ($allDone) {
            $entry->update([
                'jam_out'    => Carbon::now()->format('H:i'),
                'qr_status'  => 'completed',
            ]);

            // Reactivate the lanyard so it can be used again
            if ($entry->visitor_lanyard_id) {
                \App\Models\VisitorLanyard::where('id', $entry->visitor_lanyard_id)->update(['status' => 1]);
            }
        }

        return response()->json([
            'success'        => true,
            'message'        => 'Pengunjung ' . $qrCode->visitor_number . ' berhasil checkout.',
            'visitor_number' => $qrCode->visitor_number,
            'scanned_count'  => $scannedQr,
            'total_count'    => $totalQr,
            'all_done'       => $allDone,
        ]);
    }
}
