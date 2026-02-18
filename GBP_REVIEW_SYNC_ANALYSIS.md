# GBPレビュー同期の差分同期ロジック分析結果

## 1. 同期ボタン押下のエンドポイント/コントローラ

### エンドポイント
- **通常**: `POST /reviews/sync` → `ReviewsController::sync()`
- **オペレーター**: `POST /operator/reviews/sync` → `ReviewsController::sync()`

### ルート定義
```php
// routes/web.php
Route::post('/reviews/sync', [ReviewsController::class, 'sync'])->name('reviews.sync');
Route::post('/operator/reviews/sync', [ReviewsController::class, 'sync'])->name('operator.reviews.sync');
```

### コントローラ
- **ファイル**: `app/Http/Controllers/ReviewsController.php`
- **メソッド**: `sync()` (320-446行目)
- **実際の同期処理**: `syncReviews()` (451行目以降)

## 2. レビュー同期のService/Job/Commandの該当関数

### 主要な同期処理
- **ファイル**: `app/Http/Controllers/ReportController.php`
- **メソッド**: `syncReviews()` (872-1140行目)
- **呼び出し元**: `ReviewsController::sync()`, `ReportController::sync()`, `ShopController::sync()`

### API呼び出し
- **ファイル**: `app/Services/GoogleBusinessProfileService.php`
- **メソッド**: `listReviews()` (453行目以降)

## 3. 差分同期の実装詳細

### A. 差分同期の基準となる "カーソル"

**答え**: `shops.last_reviews_synced_at` を使用

**コード箇所**:
```php
// ReportController.php:904
$lastSyncedAt = $shop->last_reviews_synced_at;
```

**判定ロジック**:
- `last_reviews_synced_at` が `null` → 初回フル同期
- `last_reviews_synced_at` が存在 → 差分同期（ただし、**APIレベルでは使用していない**）

### B. GBPのレビュー取得API呼び出しで、差分取得のために何を指定している？

**答え**: **APIレベルでは差分フィルタを指定していない**

**コード箇所**:
```php
// ReportController.php:919
$reviewsResponse = $googleService->listReviews($accessToken, $shop->gbp_account_id, $shop->gbp_location_id);
```

**GoogleBusinessProfileService::listReviews() の実装**:
```php
// GoogleBusinessProfileService.php:453以降
public function listReviews(string $accessToken, string $accountId, string $locationId): array
{
    // API呼び出し時に updateTime や createTime のフィルタパラメータは指定していない
    // 常に全件取得している
}
```

**差分判定はアプリ側で実施**:
```php
// ReportController.php:1010-1014
// 増分同期フィルタ: 既存レコードが存在し、かつ既存のupdate_time >= APIのupdateTime の場合はスキップ
if ($existingReview && $existingReview->update_time && $parsedUpdateTime->lte($existingReview->update_time)) {
    $skippedCount++;
    continue;
}
```

### C. "前回同期以降" の判定に使っている日時の優先順位は？

**答え**: `update_time` を優先、nullの場合は `create_time` を代用

**コード箇所**:
```php
// ReportController.php:982-1000
// updateTimeを取得（review.updateTime > review.createTime の優先順位）
$updateTimeRaw = data_get($review, 'updateTime');
$createTime = \Carbon\Carbon::parse($review['createTime']);

// update_timeには常に値を持つ（updateTimeが存在すればそれ、なければcreateTime）
$parsedUpdateTime = $updateTimeRaw
    ? \Carbon\Carbon::parse($updateTimeRaw)
    : $createTime;
```

**差分判定ロジック**:
```php
// ReportController.php:1011
if ($existingReview && $existingReview->update_time && $parsedUpdateTime->lte($existingReview->update_time)) {
    // スキップ
}
```

**注意点**:
- `update_time` が null の場合は差分判定が成立しない（スキップされない）
- つまり、`update_time` が null のレビューは毎回更新される可能性がある

### D. 「初回フル同期」判定条件は何？

**答え**: `last_reviews_synced_at` が `null` の時はフル同期

**コード箇所**:
```php
// ReportController.php:904-916
$lastSyncedAt = $shop->last_reviews_synced_at;

Log::info('REVIEW_SAFE_INCREMENTAL_SYNC_START', [
    'last_synced_at' => $lastSyncedAt ? $lastSyncedAt->toIso8601String() : null,
    'is_full_sync' => is_null($lastSyncedAt),  // ← ここで判定
]);
```

**ただし、実際の処理は同じ**:
- 初回でも2回目以降でも、APIは全件取得
- アプリ側で差分判定してスキップするだけ

### E. 同期が完了したタイミングで、どのテーブルのどのカラムに "最後の同期時刻" を保存している？

**答え**: `shops.last_reviews_synced_at` に `max(update_time)` を保存

**コード箇所**:
```php
// ReportController.php:1114-1125
if ($upsertCount > 0 && $maxUpdateTime !== null) {
    $shop->update([
        'last_reviews_synced_at' => $maxUpdateTime,  // ← ここで更新
    ]);
}
```

**maxUpdateTimeの計算**:
```php
// ReportController.php:1038-1041
// 最大updateTimeを更新
if ($maxUpdateTime === null || $parsedUpdateTime->gt($maxUpdateTime)) {
    $maxUpdateTime = $parsedUpdateTime;
}
```

