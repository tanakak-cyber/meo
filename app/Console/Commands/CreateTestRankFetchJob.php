<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\MeoKeyword;
use App\Models\RankFetchJob;
use Illuminate\Console\Command;

class CreateTestRankFetchJob extends Command
{
    protected $signature = 'test:create-rank-fetch-job {--shop-id= : 店舗ID（指定しない場合は最初の店舗を使用）} {--keyword= : キーワード（指定しない場合は既存のキーワードを使用、なければ作成）}';

    protected $description = 'rank_fetch_jobsにテスト用のジョブを作成します';

    public function handle()
    {
        $shopId = $this->option('shop-id');
        
        // 店舗を取得
        if ($shopId) {
            $shop = Shop::find($shopId);
            if (!$shop) {
                $this->error("店舗ID {$shopId} が見つかりません");
                return 1;
            }
        } else {
            $shop = Shop::first();
            if (!$shop) {
                $this->error('店舗が登録されていません');
                return 1;
            }
        }
        
        $this->info("店舗: {$shop->name} (ID: {$shop->id})");
        
        // キーワードを取得または作成
        $keyword = $this->option('keyword');
        if ($keyword) {
            // 指定されたキーワードを使用（既存があればそれを使用、なければ作成）
            $meoKeyword = MeoKeyword::firstOrCreate(
                ['shop_id' => $shop->id, 'keyword' => $keyword],
                ['keyword' => $keyword]
            );
            $this->info("キーワード: {$meoKeyword->keyword} (ID: {$meoKeyword->id})");
        } else {
            // 既存のキーワードを取得
            $meoKeyword = MeoKeyword::where('shop_id', $shop->id)->first();
            if (!$meoKeyword) {
                // キーワードがない場合は作成
                $meoKeyword = MeoKeyword::create([
                    'shop_id' => $shop->id,
                    'keyword' => 'ラーメン 渋谷', // デフォルトキーワード
                ]);
                $this->info("キーワードを作成しました: {$meoKeyword->keyword} (ID: {$meoKeyword->id})");
            } else {
                $this->info("キーワード: {$meoKeyword->keyword} (ID: {$meoKeyword->id})");
            }
        }
        
        // 管理者ユーザーを取得（requested_by_id用）
        $adminUser = \App\Models\User::where('is_admin', true)->first();
        $requestedById = $adminUser ? $adminUser->id : 1;
        
        // テストジョブを作成
        try {
            $job = RankFetchJob::create([
                'shop_id' => $shop->id,
                'target_date' => now()->format('Y-m-d'),
                'status' => 'queued',
                'requested_by_type' => 'admin',
                'requested_by_id' => $requestedById,
            ]);
            
            $this->info("✅ テストジョブを作成しました:");
            $this->line("   ID: {$job->id}");
            $this->line("   店舗ID: {$job->shop_id}");
            $this->line("   対象日: {$job->target_date}");
            $this->line("   ステータス: {$job->status}");
            $this->line("");
            $this->info("次のコマンドでワーカーを実行できます:");
            $this->line("   node rank-worker.cjs");
            
            return 0;
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                $this->warn("⚠️  この日付・店舗のジョブは既に存在します");
                $this->info("既存のジョブを確認してください:");
                $this->line("   php artisan tinker");
                $this->line("   App\\Models\\RankFetchJob::where('shop_id', {$shop->id})->where('target_date', '".now()->format('Y-m-d')."')->get();");
            } else {
                $this->error("エラー: {$e->getMessage()}");
            }
            return 1;
        }
    }
}

