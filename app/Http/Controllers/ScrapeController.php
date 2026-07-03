<?php

namespace App\Http\Controllers;

use App\Services\ImageStorage;
use App\Services\ProductScraper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScrapeController extends Controller
{
    /** POST /scrape {url} — lê a página do produto e espelha a imagem localmente. */
    public function store(Request $request, ProductScraper $scraper, ImageStorage $storage)
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
        ]);

        try {
            $product = $scraper->scrape($data['url']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        // Espelha a imagem do produto localmente (evita hotlink/CORS no navegador).
        $localImageUrl = null;
        if ($product['imageUrl']) {
            try {
                $localImageUrl = $storage->saveFromUrl($product['imageUrl']);
            } catch (\Throwable $e) {
                Log::warning('Não foi possível baixar a imagem do produto', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'title' => $product['title'],
            'price' => $product['price'],
            'sourceUrl' => $product['sourceUrl'],
            'imageUrl' => $localImageUrl ?? $product['imageUrl'],
            'remoteImageUrl' => $product['imageUrl'],
        ]);
    }
}
