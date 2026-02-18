# 口コミ同期 timezone修正と localPosts 全ページ取得レポート

## 修正内容

### 1. DB由来の日時パースをUTC指定に統一

**問題**: DBの `existing_raw:"YYYY-mm-dd HH:ii:ss"` を Carbon が JST として解釈し UTC変換してしまい、`existing_time_iso` が API より9時間古くなっていた。

**修正箇所**: `app/Services/ReviewSyncService.php:237-267`

**修正前**:
```php
if ($existingRaw instanceof \Carbon\Carbon) {
    // Carbon型の場合はそのまま ->utc() して CarbonImmutable に変換
    $existingTime = CarbonImmutable::instance($existingRaw)->utc();
} else {
    // 文字列の場合は createFromFormat で厳密に読む
    $existingTimeRaw = (string)$existingRaw;
    $existingTime = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC')->utc();
}
```

**修正後**:
```php
// DB由来の日時は必ずUTC指定でパース（timezone事故防止）
// Carbon型の場合も、一旦文字列化してからUTCとして読み直す
if ($existingRaw instanceof \Carbon\Carbon) {
    // Carbon型の場合は format してから createFromFormat でUTCとして読み直す
    $existingTimeRaw = $existingRaw->format('Y-m-d H:i:s');
    $existingTime = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC')->utc();
} else {
    // 文字列の場合は createFromFormat で厳密に読む（UTC指定）
    $existingTimeRaw = (string)$existingRaw;
    $existingTime = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC')->utc();
}
```

**修正内容**:
- Carbon型の場合も、一旦 `format('Y-m-d H:i:s')` してから `createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC')` でUTCとして読み直す
- `parse()` 等のデフォルトtimezone依存を禁止し、必ずUTC指定でパースする

**その他の修正箇所**:
- `app/Services/ReviewSyncService.php:345-347`: `repliedAt` のパースも `CarbonImmutable::parse($replyUpdateTimeRaw, 'UTC')->utc()` に統一
- `app/Services/ReviewSyncService.php:488-490`: `repliedAt` のパースも `CarbonImmutable::parse($replyUpdateTimeRaw, 'UTC')->utc()` に統一

### 2. localPosts の全ページ取得ループを実装

**問題**: `localPosts` は `nextPageToken` が返っているので全ページ取得ループを実装する必要があった。

**修正箇所**: `app/Services/GoogleBusinessProfileService.php:1016-1097`

**修正前**:
```php
$response = Http::withToken($accessToken)
    ->get($url);

if ($response->successful()) {
    $data = $response->json();
    $localPosts = $data['localPosts'] ?? [];
    return $localPosts;
}
```

**修正後**:
```php
// 全ページ取得ループ（nextPageToken対応）
$allLocalPosts = [];
$pageToken = null;
$pageIndex = 0;
$maxPages = 50; // 無限ループ防止

do {
    $url = $baseUrl;
    if ($pageToken) {
        $url .= '?pageToken=' . urlencode($pageToken);
    }
    
    $response = Http::withToken($accessToken)
        ->get($url);

    if ($response->successful()) {
        $data = $response->json();
        $localPosts = $data['localPosts'] ?? [];
        $allLocalPosts = array_merge($allLocalPosts, $localPosts);
        
        $pageToken = $data['nextPageToken'] ?? null;
        $pageIndex++;
        
        // ページングログ
        Log::info('LOCAL_POSTS_LIST_PAGINATION', [
            'account_id' => $accountId,
            'location_id' => $locationId,
            'page' => $pageIndex,
            'fetched_this_page' => count($localPosts),
            'fetched_total' => count($allLocalPosts),
            'has_next' => $pageToken !== null,
        ]);
    } else {
        break;
    }
} while ($pageToken !== null && $pageIndex < $maxPages);

return $allLocalPosts;
```

**修正内容**:
- `nextPageToken` が返る限りループし、全ページを取得
- 各ページで `LOCAL_POSTS_LIST_PAGINATION` ログを出力
- 無限ループ防止に最大ページ数ガード（50ページ）を追加

### 3. 検証用ログの追加

**修正箇所**: `app/Services/ReviewSyncService.php:570-581`

**追加内容**:
```php
Log::info('ReviewSyncService: 口コミ同期完了', [
    'shop_id' => $shop->id,
    'shop_name' => $shop->name,
    'operator_id' => $operatorId,
    'fetched_count' => $fetchedCount,
    'rows_to_write_count' => count($rows),
    'skipped_count' => $skippedCount,
    'inserted_count' => $insertedCount,
    'updated_count' => $updatedCount,
    'synced_count' => $syncedCount,
    'max_update_time' => $maxUpdateTime ? $maxUpdateTime->toIso8601String() : null,
    'timezone_fix_applied' => true, // UTC指定でパースしたことを示す
    'expected_behavior' => [
        '2nd_sync_updated_count_should_be_0' => $updatedCount === 0,
        '2nd_sync_skipped_count_should_be_high' => $skippedCount >= ($fetchedCount * 0.9), // 90%以上スキップされることを期待
    ],
]);
```

