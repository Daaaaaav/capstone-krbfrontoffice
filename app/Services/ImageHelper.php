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

        // Convert palette/indexed images to true-color for WebP compatibility
        if (!imageistruecolor($source)) {
            $width  = imagesx($source);
            $height = imagesy($source);
            $trueColor = imagecreatetruecolor($width, $height);

            // Preserve transparency
            imagealphablending($trueColor, false);
            imagesavealpha($trueColor, true);
            $transparent = imagecolorallocatealpha($trueColor, 0, 0, 0, 127);
            imagefill($trueColor, 0, 0, $transparent);

            // Copy palette image to true-color image
            imagealphablending($trueColor, true);
            imagecopy($trueColor, $source, 0, 0, 0, 0, $width, $height);

            imagedestroy($source);
            $source = $trueColor;
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

    public static function storeBase64AsWebp(
        string $base64Data,
        string $directory,
        string $prefix = 'img',
        string $disk   = 'public',
        int    $quality = 82
    ): string {
        // Strip out the data url part if present
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        }
        $data = base64_decode($base64Data);
        if ($data === false) {
            throw new \Exception('Base64 decode failed');
        }

        $source = @imagecreatefromstring($data);
        if ($source === false) {
            // fallback, save as png
            $filename = $prefix . '_' . now()->format('Ymd_His') . '_' . uniqid() . '.png';
            $relativePath = $directory . '/' . $filename;
            Storage::disk($disk)->put($relativePath, $data);
            return $relativePath;
        }

        // Convert palette/indexed images to true-color for WebP compatibility
        if (!imageistruecolor($source)) {
            $width  = imagesx($source);
            $height = imagesy($source);
            $trueColor = imagecreatetruecolor($width, $height);

            // Preserve transparency
            imagealphablending($trueColor, false);
            imagesavealpha($trueColor, true);
            $transparent = imagecolorallocatealpha($trueColor, 0, 0, 0, 127);
            imagefill($trueColor, 0, 0, $transparent);

            // Copy palette image to true-color image
            imagealphablending($trueColor, true);
            imagecopy($trueColor, $source, 0, 0, 0, 0, $width, $height);

            imagedestroy($source);
            $source = $trueColor;
        }

        imagealphablending($source, true);
        imagesavealpha($source, true);
    
        ob_start();
        imagewebp($source, null, $quality);
        $webpData = ob_get_clean();
        imagedestroy($source);

        $filename = $prefix . '_' . now()->format('Ymd_His') . '_' . uniqid() . '.webp';
        $relativePath = $directory . '/' . $filename;
        Storage::disk($disk)->put($relativePath, $webpData);

        return $relativePath;
    }
}
