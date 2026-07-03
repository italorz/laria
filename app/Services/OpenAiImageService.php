<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Geração de imagem via OpenAI (endpoint /v1/images/edits, modelo gpt-image-1
 * com quality "low" — o "gpt-image-low"). Recebe as mesmas 3 imagens do fluxo
 * Gemini (original, anotada e produto) e o mesmo prompt de edição.
 */
class OpenAiImageService
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
        $apiKey = config('services.openai.key');
        if (! $apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY não configurada');
        }

        $extByMime = ['image/png' => 'png', 'image/webp' => 'webp'];
        $name = fn (array $img, string $base) => $base.'.'.($extByMime[$img[1]] ?? 'jpg');

        $response = Http::timeout(180)
            ->withToken($apiKey)
            ->attach('image[]', $original[0], $name($original, 'original'), ['Content-Type' => $original[1]])
            ->attach('image[]', $annotated[0], $name($annotated, 'annotated'), ['Content-Type' => $annotated[1]])
            ->attach('image[]', $product[0], $name($product, 'product'), ['Content-Type' => $product[1]])
            ->post('https://api.openai.com/v1/images/edits', [
                'model' => config('services.openai.image_model', 'gpt-image-1'),
                'prompt' => $this->buildPrompt($productTitle, $extraInstruction),
                'quality' => config('services.openai.image_quality', 'low'),
                'n' => 1,
            ]);

        if ($response->failed()) {
            $raw = $response->json('error.message') ?? $response->body();
            $status = $response->status();
            if ($status === 429 || preg_match('/rate limit|quota|billing/i', $raw)) {
                throw new \RuntimeException('Cota da OpenAI esgotada ou limite de requisições atingido. Verifique o billing da conta.', 429);
            }
            if ($status >= 500 || preg_match('/overloaded|server_error/i', $raw)) {
                throw new \RuntimeException('A OpenAI está indisponível no momento. Tente novamente em alguns segundos.', 503);
            }
            if ($status === 401 || preg_match('/invalid_api_key|incorrect api key/i', $raw)) {
                throw new \RuntimeException('Credencial da OpenAI inválida. Verifique a OPENAI_API_KEY.', 502);
            }
            throw new \RuntimeException($raw ?: 'Falha ao gerar a imagem', 502);
        }

        $b64 = $response->json('data.0.b64_json');
        if (! $b64) {
            throw new \RuntimeException('A OpenAI não retornou imagem.', 502);
        }

        $format = $response->json('output_format', 'png');

        return [base64_decode($b64), "image/{$format}"];
    }

    private function buildPrompt(?string $productTitle, ?string $extraInstruction): string
    {
        $target = $productTitle ? "o produto \"{$productTitle}\"" : 'o produto da imagem 3';

        $lines = [
            'Você recebeu 3 imagens:',
            '(1) a foto ORIGINAL da cena;',
            '(2) a MESMA foto com a REGIÃO A ALTERAR destacada/contornada em rosa;',
            '(3) o PRODUTO de referência a ser inserido.',
            '',
            "Tarefa: gere a imagem 1 novamente substituindo APENAS o elemento que está dentro da região destacada na imagem 2 por {$target}.",
            'PRESERVE EXATAMENTE todo o resto da imagem 1: pessoas, rostos, fundo, objetos vizinhos,',
            'iluminação, sombras, reflexos, perspectiva, cores, textura e enquadramento.',
            'Combine a perspectiva, a escala, a posição, a iluminação e as sombras do novo produto com a',
            'cena original para um resultado fotorrealista e coerente.',
            'Não altere absolutamente nada fora da região destacada. Não recorte, não adicione bordas,',
            'não mude o estilo. Mantenha a mesma proporção da imagem original.',
            $extraInstruction ? "Instrução adicional do usuário: {$extraInstruction}" : '',
        ];

        return implode("\n", array_filter($lines, fn ($l) => $l !== ''));
    }
}
