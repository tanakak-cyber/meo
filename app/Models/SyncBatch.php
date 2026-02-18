<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'total_shops',
        'completed_shops',
        'total_inserted',
        'total_updated',
        'status',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * 進捗を更新
     */
    public function incrementProgress(int $inserted = 0, int $updated = 0): void
    {
        $this->increment('completed_shops');
        $this->increment('total_inserted', $inserted);
        $this->increment('total_updated', $updated);

        // DBから最新の値を取得
        $this->refresh();

        // すべての店舗が完了した場合
        if ($this->completed_shops >= $this->total_shops) {
            $this->update([
                'status' => 'finished',
                'finished_at' => now(),
            ]);
        }
    }

    /**
     * 進捗率を取得（0-100）
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_shops === 0) {
            return 0;
        }
        return ($this->completed_shops / $this->total_shops) * 100;
    }
}

