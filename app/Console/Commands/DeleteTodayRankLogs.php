<?php

namespace App\Console\Commands;

use App\Models\MeoRankLog;
use App\Models\MeoKeyword;
use App\Models\RankFetchJob;
use Illuminate\Console\Command;
use Carbon\Carbon;

class DeleteTodayRankLogs extends Command
{
    protected $signature = 'rank-logs:delete-today {shop_id : 店舗ID}';
    protected $description = '指定店舗の本日の順位チェックデータを削除します';

    public function handle()
    {
        $shopId = (int) $this->argument('shop_id');
        $today = Carbon::today()->format('Y-m-d');

        $this->info("店舗ID {$shopId} の本日({$today})の順位チェックデータを削除します...");

        // 店舗のキーワードIDを取得
        $keywordIds = MeoKeyword::where('shop_id', $shopId)->pluck('id');
        
        if ($keywordIds->isEmpty()) {
            $this->warn("店舗ID {$shopId} に関連するキーワードが見つかりません。");
            return 0;
        }

        $this->info("関連キーワード数: {$keywordIds->count()}件");

        // meo_rank_logs から本日のデータを削除
        $deletedLogs = MeoRankLog::whereIn('meo_keyword_id', $keywordIds)
            ->whereDate('checked_at', $today)
            ->delete();

        $this->info("meo_rank_logs から {$deletedLogs} 件のレコードを削除しました。");

        // rank_fetch_jobs から本日のジョブを削除（オプション）
        $deletedJobs = RankFetchJob::where('shop_id', $shopId)
            ->whereDate('target_date', $today)
            ->delete();

        $this->info("rank_fetch_jobs から {$deletedJobs} 件のジョブを削除しました。");

        $this->info("削除完了: 合計 " . ($deletedLogs + $deletedJobs) . " 件");

        return 0;
    }
}









