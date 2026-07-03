<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Armazenamento de imagens no disco público (storage/app/public/uploads,
 * servido em /storage/uploads) e compressão para envio ao Gemini.
 */
class ImageStorage
{
    private const EXT_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /** Salva um binário e devolve a URL pública (/storage/uploads/...). */
    public function saveBuffer(string $binary, string $mime = 'image/jpeg'): string
    {
        $ext = self::EXT_BY_MIME[strtolower(trim($mime))] ?? 'jpg';
        $path = 'uploads/'.Str::uuid().'.'.$ext;
        Storage::disk('public')->put($path, $binary);

        return Storage::url($path);
    }

    /** Baixa uma imagem externa e salva localmente (evita hotlink/CORS). */
    public function saveFromUrl(string $url): string
    {
        $response = Http::timeout(30)->get($url);
        if (! $response->successful()) {
            throw new \RuntimeException("Falha ao baixar imagem ({$response->status()})");
        }
        $mime = trim(explode(';', $response->header('Content-Type') ?: 'image/jpeg')[0]);

        return $this->saveBuffer($response->body(), $mime);
    }

    /** Resolve uma URL pública local (/storage/...) para binário; senão baixa da rede. */
    public function fetchBinary(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if (str_starts_with($path, '/storage/')) {
            $relative = substr($path, strlen('/storage/'));
            if (Storage::disk('public')->exists($relative)) {
                $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
                $mime = array_search($ext === 'jpeg' ? 'jpg' : $ext, self::EXT_BY_MIME, true) ?: 'image/jpeg';

                return [Storage::disk('public')->get($relative), $mime];
            }
        }

        $response = Http::timeout(30)->get($url);
        if (! $response->successful()) {
            throw new \RuntimeException("Falha ao baixar imagem ({$response->status()})");
        }

        return [$response->body(), trim(explode(';', $response->header('Content-Type') ?: 'image/jpeg')[0])];
    }

    /**
     * Comprime/redimensiona para envio ao Gemini (reduz custo e payload).
     * Corrige a orientação EXIF e converte para JPEG. Em caso de falha,
     * devolve o original sem quebrar o fluxo.
     *
     * @return array{0:string,1:string} [binário, mime]
     */
    public function compressForGemini(string $binary, int $maxDim = 1536, int $quality = 82): array
    {
        try {
            $img = @imagecreatefromstring($binary);
            if ($img === false) {
                return [$binary, 'image/jpeg'];
            }

            // Orientação EXIF (apenas JPEG tem esse metadado).
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($binary));
                $orientation = $exif['Orientation'] ?? 1;
                $img = match ($orientation) {
                    3 => imagerotate($img, 180, 0),
                    6 => imagerotate($img, -90, 0),
                    8 => imagerotate($img, 90, 0),
                    default => $img,
                };
            }

            $w = imagesx($img);
            $h = imagesy($img);
            $scale = min(1, $maxDim / max($w, $h));
            if ($scale < 1) {
                $img = imagescale($img, (int) round($w * $scale), (int) round($h * $scale), IMG_BICUBIC);
            }

            ob_start();
            imagejpeg($img, null, $quality);
            $out = ob_get_clean();

            return $out !== false && $out !== '' ? [$out, 'image/jpeg'] : [$binary, 'image/jpeg'];
        } catch (\Throwable) {
            return [$binary, 'image/jpeg'];
        }
    }
}
