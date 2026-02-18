<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'snapshot_id',
        'gbp_media_id',
        'gbp_media_name',
        'media_format',
        'google_url',
        'thumbnail_url',
        'create_time',
        'gbp_update_time',
        'width_pixels',
        'height_pixels',
        'location_association_category',
    ];

    protected $casts = [
        'create_time' => 'datetime',
        'gbp_update_time' => 'datetime',
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

