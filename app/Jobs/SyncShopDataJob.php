<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Models\GbpSnapshot;
use App\Models\Photo;
use App\Models\SyncBatch;
use App\Services\GoogleBusinessProfileService;
use App\Services\ReviewSyncService;
use App\Helpers\AuthHelper;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\CarbonImmutable;

class SyncShopDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    protected $shopId;
    protected $sinceDate;
    protected $operatorId;
    protected $startDateCarbon;
    protected $endDateCarbon;
    protected $syncBatchId;

    public function __construct(int $shopId, string $sinceDate, ?int $operatorId = null, ?string $startDateCarbon = null, ?string $endDateCarbon = null, ?int $syncBatchId = null)
    {
        $this->shopId = $shopId;
        $this->sinceDate = $sinceDate;
        $this->operatorId = $operatorId;
        $this->startDateCarbon = $startDateCarbon;
        $this->endDateCarbon = $endDateCarbon;
        $this->syncBatchId = $syncBatchId;
    }

    public function handle(GoogleBusinessProfileService $googleService)
    {
        // バッチがキャンセルされている場合は処理を中断
        if ($this->batch()?->cancelled()) {
            return;
        }

        $shop = Shop::find($this->shopId);

        if (!$shop) {
            Log::warning('SYNC_SHOP_DATA_JOB_SHOP_NOT_FOUND', [
                'shop_id' => $this->shopId,
            ]);
            return;
        }

        try {
            // アクセストークンを取得
            $accessToken = $googleService->getAccessToken($shop);
            
            if (!$accessToken) {
                Log::error('SYNC_SHOP_DATA_JOB_NO_ACCESS_TOKEN', [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                ]);
                return;
            }

            // スナップショットを作成
            $currentUserId = AuthHelper::getCurrentUserId();
            $snapshot = GbpSnapshot::create([
                'shop_id' => $shop->id,
                'user_id' => $currentUserId,
                'synced_by_operator_id' => $this->operatorId,
                'synced_at' => now(),
                'sync_params' => [
                    'start_date' => $this->startDateCarbon,
                    'end_date' => $this->endDateCarbon,
                    'since_date' => $this->sinceDate,
                ],
            ]);

            // 口コミ同期
            $reviewSyncService = new ReviewSyncService();
            $reviewResult = $reviewSyncService->syncShop($shop, $accessToken, $googleService, $snapshot->id, $this->sinceDate);
            $reviewsSynced = $reviewResult['inserted_count'] + $reviewResult['updated_count'];

            // 写真同期
            $photoResult = $this->syncPhotos($shop, $accessToken, $googleService, $snapshot->id, $this->sinceDate);

            // 投稿同期
            $postResult = $googleService->syncLocalPostsAndSave($shop, $this->sinceDate);
            $postsCount = ($postResult['inserted'] ?? 0) + ($postResult['updated'] ?? 0);

            // スナップショットの数を更新
            $snapshot->update([
                'photos_count' => $photoResult['inserted'] + $photoResult['updated'],
                'reviews_count' => $reviewsSynced,
                'posts_count' => $postsCount,
            ]);

            Log::info('SYNC_SHOP_DATA_JOB_COMPLETE', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'reviews_synced' => $reviewsSynced,
                'photos_inserted' => $photoResult['inserted'],
                'photos_updated' => $photoResult['updated'],
                'posts_count' => $postsCount,
            ]);

            // 同期バッチの進捗を更新
            if ($this->syncBatchId) {
                $syncBatch = SyncBatch::find($this->syncBatchId);
                if ($syncBatch) {
                    $totalInserted = $photoResult['inserted'] + ($postResult['inserted'] ?? 0);
                    $totalUpdated = $reviewsSynced + $photoResult['updated'] + ($postResult['updated'] ?? 0);
                    $syncBatch->incrementProgress($totalInserted, $totalUpdated);
                }
            }

        } catch (\Exception $e) {
            Log::error('SYNC_SHOP_DATA_JOB_ERROR', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 写真を同期（完全差分同期）
     */
    private function syncPhotos(Shop $shop, string $accessToken, GoogleBusinessProfileService $googleService, int $snapshotId, string $sinceDate): array
    {
        if (!$shop->gbp_location_id) {
            Log::warning('PHOTO_SYNC_GBP_LOCATION_ID_NOT_SET', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        if (!$shop->gbp_account_id) {
            Log::warning('PHOTO_SYNC_GBP_ACCOUNT_ID_NOT_SET', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        Log::info('PHOTO_SYNC_START', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'gbp_account_id' => $shop->gbp_account_id,
            'gbp_location_id' => $shop->gbp_location_id,
        ]);

        // Google Business Profile APIから写真一覧を取得
        $mediaResponse = $googleService->listMedia($accessToken, $shop->gbp_account_id, $shop->gbp_location_id);
        
        if (empty($mediaResponse)) {
            Log::warning('PHOTO_SYNC_EMPTY_RESPONSE', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        // レスポンスの構造を確認
        $apiPhotos = $mediaResponse['mediaItems'] ?? [];

        Log::info('PHOTO_SYNC_API_RESPONSE', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'media_items_count' => count($apiPhotos),
        ]);

        // PHOTO形式のみ抽出
        $photoItems = array_filter($apiPhotos, function ($item) {
            return ($item['mediaFormat'] ?? null) === 'PHOTO';
        });

        // sinceDateをUTCに変換
        $sinceUtc = CarbonImmutable::parse($sinceDate, 'Asia/Tokyo')
            ->startOfDay()
            ->timezone('UTC');

        // 最新20件のみチェック対象（APIレスポンスは通常 updateTime DESC で返る）
        $latestPhotos = array_slice($photoItems, 0, 20);

        if (empty($latestPhotos)) {
            Log::info('PHOTO_SYNC_NO_PHOTOS', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        // DBに存在するか一括取得
        $mediaNames = collect($latestPhotos)->pluck('name')->filter()->toArray();
        $existingIds = Photo::where('shop_id', $shop->id)
            ->whereIn('gbp_media_name', $mediaNames)
            ->pluck('gbp_media_name')
            ->toArray();

        $insertedCount = 0;
        $updatedCount = 0;

        foreach ($latestPhotos as $photoData) {
            try {
                // updateTime基準で打ち切り判定
                if (isset($photoData['updateTime'])) {
                    $photoTime = CarbonImmutable::parse($photoData['updateTime'], 'UTC');
                    if ($photoTime->lessThan($sinceUtc)) {
                        break;
                    }
                }

                $mediaName = $photoData['name'] ?? null;
                if (!$mediaName) {
                    continue;
                }

                // 既にDBに存在する場合はスキップ（古い写真は触らない）
                if (in_array($mediaName, $existingIds)) {
                    continue;
                }

                // 新規のみ保存
                $apiUpdateTime = isset($photoData['updateTime'])
                    ? CarbonImmutable::parse($photoData['updateTime'], 'UTC')->format('Y-m-d H:i:s')
                    : null;

                Photo::create([
                    'shop_id' => $shop->id,
                    'gbp_media_name' => $mediaName,
                    'google_url' => $photoData['googleUrl'] ?? null,
                    'gbp_update_time' => $apiUpdateTime,
                ]);

                $insertedCount++;

            } catch (\Exception $e) {
                Log::error('PHOTO_SYNC_ITEM_ERROR', [
                    'shop_id' => $shop->id,
                    'photo_data' => $photoData,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // 早期終了ログ
        if ($insertedCount === 0) {
            Log::info('PHOTO_SYNC_NO_NEW_PHOTOS', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'checked_count' => count($latestPhotos),
            ]);
        }

        Log::info('PHOTO_SYNC_COMPLETE', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'inserted_count' => $insertedCount,
            'updated_count' => $updatedCount,
        ]);

        Log::info('SYNC_WITH_SINCE_FILTER', [
            'shop_id' => $shop->id,
            'since_date' => $sinceDate,
            'type' => 'photo',
        ]);

        return ['inserted' => $insertedCount, 'updated' => $updatedCount];
    }
}