**検証項目**:
- `timezone_fix_applied`: UTC指定でパースしたことを示す
- `expected_behavior.2nd_sync_updated_count_should_be_0`: 2回目同期で `updated_count` が 0 になることを期待
- `expected_behavior.2nd_sync_skipped_count_should_be_high`: 2回目同期で `skipped_count` が `fetched_count` の90%以上になることを期待

### 4. photo の除外処理について

**要件**: photo の media/profile（createTime 1970）は同期対象から除外または別扱い。

**実装方針**:
- `listMedia` メソッドは全件取得を維持し、フィルタリングは呼び出し側（ReportController等）で実施
- 呼び出し側で `createTime` が 1970-01-01 の photo を除外する処理を追加することを推奨

**除外条件の例**:
```php
$mediaItems = array_filter($mediaItems, function($item) {
    $createTime = $item['createTime'] ?? null;
    if ($createTime) {
        $createTimeCarbon = CarbonImmutable::parse($createTime, 'UTC');
        // createTime が 1970-01-01 の場合は除外
        if ($createTimeCarbon->year === 1970) {
            return false;
        }
    }
    // media/profile の場合は除外
    if (isset($item['mediaFormat']) && $item['mediaFormat'] === 'PHOTO' && isset($item['sourceUrl'])) {
        if (strpos($item['sourceUrl'], '/media/profile') !== false) {
            return false;
        }
    }
    return true;
});
```

## 検証手順

### 1. 同期を実行

同一店舗で同期を2回連続実行

### 2. ログを確認

```bash
# ReviewSyncService: 口コミ同期完了 ログを確認
grep "ReviewSyncService: 口コミ同期完了" storage/logs/laravel.log | tail -10

# REVIEWS_DIFF_DECISION ログを確認（existing_time_iso が API と同一になることを確認）
grep "REVIEWS_DIFF_DECISION" storage/logs/laravel.log | tail -20

# LOCAL_POSTS_LIST_PAGINATION ログを確認（全ページ取得を確認）
grep "LOCAL_POSTS_LIST_PAGINATION" storage/logs/laravel.log | tail -20
```

### 3. 期待値（2回目同期）

**正常な場合（修正後）**:
- `updated_count`: 0（または極小）
- `skipped_count`: `fetched_count` の90%以上
- `existing_time_iso` が `api_time_iso` と同一になる
- `timezone_fix_applied`: `true`
- `expected_behavior.2nd_sync_updated_count_should_be_0`: `true`
- `expected_behavior.2nd_sync_skipped_count_should_be_high`: `true`

**REVIEWS_DIFF_DECISION ログ（2回目同期、最初の3件）**:
```json
{
  "shop_id": 1,
  "gbp_review_id": "AbFv...",
  "api_time_iso": "2025-12-24T01:39:52+00:00",
  "existing_time_iso": "2025-12-24T01:39:52+00:00",  // ← API と同一になる
  "api_ts": 1735012792,
  "existing_ts": 1735012792,  // ← 同値
  "compare_api_lte_existing": true,
  "shouldSkip": true,
  "hasChanges": false,
  "decision": "SKIP",
  "reason": "update_time_lte_existing"
}
```

**ReviewSyncService: 口コミ同期完了（2回目）**:
```json
{
  "shop_id": 1,
  "fetched_count": 176,
  "rows_to_write_count": 0,
  "skipped_count": 176,
  "inserted_count": 0,
  "updated_count": 0,  // ← 0 になる
  "synced_count": 0,
  "timezone_fix_applied": true,
  "expected_behavior": {
    "2nd_sync_updated_count_should_be_0": true,
    "2nd_sync_skipped_count_should_be_high": true
  }
}
```

## 修正の効果

### 修正前

- DBの日時がJSTとして解釈され、UTC変換時に9時間ズレる
- `existing_time_iso` が `api_time_iso` より9時間古くなる
- `compare_api_lte_existing` が `false` になり、毎回 `update_time_gt` 判定で全件UPDATEになる

### 修正後

- DBの日時がUTCとして正しく解釈される
- `existing_time_iso` が `api_time_iso` と同一になる
- `compare_api_lte_existing` が `true` になり、既存レビューは `SKIP` される
- 2回目同期で `updated_count` が 0 になり、`skipped_count` が増える

## 完了条件

✅ DB由来の日時パースをUTC指定に統一（Carbon型の場合も文字列化してからUTCとして読み直す）  
✅ `parse()` 等のデフォルトtimezone依存を禁止  
✅ localPosts の全ページ取得ループを実装（nextPageToken対応）  
✅ 検証用ログを追加（timezone_fix_applied, expected_behavior）  

**検証**: 上記の検証手順に従って、2回目同期で `updated_count` が 0 になり、`skipped_count` が増えることを確認








