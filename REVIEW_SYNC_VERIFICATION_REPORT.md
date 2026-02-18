# 口コミ同期の差分同期検証レポート

## 1. 口コミ同期の入口特定

### エンドポイント
- **通常**: `POST /reviews/sync` → `ReviewsController::sync()`
- **オペレーター**: `POST /operator/reviews/sync` → `ReviewsController::sync()`

### 実際の同期処理
- **ファイル**: `app/Http/Controllers/ReportController.php`
- **メソッド**: `syncReviews()` (872行目以降)

### API呼び出し
- **ファイル**: `app/Services/GoogleBusinessProfileService.php`
- **メソッド**: `listReviews()` (453行目以降)

### まとめ
- `app/Http/Controllers/ReviewsController.php` : `sync()`
- `app/Http/Controllers/ReportController.php` : `syncReviews()`
- `app/Services/GoogleBusinessProfileService.php` : `listReviews()`

## 2. 取得（API呼び出し）側が差分かをログで確定

### 追加したログ

**ログキー**: `REVIEW_SYNC_API_REQUEST_START`, `REVIEW_SYNC_API_REQUEST_END`, `SYNC_REVIEWS_API_COUNT`

**出力内容**:
- `shop_id`, `account_id`, `location_id`
- `request_params`: APIリクエストパラメータ（現在は空 = フィルタなし）
- `fetched_count`: APIから取得したレビュー件数
- `pages`: ページネーション回数（GBP API v4はページネーションなし = 1）
- `api_elapsed_ms`: APIにかかった時間
- `has_delta_filter`: false（updateTime フィルタなし = 全件取得）
- `is_delta_fetch`: false（全件取得）

### コード確認結果

```453:474:app/Services/GoogleBusinessProfileService.php
public function listReviews(string $accessToken, string $accountId, string $locationId): array
{
    // URL構造: /v4/accounts/{gbp_account_id}/locations/{location_id}/reviews
    // 注意: 全件取得し、フィルタリングは呼び出し側（ReportController）で実施
    $url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationIdClean}/reviews";
    
    // APIリクエストパラメータ（差分取得の有無を確認）
    $requestParams = [];
    // 注意: GBP API v4 の reviews.list には updateTime フィルタがないため、全件取得
    
    $response = Http::withToken($accessToken)
        ->get($url);
}
```

**結論（コード上）**: APIレベルでは差分取得していない（全件取得）

## 3. 保存（DB更新）が差分かをログで確定

### 追加したログ

**ログキー**: `REVIEWS_UPSERT_EXECUTED`, `REVIEW_SAFE_INCREMENTAL_SYNC_END`

**出力内容**:
- `upsert_inserted_count`: 新規追加数
- `upsert_updated_count`: 更新数
- `skipped_unchanged_count`: 変更なしでスキップした数
- `total_db_write_count`: insert+update合計
- `db_elapsed_ms`: DB書き込みにかかった時間
- `unique_key_columns`: ['shop_id', 'gbp_review_id']
- `db_operation`: 'upsert'

### 差分判定ロジック

```1018:1031:app/Http/Controllers/ReportController.php
// 増分同期フィルタ: 既存レコードが存在し、かつ既存のupdate_time >= APIのupdateTime の場合はスキップ
// 修正: update_time が null の場合は create_time で判定
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
```

**結論（コード上）**: アプリ側で差分判定してスキップしている（DB保存は差分）

## 4. updateTime=null の扱いを事実で確認

### コード確認結果

```982:1000:app/Http/Controllers/ReportController.php
// updateTimeを取得（review.updateTime > review.createTime の優先順位）
$updateTimeRaw = data_get($review, 'updateTime');
$createTime = \Carbon\Carbon::parse($review['createTime']);

// update_timeには常に値を持つ（updateTimeが存在すればそれ、なければcreateTime）
$parsedUpdateTime = $updateTimeRaw
    ? \Carbon\Carbon::parse($updateTimeRaw)
    : $createTime;
```

**updateTime=null の扱い**:
- API側の `updateTime` が null の場合、`createTime` を `parsedUpdateTime` として使用
- 既存レコードの `update_time` が null の場合、`create_time` で差分判定
- 修正後: `update_time` が null でも差分判定が成立する

## 5. DBで"全件更新"してないかをSQLで確認

### SQLクエリ（実行前の時刻をメモして実行）

```sql
-- 同期実行前の時刻をメモ（例: 2024-01-15 10:00:00）
-- 同期実行後、以下を実行

-- 更新されたレコード数
SELECT COUNT(*) AS updated_rows
FROM reviews
WHERE updated_at >= '2024-01-15 10:00:00';

-- 新規追加されたレコード数
SELECT COUNT(*) AS created_rows
FROM reviews
WHERE created_at >= '2024-01-15 10:00:00';
```

## 6. 2回連続同期テスト

### テスト手順

1. 同期実行前の時刻をメモ
2. 1回目の同期を実行
3. ログを確認
4. SQLで更新/追加件数を確認
5. 2回目の同期を実行（間を空けずに）
6. ログを確認
7. SQLで更新/追加件数を確認

### 期待される結果

**差分同期が成立している場合**:
- `fetched_count(2回目)` = 全件（APIは全件取得）
- `total_db_write_count(2回目)` = 0 または少数（差分保存）
- `skipped_count(2回目)` = ほぼ全件

**差分同期が成立していない場合**:
- `fetched_count(2回目)` = 全件（APIは全件取得）
- `total_db_write_count(2回目)` = 全件（全件更新）

## 7. 結論（コード上での断定）

### A) API取得が差分か？
**答え**: **全件取得**（APIレベルでは差分フィルタなし）

### B) DB保存が差分か？
**答え**: **差分保存**（アプリ側で差分判定してスキップ）

### C) 差分判定キーは何か？
**答え**: **update_time_or_create_time**（update_time が null の場合は create_time を使用）

### updateTime=null が差分判定に与える影響
**答え**: **修正後は問題なし**（update_time が null でも create_time で差分判定）

---

**注意**: 実際のログとSQL結果は、同期を実行してから確認する必要があります。









