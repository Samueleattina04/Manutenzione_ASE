<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Ottimizza le foto per ridurne il peso su disco.
 *
 * Le immagini che arrivano dal telefono sono spesso da 3-8 MB (12+ megapixel):
 * vengono ridimensionate (lato lungo max da configurazione) e ricompresse in
 * WebP (o JPEG se il server non supporta WebP), con rimozione dei metadati EXIF.
 * Il risultato è in genere 10-30 volte più piccolo, mantenendo una qualità
 * più che adeguata alla consultazione.
 *
 * Usa solo l'estensione GD (nessuna dipendenza aggiuntiva). Se l'immagine non è
 * gestibile (es. formato HEIC non supportato da GD), restituisce null.
 */
class ImageOptimizer
{
    /**
     * Ottimizza un file appena caricato (usato in fase di upload).
     *
     * @return array{contents:string, extension:string, mime:string, width:int, height:int}|null
     */
    public static function optimize(UploadedFile $file): ?array
    {
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        return self::process($raw, $file->getClientMimeType(), $path);
    }

    /**
     * Ottimizza un'immagine già presente su disco (usato dal comando che
     * ricomprime le foto caricate in precedenza).
     *
     * @return array{contents:string, extension:string, mime:string, width:int, height:int}|null
     */
    public static function optimizeFile(string $absolutePath, ?string $mime = null): ?array
    {
        if (! is_readable($absolutePath)) {
            return null;
        }

        $raw = @file_get_contents($absolutePath);
        if ($raw === false || $raw === '') {
            return null;
        }

        return self::process($raw, $mime, $absolutePath);
    }

    /**
     * Nucleo dell'ottimizzazione: ridimensiona e ricomprime i byte dell'immagine.
     *
     * @return array{contents:string, extension:string, mime:string, width:int, height:int}|null
     */
    private static function process(string $raw, ?string $mimeHint, ?string $exifPath): ?array
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $img = @imagecreatefromstring($raw);
        if (! $img) {
            // Formato non supportato da GD (es. HEIC/HEIF).
            return null;
        }

        try {
            $img = self::applyExifOrientation($img, $exifPath, $mimeHint);

            $w = imagesx($img);
            $h = imagesy($img);

            $max = (int) config('immagini.max_lato', 1600);
            if ($max > 0 && ($w > $max || $h > $max)) {
                $scale = $max / max($w, $h);
                $nw = max(1, (int) round($w * $scale));
                $nh = max(1, (int) round($h * $scale));

                $resized = imagecreatetruecolor($nw, $nh);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img);
                $img = $resized;
                $w = $nw;
                $h = $nh;
            }

            $quality = (int) config('immagini.qualita', 80);
            $quality = max(1, min(100, $quality));
            $useWebp = self::webpSupported() && config('immagini.formato', 'webp') !== 'jpeg';

            ob_start();
            if ($useWebp) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                imagewebp($img, null, $quality);
                $ext = 'webp';
                $mime = 'image/webp';
            } else {
                // JPEG non gestisce la trasparenza: appiattisce su sfondo bianco.
                $flat = imagecreatetruecolor($w, $h);
                $white = imagecolorallocate($flat, 255, 255, 255);
                imagefilledrectangle($flat, 0, 0, $w, $h, $white);
                imagecopy($flat, $img, 0, 0, 0, 0, $w, $h);
                imagedestroy($img);
                $img = $flat;
                imageinterlace($img, true); // JPEG progressivo: più leggero
                imagejpeg($img, null, $quality);
                $ext = 'jpg';
                $mime = 'image/jpeg';
            }
            $contents = ob_get_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            return null;
        } finally {
            if ($img instanceof \GdImage) {
                imagedestroy($img);
            }
        }

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        return [
            'contents' => $contents,
            'extension' => $ext,
            'mime' => $mime,
            'width' => $w,
            'height' => $h,
        ];
    }

    private static function webpSupported(): bool
    {
        if (! function_exists('imagewebp')) {
            return false;
        }
        $info = function_exists('gd_info') ? gd_info() : [];

        return ! empty($info['WebP Support']);
    }

    /**
     * Applica l'orientamento EXIF (solo per i JPEG) così la foto non risulta
     * ruotata o capovolta dopo la ricompressione.
     */
    private static function applyExifOrientation(\GdImage $img, ?string $exifPath, ?string $mimeHint): \GdImage
    {
        if (! $exifPath || ! function_exists('exif_read_data')) {
            return $img;
        }

        $mime = strtolower($mimeHint ?: '');
        if ($mime !== '' && ! str_contains($mime, 'jpeg') && ! str_contains($mime, 'jpg')) {
            return $img;
        }

        $exif = @exif_read_data($exifPath);
        if (! $exif || empty($exif['Orientation'])) {
            return $img;
        }

        switch ((int) $exif['Orientation']) {
            case 2:
                imageflip($img, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $img = imagerotate($img, 180, 0);
                break;
            case 4:
                imageflip($img, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $img = imagerotate($img, -90, 0);
                imageflip($img, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $img = imagerotate($img, -90, 0);
                break;
            case 7:
                $img = imagerotate($img, 90, 0);
                imageflip($img, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                $img = imagerotate($img, 90, 0);
                break;
        }

        return $img;
    }
}
