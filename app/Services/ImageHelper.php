<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageHelper
{
    /**
     * Convert an uploaded image to WebP and store it on the given disk.
     *
     * Returns the stored path (relative to the disk root), e.g.
     * "images/deliveries/delivery_20260710_123456_abc123.webp"
     *
     * @param  UploadedFile  $file       The Livewire / Laravel uploaded file.
     * @param  string        $directory  Storage directory, e.g. "images/deliveries".
     * @param  string        $prefix     Filename prefix, e.g. "delivery".
     * @param  string        $disk       Laravel storage disk (default: "public").
     * @param  int           $quality    WebP quality 0–100 (default: 82).
     * @return string  The stored relative path.
     */
    public static function storeAsWebp(
        UploadedFile $file,
        string $directory,
        string $prefix = 'img',
        string $disk   = 'public',
        int    $quality = 82
    ): string {
        $filename = $prefix . '_' . now()->format('Ymd_His') . '_' . uniqid() . '.webp';
        $relativePath = $directory . '/' . $filename;

        // Read the source image into a GD resource
        $tmpPath = $file->getRealPath();
        $mime    = $file->getMimeType() ?? '';

        $source = match (true) {
            str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') => @imagecreatefromjpeg($tmpPath),
            str_contains($mime, 'png')                                => @imagecreatefrompng($tmpPath),
            str_contains($mime, 'gif')                                => @imagecreatefromgif($tmpPath),
            str_contains($mime, 'webp')                               => @imagecreatefromwebp($tmpPath),
            str_contains($mime, 'bmp')                                => @imagecreatefrombmp($tmpPath),
            default                                                   => @imagecreatefromstring(file_get_contents($tmpPath)),
        };

        if ($source === false) {
            // GD could not parse the image — fall back to storing the original file as-is
            $ext      = strtolower($file->getClientOriginalExtension() ?: 'png');
            $fallback = $prefix . '_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
            return $file->storeAs($directory, $fallback, $disk);
        }

        // Preserve alpha channel for PNG / WebP sources
        imagealphablending($source, true);
        imagesavealpha($source, true);

        // Render to a memory buffer
        ob_start();
        imagewebp($source, null, $quality);
        $webpData = ob_get_clean();
        imagedestroy($source);

        // Write to storage
        Storage::disk($disk)->put($relativePath, $webpData);

        return $relativePath;
    }
}
