# 口コミ同期 update_time 比較修正レポート

## 修正内容

### 問題
- `api_update_time` と `existing_update_time` が同値なのに `compare_api_lte_existing=false`
- `decision` が `UPSERT` / `reason=update_time_gt` になってしまう

### 原因
- 文字列比較になっている可能性
- Carbon型が揃っていない / timezoneが揃っていない
- 比較用変数取り違え

### 修正方針
1. `apiUpdateTime` / `existingTime` を必ず `CarbonImmutable` に統一し UTCに揃える
2. 比較は必ず Carbon同士で行う
3. 判定は「api_update_time <= existing_time なら SKIP」「api_update_time > existing_time のときのみ UPSERT」

### 実装内容

#### 1. CarbonImmutable のインポート追加

```php
use Carbon\CarbonImmutable;
```

#### 2. API側の時刻パース（CarbonImmutable統一）

**修正前**:
```php
$createTime = Carbon::parse($createTimeRaw)->utc();
$updateTime = Carbon::parse($updateTimeRaw)->utc();
$effectiveUpdateTime = $updateTime ?? $createTime;
```

**修正後**:
```php
// APIの時間は ISO8601 形式なので parse でOK
$createTime = CarbonImmutable::parse($createTimeRaw, 'UTC')->utc();
$updateTime = CarbonImmutable::parse($updateTimeRaw, 'UTC')->utc();
$apiUpdateTime = $updateTime ?? $createTime; // CarbonImmutable統一
```

#### 3. 既存側の時刻パース（CarbonImmutable統一）

**修正前**:
```php
if ($existingReview->update_time) {
    $existingTimeRaw = $existingReview->update_time instanceof \Carbon\Carbon
        ? $existingReview->update_time->format('Y-m-d H:i:s')
        : (string)$existingReview->update_time;
    $existingTime = Carbon::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC');
} elseif ($existingReview->create_time) {
    $existingTimeRaw = $existingReview->create_time instanceof \Carbon\Carbon
        ? $existingReview->create_time->format('Y-m-d H:i:s')
        : (string)$existingReview->create_time;
    $existingTime = Carbon::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC');
}
```

**修正後**:
```php
// 既存側: DBの update_time/create_time は 'Y-m-d H:i:s' 形式なので createFromFormat で厳密に読む
$existingRaw = $existingReview->update_time ?? $existingReview->create_time;
if ($existingRaw) {
    try {
        if ($existingRaw instanceof \Carbon\Carbon) {
            // Carbon型の場合は format してから createFromFormat
            $existingTimeRaw = $existingRaw->format('Y-m-d H:i:s');
        } else {
            // 文字列の場合はそのまま
            $existingTimeRaw = (string)$existingRaw;
        }
        // DBの update_time/create_time は 'Y-m-d H:i:s' 形式なので createFromFormat で厳密に読む
        $existingTime = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC')->utc();
    } catch (\Exception $e) {
        Log::warning('ReviewSyncService: 既存時刻のパースに失敗', [...]);
        // パース失敗時は既存時刻を null として扱う（新規レコード扱い）
        $existingTime = null;
    }
}
```

#### 4. 比較ロジックの修正（Carbon同士で比較）

**修正前**:
```php
if ($existingTime && $effectiveUpdateTime->lte($existingTime)) {
    $shouldSkip = true;
}
```

**修正後**:
```php
// 判定: api_update_time <= existing_time なら SKIP、api_update_time > existing_time のときのみ UPSERT
if ($existingTime && $apiUpdateTime->lessThanOrEqualTo($existingTime)) {
    $shouldSkip = true;
}
```

#### 5. hasChanges 判定の修正

**修正前**:
```php
if ($existingTime && $effectiveUpdateTime->gt($existingTime)) {
    $hasChanges = true;
    $changeReasons[] = 'update_time_gt';
}
```

**修正後**:
```php
// 判定: api_update_time > existing_time のときのみ UPSERT
if ($existingTime && $apiUpdateTime->greaterThan($existingTime)) {
    $hasChanges = true;
    $changeReasons[] = 'update_time_gt';
}
```

#### 6. デバッグログの追加

**追加ログ項目**:
- `api_time_iso8601`: API側の時刻（ISO8601形式）
- `existing_time_iso8601`: 既存側の時刻（ISO8601形式）
- `api_timestamp`: API側の時刻（Unix timestamp）
- `existing_timestamp`: 既存側の時刻（Unix timestamp）

