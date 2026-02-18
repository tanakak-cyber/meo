<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shop;
use App\Models\MeoKeyword;
use App\Models\MeoRankLog;
use App\Models\Review;
use App\Models\GbpLocation;
use Illuminate\Support\Facades\DB;

class CleanupTestDataSeeder extends Seeder
{
    /**
     * テストデータの整理
     * ID 1の店舗（カウンジャー小岩店）以外の店舗とその関連データを削除
     */
    public function run(): void
    {
        $this->command->info('テストデータの整理を開始します...');
        
        // ID 1の店舗を確認
        $targetShop = Shop::find(1);
        
        if (!$targetShop) {
            $this->command->warn('ID 1の店舗が見つかりませんでした。');
            return;
        }
        
        $this->command->info("対象店舗ID: 1 (店舗名: {$targetShop->name})");
        
        // 削除対象の店舗IDを取得
        $shopsToDelete = Shop::where('id', '!=', 1)->pluck('id');
        
        if ($shopsToDelete->isEmpty()) {
            $this->command->info('削除対象の店舗はありません。');
            return;
        }
        
        $this->command->info("削除対象店舗数: {$shopsToDelete->count()}件");
        
        // 関連データを削除
        foreach ($shopsToDelete as $shopId) {
            // meo_rank_logs を削除
            $rankLogCount = MeoRankLog::whereHas('meoKeyword', function ($query) use ($shopId) {
                $query->where('shop_id', $shopId);
            })->count();
            
            if ($rankLogCount > 0) {
                MeoRankLog::whereHas('meoKeyword', function ($query) use ($shopId) {
                    $query->where('shop_id', $shopId);
                })->delete();
                $this->command->info("  店舗ID {$shopId}: 順位ログ {$rankLogCount}件を削除");
            }
            
            // meo_keywords を削除
            $keywordCount = MeoKeyword::where('shop_id', $shopId)->count();
            if ($keywordCount > 0) {
                MeoKeyword::where('shop_id', $shopId)->delete();
                $this->command->info("  店舗ID {$shopId}: キーワード {$keywordCount}件を削除");
            }
            
            // reviews を削除
            $reviewCount = Review::where('shop_id', $shopId)->count();
            if ($reviewCount > 0) {
                Review::where('shop_id', $shopId)->delete();
                $this->command->info("  店舗ID {$shopId}: 口コミ {$reviewCount}件を削除");
            }
            
            // gbp_locations を削除（GbpLocationモデルが存在する場合）
            if (class_exists('App\Models\GbpLocation')) {
                $gbpLocationCount = GbpLocation::where('shop_id', $shopId)->count();
                if ($gbpLocationCount > 0) {
                    GbpLocation::where('shop_id', $shopId)->delete();
                    $this->command->info("  店舗ID {$shopId}: GBPロケーション {$gbpLocationCount}件を削除");
                }
            }
        }
        
        // gbp_locations, gbp_reviews, gbp_photos, gbp_posts, kpi_logs テーブルが存在する場合の削除
        $tables = ['gbp_locations', 'gbp_reviews', 'gbp_photos', 'gbp_posts', 'kpi_logs'];
        
        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $deleted = DB::table($table)
                    ->whereIn('shop_id', $shopsToDelete)
                    ->delete();
                
                if ($deleted > 0) {
                    $this->command->info("{$table}: {$deleted}件を削除");
                }
            }
        }
        
        // 店舗を削除
        $deletedCount = Shop::where('id', '!=', 1)->delete();
        $this->command->info("店舗 {$deletedCount}件を削除しました。");
        
        $this->command->info('テストデータの整理が完了しました。');
        $this->command->info('ID 1の店舗（カウンジャー小岩店）のみが残っています。');
    }
}

