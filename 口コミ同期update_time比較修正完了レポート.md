# 口コミ同期 update_time 比較修正完了レポート

## 修正内容

### 該当箇所のコード（修正後）

```php
// app/Services/ReviewSyncService.php:117-256

// updateTime/createTime を CarbonImmutable(UTC) で統一
// 仕様: update_time は NULL にしない。create_time がある限り必ず埋める

// API側: updateTime または createTime を取得
$apiRaw = data_get($review, 'updateTime') ?? data_get($review, 'createTime') ?? null;
if (!$apiRaw) {
    Log::warning('REVIEWS_SKIP_NO_TIME', [
        'shop_id' => $shop->id,
        'gbp_review_id' => $reviewId,
        'reason' => 'updateTimeとcreateTimeが両方存在しません',
    ]);
    $skippedCount++;
    continue;
}

// API側: ISO8601想定で parse
$apiTime = null;
try {
    $apiTime = CarbonImmutable::parse($apiRaw, 'UTC')->utc();
} catch (\Exception $e) {
    Log::warning('REVIEWS_SKIP_NO_TIME', [
        'shop_id' => $shop->id,
        'gbp_review_id' => $reviewId,
        'reason' => 'API時刻のパースに失敗',
        'api_raw' => $apiRaw,
        'error' => $e->getMessage(),
    ]);
    $skippedCount++;
    continue;
}

// createTime も取得（DB保存用）
$createTimeRaw = $review['createTime'] ?? null;
if (!$createTimeRaw) {
    Log::warning('REVIEWS_SKIP_NO_TIME', [
        'shop_id' => $shop->id,
        'gbp_review_id' => $reviewId,
        'reason' => 'createTimeが存在しません',
    ]);
    $skippedCount++;
    continue;
}

$createTime = null;
try {
    $createTime = CarbonImmutable::parse($createTimeRaw, 'UTC')->utc();
} catch (\Exception $e) {
    Log::warning('REVIEWS_SKIP_NO_TIME', [
        'shop_id' => $shop->id,
        'gbp_review_id' => $reviewId,
        'reason' => 'createTimeのパースに失敗',
        'createTime_raw' => $createTimeRaw,
        'error' => $e->getMessage(),
    ]);
    $skippedCount++;
    continue;
}

// 既存の update_time（NULLなら create_time）と比較し、API updateTime <= 既存ならスキップ
$existingReview = $existingReviews->get($reviewId);
$shouldSkip = false;
$existingTime = null;

if ($existingReview) {
    // 既存側: DBの update_time/create_time を取得
    $existingRaw = $existingReview->update_time ?? $existingReview->create_time ?? null;
    if ($existingRaw) {
        try {
            // DB形式想定('Y-m-d H:i:s'): createFromFormat で厳密に読む
            if ($existingRaw instanceof \Carbon\Carbon) {
                // Carbon型の場合は format してから createFromFormat
                $existingTimeRaw = $existingRaw->format('Y-m-d H:i:s');
            } else {
                // 文字列の場合はそのまま
                $existingTimeRaw = (string)$existingRaw;
            }
            $existingTime = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC')->utc();
        } catch (\Exception $e) {
            // createFromFormat が例外なら parse fallback
            try {
                $existingTime = CarbonImmutable::parse($existingRaw, 'UTC')->utc();
            } catch (\Exception $e2) {
                Log::warning('ReviewSyncService: 既存時刻のパースに失敗', [
                    'shop_id' => $shop->id,
                    'gbp_review_id' => $reviewId,
                    'existing_raw' => $existingRaw,
                    'error' => $e2->getMessage(),
                ]);
                // パース失敗時は既存時刻を null として扱う（UPSERT側）
                $existingTime = null;
            }
        }
    }
    // もし $existingRaw が null なら、existingが壊れてるのでUPSERT側（create_timeも入れる）

    // 判定: api_update_time <= existing_time なら SKIP、api_update_time > existing_time のときのみ UPSERT
    // 比較は必ず CarbonImmutable(UTC) 同士で行う
    if ($existingTime) {
        $shouldSkip = $apiTime->lessThanOrEqualTo($existingTime);
    }
}

if ($shouldSkip) {
    $skippedCount++;
    // 検証用ログ（最初の3件のみ）
    if ($skippedCount <= 3) {
        // ログは比較に使った値から作る（ズレ防止）
        $compareResult = $existingTime ? $apiTime->lessThanOrEqualTo($existingTime) : false;
        
        Log::info('REVIEWS_DIFF_DECISION', [
            'shop_id' => $shop->id,
            'gbp_review_id' => $reviewId,
            'api_raw' => $apiRaw,
            'existing_raw' => $existingReview ? ($existingReview->update_time ?? $existingReview->create_time ?? null) : null,
            'api_update_time' => $apiTime->format('Y-m-d H:i:s'), // 比較に使った値から
            'existing_update_time' => $existingTime ? $existingTime->format('Y-m-d H:i:s') : null, // 比較に使った値から
            'api_time_iso' => $apiTime->toIso8601String(),
            'existing_time_iso' => $existingTime ? $existingTime->toIso8601String() : null,
            'api_ts' => $apiTime->timestamp,
            'existing_ts' => $existingTime ? $existingTime->timestamp : null,
            'compare_api_lte_existing' => $compareResult, // 実際に判定に使った結果をそのまま出す
            // ...
            'decision' => 'SKIP',
            'reason' => 'update_time_lte_existing',
        ]);
    }
    continue;
}

// 変更があるかチェック（既存レコードがある場合）
$hasChanges = false;
$changeReasons = [];

if ($existingReview) {
    // ... 他の比較処理 ...
    
    // 判定: api_update_time > existing_time のときのみ UPSERT
    if ($existingTime && $apiTime->greaterThan($existingTime)) {
        $hasChanges = true;
        $changeReasons[] = 'update_time_gt';
    }
    
    // 検証用ログ（最初の3件のみ）
    if (count($rows) < 3) {
        // ログは比較に使った値から作る（ズレ防止）
        $compareResult = $existingTime ? $apiTime->lessThanOrEqualTo($existingTime) : false;
        
        Log::info('REVIEWS_DIFF_DECISION', [
            // ... 同様のログ項目 ...
            'decision' => $hasChanges ? 'UPSERT' : 'SKIP',
            'reason' => $hasChanges ? implode(',', $changeReasons) : 'no_changes',
        ]);
    }
}
```