**ログ例**:
```php
Log::info('REVIEWS_DIFF_DECISION', [
    'shop_id' => $shop->id,
    'gbp_review_id' => $reviewId,
    'api_update_time' => $apiUpdateTime->format('Y-m-d H:i:s'),
    'existing_update_time' => $existingTime ? $existingTime->format('Y-m-d H:i:s') : null,
    'api_time_iso8601' => $apiUpdateTime->toIso8601String(),
    'existing_time_iso8601' => $existingTime ? $existingTime->toIso8601String() : null,
    'api_timestamp' => $apiUpdateTime->timestamp,
    'existing_timestamp' => $existingTime ? $existingTime->timestamp : null,
    'compare_api_lte_existing' => $existingTime ? $apiUpdateTime->lessThanOrEqualTo($existingTime) : false,
    // ...
]);
```

### 修正箇所まとめ

1. **`app/Services/ReviewSyncService.php:9`**
   - `use Carbon\CarbonImmutable;` を追加

2. **`app/Services/ReviewSyncService.php:130-174`**
   - API側の時刻パースを `CarbonImmutable` に統一
   - `$effectiveUpdateTime` → `$apiUpdateTime` に変数名変更

3. **`app/Services/ReviewSyncService.php:176-199`**
   - 既存側の時刻パースを `CarbonImmutable` に統一
   - エラーハンドリングを追加

4. **`app/Services/ReviewSyncService.php:196`**
   - 比較ロジックを `lessThanOrEqualTo()` に変更

5. **`app/Services/ReviewSyncService.php:296`**
   - hasChanges判定を `greaterThan()` に変更

6. **`app/Services/ReviewSyncService.php:201-237, 300-330, 332-350`**
   - デバッグログに `api_time_iso8601`, `existing_time_iso8601`, `api_timestamp`, `existing_timestamp` を追加

7. **`app/Services/ReviewSyncService.php:283, 277`**
   - `$effectiveUpdateTime` → `$apiUpdateTime` に変数名変更

## 検証手順

### 1. 同期を実行

Web UIから同期ボタンを押す

### 2. ログを確認

```bash
# REVIEWS_DIFF_DECISION ログを抽出
grep "REVIEWS_DIFF_DECISION" storage/logs/laravel.log | tail -20

# 同値なのに判定が逆になっていないか確認
grep "REVIEWS_DIFF_DECISION" storage/logs/laravel.log | grep -A 5 "api_timestamp" | tail -20
```

### 3. ログから原因を特定

**確認ポイント**:
1. **`api_timestamp` と `existing_timestamp` が同じ場合**:
   - `compare_api_lte_existing` が `true` であることを確認
   - `decision` が `SKIP` であることを確認
   - `reason` が `update_time_lte_existing` であることを確認

2. **`api_timestamp` と `existing_timestamp` が異なる場合**:
   - `api_timestamp` > `existing_timestamp` の場合、`decision` が `UPSERT` で `reason` が `update_time_gt` であることを確認
   - `api_timestamp` < `existing_timestamp` の場合、`decision` が `SKIP` で `reason` が `update_time_lte_existing` であることを確認

3. **同値なのに判定が逆になっている場合**:
   - `api_time_iso8601` と `existing_time_iso8601` を比較
   - `api_timestamp` と `existing_timestamp` を比較
   - タイムゾーンの問題がないか確認

## 期待される結果

### 正常な場合（修正後）

**同値の場合**:
- `api_timestamp` = `existing_timestamp`
- `compare_api_lte_existing` = `true`
- `decision` = `SKIP`
- `reason` = `update_time_lte_existing`

**API側が新しい場合**:
- `api_timestamp` > `existing_timestamp`
- `compare_api_lte_existing` = `false`
- `decision` = `UPSERT`
- `reason` = `update_time_gt`

**API側が古い場合**:
- `api_timestamp` < `existing_timestamp`
- `compare_api_lte_existing` = `true`
- `decision` = `SKIP`
- `reason` = `update_time_lte_existing`

### 問題がある場合（修正前）

**同値なのに判定が逆**:
- `api_timestamp` = `existing_timestamp` なのに
- `compare_api_lte_existing` = `false`
- `decision` = `UPSERT`
- `reason` = `update_time_gt`

## 完了条件

✅ `apiUpdateTime` / `existingTime` を必ず `CarbonImmutable` に統一  
✅ UTCに揃える  
✅ 比較は必ず Carbon同士で行う  
✅ 判定は「api_update_time <= existing_time なら SKIP」「api_update_time > existing_time のときのみ UPSERT」  
✅ デバッグログを追加（`api_time_iso8601`, `existing_time_iso8601`, `api_timestamp`, `existing_timestamp`）  
✅ DBの update_time/create_time は `createFromFormat` で厳密に読む  
✅ APIの時間は `parse(...,'UTC')` で読む  

**検証**: 上記の検証手順に従って、同値なのに判定が逆になっていないことを確認