**注意点**:
- `upsertCount > 0` かつ `maxUpdateTime !== null` の場合のみ更新
- 新規/更新が0件の場合は `last_reviews_synced_at` は更新されない

## 4. 現状の実装方式の結論

### 実装方式
**「取得後にDB比較で差分」方式**

### 詳細
1. **API呼び出し**: 常に全件取得（差分フィルタなし）
2. **差分判定**: アプリ側で既存レコードの `update_time` と比較
3. **スキップ条件**: `既存のupdate_time >= APIのupdateTime` の場合
4. **同期時刻更新**: `shops.last_reviews_synced_at` に `max(update_time)` を保存

### 問題点

#### 1. APIレベルで差分取得していない
- 毎回全件取得するため、レビュー数が多い店舗では時間がかかる
- `last_reviews_synced_at` を取得しているが、API呼び出しに使用していない

#### 2. update_timeがnullの場合の扱い
- `update_time` が null のレビューは毎回更新される可能性がある
- 差分判定が成立しない

#### 3. last_reviews_synced_atが更新されない条件
- `upsertCount > 0` かつ `maxUpdateTime !== null` の場合のみ更新
- 新規/更新が0件、または `maxUpdateTime` が null の場合は更新されない

## 5. 具体的なコード箇所

### 差分判定の実装箇所

```872:1140:app/Http/Controllers/ReportController.php
private function syncReviews(Shop $shop, string $accessToken, GoogleBusinessProfileService $googleService, int $snapshotId, ?Carbon $startDate = null, ?Carbon $endDate = null): int
{
    // 最終同期日時を取得（増分同期用）
    $lastSyncedAt = $shop->last_reviews_synced_at;
    
    // 全件取得（startDateフィルタは使用しない）
    $reviewsResponse = $googleService->listReviews($accessToken, $shop->gbp_account_id, $shop->gbp_location_id);
    
    // 差分判定: 既存レコードが存在し、かつ既存のupdate_time >= APIのupdateTime の場合はスキップ
    if ($existingReview && $existingReview->update_time && $parsedUpdateTime->lte($existingReview->update_time)) {
        $skippedCount++;
        continue;
    }
    
    // 最終同期日時を更新
    if ($upsertCount > 0 && $maxUpdateTime !== null) {
        $shop->update([
            'last_reviews_synced_at' => $maxUpdateTime,
        ]);
    }
}
```

### API呼び出し箇所（差分フィルタなし）

```453:534:app/Services/GoogleBusinessProfileService.php
public function listReviews(string $accessToken, string $accountId, string $locationId): array
{
    // 注意: 全件取得し、フィルタリングは呼び出し側（ReportController）で実施
    $url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationIdClean}/reviews";
    
    $response = Http::withToken($accessToken)
        ->get($url);
    
    // updateTime や createTime のフィルタパラメータは指定していない
}
```

## 6. 改善提案

### 提案1: APIレベルで差分取得を実装
GBP APIの `listReviews` に `updateTime` フィルタを追加（可能であれば）

**実装例**:
```php
// GoogleBusinessProfileService.php
public function listReviews(string $accessToken, string $accountId, string $locationId, ?Carbon $lastSyncedAt = null): array
{
    $url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationIdClean}/reviews";
    
    $params = [];
    if ($lastSyncedAt) {
        // APIがサポートしていれば、updateTimeフィルタを追加
        $params['updateTime'] = $lastSyncedAt->toRfc3339String();
    }
    
    $response = Http::withToken($accessToken)
        ->get($url, $params);
}
```

### 提案2: update_timeがnullの場合の処理改善
- `update_time` が null の場合は `create_time` で差分判定する
- または、`update_time` が null の場合は常に更新する（現状の動作を維持）

**実装例**:
```php
// ReportController.php:1010-1014 を修正
// update_timeがnullの場合はcreate_timeで判定
$existingTime = $existingReview->update_time ?? $existingReview->create_time;
$apiTime = $parsedUpdateTime;

if ($existingReview && $existingTime && $apiTime->lte($existingTime)) {
    $skippedCount++;
    continue;
}
```

### 提案3: last_reviews_synced_atの更新条件見直し
- `upsertCount = 0` でも、`maxUpdateTime` が存在する場合は更新する
- または、同期実行時刻を保存する別カラムを追加

**実装例**:
```php
// ReportController.php:1114-1125 を修正
// maxUpdateTimeが存在する場合は、upsertCountに関係なく更新
if ($maxUpdateTime !== null) {
    $shop->update([
        'last_reviews_synced_at' => $maxUpdateTime,
    ]);
}
```

## 7. 現状の問題点まとめ

### 問題1: APIレベルで差分取得していない
- **現状**: 毎回全件取得
- **影響**: レビュー数が多い店舗では時間がかかる
- **解決策**: APIがサポートしていれば、`updateTime` フィルタを追加

### 問題2: update_timeがnullの場合の扱い
- **現状**: `update_time` が null のレビューは毎回更新される可能性がある
- **影響**: 不要な更新処理が発生する可能性
- **解決策**: `create_time` で差分判定する、または明示的に処理方針を決める

### 問題3: last_reviews_synced_atが更新されない条件
- **現状**: `upsertCount > 0` かつ `maxUpdateTime !== null` の場合のみ更新
- **影響**: 新規/更新が0件の場合、`last_reviews_synced_at` が更新されない
- **解決策**: `maxUpdateTime` が存在する場合は、`upsertCount` に関係なく更新

