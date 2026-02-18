# GBPレビュー同期の差分同期修正 - 実装サマリー

## 修正内容

### A. 差分判定の修正

**修正前**:
```php
// 既存のupdate_timeが存在する場合のみ差分判定
if ($existingReview && $existingReview->update_time && $parsedUpdateTime->lte($existingReview->update_time)) {
    $skippedCount++;
    continue;
}
```

**修正後**:
```php
// update_time が null の場合は create_time で判定
$existingTime = null;
$shouldSkip = false;

if ($existingReview) {
    // update_time が null の場合は create_time を使用
    $existingTime = $existingReview->update_time ?? $existingReview->create_time;
    
    // 既存の時刻が存在し、かつ APIのupdateTime <= 既存の時刻 の場合はスキップ
    if ($existingTime && $parsedUpdateTime->lte($existingTime)) {
        $shouldSkip = true;
    }
}

// スキップ判定のログ
Log::info('REVIEW_SYNC_SKIP_CHECK', [
    'shop_id' => $shop->id,
    'gbp_review_id' => $reviewId,
    'existing_review_exists' => $existingReview !== null,
    'existing_update_time' => $existingReview?->update_time ? $existingReview->update_time->toIso8601String() : null,
    'existing_create_time' => $existingReview?->create_time ? $existingReview->create_time->toIso8601String() : null,
    'existing_time_used' => $existingTime ? $existingTime->toIso8601String() : null,
    'parsed_update_time' => $parsedUpdateTime->toIso8601String(),
    'should_skip' => $shouldSkip,
]);

if ($shouldSkip) {
    $skippedCount++;
    continue;
}
```

**変更点**:
- `update_time` が null の場合でも `create_time` で差分判定が成立するように修正
- スキップ判定の詳細ログを追加

### B. last_reviews_synced_at の更新条件修正

**修正前**:
```php
// 新規または更新が0件の場合は更新しない
if ($upsertCount > 0 && $maxUpdateTime !== null) {
    $shop->update([
        'last_reviews_synced_at' => $maxUpdateTime,
    ]);
}
```

**修正後**:
```php
// 修正: maxUpdateTime が存在する場合は upsertCount に関係なく更新
// 同期でレビューを取得できた時点で "最後に見たupdateTime" を前進させる
if ($maxUpdateTime !== null) {
    $shop->update([
        'last_reviews_synced_at' => $maxUpdateTime,
    ]);
    
    Log::info('REVIEW_LAST_SYNC_UPDATED', [
        'shop_id' => $shop->id,
        'previous_last_synced_at' => $previousLastSyncedAt ? $previousLastSyncedAt->toIso8601String() : null,
        'new_last_synced_at' => $maxUpdateTime->toIso8601String(),
        'upsert_count' => $upsertCount,
        'skipped_count' => $skippedCount,
        'max_update_time' => $maxUpdateTime->toIso8601String(),
        'update_reason' => 'maxUpdateTime exists (upsertCount independent)',
    ]);
} else {
    Log::info('REVIEW_LAST_SYNC_NOT_UPDATED', [
        'shop_id' => $shop->id,
        'previous_last_synced_at' => $previousLastSyncedAt ? $previousLastSyncedAt->toIso8601String() : null,
        'upsert_count' => $upsertCount,
        'skipped_count' => $skippedCount,
        'max_update_time' => null,
        'update_reason' => 'maxUpdateTime is null (no reviews processed)',
    ]);
}
```

**変更点**:
- `upsertCount > 0` の条件を削除
- `maxUpdateTime !== null` の場合のみ更新（同期でレビューを取得できた時点で更新）
- 更新有無のログを追加

## 追加されたログ

### 1. REVIEW_SYNC_SKIP_CHECK
差分判定の詳細ログ（各レビューごと）

**出力内容**:
- `existing_review_exists`: 既存レコードの有無
- `existing_update_time`: 既存のupdate_time
- `existing_create_time`: 既存のcreate_time
- `existing_time_used`: 実際に使用した時刻（update_time or create_time）
- `parsed_update_time`: API側のupdateTime
- `should_skip`: スキップ判定の結果

