<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GbpSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'user_id',
        'synced_by_operator_id',
        'synced_at',
        'photos_count',
        'reviews_count',
        'posts_count', // Googleが現在有効とみなしている投稿数（検索順位に影響する投稿数）
        'sync_params',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'sync_params' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function syncedByOperator(): BelongsTo
    {
        return $this->belongsTo(OperationPerson::class, 'synced_by_operator_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}

