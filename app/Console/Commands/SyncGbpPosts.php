<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shop;
use App\Models\GbpPost;
use App\Services\GoogleBusinessProfileService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SyncGbpPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gbp:sync 
                            {--shop-id= : Shop ID to sync posts for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Local Posts from Google Business Profile API to database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shopId = $this->option('shop-id');

        if (!$shopId) {
            $this->error('--shop-id オプションを指定してください。');
            return 1;
        }

        $shop = Shop::find($shopId);
        
        if (!$shop) {
            $this->error("Shop ID {$shopId} が見つかりません。");
            return 1;
        }

        $this->info("店舗: {$shop->name} (ID: {$shop->id})");
        $this->info("GBPデータはSingle Source of Truthとして保存されます（operator_idは関係ありません）");
        $this->info("");

        // アクセストークンの取得
        $gbpService = new GoogleBusinessProfileService();
        $accessToken = $gbpService->getAccessToken($shop);

        if (!$accessToken) {
            $this->error("アクセストークンの取得に失敗しました。");
            return 1;
        }

        $this->info("アクセストークンを取得しました。");
        $this->info("");

        // 投稿同期の実行（GBPデータは共有として保存）
        $syncedCount = $this->syncPosts($shop, $accessToken, $gbpService);

        $this->info("==========================================");
        $this->info("同期完了");
        $this->info("==========================================");
        $this->info("同期された投稿数: {$syncedCount}件");
        $this->info("");

        return 0;
    }

    /**
     * 投稿を同期する（GBPデータはSingle Source of Truthとして保存）
     */
    private function syncPosts(Shop $shop, string $accessToken, GoogleBusinessProfileService $googleService): int
    {
        if (!$shop->gbp_location_id) {
            Log::warning('GBP_POST_SYNC_GBP_LOCATION_ID_NOT_SET', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);
            $this->warn("GBP Location IDが設定されていません。");
            return 0;
        }

        if (!$shop->gbp_account_id) {
            Log::warning('GBP_POST_SYNC_GBP_ACCOUNT_ID_NOT_SET', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);
            $this->warn("GBP Account IDが設定されていません。");
            return 0;
        }

        Log::info("[GBP_POST_SYNC_START] shop_id={$shop->id}");

        $this->info("Google Business Profile APIから投稿一覧を取得中...");

        // Google Business Profile APIから投稿一覧を取得
        $postsResponse = $googleService->listLocalPosts($accessToken, $shop->gbp_account_id, $shop->gbp_location_id);
        
        if (empty($postsResponse)) {
            Log::warning('GBP_POST_SYNC_EMPTY_RESPONSE', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);
            $this->warn("投稿の取得に失敗しました。");
            return 0;
        }

        // レスポンスの構造を確認
        $localPosts = $postsResponse['localPosts'] ?? [];
        $totalCount = count($localPosts);

        $this->info("取得された投稿数: {$totalCount}件");
        $this->info("");

        $savedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        $progressBar = $this->output->createProgressBar(count($localPosts));
        $progressBar->start();

        foreach ($localPosts as $localPost) {
            try {
                // name から gbp_post_id を取得（最後の部分）
                $name = $localPost['name'] ?? null;
                if (!$name) {
                    $errorCount++;
                    $progressBar->advance();
                    continue;
                }

                $parts = explode('/', $name);
                $gbpPostId = end($parts);

                // (shop_id, gbp_post_id) がすでに存在する場合はスキップ（operator_idは関係ない）
                $exists = GbpPost::where('shop_id', $shop->id)
                    ->where('gbp_post_id', $gbpPostId)
                    ->exists();

                if ($exists) {
                    $skippedCount++;
                    Log::info("[GBP_POST_SKIPPED] already exists {$gbpPostId}");
                    $progressBar->advance();
                    continue;
                }

                // createTime をパース
                $createTime = isset($localPost['createTime']) 
                    ? Carbon::parse($localPost['createTime']) 
                    : now();

                // 投稿を保存（operator_idは保存しない）
                GbpPost::create([
                    'shop_id' => $shop->id,
                    'gbp_post_id' => $gbpPostId,
                    'create_time' => $createTime,
                    'fetched_at' => now(),
                ]);

                $savedCount++;
                Log::info("[GBP_POST_SAVED] post_id={$gbpPostId}");

                $progressBar->advance();

            } catch (\Exception $e) {
                $errorCount++;
                Log::error('GBP_POST_ITEM_ERROR', [
                    'shop_id' => $shop->id,
                    'local_post' => $localPost,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("\nエラー: " . $e->getMessage());
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->info("");
        $this->info("");

        Log::info("[GBP_POST_SYNC_COMPLETE] saved={$savedCount} skipped={$skippedCount}");

        if ($skippedCount > 0) {
            $this->info("スキップされた投稿数: {$skippedCount}件（既に存在）");
        }

        if ($errorCount > 0) {
            $this->warn("エラーが発生した投稿数: {$errorCount}件");
        }

        return $savedCount;
    }
}