### 2. REVIEW_LAST_SYNC_UPDATED
`last_reviews_synced_at` が更新された場合のログ

**出力内容**:
- `previous_last_synced_at`: 更新前の値
- `new_last_synced_at`: 更新後の値
- `upsert_count`: upsert件数
- `skipped_count`: スキップ件数
- `max_update_time`: 最大updateTime
- `update_reason`: 更新理由

### 3. REVIEW_LAST_SYNC_NOT_UPDATED
`last_reviews_synced_at` が更新されなかった場合のログ

**出力内容**:
- `previous_last_synced_at`: 現在の値
- `upsert_count`: upsert件数
- `skipped_count`: スキップ件数
- `max_update_time`: null
- `update_reason`: 更新されなかった理由

## 影響範囲

### レビューのupsertが増える/減る

**修正前**:
- `update_time` が null のレビューは毎回upsertされる可能性があった

**修正後**:
- `update_time` が null のレビューも `create_time` で差分判定されるため、不要なupsertが減る
- ただし、`create_time` が同じで `update_time` が更新された場合は、正しくupsertされる

**期待される効果**:
- 不要なupsertが減る
- 差分同期がより正確に動作する

### last_reviews_synced_at の動き

**修正前**:
- `upsertCount > 0` かつ `maxUpdateTime !== null` の場合のみ更新
- upsertが0件だと更新されず、次回も同じ処理を繰り返す

**修正後**:
- `maxUpdateTime !== null` の場合のみ更新（upsertCount に関係なく）
- 同期でレビューを取得できた時点で "最後に見たupdateTime" を前進させる

**期待される効果**:
- 全件スキップされた場合でも `last_reviews_synced_at` が更新される
- 次回の同期で、前回取得したレビューが確実にスキップされる
- 差分同期がより効率的に動作する

## テストシナリオ

### シナリオ1: update_time が null のレビューが存在する場合

**前提**:
- 既存レビュー: `update_time = null`, `create_time = '2024-01-01 10:00:00'`
- APIレスポンス: `updateTime = null`, `createTime = '2024-01-01 10:00:00'`

**期待動作**:
- `existing_time_used = '2024-01-01 10:00:00'` (create_time)
- `parsed_update_time = '2024-01-01 10:00:00'` (createTime)
- `should_skip = true` (差分判定が成立)
- upsertされない

### シナリオ2: 全件スキップされた場合

**前提**:
- 既存レビューがすべて最新
- APIレスポンス: すべて既存レビューと同じか古い

**期待動作**:
- `upsert_count = 0`
- `skipped_count > 0`
- `max_update_time` が存在する場合、`last_reviews_synced_at` が更新される
- 次回の同期で、同じレビューが確実にスキップされる

### シナリオ3: 新規レビューが追加された場合

**前提**:
- 既存レビュー: `update_time = '2024-01-01 10:00:00'`
- APIレスポンス: 新規レビュー `updateTime = '2024-01-02 10:00:00'`

**期待動作**:
- `should_skip = false` (新規レビュー)
- upsertされる
- `max_update_time = '2024-01-02 10:00:00'`
- `last_reviews_synced_at` が更新される

## 確認方法

### ログで確認すべきポイント

1. **REVIEW_SYNC_SKIP_CHECK** ログ
   - `existing_time_used` が正しく設定されているか（update_time or create_time）
   - `should_skip` が正しく判定されているか

2. **REVIEW_LAST_SYNC_UPDATED** ログ
   - `upsert_count = 0` でも `last_reviews_synced_at` が更新されているか
   - `max_update_time` が正しく設定されているか

3. **REVIEW_SAFE_INCREMENTAL_SYNC_END** ログ
   - `last_synced_at_updated` が正しく設定されているか（`maxUpdateTime !== null` の場合 true）

### DBで確認すべきポイント

1. **reviews テーブル**
   - `update_time` が null のレビューが不要にupsertされていないか
   - 新規レビューが正しく追加されているか

2. **shops テーブル**
   - `last_reviews_synced_at` が正しく更新されているか
   - 全件スキップされた場合でも更新されているか









