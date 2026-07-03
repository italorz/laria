<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PostController extends Controller
{
    /** Publica o resultado gerado, com os produtos (hotspots) vinculados. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'imageUrl' => ['required', 'string', 'max:2048'],
            'originalImageUrl' => ['nullable', 'string', 'max:2048'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'products' => ['array'],
            'products.*.sourceUrl' => ['required', 'string', 'max:2048'],
            'products.*.title' => ['nullable', 'string', 'max:255'],
            'products.*.price' => ['nullable', 'string', 'max:255'],
            'products.*.imageUrl' => ['nullable', 'string', 'max:2048'],
            'products.*.posX' => ['nullable', 'numeric', 'between:0,1'],
            'products.*.posY' => ['nullable', 'numeric', 'between:0,1'],
        ]);

        $post = DB::transaction(function () use ($request, $data) {
            $post = $request->user()->posts()->create([
                'image_url' => $data['imageUrl'],
                'original_image_url' => $data['originalImageUrl'] ?? null,
                'caption' => $data['caption'] ?? null,
            ]);

            foreach ($data['products'] ?? [] as $p) {
                $post->products()->create([
                    'source_url' => $p['sourceUrl'],
                    'title' => $p['title'] ?? null,
                    'price' => $p['price'] ?? null,
                    'image_url' => $p['imageUrl'] ?? null,
                    'pos_x' => $p['posX'] ?? 0.5,
                    'pos_y' => $p['posY'] ?? 0.5,
                ]);
            }

            return $post;
        });

        return redirect()->route('posts.show', $post)->with('status', 'Publicado no seu perfil! 🎉');
    }

    /** Detalhe do post: imagem com hotspots, produtos e like. */
    public function show(Request $request, Post $post)
    {
        $viewerId = $request->user()->id;
        $post->load(['user', 'products'])->loadCount('likers');

        return Inertia::render('PostShow', [
            'post' => $post->toFeedArray($viewerId),
        ]);
    }

    /** Alterna o like (otimista no front). Retorna JSON {liked, likeCount}. */
    public function toggleLike(Request $request, Post $post)
    {
        $user = $request->user();
        $changes = $post->likers()->toggle($user->id);
        $liked = count($changes['attached']) > 0;

        return response()->json([
            'liked' => $liked,
            'likeCount' => $post->likers()->count(),
        ]);
    }
}
