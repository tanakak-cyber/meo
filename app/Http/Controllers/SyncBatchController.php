<?php

namespace App\Http\Controllers;

use App\Models\SyncBatch;
use App\Models\GbpSnapshot;
use App\Models\Shop;
use Illuminate\Http\Request;

class SyncBatchController extends Controller
{
    /**
     * 同期バッチの進捗を取得
     */
    public function show($id)
    {
        $syncBatch = SyncBatch::findOrFail($id);

        $shopResults = [];
        
        // バッチ処理が完了している場合、店舗ごとの結果を取得
        if ($syncBatch->status === 'finished' && $syncBatch->finished_at) {
            // バッチの開始時刻から終了時刻までのスナップショットを取得
            $snapshots = GbpSnapshot::whereBetween('synced_at', [$syncBatch->started_at, $syncBatch->finished_at])
                ->get()
                ->groupBy('shop_id')
                ->map(function ($shopSnapshots) {
                    // 各店舗の最新のスナップショットを使用
                    $latestSnapshot = $shopSnapshots->sortByDesc('synced_at')->first();
                    $shop = Shop::find($latestSnapshot->shop_id);
                    
                    if (!$shop) {
                        return null;
                    }
                    
                    return [
                        'shop_id' => $shop->id,
                        'shop_name' => $shop->name,
                        'reviews_changed' => $latestSnapshot->reviews_count ?? 0,
                        'photos_inserted' => 0, // スナップショットからは取得できないため、別途計算が必要
                        'photos_updated' => 0,
                        'posts_synced' => $latestSnapshot->posts_count ?? 0,
                    ];
                })
                ->filter()
                ->values()
                ->toArray();
            
            $shopResults = $snapshots;
        }

        return response()->json([
            'status' => $syncBatch->status,
            'completed_shops' => $syncBatch->completed_shops,
            'total_shops' => $syncBatch->total_shops,
            'total_inserted' => $syncBatch->total_inserted,
            'total_updated' => $syncBatch->total_updated,
            'finished_at' => $syncBatch->finished_at?->toDateTimeString(),
            'progress_percentage' => $syncBatch->progress_percentage,
            'shop_results' => $shopResults,
        ]);
    }
}





