<?php

namespace App\Console\Commands;

use App\Models\ShopMediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DeleteAllMediaAssets extends Command
{
    protected $signature = 'media-assets:delete-all';
    protected $description = 'すべての投稿素材（画像・動画）を削除します';

    public function handle()
    {
        if (!$this->confirm('投稿素材ストレージ機能のすべての画像・動画を削除しますか？この操作は取り消せません。')) {
            $this->info('削除をキャンセルしました。');
            return 0;
        }

        // shop_media_assetsテーブルのデータのみを取得
        $assets = ShopMediaAsset::withTrashed()->get();
        $count = $assets->count();

        if ($count === 0) {
            $this->info('削除する素材がありません。');
            return 0;
        }

        $this->info("投稿素材ストレージ: {$count}件の素材を削除します...");

        $deletedCount = 0;
        $fileDeletedCount = 0;
        $fileErrorCount = 0;

        foreach ($assets as $asset) {
            // ファイルを削除（storage/app/public/media_assets/ 配下のみ）
            if ($asset->file_path && Storage::disk('public')->exists($asset->file_path)) {
                try {
                    Storage::disk('public')->delete($asset->file_path);
                    $fileDeletedCount++;
                } catch (\Exception $e) {
                    $this->error("ファイル削除エラー: {$asset->file_path} - {$e->getMessage()}");
                    $fileErrorCount++;
                }
            }

            // DBレコードを完全削除（soft deleteも含む）
            try {
                $asset->forceDelete();
                $deletedCount++;
            } catch (\Exception $e) {
                $this->error("DB削除エラー: asset_id={$asset->id} - {$e->getMessage()}");
            }
        }

        $this->info("削除完了:");
        $this->info("  - DBレコード (shop_media_assets): {$deletedCount}件");
        $this->info("  - ファイル (storage/app/public/media_assets/): {$fileDeletedCount}件");
        if ($fileErrorCount > 0) {
            $this->warn("  - ファイル削除エラー: {$fileErrorCount}件");
        }

        return 0;
    }
}

