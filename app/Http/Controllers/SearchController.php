<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchController extends Controller
{
    /** Busca usuários (por nome) e posts (por legenda/título de produto). */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $users = [];
        $posts = [];

        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';

            $users = User::where('name', 'like', $like)
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'displayName' => $u->name,
                    'avatarUrl' => $u->avatar_url,
                    'bio' => $u->bio,
                ])
                ->all();

            $posts = Post::where('caption', 'like', $like)
                ->orWhereHas('products', fn ($pq) => $pq->where('title', 'like', $like))
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
                ->map(fn (Post $p) => [
                    'id' => $p->id,
                    'imageUrl' => $p->image_url,
                    'caption' => $p->caption,
                    'createdAt' => $p->created_at->toIso8601String(),
                ])
                ->all();
        }

        return Inertia::render('Search', [
            'q' => $q,
            'users' => $users,
            'posts' => $posts,
        ]);
    }
}
