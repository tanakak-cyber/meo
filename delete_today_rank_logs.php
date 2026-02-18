<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MeoRankLog;
use App\Models\MeoKeyword;
use App\Models\RankFetchJob;
use Carbon\Carbon;

$shopId = 1;
$today = Carbon::today()->format('Y-m-d');

echo "店舗ID {$shopId} の本日({$today})の順位チェックデータを削除します...\n";

// 店舗のキーワードIDを取得
$keywordIds = MeoKeyword::where('shop_id', $shopId)->pluck('id');

if ($keywordIds->isEmpty()) {
    echo "店舗ID {$shopId} に関連するキーワードが見つかりません。\n";
    exit(1);
}

echo "関連キーワード数: {$keywordIds->count()}件\n";

// meo_rank_logs から本日のデータを削除
$deletedLogs = MeoRankLog::whereIn('meo_keyword_id', $keywordIds)
    ->whereDate('checked_at', $today)
    ->delete();

echo "meo_rank_logs から {$deletedLogs} 件のレコードを削除しました。\n";

// rank_fetch_jobs から本日のジョブを削除
$deletedJobs = RankFetchJob::where('shop_id', $shopId)
    ->whereDate('target_date', $today)
    ->delete();

echo "rank_fetch_jobs から {$deletedJobs} 件のジョブを削除しました。\n";

echo "削除完了: 合計 " . ($deletedLogs + $deletedJobs) . " 件\n";









