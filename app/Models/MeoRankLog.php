<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeoRankLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'meo_keyword_id',
        'position',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'date',
    ];

    public function meoKeyword(): BelongsTo
    {
        return $this->belongsTo(MeoKeyword::class);
    }
}










