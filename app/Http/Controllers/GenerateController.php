<?php

namespace App\Http\Controllers;

use App\Services\AiProvider;
use App\Services\GeminiImageService;
use App\Services\ImageStorage;
use App\Services\OpenAiImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GenerateController extends Controller
{
    /**
     * POST /generate (multipart)
     *   files:  original (foto), annotated (foto com região marcada)
     *   fields: productImageUrl, productTitle?, extraInstruction?
     * Retorna JSON {imageUrl, originalImageUrl} com as URLs públicas salvas.
     */
    public function store(Request $request, ImageStorage $storage)
    {
        $request->validate([
            'original' => ['required', 'file', 'image', 'max:15360'],
            'annotated' => ['required', 'file', 'image', 'max:15360'],
            'productImageUrl' => ['required', 'string', 'max:2048'],
            'productTitle' => ['nullable', 'string', 'max:255'],
            'extraInstruction' => ['nullable', 'string', 'max:1000'],
        ]);

        set_time_limit(180); // geração no Gemini pode demorar

        try {
            $productRaw = $storage->fetchBinary($request->input('productImageUrl'));
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Não foi possível obter a imagem do produto'], 400);
        }

        // Comprime as 3 imagens antes de enviar ao Gemini (reduz custo e payload).
        $original = $storage->compressForGemini($request->file('original')->get());
        $annotated = $storage->compressForGemini($request->file('annotated')->get());
        $product = $storage->compressForGemini($productRaw[0]);

        // Provider global escolhido pelo admin (Gemini por padrão).
        $generator = AiProvider::current() === AiProvider::GPT_IMAGE_LOW
            ? app(OpenAiImageService::class)
            : app(GeminiImageService::class);

        try {
            [$binary, $mime] = $generator->generate(
                $original,
                $annotated,
                $product,
                $request->input('productTitle'),
                $request->input('extraInstruction'),
            );
        } catch (\RuntimeException $e) {
            Log::error('Falha na geração com Gemini', ['error' => $e->getMessage()]);
            $status = in_array($e->getCode(), [429, 502, 503], true) ? $e->getCode() : 502;

            return response()->json(['error' => $e->getMessage()], $status);
        }

        // Salva também a original para o toggle antes/depois e o post.
        $originalUrl = $storage->saveBuffer($original[0], $original[1]);
        $generatedUrl = $storage->saveBuffer($binary, $mime);

        return response()->json([
            'imageUrl' => $generatedUrl,
            'originalImageUrl' => $originalUrl,
        ]);
    }
}