### 変数の型・timezone・フォーマット一覧

| 変数名 | 型 | timezone | フォーマット | 用途 |
|--------|-----|----------|--------------|------|
| `$apiRaw` | string | - | ISO8601 | APIから取得した生の値（updateTime または createTime） |
| `$apiTime` | CarbonImmutable | UTC | - | 比較用（API側の時刻） |
| `$createTimeRaw` | string | - | ISO8601 | APIから取得したcreateTimeの生の値 |
| `$createTime` | CarbonImmutable | UTC | - | DB保存用（create_timeカラム） |
| `$existingRaw` | Carbon型またはstring | - | 'Y-m-d H:i:s' | DBから取得した既存の値（update_time または create_time） |
| `$existingTimeRaw` | string | - | 'Y-m-d H:i:s' | 既存側の時刻を文字列化したもの |
| `$existingTime` | CarbonImmutable | UTC | - | 比較用（既存側の時刻） |
| `$shouldSkip` | bool | - | - | スキップ判定結果（`$apiTime->lessThanOrEqualTo($existingTime)`） |

### 修正のポイント

1. **API側の処理を簡素化**
   - `$apiRaw = data_get($review, 'updateTime') ?? data_get($review, 'createTime') ?? null;` で取得
   - `$apiTime = CarbonImmutable::parse($apiRaw, 'UTC')->utc();` でパース
   - `$createTime` は別途取得（DB保存用）

2. **既存側の処理を改善**
   - `$existingRaw = $existingReview->update_time ?? $existingReview->create_time ?? null;` で取得
   - `CarbonImmutable::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC')->utc();` でパース
   - 失敗時は `parse` でフォールバック

3. **比較ロジックの統一**
   - `$shouldSkip = $apiTime->lessThanOrEqualTo($existingTime);` で判定
   - 比較は必ず CarbonImmutable(UTC) 同士で行う

4. **ログの改善**
   - `compare_api_lte_existing` は実際に判定に使った結果をそのまま出す（`$compareResult`）
   - `api_raw`, `existing_raw` を追加
   - `api_time_iso`, `existing_time_iso`, `api_ts`, `existing_ts` を追加
   - ログは比較に使った値から作る（ズレ防止）

5. **変数名の統一**
   - `$apiUpdateTime` → `$apiTime` に統一（比較用）
   - `$createTime` はDB保存用として残す

## 検証手順

### 1. 同期を実行

Web UIから同期ボタンを押す

### 2. ログを確認

```bash
# REVIEWS_DIFF_DECISION ログを抽出
grep "REVIEWS_DIFF_DECISION" storage/logs/laravel.log | tail -20

# 同値なのに判定が逆になっていないか確認
grep "REVIEWS_DIFF_DECISION" storage/logs/laravel.log | grep -A 5 "api_ts" | tail -20
```

### 3. ログから原因を特定

**確認ポイント**:
1. **`api_ts` と `existing_ts` が同じ場合**:
   - `compare_api_lte_existing` が `true` であることを確認
   - `decision` が `SKIP` であることを確認
   - `reason` が `update_time_lte_existing` であることを確認

2. **`api_ts` と `existing_ts` が異なる場合**:
   - `api_ts` > `existing_ts` の場合、`decision` が `UPSERT` で `reason` が `update_time_gt` であることを確認
   - `api_ts` < `existing_ts` の場合、`decision` が `SKIP` で `reason` が `update_time_lte_existing` であることを確認

3. **`api_raw` と `existing_raw` を確認**:
   - 生の値が正しく取得できているか確認
   - パースエラーがないか確認

## 期待される結果

### 正常な場合（修正後）

**同値の場合**:
- `api_ts` = `existing_ts`
- `compare_api_lte_existing` = `true`
- `decision` = `SKIP`
- `reason` = `update_time_lte_existing`

**API側が新しい場合**:
- `api_ts` > `existing_ts`
- `compare_api_lte_existing` = `false`
- `decision` = `UPSERT`
- `reason` = `update_time_gt`

**API側が古い場合**:
- `api_ts` < `existing_ts`
- `compare_api_lte_existing` = `true`
- `decision` = `SKIP`
- `reason` = `update_time_lte_existing`

### 完了条件

✅ 同一店舗で2回連続同期したとき、`rows_to_write_count` が 0（または極小）になり、`skipped_count` がほぼ `fetched_count` になること  
✅ REVIEWS_DIFF_DECISION で、同値の場合は `compare_api_lte_existing=true` / `decision=SKIP` になること  
✅ 比較は必ず CarbonImmutable(UTC) 同士で行う  
✅ ログは比較に使った値から作る（ズレ防止）  
✅ `compare_api_lte_existing` は実際に判定に使った結果をそのまま出す  








