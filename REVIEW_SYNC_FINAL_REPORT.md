# 口コミ同期の差分同期検証 - 最終レポート

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

## 2. 取得（API呼び出し）側が差分かをコードで確定

### コード上の事実

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

**コード上の事実**:
- `$requestParams` は空配列（フィルタパラメータなし）
- URLにクエリパラメータを追加していない
- コメントに「全件取得し、フィルタリングは呼び出し側で実施」と明記

**結論（コード上）**: **APIレベルでは差分取得していない（全件取得）**

## 3. 保存（DB更新）が差分かをコードで確定

### 差分判定ロジック

```1018:1047:app/Http/Controllers/ReportController.php
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

if ($shouldSkip) {
    $skippedCount++;
    continue; // $rows に追加されない = upsertされない
}
```

**コード上の事実**:
- 既存レコードの `update_time` または `create_time` と比較
- `shouldSkip = true` の場合は `continue` でスキップ
- スキップされたレビューは `$rows` 配列に追加されない

### DB書き込みコード

```1118:1132:app/Http/Controllers/ReportController.php
Review::upsert(
    $rows,
    ['shop_id', 'gbp_review_id'], // ユニークキー: shop_id + gbp_review_id
    [
        'snapshot_id',
        'author_name',
        'rating',
        'comment',
        'create_time',
        'reply_text',
        'replied_at',
        'update_time',
        'updated_at'
    ]
);
```

**コード上の事実**:
- `$rows` 配列に追加されたレビューのみupsert
- スキップされたレビューは `$rows` に含まれない
- ユニークキー: `['shop_id', 'gbp_review_id']`

**結論（コード上）**: **DB保存は差分（アプリ側で差分判定してスキップ）**

## 4. updateTime=null の扱いをコードで確認

### API側のupdateTime処理

```982:1000:app/Http/Controllers/ReportController.php
// updateTimeを取得（review.updateTime > review.createTime の優先順位）
$updateTimeRaw = data_get($review, 'updateTime');
$createTime = \Carbon\Carbon::parse($review['createTime']);

// update_timeには常に値を持つ（updateTimeが存在すればそれ、なければcreateTime）
$parsedUpdateTime = $updateTimeRaw
    ? \Carbon\Carbon::parse($updateTimeRaw)
    : $createTime;
```

**コード上の事実**:
- API側の `updateTime` が null の場合、`createTime` を `parsedUpdateTime` として使用
- `parsedUpdateTime` は常に値を持つ（nullにならない）

### 既存レコードのupdateTime処理

```1023:1030:app/Http/Controllers/ReportController.php
if ($existingReview) {
    // update_time が null の場合は create_time を使用
    $existingTime = $existingReview->update_time ?? $existingReview->create_time;
    
    // 既存の時刻が存在し、かつ APIのupdateTime <= 既存の時刻 の場合はスキップ
    if ($existingTime && $parsedUpdateTime->lte($existingTime)) {
        $shouldSkip = true;
    }
}
```

**コード上の事実**:
- 既存レコードの `update_time` が null の場合、`create_time` を使用
- `existingTime` が null でない場合のみ差分判定
- 修正後: `update_time` が null でも `create_time` で差分判定が成立

**結論（コード上）**: **updateTime=null でも差分判定が成立する（修正後）**

## 5. 差分判定キーの特定

### コード上の差分判定キー

```1025:1028:app/Http/Controllers/ReportController.php
// update_time が null の場合は create_time を使用
$existingTime = $existingReview->update_time ?? $existingReview->create_time;

// 既存の時刻が存在し、かつ APIのupdateTime <= 既存の時刻 の場合はスキップ
if ($existingTime && $parsedUpdateTime->lte($existingTime)) {
    $shouldSkip = true;
}
```

**差分判定キー**: `update_time_or_create_time`
- 既存レコード: `update_time ?? create_time`
- API側: `updateTime ?? createTime` (常に値を持つ)

**ユニークキー**: `['shop_id', 'gbp_review_id']`

## 6. 追加したログ（検証用）

### API取得関連
- `REVIEW_SYNC_API_REQUEST_START`: APIリクエスト開始
- `REVIEW_SYNC_API_REQUEST_END`: APIリクエスト終了（fetched_count, pages, api_elapsed_ms）
- `SYNC_REVIEWS_API_COUNT`: API取得件数

### DB保存関連
- `REVIEWS_UPSERT_EXECUTED`: upsert実行結果（upsert_inserted_count, upsert_updated_count, skipped_unchanged_count, total_db_write_count, db_elapsed_ms）
- `REVIEW_SAFE_INCREMENTAL_SYNC_END`: 同期終了（差分同期検証用の全情報）

### 差分判定関連
- `REVIEW_SYNC_SKIP_CHECK`: スキップ判定の詳細（existing_time_used, parsed_update_time, should_skip）

## 7. コード上の結論（ログ/SQL実行前の断定）

### A) API取得が差分か？
**答え**: **全件取得**（APIレベルでは差分フィルタなし）

**証拠**: `GoogleBusinessProfileService::listReviews()` で `$requestParams = []`（フィルタなし）

### B) DB保存が差分か？
**答え**: **差分保存**（アプリ側で差分判定してスキップ）

**証拠**: `ReportController::syncReviews()` で `shouldSkip = true` の場合は `continue` でスキップ

### C) 差分判定キーは何か？
**答え**: **update_time_or_create_time**（update_time が null の場合は create_time を使用）

**証拠**: `$existingTime = $existingReview->update_time ?? $existingReview->create_time`

### updateTime=null が差分判定に与える影響
**答え**: **修正後は問題なし**（update_time が null でも create_time で差分判定）

**証拠**: 修正後のコードで `update_time ?? create_time` を使用

---

## 次のステップ（実際のテスト実行）

### 1. テスト実行
- `REVIEW_SYNC_TEST_INSTRUCTIONS.md` を参照してテストを実行

### 2. SQLで確認
- `REVIEW_SYNC_VERIFICATION_SQL.sql` を実行してDBの更新件数を確認

### 3. ログで確認
- `storage/logs/laravel.log` から該当ログを確認
- または、PowerShellで以下を実行:
```powershell
Select-String -Path "storage\logs\laravel.log" -Pattern "REVIEW_SAFE_INCREMENTAL_SYNC_END" | Select-Object -Last 2
```

### 4. 最終断定
- ログとSQL結果を基に、以下を断定：
  - `fetched_count(2回目)` = 全件 → 取得は差分じゃない（想定通り）
  - `total_db_write_count(2回目)` = 0 または少数 → 保存が差分（期待値）
  - `updated_rows_2nd` = 0 または少数 → 差分保存が成立（期待値）









