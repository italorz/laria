<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /** Perfil de um usuário com seus posts. */
    public function show(Request $request, User $user)
    {
        $viewerId = $request->user()->id;

        $posts = $user->posts()
            ->with(['user', 'products'])
            ->withCount('likers')
            ->withExists(['likers as liked_by_viewer' => fn ($q) => $q->whereKey($viewerId)])
            ->orderByDesc('created_at')
            ->limit(60)
            ->get();

        return Inertia::render('Profile', [
            'profile' => [
                'id' => $user->id,
                'displayName' => $user->name,
                'avatarUrl' => $user->avatar_url,
                'bio' => $user->bio,
                'postCount' => $user->posts()->count(),
                'isMe' => $user->id === $viewerId,
            ],
            'posts' => $posts->map(fn (Post $p) => $p->toFeedArray($viewerId))->all(),
        ]);
    }
}
