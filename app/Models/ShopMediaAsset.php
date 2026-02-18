<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopMediaAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shop_id',
        'type',
        'file_path',
        'public_url',
        'original_filename',
        'mime_type',
        'file_size',
        'uploaded_at',
        'used_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'used_at' => 'datetime',
        'file_size' => 'integer',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * ストレージパスを取得（Storageファサード用）
     */
    public function getStoragePath(): string
    {
        return $this->file_path;
    }
}

