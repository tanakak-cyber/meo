# 口コミ同期の差分同期検証 - 実装完了サマリー

## 実装したログ追加

### 1. API取得関連のログ

**ログキー**: `REVIEW_SYNC_API_REQUEST_START`, `REVIEW_SYNC_API_REQUEST_END`, `SYNC_REVIEWS_API_COUNT`

**出力内容**:
- `shop_id`, `account_id`, `location_id`
- `request_params`: APIリクエストパラメータ（現在は空 = フィルタなし）
- `fetched_count`: APIから取得したレビュー件数
- `pages`: ページネーション回数（GBP API v4はページネーションなし = 1）
- `api_elapsed_ms`: APIにかかった時間
- `has_delta_filter`: false（updateTime フィルタなし = 全件取得）
- `is_delta_fetch`: false（全件取得）

### 2. DB保存関連のログ

**ログキー**: `REVIEWS_UPSERT_EXECUTED`, `REVIEW_SAFE_INCREMENTAL_SYNC_END`

**出力内容**:
- `upsert_inserted_count`: 新規追加数
- `upsert_updated_count`: 更新数
- `skipped_unchanged_count`: 変更なしでスキップした数
- `total_db_write_count`: insert+update合計
- `db_elapsed_ms`: DB書き込みにかかった時間
- `unique_key_columns`: ['shop_id', 'gbp_review_id']
- `db_operation`: 'upsert'

### 3. 差分判定関連のログ

**ログキー**: `REVIEW_SYNC_SKIP_CHECK`

**出力内容**:
- `existing_time_used`: 実際に使用した時刻（update_time or create_time）
- `parsed_update_time`: API側のupdateTime
- `should_skip`: スキップ判定の結果
- `delta_key`: 'update_time_or_create_time'

## コード上の事実（ログ/SQL実行前の断定）

### A) API取得が差分か？
**答え**: **全件取得**（APIレベルでは差分フィルタなし）

**証拠コード**:
```453:474:app/Services/GoogleBusinessProfileService.php
// 注意: 全件取得し、フィルタリングは呼び出し側（ReportController）で実施
$url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationIdClean}/reviews";
$requestParams = []; // フィルタパラメータなし
$response = Http::withToken($accessToken)->get($url);
```

### B) DB保存が差分か？
**答え**: **差分保存**（アプリ側で差分判定してスキップ）

**証拠コード**:
```1018:1031:app/Http/Controllers/ReportController.php
// 既存の時刻が存在し、かつ APIのupdateTime <= 既存の時刻 の場合はスキップ
if ($existingTime && $parsedUpdateTime->lte($existingTime)) {
    $shouldSkip = true;
}
if ($shouldSkip) {
    $skippedCount++;
    continue; // $rows に追加されない = upsertされない
}
```

### C) 差分判定キーは何か？
**答え**: **update_time_or_create_time**（update_time が null の場合は create_time を使用）

**証拠コード**:
```1025:1028:app/Http/Controllers/ReportController.php
// update_time が null の場合は create_time を使用
$existingTime = $existingReview->update_time ?? $existingReview->create_time;
if ($existingTime && $parsedUpdateTime->lte($existingTime)) {
    $shouldSkip = true;
}
```

### updateTime=null が差分判定に与える影響
**答え**: **修正後は問題なし**（update_time が null でも create_time で差分判定）

**証拠コード**:
```982:1000:app/Http/Controllers/ReportController.php
// update_timeには常に値を持つ（updateTimeが存在すればそれ、なければcreateTime）
$parsedUpdateTime = $updateTimeRaw
    ? \Carbon\Carbon::parse($updateTimeRaw)
    : $createTime; // 常に値を持つ
```

## 次のステップ（実際のテスト実行）

### 1. テスト実行
- `REVIEW_SYNC_TEST_INSTRUCTIONS.md` を参照してテストを実行

### 2. SQLで確認
- `REVIEW_SYNC_VERIFICATION_SQL.sql` を実行してDBの更新件数を確認

### 3. ログで確認
- `REVIEW_SYNC_LOG_EXTRACTION.sh` を実行してログを抽出
- または、`storage/logs/laravel.log` から該当ログを確認

### 4. 最終断定
- ログとSQL結果を基に、以下を断定：
  - `fetched_count(2回目)` = 全件 → 取得は差分じゃない（想定通り）
  - `total_db_write_count(2回目)` = 0 または少数 → 保存が差分（期待値）
  - `updated_rows_2nd` = 0 または少数 → 差分保存が成立（期待値）

## 期待される結果

### 差分同期が成立している場合

**API取得**:
- `fetched_count(1回目)` = `fetched_count(2回目)` = 全件（APIは全件取得）

**DB保存**:
- `total_db_write_count(2回目)` = 0 または少数
- `skipped_count(2回目)` = ほぼ全件
- `updated_rows_2nd` = 0 または少数

### 差分同期が成立していない場合

**API取得**:
- `fetched_count(1回目)` = `fetched_count(2回目)` = 全件（APIは全件取得）

**DB保存**:
- `total_db_write_count(2回目)` = 全件
- `skipped_count(2回目)` = 0 または少数
- `updated_rows_2nd` = 全件









