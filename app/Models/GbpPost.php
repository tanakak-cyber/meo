<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GbpPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'gbp_post_name',
        'gbp_post_id',
        'source_url',
        'source_type',
        'source_external_id',
        'posted_at',
        'summary',
        'snapshot_id',
        'create_time',
        'media_url',
        'is_deleted',
        'fetched_at',
        'wp_post_id',
        'wp_posted_at',
        'wp_post_status',
    ];

    protected $casts = [
        'create_time' => 'datetime',
        'fetched_at' => 'datetime',
        'posted_at' => 'datetime',
        'wp_posted_at' => 'datetime',
        'is_deleted' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(GbpSnapshot::class);
    }
}
