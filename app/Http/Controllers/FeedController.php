<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeedController extends Controller
{
    /** Feed principal (posts mais recentes), com paginação por cursor de data. */
    public function index(Request $request)
    {
        $viewerId = $request->user()->id;
        $limit = min((int) $request->query('limit', 12), 50);
        $cursor = $request->query('cursor');

        $query = Post::with(['user', 'products'])
            ->withCount('likers')
            ->withExists(['likers as liked_by_viewer' => fn ($q) => $q->whereKey($viewerId)])
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($cursor) {
            $query->where('created_at', '<', $cursor);
        }

        $posts = $query->get();
        $payload = [
            'posts' => $posts->map(fn (Post $p) => $p->toFeedArray($viewerId))->all(),
            'nextCursor' => $posts->count() === $limit
                ? $posts->last()->created_at->toIso8601String()
                : null,
        ];

        // Paginação incremental via fetch JSON (scroll infinito).
        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Feed', $payload);
    }
}
