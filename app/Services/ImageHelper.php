<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageHelper
{
    public static function storeAsWebp(
        UploadedFile $file,
        string $directory,
        string $prefix = 'img',
        string $disk   = 'public',
        int    $quality = 82
    ): string {
        $filename = $prefix . '_' . now()->format('Ymd_His') . '_' . uniqid() . '.webp';
        $relativePath = $directory . '/' . $filename;
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
            $ext      = strtolower($file->getClientOriginalExtension() ?: 'png');
            $fallback = $prefix . '_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
            return $file->storeAs($directory, $fallback, $disk);
        }
        // preserve alpha channel for image sources
        imagealphablending($source, true);
        imagesavealpha($source, true);
    
        ob_start();
        imagewebp($source, null, $quality);
        $webpData = ob_get_clean();
        imagedestroy($source);

        Storage::disk($disk)->put($relativePath, $webpData);

        return $relativePath;
    }
}
