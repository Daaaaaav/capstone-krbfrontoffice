<?php

namespace App\Mail;

use App\Models\Guestbook;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Common\ErrorCorrectionLevel;
use Barryvdh\DomPDF\Facade\Pdf;

class GuestbookQrMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $qrItems = [];

    private string $pdfContent;

    public function __construct(public Guestbook $entry)
    {
        if (!$entry->relationLoaded('qrCodes')) {
            $entry->load('qrCodes');
        }

        foreach ($entry->qrCodes->sortBy('visitor_number') as $qrCode) {
            $qrData  = 'GUESTBOOK-CHECKOUT:' . $qrCode->qr_token;
            $pngData = self::generateQrPng($qrData, 300);

            $this->qrItems[] = [
                'visitor_number' => $qrCode->visitor_number,
                'png'            => $pngData,
                'token'          => $qrCode->qr_token,
            ];
        }
        $this->pdfContent = $this->buildPdf();
    }

    public function build(): static
    {
        $mail = $this->subject('Konfirmasi Kunjungan – ' . $this->entry->visitor_count . ' QR Code Tamu')
                     ->view('mail.guestbook-qr');
        $mail->attachData(
            $this->pdfContent,
            'QR-Code-Kunjungan-' . $this->entry->name . '.pdf',
            ['mime' => 'application/pdf']
        );

        return $mail;
    }

    public static function generateQrPng(string $data, int $size = 300, int $margin = 2): string
    {
        $encoder = Encoder::encode(
            $data,
            ErrorCorrectionLevel::M(),
            'UTF-8'
        );

        $matrix     = $encoder->getMatrix();
        $matrixSize = $matrix->getWidth();
        $totalModules = $matrixSize + ($margin * 2);
        $scale        = (int) floor($size / $totalModules);
        $imgSize      = $scale * $totalModules;
        $img   = imagecreatetruecolor($imgSize, $imgSize);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);

        imagefill($img, 0, 0, $white);

        for ($y = 0; $y < $matrixSize; $y++) {
            for ($x = 0; $x < $matrixSize; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $px = ($x + $margin) * $scale;
                    $py = ($y + $margin) * $scale;
                    imagefilledrectangle($img, $px, $py, $px + $scale - 1, $py + $scale - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($img);
        $pngData = ob_get_clean();
        imagedestroy($img);

        return $pngData;
    }

    private function buildPdf(): string
    {
        $pdfItems = [];
        foreach ($this->qrItems as $item) {
            $pdfItems[] = [
                'visitor_number' => $item['visitor_number'],
                'png_base64'     => base64_encode($item['png']),
                'token'          => $item['token'],
            ];
        }

        $pdf = Pdf::loadView('mail.guestbook-qr-pdf', [
            'entry'    => $this->entry,
            'pdfItems' => $pdfItems,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }
}
