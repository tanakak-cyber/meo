# GBP reviews.list ページネーション対応修正レポート

## 修正内容

### 問題
- `reviews.list` のレスポンスが 50件で頭打ちになっている可能性がある（ページング未対応）
- その結果、DBの `existing_count` が `fetched_count` を上回る等の不整合が出る

### 修正方針
- `pageToken` / `nextPageToken` を使って全ページを取得し、`reviews` を配列結合して返す
- `pageSize` を指定（仕様上の上限に合わせる。例: 100 など）
- `nextPageToken` が返る限りループし、全件を集める
- 各リクエストで `query` に `pageToken` を付与する
- 返却は `["reviews" => 全件, "averageRating" => ..., "totalReviewCount" => ...]` など既存仕様を壊さない

### 実装内容

#### GoogleBusinessProfileService::listReviews() の修正

**修正箇所**: `app/Services/GoogleBusinessProfileService.php:453-560`

**主な変更点**:

1. **シグネチャ変更**
   ```php
   // 変更前
   public function listReviews(string $accessToken, string $accountId, string $locationId): array
   
   // 変更後
   public function listReviews(string $accessToken, string $accountId, string $locationId, ?int $shopId = null): array
   ```
   - `$shopId` パラメータを追加（ログ用、オプション）

2. **ページネーションループの実装**
   ```php
   $pageSize = 100; // 1ページあたりの最大件数（API仕様に合わせる）
   $maxPages = 50; // 無限ループ防止の最大ページ数
   $allReviews = [];
   $nextPageToken = null;
   
   do {
       $pageCount++;
       
       // 無限ループ防止
       if ($pageCount > $maxPages) {
           Log::warning('GBP_REVIEWS_LIST_PAGINATION_MAX_PAGES_REACHED', [...]);
           break;
       }
       
       // URLにクエリパラメータを追加
       $url = $baseUrl;
       $queryParams = ['pageSize' => $pageSize];
       if ($nextPageToken) {
           $queryParams['pageToken'] = $nextPageToken;
       }
       $url .= '?' . http_build_query($queryParams);
       
       $response = Http::withToken($accessToken)->get($url);
       
       if (!$response->successful()) {
           // エラー時は既に取得したレビューを返す
           break;
       }
       
       $data = $response->json();
       $reviewsThisPage = $data['reviews'] ?? [];
       $allReviews = array_merge($allReviews, $reviewsThisPage);
       
       // 最初のページでメタデータを取得
       if ($pageCount === 1) {
           $averageRating = $data['averageRating'] ?? null;
           $totalReviewCount = $data['totalReviewCount'] ?? null;
       }
       
       // nextPageToken を取得
       $nextPageToken = $data['nextPageToken'] ?? null;
       
       // ページネーションログ
       Log::info('GBP_REVIEWS_LIST_PAGINATION', [
           'shop_id' => $shopId,
           'page' => $pageCount,
           'page_size' => $pageSize,
           'fetched_this_page' => count($reviewsThisPage),
           'fetched_total' => count($allReviews),
           'has_next' => isset($data['nextPageToken']),
           'next_page_token' => $nextPageToken ? substr($nextPageToken, 0, 20) . '...' : null,
       ]);
       
   } while ($nextPageToken !== null);
   ```

3. **既存仕様を壊さない形式で返す**
   ```php
   return [
       'reviews' => $allReviews,
       'averageRating' => $averageRating,
       'totalReviewCount' => $totalReviewCount,
   ];
   ```

#### 呼び出し箇所の修正

**修正箇所**:
- `app/Services/ReviewSyncService.php:55` - `shop->id` を渡すように修正
- `app/Http/Controllers/ReportController.php:937` - `shop->id` を渡すように修正
- `app/Http/Controllers/ReviewsController.php:844` - `review->shop->id` を渡すように修正
- `app/Http/Controllers/ShopController.php:969` - `shop->id` を渡すように修正

**変更例**:
```php
// 変更前
$reviewsResponse = $googleService->listReviews($accessToken, $shop->gbp_account_id, $shop->gbp_location_id);

// 変更後
$reviewsResponse = $googleService->listReviews($accessToken, $shop->gbp_account_id, $shop->gbp_location_id, $shop->id);
```

### ログ追加

#### GBP_REVIEWS_LIST_PAGINATION
**条件**: 各ページ取得時

**出力内容**:
- `shop_id`: 店舗ID
- `page`: ページ番号（1から開始）
- `page_size`: 1ページあたりの件数（100）
- `fetched_this_page`: このページで取得したレビュー件数
- `fetched_total`: 累計取得レビュー件数
- `has_next`: 次のページがあるか（`nextPageToken` の有無）
- `next_page_token`: 次のページのトークン（最初の20文字のみ）

#### GBP_REVIEWS_LIST_PAGINATION_MAX_PAGES_REACHED
**条件**: 最大ページ数（50ページ）に達した場合

**出力内容**:
- `shop_id`: 店舗ID
- `account_id`: アカウントID
- `location_id`: ロケーションID
- `page_count`: 取得したページ数
- `max_pages`: 最大ページ数（50）
- `total_fetched`: 累計取得レビュー件数

### 注意事項

1. **無限ループ防止**: 最大50ページまで取得（`$maxPages = 50`）
2. **エラーハンドリング**: API失敗時は既に取得したレビューを返す（空配列の可能性あり）
3. **既存仕様の維持**: 返却形式は `['reviews' => [...], 'averageRating' => ..., 'totalReviewCount' => ...]` を維持

### 検証手順

1. **50件以上のレビューがある店舗で同期を実行**
   - Web UIから同期ボタンを押す

2. **ログでページネーションを確認**
   ```bash
   grep "GBP_REVIEWS_LIST_PAGINATION" storage/logs/laravel.log | tail -10
   # 確認項目:
   # - page が複数ある（2ページ以上）
   # - fetched_total が増えている
   # - has_next が false になるまで続いている
   ```

3. **取得件数を確認**
   ```bash
   grep "REVIEW_SYNC_API_REQUEST_END" storage/logs/laravel.log | tail -1
   # 確認項目:
   # - fetched_count が 50件以上（全件取得できている）
   # - pages が 2ページ以上
   ```

4. **DBの existing_count と fetched_count の整合性を確認**
   - `existing_count` が `fetched_count` を上回らないことを確認

## 期待される結果

### 修正前
- `fetched_count`: 50件（頭打ち）
- `pages`: 1
- `existing_count` > `fetched_count` の不整合が発生する可能性

### 修正後
- `fetched_count`: 全件（50件以上でも取得可能）
- `pages`: 2ページ以上（レビュー数に応じて）
- `existing_count` <= `fetched_count` の整合性が保たれる

## 完了条件

✅ `pageToken` / `nextPageToken` を使って全ページを取得  
✅ `pageSize` を指定（100）  
✅ `nextPageToken` が返る限りループ  
✅ 各リクエストで `query` に `pageToken` を付与  
✅ 返却は既存仕様を壊さない  
✅ ログ追加（`GBP_REVIEWS_LIST_PAGINATION`）  
✅ 無限ループ防止（最大50ページ）  
✅ API失敗時は既存のエラーハンドリングに合わせる  
✅ 呼び出し箇所を修正（`shopId` を渡す）  

**検証**: 上記の検証手順に従って、50件以上のレビューがある店舗で同期を実行し、全件取得できていることを確認








