<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shop;
use App\Models\Photo;
use App\Services\GoogleBusinessProfileService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncGbpPhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gbp:sync-photos 
                            {--shop-id= : Shop ID to sync photos for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize photos from Google Business Profile API to database';

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

        // 写真同期の実行
        $syncedCount = $this->syncPhotos($shop, $accessToken, $gbpService);

        $this->info("==========================================");
        $this->info("同期完了");
        $this->info("==========================================");
        $this->info("同期された写真数: {$syncedCount}件");
        $this->info("");

        return 0;
    }

    /**
     * 写真を同期する
     */
    private function syncPhotos(Shop $shop, string $accessToken, GoogleBusinessProfileService $googleService): int
    {
        if (!$shop->gbp_location_id) {
            $this->warn("GBP Location IDが設定されていません。");
            return 0;
        }

        if (!$shop->gbp_account_id) {
            $this->warn("GBP Account IDが設定されていません。");
            return 0;
        }

        $this->info("Google Business Profile APIから写真一覧を取得中...");

        // Google Business Profile APIから写真一覧を取得
        $mediaResponse = $googleService->listMedia($accessToken, $shop->gbp_account_id, $shop->gbp_location_id);
        
        if (empty($mediaResponse)) {
            $this->warn("写真の取得に失敗しました。");
            return 0;
        }

        // レスポンスの構造を確認
        $mediaItems = $mediaResponse['mediaItems'] ?? [];
        $totalCount = $mediaResponse['totalMediaItemCount'] ?? 0;

        $this->info("取得されたメディアアイテム数: {$totalCount}件");
        $this->info("");

        $syncedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        $progressBar = $this->output->createProgressBar(count($mediaItems));
        $progressBar->start();

        foreach ($mediaItems as $mediaItem) {
            try {
                // mediaFormatがPHOTOのもののみを処理
                $mediaFormat = $mediaItem['mediaFormat'] ?? null;
                if ($mediaFormat !== 'PHOTO') {
                    $skippedCount++;
                    $progressBar->advance();
                    continue;
                }

                // gbp_media_nameを取得（フルパス形式: "accounts/.../locations/.../media/..."）
                $gbpMediaName = $mediaItem['name'] ?? null;
                if (!$gbpMediaName) {
                    $errorCount++;
                    $progressBar->advance();
                    continue;
                }

                // media IDを抽出（nameの最後の部分）
                $parts = explode('/', $gbpMediaName);
                $gbpMediaId = end($parts);

                // upsert: gbp_media_idをユニークキーとして存在すればupdate、なければcreate
                $wasRecentlyCreated = Photo::where('gbp_media_id', $gbpMediaId)->doesntExist();
                
                $photo = Photo::updateOrCreate(
                    ['gbp_media_id' => $gbpMediaId],
                    [
                        'shop_id' => $shop->id,
                        'gbp_media_name' => $gbpMediaName,
                        'media_format' => $mediaFormat,
                        'google_url' => $mediaItem['googleUrl'] ?? null,
                        'thumbnail_url' => $mediaItem['thumbnailUrl'] ?? null,
                        'create_time' => isset($mediaItem['createTime']) ? Carbon::parse($mediaItem['createTime']) : now(),
                        'width_pixels' => $mediaItem['dimensions']['widthPixels'] ?? null,
                        'height_pixels' => $mediaItem['dimensions']['heightPixels'] ?? null,
                        'location_association_category' => $mediaItem['locationAssociation']['category'] ?? null,
                    ]
                );

                if ($wasRecentlyCreated) {
                    $syncedCount++;
                }

                $progressBar->advance();

            } catch (\Exception $e) {
                $errorCount++;
                $this->error("\nエラー: " . $e->getMessage());
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->info("");
        $this->info("");

        if ($skippedCount > 0) {
            $this->info("スキップされたアイテム数: {$skippedCount}件（PHOTO以外）");
        }

        if ($errorCount > 0) {
            $this->warn("エラーが発生したアイテム数: {$errorCount}件");
        }

        return $syncedCount;
    }
}

