<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GbpInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'location_id',
        'from_date',
        'to_date',
        'period_type',
        'year',
        'month',
        'metrics_response',
        'keywords_response',
        'impressions',
        'directions',
        'website',
        'phone',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'metrics_response' => 'array',
        'keywords_response' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

