<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RankFetchJob extends Model
{
    protected $table = 'rank_fetch_jobs';

    protected $fillable = [
        'shop_id',
        'meo_keyword_id',
        'target_date',
        'status',
        'requested_by_type',
        'requested_by_id',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected $casts = [
        'target_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function meoKeyword(): BelongsTo
    {
        return $this->belongsTo(MeoKeyword::class);
    }
}
