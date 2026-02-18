<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GbpLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'location_id',
        'account_id',
        'name',
        'address',
        'phone_number',
        'website',
        'latitude',
        'longitude',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}






















