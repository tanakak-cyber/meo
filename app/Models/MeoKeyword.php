<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeoKeyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'keyword',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function rankLogs(): HasMany
    {
        return $this->hasMany(MeoRankLog::class);
    }
}






















