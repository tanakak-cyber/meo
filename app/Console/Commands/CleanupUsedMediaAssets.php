<?php

namespace App\Console\Commands;

use App\Models\ShopMediaAsset;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CleanupUsedMediaAssets extends Command
{
    protected $signature = 'media-assets:cleanup-used {--days=7 : 使用後何日経過したら物理削除するか}';
    protected $description = '使用済み（論理削除済み）の投稿素材を物理削除します';

    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("{$days}日以上前に使用された素材を物理削除します...");

        // 論理削除済みで、used_atが指定日数以上前のレコードを取得
        $assets = ShopMediaAsset::onlyTrashed()
            ->whereNotNull('used_at')
            ->where('used_at', '<=', $cutoffDate)
            ->get();

        $count = $assets->count();

        if ($count === 0) {
            $this->info('物理削除対象の素材がありません。');
            return 0;
        }

        $this->info("{$count}件の素材を物理削除します...");

        $deletedCount = 0;

        foreach ($assets as $asset) {
            try {
                // 物理削除（forceDelete）
                $asset->forceDelete();
                $deletedCount++;
            } catch (\Exception $e) {
                $this->error("物理削除エラー: asset_id={$asset->id} - {$e->getMessage()}");
            }
        }

        $this->info("物理削除完了: {$deletedCount}件");

        return 0;
    }
}









