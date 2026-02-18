<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'name',
        'execution_time',
        'is_enabled',
        'description',
    ];

    protected $casts = [
        'execution_time' => 'string',
        'is_enabled' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}














