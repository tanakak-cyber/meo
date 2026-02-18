<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'contact_date',
        'contact_time',
        'content',
    ];

    protected $casts = [
        'contact_date' => 'date',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

