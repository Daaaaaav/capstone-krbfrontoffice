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

    public string $scanUrl;

    /**
     * Each item: [visitor_number => int, png => string (binary), token => string]
     * @var array
     */
    public array $qrItems = [];

    /** PDF binary string for attachment */
    private string $pdfContent;

    public function __construct(public Guestbook $entry)
    {
        $this->scanUrl = route('guestbook.scan', ['token' => $entry->qr_token]);

        // Load qrCodes if not already loaded
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

        // Pre-generate the PDF
        $this->pdfContent = $this->buildPdf();
    }

    public function build(): static
    {
        $mail = $this->subject('Konfirmasi Kunjungan – ' . $this->entry->visitor_count . ' QR Code Tamu')
                     ->view('mail.guestbook-qr');

        // Attach the PDF
        $mail->attachData(
            $this->pdfContent,
            'QR-Code-Kunjungan-' . $this->entry->name . '.pdf',
            ['mime' => 'application/pdf']
        );

        return $mail;
    }

    /**
     * Generate a PNG QR code image using GD (no Imagick required).
     * Uses BaconQrCode's encoder to get the matrix, then draws it with GD.
     */
    public static function generateQrPng(string $data, int $size = 300, int $margin = 2): string
    {
        // Encode the data using BaconQrCode encoder
        $encoder = Encoder::encode(
            $data,
            ErrorCorrectionLevel::M(),
            'UTF-8'
        );

        $matrix     = $encoder->getMatrix();
        $matrixSize = $matrix->getWidth();

        // Calculate pixel scale
        $totalModules = $matrixSize + ($margin * 2);
        $scale        = (int) floor($size / $totalModules);
        $imgSize      = $scale * $totalModules;

        // Create image
        $img   = imagecreatetruecolor($imgSize, $imgSize);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);

        // Fill background white
        imagefill($img, 0, 0, $white);

        // Draw QR modules
        for ($y = 0; $y < $matrixSize; $y++) {
            for ($x = 0; $x < $matrixSize; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $px = ($x + $margin) * $scale;
                    $py = ($y + $margin) * $scale;
                    imagefilledrectangle($img, $px, $py, $px + $scale - 1, $py + $scale - 1, $black);
                }
            }
        }

        // Output to string
        ob_start();
        imagepng($img);
        $pngData = ob_get_clean();
        imagedestroy($img);

        return $pngData;
    }

    /**
     * Build the PDF with all QR codes.
     */
    private function buildPdf(): string
    {
        // Prepare base64-encoded images for the PDF view
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
