<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Shop;
use App\Services\GoogleBusinessProfileService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class ReviewSyncService
{
    /**
     * ReviewID を正規化（空白/制御文字/見えない文字を除去）
     * 
     * @param string $id
     * @return string
     */
    private function normalizeReviewId(string $id): string
    {
        // trim に加えて 制御文字(\p{C}) と空白(\s) を除去（ゼロ幅スペース等も含む）
        return preg_replace('/[\p{C}\s]+/u', '', trim($id));
    }


    /**
     * 店舗の口コミを同期（差分更新対応）
     * 
     * @param Shop $shop
     * @param string $accessToken
     * @param GoogleBusinessProfileService $googleService
     * @param int|null $snapshotId
     * @param string $sinceDate 〇月〇日以降のみ同期（Y-m-d形式、JST）
     * @return array ['synced_count' => int, 'skipped_count' => int, 'inserted_count' => int, 'updated_count' => int]
     */
    public function syncShop(Shop $shop, string $accessToken, GoogleBusinessProfileService $googleService, ?int $snapshotId = null, string $sinceDate = null): array
    {
        $operatorId = session('operator_id');
        
        if (!$shop->gbp_location_id) {
            Log::warning('ReviewSyncService: gbp_location_idが設定されていません', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'operator_id' => $operatorId,
            ]);
            return ['synced_count' => 0, 'skipped_count' => 0, 'inserted_count' => 0, 'updated_count' => 0];
        }

        if (!$shop->gbp_account_id) {
            Log::warning('ReviewSyncService: gbp_account_idが設定されていません', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'operator_id' => $operatorId,
            ]);
            return ['synced_count' => 0, 'skipped_count' => 0, 'inserted_count' => 0, 'updated_count' => 0];
        }

        // sinceDateをUTCに変換
        $sinceUtc = $sinceDate 
            ? CarbonImmutable::parse($sinceDate, 'Asia/Tokyo')->startOfDay()->timezone('UTC')
            : null;

        // STEP0: 事前準備
        // cutoff = shop.last_reviews_synced_update_time（NULLなら初回フル同期）
        // DBの値はUTCとして保存されているので、UTCとしてparseする
        $cutoff = $shop->last_reviews_synced_update_time 
            ? CarbonImmutable::parse($shop->last_reviews_synced_update_time, 'UTC')
            : null;
        // maxSeen = 今回同期中に見た review.updateTime の最大値（最終的にshopへ保存）
        $maxSeen = $cutoff; // NULLなら maxSeen=NULL
        
        // 同期開始時刻を記録
        $syncStartedAt = now();
        $shop->update([
            'last_reviews_sync_started_at' => $syncStartedAt,
        ]);

        Log::info('ReviewSyncService: 口コミ同期開始', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'operator_id' => $operatorId,
            'snapshot_id' => $snapshotId,
            'gbp_account_id' => $shop->gbp_account_id,
            'gbp_location_id' => $shop->gbp_location_id,
            'cutoff' => $cutoff ? $cutoff->toIso8601String() : null,
            'is_full_sync' => $cutoff === null,
            'since_date' => $sinceDate,
            'since_utc' => $sinceUtc ? $sinceUtc->toIso8601String() : null,
        ]);

        // STEP1: ページ取得ループ（cutoff判定による早期停止対応）
        $apiStartTime = microtime(true);
        $allReviews = [];
        $pageCount = 0;
        $pageSize = 100;
        $maxPages = 50;
        $nextPageToken = null;
        $stoppedByCutoff = false;
        
        try {
            do {
                $pageCount++;
                
                // 無限ループ防止
                if ($pageCount > $maxPages) {
                    Log::warning('GBP_REVIEWS_LIST_PAGINATION_MAX_PAGES_REACHED', [
                        'shop_id' => $shop->id,
                        'page_count' => $pageCount,
                        'max_pages' => $maxPages,
                    ]);
                    break;
                }
                
                // 1ページ取得
                $reviewsResponse = $googleService->listReviews($accessToken, $shop->gbp_account_id, $shop->gbp_location_id, $shop->id, $nextPageToken, $pageSize);
                
                if (empty($reviewsResponse)) {
                    Log::warning('ReviewSyncService: 口コミの取得に失敗: レスポンスが空', [
                        'shop_id' => $shop->id,
                        'page' => $pageCount,
                    ]);
                    break;
                }
                
                // レスポンスの構造を確認
                $reviewsThisPage = [];
                if (isset($reviewsResponse['reviews'])) {
                    $reviewsThisPage = $reviewsResponse['reviews'];
                } elseif (is_array($reviewsResponse) && isset($reviewsResponse[0])) {
                    $reviewsThisPage = $reviewsResponse;
                }
                
                // STEP2: 各reviewの停止判定（差分同期の核）
                foreach ($reviewsThisPage as $review) {
                    $reviewUpdateTimeRaw = data_get($review, 'updateTime') ?? data_get($review, 'createTime');
                    if ($reviewUpdateTimeRaw) {
                        try {
                            // UTCとしてparse（->utc() は不要）
                            $reviewUpdate = CarbonImmutable::parse($reviewUpdateTimeRaw, 'UTC');
                            
                            // sinceDateによる打ち切り判定（updateTime基準）
                            if ($sinceUtc !== null && $reviewUpdate->lessThan($sinceUtc)) {
                                Log::info('ReviewSyncService: sinceDate判定で早期停止', [
                                    'shop_id' => $shop->id,
                                    'review_update_time' => $reviewUpdate->toIso8601String(),
                                    'since_utc' => $sinceUtc->toIso8601String(),
                                    'page' => $pageCount,
                                ]);
                                break 2; // 内側と外側のループを抜ける
                            }
                            
                            // もし cutoff != NULL かつ reviewUpdate <= cutoff なら：
                            // ここで「以降のレビューは全部cutoff以下」なので同期を打ち切る
                            if ($cutoff !== null && $reviewUpdate->lessThanOrEqualTo($cutoff)) {
                                $stoppedByCutoff = true;
                                Log::info('ReviewSyncService: cutoff判定で早期停止', [
                                    'shop_id' => $shop->id,
                                    'review_update_time' => $reviewUpdate->toIso8601String(),
                                    'cutoff' => $cutoff->toIso8601String(),
                                    'page' => $pageCount,
                                ]);
                                break 2; // 内側と外側のループを抜ける
                            }
                        } catch (\Exception $e) {
                            // パース失敗時はスキップして続行
                            Log::warning('ReviewSyncService: review.updateTimeのパースに失敗', [
                                'shop_id' => $shop->id,
                                'review_update_time_raw' => $reviewUpdateTimeRaw,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    
                    // cutoff判定を通過したレビューを追加
                    $allReviews[] = $review;
                }
                
                // nextPageToken を取得
                $nextPageToken = $reviewsResponse['nextPageToken'] ?? null;
                
                // 停止した場合は nextPageToken があっても無視して終了
                if ($stoppedByCutoff) {
                    break;
                }
                
            } while ($nextPageToken !== null);
        } catch (\Throwable $e) {
            Log::error('ReviewSyncService: ページ取得ループでエラー', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // 例外時は既に取得したレビューを処理（空配列の可能性あり）
            // 例外が出た場合は shop.last_reviews_synced_update_time を更新しない（進捗を進めない）
            $syncFinishedAt = now();
            $shop->update([
                'last_reviews_sync_finished_at' => $syncFinishedAt,
            ]);
            // cutoffは更新しない（例外時は進捗を進めない）
            return ['synced_count' => 0, 'skipped_count' => 0, 'inserted_count' => 0, 'updated_count' => 0];
        }
        
        $apiElapsedMs = (microtime(true) - $apiStartTime) * 1000;
        $fetchedCount = count($allReviews);
        
        Log::info('ReviewSyncService: APIから取得したレビュー数', [
            'shop_id' => $shop->id,
            'fetched_count' => $fetchedCount,
            'api_elapsed_ms' => round($apiElapsedMs, 2),
            'pages' => $pageCount,
            'stopped_by_cutoff' => $stoppedByCutoff,
        ]);

        // 既存レビューを shop_id で一括取得し、gbp_review_id をキーにMap化（正規化キー）
        $existingReviews = Review::where('shop_id', $shop->id)
            ->whereNotNull('gbp_review_id')
            ->get()
            ->keyBy(function ($review) {
                // 正規化キーでMap化（空白/制御文字/見えない文字を除去）
                return $this->normalizeReviewId((string)$review->gbp_review_id);
            });

        Log::info('ReviewSyncService: 既存レビュー数', [
            'shop_id' => $shop->id,
            'existing_count' => $existingReviews->count(),
        ]);

        $rows = [];
        $skippedCount = 0;
        $skippedReasons = [
            'no_update_time' => 0,
            'no_reply_key' => 0,
            'update_time_lte_existing' => 0,
            'no_changes' => 0,
            'other' => 0,
        ];
        $maxUpdateTime = null; // APIから取得できた全reviewの最大updateTime（全スキップでも算出）
        $updateCandidatesCount = 0; // updateTime条件でUPDATE対象になった数
        $replyDiffUpdateCount = 0; // 返信差分でUPDATE対象になった数
        $debugCounter = 0; // existingReview null 問題のデバッグ用カウンター
        $lookupDebugCounter = 0; // 既存レビュー lookup 調査用カウンター（最初の5件のみ）

        // STEP3: DBへ upsert（各reviewを処理）
        foreach ($allReviews as $review) {
            try {
                // reviewId = basename(review['name']) を必須化（無ければskip+warn log）
                if (!isset($review['name'])) {
                    Log::warning('ReviewSyncService: review[name]が存在しません', [
                        'shop_id' => $shop->id,
                        'review_data' => $review,
                    ]);
                    $skippedCount++;
                    continue;
                }

                // API側 reviewId を取得（raw版）
                $reviewIdRaw = basename((string)$review['name']);
                // 正規化（空白/制御文字/見えない文字を除去）
                $reviewId = $this->normalizeReviewId($reviewIdRaw);
                
                if (empty($reviewId)) {
                    Log::warning('ReviewSyncService: reviewIdが空です（正規化後）', [
                        'shop_id' => $shop->id,
                        'review_name' => $review['name'] ?? null,
                        'review_id_raw' => $reviewIdRaw,
                    ]);
                    $skippedCount++;
                    continue;
                }

                // 方針A: 更新判定時刻は updateTime を優先
                // API側: updateTime または createTime を取得（updateTime優先）
                $apiUpdateTimeRaw = data_get($review, 'updateTime');
                $apiCreateTimeRaw = data_get($review, 'createTime');
                $apiRaw = $apiUpdateTimeRaw ?? $apiCreateTimeRaw ?? null;
                
                // api_update_time が取れない場合は「更新判定ではスキップ」だが、返信差分判定は別で走る
                $apiUpdateTime = null; // 比較用（秒精度のUTC）
                $gbpUpdateTime = null; // DB保存用（UTC）
                $gbpCreateTime = null; // DB保存用（UTC）
                if ($apiRaw) {
                    try {
                        // API側: UTCとしてparse（秒精度で比較）
                        $apiUpdateTime = CarbonImmutable::parse($apiRaw, 'UTC');
                        // DB保存用: UTCとしてparse（同じ値を使用）
                        $gbpUpdateTime = $apiUpdateTime;
                        // STEP4: maxSeen更新
                        if ($maxSeen === null || $gbpUpdateTime->greaterThan($maxSeen)) {
                            $maxSeen = $gbpUpdateTime;
                        }
                        // 後方互換用（既存コードとの互換性）
                        if ($maxUpdateTime === null || $apiUpdateTime->greaterThan($maxUpdateTime)) {
                            $maxUpdateTime = $apiUpdateTime;
                        }
                    } catch (\Exception $e) {
                        Log::warning('REVIEWS_SKIP_NO_TIME', [
                            'shop_id' => $shop->id,
                            'gbp_review_id' => $reviewId,
                            'reason' => 'API時刻のパースに失敗',
                            'api_raw' => $apiRaw,
                            'error' => $e->getMessage(),
                        ]);
                        // パース失敗時は更新判定ではスキップだが、返信差分判定は別で走る
                        $skippedReasons['no_update_time']++;
                    }
                } else {
                    Log::warning('REVIEWS_SKIP_NO_TIME', [
                        'shop_id' => $shop->id,
                        'gbp_review_id' => $reviewId,
                        'reason' => 'updateTimeとcreateTimeが両方存在しません',
                    ]);
                    $skippedReasons['no_update_time']++;
                    // updateTimeが無い場合は更新判定ではスキップだが、返信差分判定は別で走る
                }

                // createTime も取得（DB保存用、必須）
                $createTimeRaw = $review['createTime'] ?? null;
                if (!$createTimeRaw) {
                    Log::warning('REVIEWS_SKIP_NO_TIME', [
                        'shop_id' => $shop->id,
                        'gbp_review_id' => $reviewId,
                        'reason' => 'createTimeが存在しません（DB保存に必須）',
                    ]);
                    $skippedReasons['no_update_time']++;
                    $skippedCount++;
                    continue; // createTimeが無い場合はDB保存できないのでスキップ
                }

                $createTime = null;
                try {
                    // createTime: UTCとしてparse（DB保存用）
                    $gbpCreateTime = CarbonImmutable::parse($createTimeRaw, 'UTC');
                    $createTime = $gbpCreateTime;
                } catch (\Exception $e) {
                    Log::warning('REVIEWS_SKIP_NO_TIME', [
                        'shop_id' => $shop->id,
                        'gbp_review_id' => $reviewId,
                        'reason' => 'createTimeのパースに失敗',
                        'createTime_raw' => $createTimeRaw,
                        'error' => $e->getMessage(),
                    ]);
                    $skippedReasons['no_update_time']++;
                    $skippedCount++;
                    continue; // createTimeがパースできない場合はDB保存できないのでスキップ
                }

                // 既存の update_time（NULLなら create_time）と比較し、API updateTime <= 既存ならスキップ
                $existingReview = $existingReviews->get($reviewId);
                
                // 1) 既存レビュー lookup が当たっているかを "ログで" 証明（最初の5件のみ）
                if ($lookupDebugCounter < 5) {
                    $lookupDebugCounter++;
                    
                    // DBの gbp_review_id の実値（先頭5件）を取得
                    $dbReviewIds = Review::where('shop_id', $shop->id)
                        ->whereNotNull('gbp_review_id')
                        ->take(5)
                        ->pluck('gbp_review_id')
                        ->toArray();
                    
                    Log::info('REVIEWS_LOOKUP_DEBUG', [
                        'shop_id' => $shop->id,
                        'review_index' => $lookupDebugCounter,
                        'api_review_id_raw' => $reviewIdRaw,
                        'api_review_id_normalized' => $reviewId,
                        'existing_reviews_keys_sample' => $existingReviews->keys()->take(5)->toArray(),
                        'existing_review_hit' => $existingReview !== null ? 'HIT' : 'MISS',
                        'db_gbp_review_ids_sample' => $dbReviewIds,
                        'db_gbp_review_ids_normalized_sample' => array_map(function($id) {
                            return $this->normalizeReviewId((string)$id);
                        }, $dbReviewIds),
                    ]);
                }
                
                // "existingReview null 問題"を確定させる一発ログ（最初の5件だけ）
                if (!$existingReview && $debugCounter < 5) {
                    $debugCounter++;
                    
                    // 正規化前のキーでもヒットするかの確認用に、raw版のmapも作って当てる
                    $existingReviewsRaw = Review::where('shop_id', $shop->id)
                        ->whereNotNull('gbp_review_id')
                        ->get()
                        ->keyBy('gbp_review_id');
                    $existingHitRawKey = $existingReviewsRaw->get($reviewIdRaw) !== null;
                    
                    // DBを where shop_id + gbp_review_id = normalized で exists
                    $dbExistsByNormalized = Review::where('shop_id', $shop->id)
                        ->where('gbp_review_id', $reviewId)
                        ->exists();
                    
                    // DBを where shop_id + gbp_review_id = raw で exists
                    $dbExistsByRaw = Review::where('shop_id', $shop->id)
                        ->where('gbp_review_id', $reviewIdRaw)
                        ->exists();
                    
                    Log::info('REVIEWS_ID_MISMATCH', [
                        'shop_id' => $shop->id,
                        'review_name' => $review['name'] ?? null,
                        'review_id_raw' => $reviewIdRaw,
                        'review_id_normalized' => $reviewId,
                        'existing_keys_sample' => $existingReviews->keys()->take(5)->toArray(),
                        'existing_keys_sample_lengths' => $existingReviews->keys()->take(5)->map(fn($k) => strlen($k))->toArray(),
                        'existing_hit_raw_key' => $existingHitRawKey,
                        'db_exists_by_normalized' => $dbExistsByNormalized,
                        'db_exists_by_raw' => $dbExistsByRaw,
                    ]);
                }
                
                $shouldSkip = false;
                $existingUpdateTime = null; // 比較用（分単位に丸めたUTC）

                if ($existingReview) {
                    // 既存側: DBの gbp_update_time（優先）を取得してUTCとしてparse（秒精度で比較）
                    $existingUpdateTime = $existingReview->gbp_update_time
                        ? CarbonImmutable::parse($existingReview->gbp_update_time, 'UTC')
                        : null;

                    // 判定: apiUpdateTime > existingUpdateTime なら更新、それ以外は更新不要
                    // 比較はタイムスタンプ比較（timezoneを一切使わない）
                    // 注意: apiUpdateTime が null の場合は更新判定ではスキップ（返信差分判定は別で走る）
                    if ($existingUpdateTime === null || ($apiUpdateTime && $apiUpdateTime->timestamp > $existingUpdateTime->timestamp)) {
                        $shouldSkip = false; // 更新が必要
                    } elseif (!$apiUpdateTime) {
                        // apiUpdateTime が null の場合は更新判定ではスキップ（返信差分判定は別で走る）
                        $shouldSkip = true;
                    } else {
                        $shouldSkip = true; // apiUpdateTime <= existingUpdateTime の場合はスキップ
                    }
                }
                
                // 確認ログ（デバッグ用）
                if ($lookupDebugCounter <= 5) {
                    Log::info('TZ_COMPARE_DEBUG', [
                        'shop_id' => $shop->id,
                        'review_index' => $lookupDebugCounter,
                        'gbp_review_id' => $reviewId,
                        'api_iso' => $apiUpdateTime ? $apiUpdateTime->toIso8601String() : null,
                        'db_iso' => $existingUpdateTime ? $existingUpdateTime->toIso8601String() : null,
                    ]);
                }
                
                // 2) 時刻比較がズレて "常に更新" になってないかを "ログで" 証明（最初の5件のみ）
                if ($lookupDebugCounter <= 5) {
                    Log::info('REVIEWS_TIME_COMPARISON_DEBUG', [
                        'shop_id' => $shop->id,
                        'review_index' => $lookupDebugCounter,
                        'gbp_review_id' => $reviewId,
                        'api_raw' => $apiRaw,
                        'api_parsed_utc' => $gbpUpdateTime ? $gbpUpdateTime->toIso8601String() : null,
                        'api_rounded' => $apiUpdateTime ? $apiUpdateTime->format('Y-m-d H:i:s') : null,
                        'api_timestamp' => $apiUpdateTime ? $apiUpdateTime->timestamp : null,
                        'existing_gbp_update_time_raw' => $existingReview && $existingReview->gbp_update_time ? (is_object($existingReview->gbp_update_time) ? get_class($existingReview->gbp_update_time) : gettype($existingReview->gbp_update_time)) : null,
                        'existing_gbp_update_time_value' => $existingReview && $existingReview->gbp_update_time ? (string)$existingReview->gbp_update_time : null,
                        'existing_parsed_utc' => $existingUpdateTime ? $existingUpdateTime->toIso8601String() : null,
                        'existing_rounded' => $existingUpdateTime ? $existingUpdateTime->format('Y-m-d H:i:s') : null,
                        'existing_timestamp' => $existingUpdateTime ? $existingUpdateTime->timestamp : null,
                        'update_time_changed' => $updateTimeChanged ?? false,
                        'should_skip' => $shouldSkip,
                        'reply_changed' => $replyChanged ?? false,
                        'needs_update' => $needsUpdate ?? false,
                        'will_add_to_rows' => ($needsUpdate ?? false),
                    ]);
                }

                // 方針B: 返信差分は updateTime比較と独立
                // 返信差分判定を updateTime とは独立させて「返信が変わっていれば updateTimeが同じでも UPDATE」する
                $hasReplyKey = array_key_exists('reviewReply', $review); // isset()禁止、array_key_exists必須
                $replyChanged = false;
                $apiReplyText = null;
                $apiReplyUpdateTime = null;
                
                if ($hasReplyKey) {
                    // 返信フィールドが存在する時だけ比較
                    $apiReplyText = $review['reviewReply']['comment'] ?? null; // commentキー不在もあり得る
                    $apiReplyUpdateTimeRaw = $review['reviewReply']['updateTime'] ?? null;
                    $apiReplyUpdateTime = $apiReplyUpdateTimeRaw
                        ? CarbonImmutable::parse($apiReplyUpdateTimeRaw, 'UTC')
                        : null;
                    
                    if ($existingReview) {
                        // reply 差分判定の原因調査用ログ
                        Log::info('REPLY_DIFF_DEBUG', [
                            'review_id' => $reviewId,
                            'hasReplyKey' => $hasReplyKey ?? null,
                            'api_reply_len' => strlen((string)($apiReplyText ?? '')),
                            'db_reply_len' => strlen((string)($existingReview->reply_text ?? '')),
                            'api_reply_hash' => md5((string)($apiReplyText ?? '')),
                            'db_reply_hash' => md5((string)($existingReview->reply_text ?? '')),
                            'api_reply_update_time' => $apiReplyUpdateTime ?? null,
                            'db_reply_update_time' => $existingReview->gbp_reply_update_time ?? null,
                        ]);
                        
                        $existingReplyText = $existingReview->reply_text;
                        // 返信差分判定: api_reply_text !== db_reply_text なら UPDATE 対象（updateTimeが同じでも）
                        $normalizeString = function($value) {
                            if ($value === null || $value === '') {
                                return null;
                            }
                            return trim($value);
                        };
                        $normalizedApiReplyText = $normalizeString($apiReplyText);
                        $normalizedExistingReplyText = $normalizeString($existingReplyText);
                        
                        if ($normalizedApiReplyText !== $normalizedExistingReplyText) {
                            $replyChanged = true;
                        }
                    } else {
                        // 新規レコードで返信がある場合は変更あり
                        if ($apiReplyText !== null) {
                            $replyChanged = true;
                        }
                    }
                }
                // if !hasReplyKey: 返信差分判定は "しない"（不明扱い）。DB側をnullに上書きもしない。

                // 更新判定: apiUpdateTime が存在し、既存より新しい場合（タイムスタンプ比較）
                $updateTimeChanged = false;
                if ($existingUpdateTime === null || ($apiUpdateTime && $apiUpdateTime->timestamp > $existingUpdateTime->timestamp)) {
                    $updateTimeChanged = true;
                    $updateCandidatesCount++;
                }

                // 更新が必要な条件: updateTimeが新しい OR 返信が変わった OR 新規レコード
                $needsUpdate = false;
                if (!$existingReview) {
                    // 新規レコード
                    $needsUpdate = true;
                } elseif ($updateTimeChanged || $replyChanged) {
                    // 既存レコードで updateTimeが新しい OR 返信が変わった
                    $needsUpdate = true;
                    if ($replyChanged) {
                        $replyDiffUpdateCount++;
                    }
                }

                if ($shouldSkip && !$replyChanged) {
                    // updateTime判定でスキップ かつ 返信差分なし
                    $skippedCount++;
                    $skippedReasons['update_time_lte_existing']++;
                    // 検証用ログ（最初の3件のみ）
                    if ($skippedCount <= 3) {
                        // ログは比較に使った値から作る（ズレ防止）
                        $compareResult = $existingUpdateTime ? ($apiUpdateTime && $apiUpdateTime->timestamp <= $existingUpdateTime->timestamp) : false;
                        
                        Log::info('REVIEWS_DIFF_DECISION', [
                            'shop_id' => $shop->id,
                            'gbp_review_id' => $reviewId,
                            'api_raw' => $apiRaw,
                            'existing_raw' => $existingReview && $existingReview->gbp_update_time ? (string)$existingReview->gbp_update_time : null,
                            'api_update_time' => $apiUpdateTime ? $apiUpdateTime->format('Y-m-d H:i:s') : null, // 比較に使った値から（分単位に丸めたUTC）
                            'existing_update_time' => $existingUpdateTime ? $existingUpdateTime->format('Y-m-d H:i:s') : null, // 比較に使った値から（分単位に丸めたUTC）
                            'api_time_iso' => $apiUpdateTime ? $apiUpdateTime->toIso8601String() : null,
                            'existing_time_iso' => $existingUpdateTime ? $existingUpdateTime->toIso8601String() : null,
                            'api_ts' => $apiUpdateTime ? $apiUpdateTime->timestamp : null,
                            'existing_ts' => $existingUpdateTime ? $existingUpdateTime->timestamp : null,
                            'api_min' => $apiUpdateTime ? $apiUpdateTime->format('Y-m-d H:i:00') : null, // 分単位の値（ログ用）
                            'existing_min' => $existingUpdateTime ? $existingUpdateTime->format('Y-m-d H:i:00') : null, // 分単位の値（ログ用）
                            'api_tz' => $apiUpdateTime ? $apiUpdateTime->timezoneName : null,
                            'existing_tz' => $existingUpdateTime ? $existingUpdateTime->timezoneName : null,
                            'api_class' => $apiUpdateTime ? get_class($apiUpdateTime) : null,
                            'existing_class' => $existingUpdateTime ? get_class($existingUpdateTime) : null,
                            'shouldSkip' => $shouldSkip, // 最終値
                            'hasChanges' => false, // SKIP時は常にfalse
                            'changeReasons' => [], // SKIP時は空
                            'compare_api_lte_existing' => $compareResult, // 実際に判定に使った結果をそのまま出す
                            'api_replied_at' => null, // SKIP時は未取得
                            'existing_replied_at' => $existingReview && $existingReview->replied_at 
                                ? ($existingReview->replied_at instanceof \Carbon\Carbon 
                                    ? $existingReview->replied_at->format('Y-m-d H:i:s')
                                    : (string)$existingReview->replied_at)
                                : null,
                            'has_reply_key' => $hasReplyKey,
                            'api_reply_text_hash' => null, // SKIP時は未取得
                            'existing_reply_text_hash' => $existingReview && $existingReview->reply_text 
                                ? sha1($existingReview->reply_text) 
                                : null,
                            'reply_changed' => false, // SKIP時は未取得
                            'api_comment_hash' => null,
                            'existing_comment_hash' => $existingReview && $existingReview->comment 
                                ? sha1($existingReview->comment) 
                                : null,
                            'decision' => 'SKIP',
                            'reason' => 'update_time_lte_existing',
                        ]);
                    }
                    continue;
                }

                // 変更があるかチェック（既存レコードがある場合）
                // 注意: 返信差分判定は既に上で実施済み（$replyChanged）
                $hasChanges = false;
                $changeReasons = [];
                
                if ($existingReview) {
                    // 既存レコードと比較（空文字/NULLの正規化）
                    $normalizeString = function($value) {
                        if ($value === null || $value === '') {
                            return null;
                        }
                        return trim($value);
                    };

                    $newAuthorName = $normalizeString($review['reviewer']['displayName'] ?? '不明');
                    $newRating = $this->convertStarRating($review['starRating'] ?? null);
                    $newComment = $normalizeString($review['comment'] ?? null);

                    $existingAuthorName = $normalizeString($existingReview->author_name);
                    $existingRating = $existingReview->rating;
                    $existingComment = $normalizeString($existingReview->comment);

                    // 変更があるかチェック（理由を記録）
                    if ($newAuthorName !== $existingAuthorName) {
                        $hasChanges = true;
                        $changeReasons[] = 'author_name_diff';
                    }
                    if ($newRating !== $existingRating) {
                        $hasChanges = true;
                        $changeReasons[] = 'rating_diff';
                    }
                    if ($newComment !== $existingComment) {
                        $hasChanges = true;
                        $changeReasons[] = 'comment_diff';
                    }
                    
                    // 返信差分判定（updateTimeと独立）
                    if ($replyChanged) {
                        $hasChanges = true;
                        $changeReasons[] = 'reply_text_diff';
                    }
                    
                    // 判定: api_min > existing_min のときのみ UPSERT（分単位に丸めた値で比較）
                    if ($updateTimeChanged) {
                        $hasChanges = true;
                        $changeReasons[] = 'update_time_gt';
                    }
                    
                    // 更新が必要な条件を再確認
                    $hasChanges = $needsUpdate;
                    
                    // 検証用ログ（最初の3件のみ）
                    if (count($rows) < 3) {
                        // ログは比較に使った値から作る（ズレ防止）
                        $compareResult = $existingUpdateTime ? ($apiUpdateTime && $apiUpdateTime->timestamp <= $existingUpdateTime->timestamp) : false;
                        
                        Log::info('REVIEWS_DIFF_DECISION', [
                            'shop_id' => $shop->id,
                            'gbp_review_id' => $reviewId,
                            'api_raw' => $apiRaw,
                            'existing_raw' => $existingReview && $existingReview->gbp_update_time ? (string)$existingReview->gbp_update_time : null,
                            'api_update_time' => $apiUpdateTime ? $apiUpdateTime->format('Y-m-d H:i:s') : null, // 比較に使った値から（分単位に丸めたUTC）
                            'existing_update_time' => $existingUpdateTime ? $existingUpdateTime->format('Y-m-d H:i:s') : null, // 比較に使った値から（分単位に丸めたUTC）
                            'api_time_iso' => $apiUpdateTime ? $apiUpdateTime->toIso8601String() : null,
                            'existing_time_iso' => $existingUpdateTime ? $existingUpdateTime->toIso8601String() : null,
                            'api_ts' => $apiUpdateTime ? $apiUpdateTime->timestamp : null,
                            'existing_ts' => $existingUpdateTime ? $existingUpdateTime->timestamp : null,
                            'api_min' => $apiUpdateTime ? $apiUpdateTime->format('Y-m-d H:i:00') : null, // 分単位の値（ログ用）
                            'existing_min' => $existingUpdateTime ? $existingUpdateTime->format('Y-m-d H:i:00') : null, // 分単位の値（ログ用）
                            'api_tz' => $apiUpdateTime ? $apiUpdateTime->timezoneName : null,
                            'existing_tz' => $existingUpdateTime ? $existingUpdateTime->timezoneName : null,
                            'api_class' => $apiUpdateTime ? get_class($apiUpdateTime) : null,
                            'existing_class' => $existingUpdateTime ? get_class($existingUpdateTime) : null,
                            'shouldSkip' => $shouldSkip, // 最終値
                            'hasChanges' => $hasChanges, // 最終値
                            'changeReasons' => $changeReasons, // 変更理由の配列
                            'compare_api_lte_existing' => $compareResult, // 実際に判定に使った結果をそのまま出す
                            'api_replied_at' => $apiReplyUpdateTime ? $apiReplyUpdateTime->format('Y-m-d H:i:s') : null,
                            'existing_replied_at' => $existingReview && $existingReview->replied_at 
                                ? ($existingReview->replied_at instanceof \Carbon\Carbon 
                                    ? $existingReview->replied_at->format('Y-m-d H:i:s')
                                    : (string)$existingReview->replied_at)
                                : null,
                            'has_reply_key' => $hasReplyKey,
                            'api_reply_text_hash' => $hasReplyKey && $apiReplyText ? sha1($apiReplyText) : null, // キー不在の場合は未計算
                            'existing_reply_text_hash' => $existingReview && $existingReview->reply_text ? sha1($existingReview->reply_text) : null,
                            'reply_changed' => $replyChanged,
                            'api_comment_hash' => $newComment ? sha1($newComment) : null,
                            'existing_comment_hash' => $existingComment ? sha1($existingComment) : null,
                            'decision' => $hasChanges ? 'UPSERT' : 'SKIP',
                            'reason' => $hasChanges ? implode(',', $changeReasons) : 'no_changes',
                        ]);
                    }
                } else {
                    // 新規レコード
                    $hasChanges = true;
                    
                    // 検証用ログ（最初の3件のみ）
                    if (count($rows) < 3) {
                        $newRepliedAtRaw = data_get($review, 'reviewReply.updateTime');
                        $newRepliedAt = $newRepliedAtRaw 
                            ? CarbonImmutable::parse($newRepliedAtRaw, 'UTC')->utc()->format('Y-m-d H:i:s')
                            : null;
                        
                        Log::info('REVIEWS_DIFF_DECISION', [
                            'shop_id' => $shop->id,
                            'gbp_review_id' => $reviewId,
                            'api_raw' => $apiRaw,
                            'existing_raw' => null,
                            'api_update_time' => $apiUpdateTime ? $apiUpdateTime->format('Y-m-d H:i:s') : null, // 比較に使った値から（分単位に丸めたUTC）
                            'existing_update_time' => null,
                            'api_time_iso' => $apiUpdateTime ? $apiUpdateTime->toIso8601String() : null,
                            'existing_time_iso' => null,
                            'api_ts' => $apiUpdateTime ? $apiUpdateTime->timestamp : null,
                            'existing_ts' => null,
                            'api_min' => $apiUpdateTime ? $apiUpdateTime->format('Y-m-d H:i:00') : null, // 分単位の値（ログ用）
                            'existing_min' => null, // 新規レコードなのでnull
                            'api_tz' => $apiUpdateTime ? $apiUpdateTime->timezoneName : null,
                            'existing_tz' => null,
                            'api_class' => $apiUpdateTime ? get_class($apiUpdateTime) : null,
                            'existing_class' => null,
                            'shouldSkip' => false, // 新規レコードは常にfalse
                            'hasChanges' => true, // 新規レコードは常にtrue
                            'changeReasons' => ['new_record'], // 新規レコード
                            'compare_api_lte_existing' => false,
                            'has_reply_key' => $hasReplyKey,
                            'api_replied_at' => $apiReplyUpdateTime ? $apiReplyUpdateTime->format('Y-m-d H:i:s') : null,
                            'existing_replied_at' => null,
                            'api_reply_text_hash' => $hasReplyKey && $apiReplyText ? sha1($apiReplyText) : null, // キー不在の場合は未計算
                            'reply_changed' => $hasReplyKey && $apiReplyText !== null, // 新規レコードで返信がある場合
                            'existing_reply_text_hash' => null,
                            'api_comment_hash' => isset($review['comment']) ? sha1($review['comment']) : null,
                            'existing_comment_hash' => null,
                            'decision' => 'UPSERT',
                            'reason' => 'new_record',
                        ]);
                    }
                }

                // 最短で原因を炙り出す一発ログ（最初の5件だけ）
                if (count($rows) + $skippedCount < 5) {
                    Log::info('REVIEWS_DIFF_ROOT_CAUSE', [
                        'shop_id' => $shop->id,
                        'gbp_review_id' => $reviewId,
                        'existing_hit' => $existingReview !== null, // 既存レビューがヒットしたか
                        'existing_time_is_null' => $existingUpdateTime === null, // existingUpdateTimeがnullか
                        'shouldSkip' => $shouldSkip, // 最終値
                        'hasChanges' => $hasChanges, // 最終値（判定後）
                        'rows_appended' => $hasChanges, // $rowsに追加されるか
                        'changeReasons' => $changeReasons, // 変更理由の配列
                        'api_ts' => $apiUpdateTime ? $apiUpdateTime->timestamp : null,
                        'existing_ts' => $existingUpdateTime ? $existingUpdateTime->timestamp : null,
                        'api_time_iso' => $apiUpdateTime ? $apiUpdateTime->toIso8601String() : null,
                        'existing_time_iso' => $existingUpdateTime ? $existingUpdateTime->toIso8601String() : null,
                        'review_id_from_api' => $reviewId, // APIから取得したreviewId
                        'existing_review_ids_sample' => $existingReviews->keys()->take(3)->toArray(), // 既存レビューのキーサンプル（最初の3件）
                    ]);
                }

                // 変更がある場合のみ $rows に追加（needsUpdate判定を使用）
                if ($needsUpdate) {
                    // 方針C: "フィールド不在" を "null" に変換しない
                    // hasReplyKey==false のときは、DB側をnullに上書きしない
                    $replyTextToSave = null;
                    $repliedAtToSave = null;
                    
                    if ($hasReplyKey) {
                        // 返信フィールドが存在する時だけ保存
                        $replyTextToSave = $apiReplyText; // 既に取得済み
                        $repliedAtToSave = $apiReplyUpdateTime ? $apiReplyUpdateTime->format('Y-m-d H:i:s') : null;
                    } elseif ($existingReview) {
                        // キー不在 かつ 既存レコードがある場合: DB側をnullに上書きしない（既存値を保持）
                        $replyTextToSave = $existingReview->reply_text;
                        $repliedAtToSave = $existingReview->replied_at 
                            ? ($existingReview->replied_at instanceof \Carbon\Carbon 
                                ? $existingReview->replied_at->format('Y-m-d H:i:s')
                                : (string)$existingReview->replied_at)
                            : null;
                    }
                    // 新規レコード かつ キー不在の場合: reply_text と replied_at は null のまま（新規なので問題なし）

                    // gbp_reply_update_time を取得
                    $gbpReplyUpdateTime = null;
                    if ($hasReplyKey && $apiReplyUpdateTime) {
                        $gbpReplyUpdateTime = $apiReplyUpdateTime;
                    }
                    
                    $rows[] = [
                        'shop_id' => $shop->id,
                        'gbp_review_id' => $reviewId,
                        'snapshot_id' => $snapshotId,
                        'author_name' => $review['reviewer']['displayName'] ?? '不明',
                        'rating' => $this->convertStarRating($review['starRating'] ?? null),
                        'comment' => $review['comment'] ?? null,
                        'create_time' => $gbpCreateTime->format('Y-m-d H:i:s'), // UTCとして保存
                        'update_time' => $apiUpdateTime ? $apiUpdateTime->format('Y-m-d H:i:s') : $gbpCreateTime->format('Y-m-d H:i:s'), // 分単位に丸めたUTCとして保存
                        'gbp_update_time' => $gbpUpdateTime ? $gbpUpdateTime->format('Y-m-d H:i:s') : null, // Google側のupdateTime（UTCとして保存、丸めない）
                        'gbp_create_time' => $gbpCreateTime ? $gbpCreateTime->format('Y-m-d H:i:s') : null, // Google側のcreateTime（UTCとして保存）
                        'reply_text' => $replyTextToSave,
                        'replied_at' => $repliedAtToSave,
                        'gbp_reply_update_time' => $gbpReplyUpdateTime ? $gbpReplyUpdateTime->format('Y-m-d H:i:s') : null, // Google側のreply.updateTime（UTCとして保存）
                        'has_reply' => $hasReplyKey && $apiReplyText !== null, // 返信の有無
                    ];
                } else {
                    // 更新不要（updateTimeが同じ かつ 返信差分なし かつ その他の差分なし）
                    $skippedCount++;
                    $skippedReasons['no_changes']++;
                }

            } catch (\Throwable $e) {
                Log::error('ReviewSyncService: レビュー処理エラー', [
                    'shop_id' => $shop->id,
                    'operator_id' => $operatorId,
                    'review_data' => $review,
                    'message' => $e->getMessage(),
                ]);
                $skippedCount++;
                $skippedReasons['other']++;
                // 個別レビューのエラーはスキップして続行（maxSeen更新は継続）
            }
        }

        // Review::upsert(rows, ['shop_id','gbp_review_id'], 更新カラム...) 実行
        $insertedCount = 0;
        $updatedCount = 0;

        if (!empty($rows)) {
            // upsert前の既存レコードを取得（差分判定用）
            $gbpReviewIds = array_column($rows, 'gbp_review_id');
            $existingReviews = Review::where('shop_id', $shop->id)
                ->whereIn('gbp_review_id', $gbpReviewIds)
                ->get()
                ->keyBy('gbp_review_id');

            // upsert実行
            Review::upsert(
                $rows,
                ['shop_id', 'gbp_review_id'], // ユニークキー
                [
                    // 'snapshot_id' は除外（毎回変わるため、更新時に変更すると全件更新になる）
                    // snapshot_id は新規insert時のみ設定される（upsertの仕様上、insert時は全カラムが設定される）
                    'author_name',
                    'rating',
                    'comment',
                    'create_time',
                    'reply_text',
                    'replied_at',
                    'update_time',   // ← これを必ず入れる
                    'gbp_update_time',   // Google側のupdateTime（差分同期のキー）
                    'gbp_create_time',   // Google側のcreateTime
                    'gbp_reply_update_time', // Google側のreply.updateTime
                    'has_reply', // 返信の有無
                    // 'updated_at' は除外（DBに任せる）
                ]
            );

            // upsert後に実際の変更を判定
            $upsertedReviews = Review::where('shop_id', $shop->id)
                ->whereIn('gbp_review_id', $gbpReviewIds)
                ->get()
                ->keyBy('gbp_review_id');

            foreach ($rows as $row) {
                $reviewId = $row['gbp_review_id'];
                $existingReview = $existingReviews->get($reviewId);
                $upsertedReview = $upsertedReviews->get($reviewId);

                if (!$existingReview) {
                    // 新規追加
                    $insertedCount++;
                } else {
                    // 既存レコード：実際に変更があったか判定
                    $changed = false;
                    $fieldsToCompare = [
                        'author_name', 'rating', 'comment', 'create_time',
                        'reply_text', 'replied_at', 'update_time',
                        'gbp_update_time', 'gbp_create_time', 'gbp_reply_update_time', 'has_reply'
                    ];
                    
                    foreach ($fieldsToCompare as $field) {
                        $existingValue = $existingReview->$field;
                        $newValue = $row[$field] ?? null;
                        
                        // 日時フィールドの比較
                        if (in_array($field, ['create_time', 'update_time', 'replied_at', 'gbp_update_time', 'gbp_create_time', 'gbp_reply_update_time'])) {
                            $existingStr = $existingValue ? (is_object($existingValue) ? $existingValue->format('Y-m-d H:i:s') : (string)$existingValue) : null;
                            $newStr = $newValue ? (is_object($newValue) ? $newValue->format('Y-m-d H:i:s') : (string)$newValue) : null;
                            if ($existingStr !== $newStr) {
                                $changed = true;
                                break;
                            }
                        } else {
                            // その他のフィールド
                            if ($existingValue != $newValue) {
                                $changed = true;
                                break;
                            }
                        }
                    }
                    
                    if ($changed) {
                        $updatedCount++;
                    }
                }
            }
        }

        // STEP6: 同期成功時のコミット（最後に必ず保存）
        // もし maxSeen != NULL なら：shop.last_reviews_synced_update_time = maxSeen
        // 例外が出た場合は shop.last_reviews_synced_update_time を更新しない（進捗を進めない）
        $syncFinishedAt = now();
        
        if ($maxSeen !== null) {
            $shop->update([
                'last_reviews_synced_at' => $maxSeen, // 後方互換用
                'last_reviews_synced_update_time' => $maxSeen->format('Y-m-d H:i:s'), // 差分同期のcutoff（UTCとして保存）
                'last_reviews_sync_finished_at' => $syncFinishedAt,
            ]);
            
            Log::info('ReviewSyncService: 同期成功、cutoff更新', [
                'shop_id' => $shop->id,
                'max_seen' => $maxSeen->toIso8601String(),
                'cutoff_updated' => true,
            ]);
        } else {
            // 1件も updateTime/createTime が無い場合は maxSeen は null でOK（ログで理由を出す）
            Log::warning('ReviewSyncService: maxSeen が null', [
                'shop_id' => $shop->id,
                'fetched_count' => $fetchedCount,
                'reason' => 'APIから取得したreviewにupdateTime/createTimeが存在しませんでした',
            ]);
            
            // cutoffは更新しないが、finished_atは記録
            $shop->update([
                'last_reviews_sync_finished_at' => $syncFinishedAt,
            ]);
        }

        $totalChanged = $insertedCount + $updatedCount;

        // 方針6: ログ改善（デバッグ用に必須）
        Log::info('REVIEW_SYNC_RESULT', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'operator_id' => $operatorId,
            'fetched' => $fetchedCount,
            'inserted' => $insertedCount,
            'updated' => $updatedCount,
            'total_changed' => $totalChanged,
            'skipped_count' => $skippedCount,
            'skipped_reasons' => $skippedReasons, // 理由別：no_update_time / no_reply_key など
            'has_delta_filter' => false, // 現状 false のまま（updateTime フィルタなし = 全件取得）
            'cutoff' => $cutoff ? $cutoff->toIso8601String() : null,
            'max_seen' => $maxSeen ? $maxSeen->toIso8601String() : null,
            'stopped_by_cutoff' => $stoppedByCutoff,
            'delta基準時刻' => $shop->last_reviews_synced_update_time ? $shop->last_reviews_synced_update_time->toIso8601String() : null,
            'api_max_update_time' => $maxUpdateTime ? $maxUpdateTime->toIso8601String() : null,
            'update_candidates_count' => $updateCandidatesCount, // updateTime条件でUPDATE対象になった数
            'reply_diff_update_count' => $replyDiffUpdateCount, // 返信差分でUPDATE対象になった数
            'rows_to_write_count' => count($rows),
            'timezone_fix_applied' => true, // UTC指定でパースしたことを示す
            'minute_rounding_removed' => true, // 分丸めを廃止したことを示す
        ]);

        Log::info('SYNC_WITH_SINCE_FILTER', [
            'shop_id' => $shop->id,
            'since_date' => $sinceDate,
            'type' => 'review',
        ]);

        return [
            'synced_count' => $totalChanged,
            'skipped_count' => $skippedCount,
            'inserted_count' => $insertedCount,
            'updated_count' => $updatedCount,
        ];
    }

    /**
     * 星評価を数値に変換
     */
    private function convertStarRating($starRating): ?int
    {
        if ($starRating === null) {
            return null;
        }

        if (is_string($starRating)) {
            $ratingMap = [
                'FIVE' => 5,
                'FOUR' => 4,
                'THREE' => 3,
                'TWO' => 2,
                'ONE' => 1,
            ];
            return $ratingMap[strtoupper($starRating)] ?? (int)$starRating;
        }

        return (int)$starRating;
    }
}

