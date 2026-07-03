<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Integração com o Gemini 2.5 Flash Image ("Nano Banana") via REST.
 * Recebe a foto original, a foto anotada (região marcada) e a imagem do
 * produto, e gera uma nova imagem trocando APENAS o elemento marcado.
 */
class GeminiImageService
{
    /**
     * @param  array{0:string,1:string}  $original  [binário, mime]
     * @param  array{0:string,1:string}  $annotated
     * @param  array{0:string,1:string}  $product
     * @return array{0:string,1:string} [binário da imagem gerada, mime]
     *
     * @throws \RuntimeException com mensagem amigável em falhas conhecidas
     */
    public function generate(array $original, array $annotated, array $product, ?string $productTitle = null, ?string $extraInstruction = null): array
    {
        $apiKey = config('services.gemini.key');
        if (! $apiKey) {
            throw new \RuntimeException('GEMINI_API_KEY não configurada');
        }
        $model = config('services.gemini.image_model', 'gemini-2.5-flash-image');

        $response = Http::timeout(120)
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [[
                    'parts' => [
                        ['text' => $this->buildPrompt($productTitle, $extraInstruction)],
                        ['inline_data' => ['mime_type' => $original[1], 'data' => base64_encode($original[0])]],
                        ['inline_data' => ['mime_type' => $annotated[1], 'data' => base64_encode($annotated[0])]],
                        ['inline_data' => ['mime_type' => $product[1], 'data' => base64_encode($product[0])]],
                    ],
                ]],
                'generationConfig' => ['responseModalities' => ['IMAGE', 'TEXT']],
            ]);

        if ($response->failed()) {
            $raw = $response->json('error.message') ?? $response->body();
            $status = $response->status();
            // Mensagens amigáveis para os casos mais comuns do Gemini.
            if ($status === 429 || preg_match('/RESOURCE_EXHAUSTED|quota|free_tier/i', $raw)) {
                throw new \RuntimeException('Cota do Gemini esgotada. A geração de imagem exige um plano com faturamento ativo (o free tier tem limite 0 para esse modelo).', 429);
            }
            if ($status === 503 || preg_match('/UNAVAILABLE|overloaded|high demand/i', $raw)) {
                throw new \RuntimeException('O modelo está sobrecarregado no momento. Tente novamente em alguns segundos.', 503);
            }
            if (preg_match('/API_KEY_INVALID|API key not valid|UNAUTHENTICATED|invalid authentication/i', $raw)) {
                throw new \RuntimeException('Credencial do Gemini inválida. Verifique a GEMINI_API_KEY.', 502);
            }
            throw new \RuntimeException($raw ?: 'Falha ao gerar a imagem', 502);
        }

        $parts = $response->json('candidates.0.content.parts', []);
        foreach ($parts as $part) {
            if (! empty($part['inlineData']['data'])) {
                return [
                    base64_decode($part['inlineData']['data']),
                    $part['inlineData']['mimeType'] ?? 'image/png',
                ];
            }
        }

        $textBack = collect($parts)->pluck('text')->filter()->implode(' ');
        throw new \RuntimeException('O modelo não retornou imagem. Resposta: '.($textBack ?: 'vazia'), 502);
    }

    private function buildPrompt(?string $productTitle, ?string $extraInstruction): string
    {
        $target = $productTitle ? "o produto \"{$productTitle}\"" : 'o produto da imagem 3';

        $lines = [
            'Você recebeu 3 imagens:',
            '(1) a foto ORIGINAL da cena;',
            '(2) a MESMA foto com a REGIÃO A ALTERAR destacada/contornada;',
            '(3) o PRODUTO de referência a ser inserido.',
            '',
            "Tarefa: substitua APENAS o elemento que está dentro da região destacada na imagem 2 por {$target}.",
            'PRESERVE EXATAMENTE todo o resto da imagem 1: pessoas, rostos, fundo, objetos vizinhos,',
            'iluminação, sombras, reflexos, perspectiva, cores, textura e enquadramento.',
            'Combine a perspectiva, a escala, a posição, a iluminação e as sombras do novo produto com a',
            'cena original para um resultado fotorrealista e coerente.',
            'Não altere absolutamente nada fora da região destacada. Não recorte, não adicione bordas,',
            'não mude o estilo. Mantenha a mesma resolução e a mesma proporção da imagem original.',
            $extraInstruction ? "Instrução adicional do usuário: {$extraInstruction}" : '',
            '',
            'Retorne somente a imagem final editada.',
        ];

        return implode("\n", array_filter($lines, fn ($l) => $l !== ''));
    }
}
