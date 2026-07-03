<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasUuids;

    protected $fillable = ['post_id', 'source_url', 'title', 'price', 'image_url', 'pos_x', 'pos_y'];

    protected $casts = [
        'pos_x' => 'float',
        'pos_y' => 'float',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function toTagArray(): array
    {
        return [
            'id' => $this->id,
            'sourceUrl' => $this->source_url,
            'title' => $this->title,
            'price' => $this->price,
            'imageUrl' => $this->image_url,
            'posX' => $this->pos_x,
            'posY' => $this->pos_y,
        ];
    }
}
