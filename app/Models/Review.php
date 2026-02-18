<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'snapshot_id',
        'gbp_review_id',
        'author_name',
        'rating',
        'comment',
        'create_time',
        'update_time',
        'gbp_update_time',
        'gbp_create_time',
        'reply_text',
        'replied_at',
        'gbp_reply_update_time',
        'has_reply',
    ];

    protected $casts = [
        'create_time' => 'datetime',
        'update_time' => 'datetime',
        'gbp_update_time' => 'datetime',
        'gbp_create_time' => 'datetime',
        'replied_at' => 'datetime',
        'gbp_reply_update_time' => 'datetime',
        'has_reply' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(GbpSnapshot::class);
    }

    public function isReplied(): bool
    {
        return !empty($this->reply_text) && !is_null($this->replied_at);
    }
}

