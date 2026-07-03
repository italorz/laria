<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'image_url', 'original_image_url', 'caption'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class)->orderBy('created_at');
    }

    public function likers()
    {
        return $this->belongsToMany(User::class, 'likes')->withTimestamps();
    }

    /**
     * Serializa o post no formato consumido pelas páginas Vue
     * (mesmo shape da API antiga: imageUrl, likeCount, liked, author, products).
     */
    public function toFeedArray(?int $viewerId = null): array
    {
        return [
            'id' => $this->id,
            'imageUrl' => $this->image_url,
            'originalImageUrl' => $this->original_image_url,
            'caption' => $this->caption,
            'createdAt' => $this->created_at?->toIso8601String(),
            'likeCount' => (int) ($this->likers_count ?? $this->likers()->count()),
            'liked' => (bool) ($this->liked_by_viewer
                ?? ($viewerId !== null && $this->likers()->whereKey($viewerId)->exists())),
            'author' => [
                'id' => $this->user->id,
                'displayName' => $this->user->name,
                'avatarUrl' => $this->user->avatar_url,
            ],
            'products' => $this->products->map(fn (Product $p) => $p->toTagArray())->all(),
        ];
    }
}
