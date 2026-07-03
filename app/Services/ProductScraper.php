<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Scraping de páginas de produto delegado ao script Node (Puppeteer + stealth)
 * em scraper/scrape.mjs — o mesmo mecanismo anti-bot do backend antigo.
 */
class ProductScraper
{
    /**
     * @return array{title:?string, price:?string, imageUrl:?string, sourceUrl:string}
     *
     * @throws \RuntimeException quando o scraping falha
     */
    public function scrape(string $url): array
    {
        $script = base_path('scraper/scrape.mjs');

        // No Windows, o Node aborta (CSPRNG assertion) se SYSTEMROOT não estiver
        // no ambiente do processo filho — repassa as variáveis de sistema.
        $process = new Process(
            ['node', $script, $url],
            base_path('scraper'),
            [
                'SYSTEMROOT' => getenv('SYSTEMROOT') ?: 'C:\\Windows',
                'PATH' => getenv('PATH') ?: '',
                'TEMP' => getenv('TEMP') ?: sys_get_temp_dir(),
                'TMP' => getenv('TMP') ?: sys_get_temp_dir(),
                'USERPROFILE' => getenv('USERPROFILE') ?: '',
                'PUPPETEER_EXECUTABLE_PATH' => config('services.puppeteer.executable', ''),
            ],
            null,
            90,
        );
        $process->run();

        $output = trim($process->getOutput());
        $data = json_decode($output, true);

        if (! is_array($data)) {
            Log::error('Scraper: saída inválida', ['stdout' => $output, 'stderr' => $process->getErrorOutput()]);
            throw new \RuntimeException('Não foi possível ler a página do produto');
        }

        if (! $process->isSuccessful() || isset($data['error'])) {
            Log::error('Scraper: falha', ['data' => $data, 'stderr' => $process->getErrorOutput()]);
            throw new \RuntimeException($data['error'] ?? 'Não foi possível ler a página do produto');
        }

        return [
            'title' => $data['title'] ?? null,
            'price' => $data['price'] ?? null,
            'imageUrl' => $data['imageUrl'] ?? null,
            'sourceUrl' => $data['sourceUrl'] ?? $url,
        ];
    }
}
