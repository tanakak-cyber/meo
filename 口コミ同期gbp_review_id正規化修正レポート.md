# 口コミ同期 gbp_review_id 正規化修正レポート

## 修正内容

### 目的
`rows_to_write_count` が毎回ほぼ全件になる原因を「existingReview が null になっているか」に絞って確定させる。

原因が `gbp_review_id` のキー不一致（空白/制御文字/見えない文字/形式違い）なら、正規化して `keyBy`/`get` の両方を同一ルールにする。

### 実装内容

#### 1. ReviewID 正規化関数を追加

**実装箇所**: `app/Services/ReviewSyncService.php:14-22`

```php
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
```

**正規化内容**:
- `trim()` で前後の空白を除去
- `preg_replace('/[\p{C}\s]+/u', '', $id)` で制御文字（`\p{C}`）と空白（`\s`）を除去
- ゼロ幅スペース等の見えない文字も除去

#### 2. 既存レビューの keyBy を「正規化キー」に変更

**修正前**: `app/Services/ReviewSyncService.php:82-85`
```php
$existingReviews = Review::where('shop_id', $shop->id)
    ->whereNotNull('gbp_review_id')
    ->get()
    ->keyBy('gbp_review_id');
```

**修正後**: `app/Services/ReviewSyncService.php:82-87`
```php
// 既存レビューを shop_id で一括取得し、gbp_review_id をキーにMap化（正規化キー）
$existingReviews = Review::where('shop_id', $shop->id)
    ->whereNotNull('gbp_review_id')
    ->get()
    ->keyBy(function ($review) {
        // 正規化キーでMap化（空白/制御文字/見えない文字を除去）
        return $this->normalizeReviewId((string)$review->gbp_review_id);
    });
```

#### 3. API側 reviewId も「同じ正規化」を適用

**修正前**: `app/Services/ReviewSyncService.php:108`
```php
$reviewId = trim(basename($review['name']));
```

**修正後**: `app/Services/ReviewSyncService.php:108-116`
```php
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
```

#### 4. "existingReview null 問題"を確定させる一発ログ（最初の5件だけ）

**実装箇所**: `app/Services/ReviewSyncService.php:177-220`

```php
// 既存の update_time（NULLなら create_time）と比較し、API updateTime <= 既存ならスキップ
$existingReview = $existingReviews->get($reviewId);

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
$existingTime = null;
```

**ログ項目**:
- `review_name`: APIの `review['name']`
- `review_id_raw`: 正規化前のreviewId
- `review_id_normalized`: 正規化後のreviewId
- `existing_keys_sample`: 既存レビューのキーサンプル（最初の5件）
- `existing_keys_sample_lengths`: 各キーの文字列長
- `existing_hit_raw_key`: 正規化前のキーでもヒットするか（raw版のmapで確認）
- `db_exists_by_normalized`: DBを `where shop_id + gbp_review_id = normalized` で exists
- `db_exists_by_raw`: DBを `where shop_id + gbp_review_id = raw` で exists

**判定方法**:
- `existing_hit_raw_key: true` かつ `db_exists_by_raw: true` → メモリ上のmapに居ないだけ（正規化の問題）
- `existing_hit_raw_key: false` かつ `db_exists_by_normalized: true` → DBのgbp_review_idが別形式（正規化で解決）
- `existing_hit_raw_key: false` かつ `db_exists_by_normalized: false` → DBに存在しない（新規レビュー）

#### 5. デバッグカウンターの追加

**実装箇所**: `app/Services/ReviewSyncService.php:92-95`

```php
$rows = [];
$skippedCount = 0;
$maxUpdateTime = null;
$debugCounter = 0; // existingReview null 問題のデバッグ用カウンター
```

## 検証手順

### 1. 同期を実行

同一店舗で同期を2回連続実行

### 2. ログを確認

```bash
# REVIEWS_ID_MISMATCH ログを確認
grep "REVIEWS_ID_MISMATCH" storage/logs/laravel.log | tail -20

# ReviewSyncService: 口コミ同期完了 ログを確認
grep "ReviewSyncService: 口コミ同期完了" storage/logs/laravel.log | tail -10
```

### 3. 期待値（2回目）

**正常な場合（修正後）**:
- `rows_to_write_count`: 0 〜 数件
- `skipped_count`: `fetched_count` に近い
- `updated_count`: 0 〜 数件
- `inserted_count`: 0 〜 数件（新規レビューのみ）

**REVIEWS_ID_MISMATCH ログ（2回目同期、最初の5件）**:
- `existing_hit_raw_key`: `true` または `false`（正規化前のキーでもヒットするか）
- `db_exists_by_normalized`: `true` または `false`（正規化後のキーでDBに存在するか）
- `db_exists_by_raw`: `true` または `false`（正規化前のキーでDBに存在するか）

**問題がある場合（修正前）**:
- `rows_to_write_count`: ほぼ全件
- `skipped_count`: 0 または極小
- `updated_count`: ほぼ全件

**REVIEWS_ID_MISMATCH ログ（2回目同期、最初の5件）**:
- `existing_hit_raw_key`: `false`（正規化前のキーでもヒットしない）
- `db_exists_by_normalized`: `true`（正規化後のキーでDBに存在する）
- `db_exists_by_raw`: `false`（正規化前のキーでDBに存在しない）

→ これは「キー不一致（空白/制御文字/見えない文字/形式違い）」が原因であることを示す

## 修正の効果

### 修正前

- `$existingReviews->get($reviewId)` が `null` を返す（キー不一致）
- 毎回 "新規扱い" で `$hasChanges = true` になり、`$rows` に入る
- `rows_to_write_count` がほぼ全件になる

### 修正後

- `$existingReviews->get($reviewId)` が正しくヒットする（正規化により）
- 既存レビューは `$shouldSkip = true` になり、`$rows` に入らない
- `rows_to_write_count` が 0 〜 数件になる（変更があったレビューのみ）

## 完了条件

✅ ReviewID 正規化関数を追加（`normalizeReviewId`）  
✅ 既存レビューの keyBy を「正規化キー」に変更  
✅ API側 reviewId も「同じ正規化」を適用  
✅ "existingReview null 問題"を確定させる一発ログ（`REVIEWS_ID_MISMATCH`）を追加  
✅ デバッグカウンターを追加  

**検証**: 上記の検証手順に従って、2回目同期で `rows_to_write_count` が 0 〜 数件になることを確認








