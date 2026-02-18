<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\GoogleBusinessProfileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncGbpNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gbp:sync-names {--shop-id= : 特定の店舗IDのみ同期}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '既存店舗のGBP店舗名（正式名称）を同期する';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('GBP店舗名同期を開始します...');

        $googleService = new GoogleBusinessProfileService();

        // 対象店舗を取得
        $query = Shop::whereNotNull('gbp_location_id')
            ->whereNotNull('gbp_refresh_token')
            ->whereNotNull('gbp_account_id');

        // 特定の店舗IDが指定されている場合
        $shopId = $this->option('shop-id');
        if ($shopId) {
            $query->where('id', $shopId);
        }

        $shops = $query->get();

        if ($shops->isEmpty()) {
            $this->warn('同期対象の店舗が見つかりませんでした。');
            return 0;
        }

        $this->info("対象店舗数: {$shops->count()}");

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        foreach ($shops as $shop) {
            $this->line("処理中: {$shop->name} (ID: {$shop->id})");

            try {
                // 既に店舗名が設定されている場合はスキップ（--force オプションが無い限り）
                if ($shop->gbp_name && !$this->option('force')) {
                    $this->warn("  スキップ: 既に店舗名が設定されています ({$shop->gbp_name})");
                    $skippedCount++;
                    continue;
                }

                // access_tokenを取得
                $accessToken = $googleService->getAccessToken($shop);

                if (!$accessToken) {
                    $this->error("  エラー: access_tokenの取得に失敗しました");
                    $errorCount++;
                    continue;
                }

                // accountIdを取得（プレフィックスなし）
                $accountId = $shop->gbp_account_id;

                // locations.list を実行
                $locationsResponse = $googleService->listLocations($accessToken, $accountId);

                if (empty($locationsResponse) || !isset($locationsResponse['locations'])) {
                    $this->error("  エラー: ロケーションの取得に失敗しました");
                    $errorCount++;
                    continue;
                }

                $locations = $locationsResponse['locations'];

                // 現在のロケーションIDと一致するロケーションを探す
                $currentLocationId = $shop->gbp_location_id;
                $matchedLocation = null;

                foreach ($locations as $location) {
                    $locationName = $location['name'] ?? '';
                    if ($locationName === $currentLocationId) {
                        $matchedLocation = $location;
                        break;
                    }
                }

                if (!$matchedLocation) {
                    $this->warn("  警告: 現在のロケーションID ({$currentLocationId}) が見つかりませんでした");
                    $errorCount++;
                    continue;
                }

                // 店舗名（title）を取得
                $gbpName = $matchedLocation['title'] ?? null;

                if (!$gbpName) {
                    $this->warn("  警告: 店舗名（title）を取得できませんでした");
                    $errorCount++;
                    continue;
                }

                // 店舗名を保存
                $shop->update(['gbp_name' => $gbpName]);

                $this->info("  成功: 店舗名を保存しました ({$gbpName})");
                $successCount++;

                Log::info('GBP店舗名同期成功', [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'gbp_name' => $gbpName,
                ]);

            } catch (\Exception $e) {
                $this->error("  エラー: {$e->getMessage()}");
                $errorCount++;

                Log::error('GBP店舗名同期エラー', [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->newLine();
        $this->info("同期完了:");
        $this->info("  成功: {$successCount}");
        $this->info("  エラー: {$errorCount}");
        $this->info("  スキップ: {$skippedCount}");

        return 0;
    }
}

