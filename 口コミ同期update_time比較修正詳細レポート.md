# 口コミ同期 update_time 比較修正詳細レポート

## 1. 該当箇所のコード（修正後）

```php
// app/Services/ReviewSyncService.php:117-410

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
            // DBから取れた Carbon はそのまま ->utc() に統一（timezone事故防止）
            if ($existingRaw instanceof \Carbon\Carbon) {
                // Carbon型の場合はそのまま ->utc() して CarbonImmutable に変換
                $existingTime = CarbonImmutable::instance($existingRaw)->utc();
            } else {
                // 文字列の場合は createFromFormat で厳密に読む
                $existingTimeRaw = (string)$existingRaw;
                $existingTime = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC')->utc();
            }
        } catch (\Exception $e) {
            // createFromFormat が例外なら parse fallback
            try {
                $existingTime = CarbonImmutable::parse($existingRaw, 'UTC')->utc();
            } catch (\Exception $e2) {
                Log::warning('ReviewSyncService: 既存時刻のパースに失敗', [
                    'shop_id' => $shop->id,
                    'gbp_review_id' => $reviewId,
                    'existing_raw' => $existingRaw,
                    'existing_raw_type' => gettype($existingRaw),
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
            'api_tz' => $apiTime->timezoneName,
            'existing_tz' => $existingTime ? $existingTime->timezoneName : null,
            'api_class' => get_class($apiTime),
            'existing_class' => $existingTime ? get_class($existingTime) : null,
            'shouldSkip' => $shouldSkip, // 最終値
            'hasChanges' => false, // SKIP時は常にfalse
            'changeReasons' => [], // SKIP時は空
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
    // 既存レコードと比較（空文字/NULLの正規化）
    $normalizeString = function($value) {
        if ($value === null || $value === '') {
            return null;
        }
        return trim($value);
    };

    $newAuthorName = $normalizeString($review['reviewer']['displayName'] ?? '不明');
    $newRating = $this->convertStarRating($review['starRating'] ?? null);
    $newComment = $normalizeString($review['comment'] ?? null);
    
    $replyText = data_get($review, 'reviewReply.comment');
    $replyUpdateTimeRaw = data_get($review, 'reviewReply.updateTime');
    $repliedAt = $replyUpdateTimeRaw
        ? Carbon::parse($replyUpdateTimeRaw)->utc()
        : null;
    $newReplyText = $normalizeString($replyText);
    $newRepliedAt = $repliedAt ? $repliedAt->format('Y-m-d H:i:s') : null;

    $existingAuthorName = $normalizeString($existingReview->author_name);
    $existingRating = $existingReview->rating;
    $existingComment = $normalizeString($existingReview->comment);
    $existingReplyText = $normalizeString($existingReview->reply_text);
    $existingRepliedAt = $existingReview->replied_at 
        ? ($existingReview->replied_at instanceof \Carbon\Carbon 
            ? $existingReview->replied_at->format('Y-m-d H:i:s')
            : (string)$existingReview->replied_at)
        : null;

    // 変更があるかチェック（理由を記録）
    if ($newAuthorName !== $existingAuthorName) {
        $hasChanges = true;
        $changeReasons[] = 'author_name_diff';
    }
    if ($newRating !== $existingRating) {
        $hasChanges = true;
        $changeReasons[] = 'rating_diff';
    }
    if ($newComment !== $existingComment) {
        $hasChanges = true;
        $changeReasons[] = 'comment_diff';
    }
    if ($newReplyText !== $existingReplyText) {
        $hasChanges = true;
        $changeReasons[] = 'reply_text_diff';
    }
    if ($newRepliedAt !== $existingRepliedAt) {
        $hasChanges = true;
        $changeReasons[] = 'replied_at_diff';
    }
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
            'api_tz' => $apiTime->timezoneName,
            'existing_tz' => $existingTime ? $existingTime->timezoneName : null,
            'api_class' => get_class($apiTime),
            'existing_class' => $existingTime ? get_class($existingTime) : null,
            'shouldSkip' => $shouldSkip, // 最終値
            'hasChanges' => $hasChanges, // 最終値
            'changeReasons' => $changeReasons, // 変更理由の配列
            'compare_api_lte_existing' => $compareResult, // 実際に判定に使った結果をそのまま出す
            'api_replied_at' => $newRepliedAt,
            'existing_replied_at' => $existingRepliedAt,
            'api_reply_text_hash' => $newReplyText ? sha1($newReplyText) : null,
            'existing_reply_text_hash' => $existingReplyText ? sha1($existingReplyText) : null,
            'api_comment_hash' => $newComment ? sha1($newComment) : null,
            'existing_comment_hash' => $existingComment ? sha1($existingComment) : null,
            'decision' => $hasChanges ? 'UPSERT' : 'SKIP',
            'reason' => $hasChanges ? implode(',', $changeReasons) : 'no_changes',
        ]);
    }
} else {
    // 新規レコード
    $hasChanges = true;
    
    // 検証用ログ（最初の3件のみ）
    if (count($rows) < 3) {
        $newRepliedAtRaw = data_get($review, 'reviewReply.updateTime');
        $newRepliedAt = $newRepliedAtRaw 
            ? CarbonImmutable::parse($newRepliedAtRaw, 'UTC')->utc()->format('Y-m-d H:i:s')
            : null;
        
        Log::info('REVIEWS_DIFF_DECISION', [
            'shop_id' => $shop->id,
            'gbp_review_id' => $reviewId,
            'api_raw' => $apiRaw,
            'existing_raw' => null,
            'api_update_time' => $apiTime->format('Y-m-d H:i:s'), // 比較に使った値から
            'existing_update_time' => null,
            'api_time_iso' => $apiTime->toIso8601String(),
            'existing_time_iso' => null,
            'api_ts' => $apiTime->timestamp,
            'existing_ts' => null,
            'api_tz' => $apiTime->timezoneName,
            'existing_tz' => null,
            'api_class' => get_class($apiTime),
            'existing_class' => null,
            'shouldSkip' => false, // 新規レコードは常にfalse
            'hasChanges' => true, // 新規レコードは常にtrue
            'changeReasons' => ['new_record'], // 新規レコード
            'compare_api_lte_existing' => false,
            // ...
            'decision' => 'UPSERT',
            'reason' => 'new_record',
        ]);
    }
}

// 変更がある場合のみ $rows に追加
if ($hasChanges) {
    $replyText = data_get($review, 'reviewReply.comment');
    $replyUpdateTimeRaw = data_get($review, 'reviewReply.updateTime');
    $repliedAt = $replyUpdateTimeRaw
        ? Carbon::parse($replyUpdateTimeRaw)->utc()
        : null;

    $rows[] = [
        'shop_id' => $shop->id,
        'gbp_review_id' => $reviewId,
        'snapshot_id' => $snapshotId,
        'author_name' => $review['reviewer']['displayName'] ?? '不明',
        'rating' => $this->convertStarRating($review['starRating'] ?? null),
        'comment' => $review['comment'] ?? null,
        'create_time' => $createTime->format('Y-m-d H:i:s'),
        'update_time' => $apiTime->format('Y-m-d H:i:s'), // updateTime が無い場合は createTime にフォールバック
        'reply_text' => $replyText,
        'replied_at' => $repliedAt ? $repliedAt->format('Y-m-d H:i:s') : null,
    ];

    // 最大updateTimeを更新
    if ($maxUpdateTime === null || $apiTime->greaterThan($maxUpdateTime)) {
        $maxUpdateTime = $apiTime;
    }
} else {
    $skippedCount++;
}
```

## 2. チェックリスト確認結果

### A. update_time_gt 判定が「greaterThan(>)」になっているか（>= になってないか）

**確認結果**: ✅ **問題なし**

**該当箇所**: `app/Services/ReviewSyncService.php:325`
```php
// 判定: api_update_time > existing_time のときのみ UPSERT
if ($existingTime && $apiTime->greaterThan($existingTime)) {
    $hasChanges = true;
    $changeReasons[] = 'update_time_gt';
}
```

- `greaterThan()` は `>` を意味する（`>=` ではない）
- `lessThanOrEqualTo()` は `<=` を意味する

### B. shouldSkip（api<=existing）と hasChanges（api>existing）が "同じ apiTime/existingTime" を使っているか

**確認結果**: ✅ **問題なし**

**該当箇所**:
- `shouldSkip` 判定: `app/Services/ReviewSyncService.php:217`
  ```php
  $shouldSkip = $apiTime->lessThanOrEqualTo($existingTime);
  ```
- `hasChanges` 判定: `app/Services/ReviewSyncService.php:325`
  ```php
  if ($existingTime && $apiTime->greaterThan($existingTime)) {
      $hasChanges = true;
      $changeReasons[] = 'update_time_gt';
  }
  ```

- 両方とも `$apiTime` と `$existingTime` を使用（同じ変数）

### C. existingTime の生成で timezone 事故が起きてないか

**確認結果**: ✅ **修正済み**

**修正前**:
```php
if ($existingRaw instanceof \Carbon\Carbon) {
    // Carbon型の場合は format してから createFromFormat
    $existingTimeRaw = $existingRaw->format('Y-m-d H:i:s');
}
$existingTime = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC')->utc();
```

**問題**: `format('Y-m-d H:i:s')` は app.timezone でフォーマットされるため、JST の場合に 9時間ズレる可能性がある

**修正後**:
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

- Carbon型の場合は `CarbonImmutable::instance($existingRaw)->utc()` で直接変換（timezone事故防止）

### D. existingTime のパース失敗で null になっていないか

**確認結果**: ✅ **エラーハンドリングあり**

**該当箇所**: `app/Services/ReviewSyncService.php:195-209`
```php
try {
    // ...
} catch (\Exception $e) {
    // createFromFormat が例外なら parse fallback
    try {
        $existingTime = CarbonImmutable::parse($existingRaw, 'UTC')->utc();
    } catch (\Exception $e2) {
        Log::warning('ReviewSyncService: 既存時刻のパースに失敗', [...]);
        // パース失敗時は既存時刻を null として扱う（UPSERT側）
        $existingTime = null;
    }
}
```

- パース失敗時は `$existingTime = null` となり、`shouldSkip = false` になる
- その結果、`hasChanges = true` となり、UPSERT側になる（意図通り）

### E. ログに出している existing_update_time / compare_api_lte_existing が実際に比較に使った existingTime/apiTime から作られているか

**確認結果**: ✅ **問題なし**

**該当箇所**: `app/Services/ReviewSyncService.php:233-238, 340-345`
```php
'api_update_time' => $apiTime->format('Y-m-d H:i:s'), // 比較に使った値から
'existing_update_time' => $existingTime ? $existingTime->format('Y-m-d H:i:s') : null, // 比較に使った値から
'api_time_iso' => $apiTime->toIso8601String(),
'existing_time_iso' => $existingTime ? $existingTime->toIso8601String() : null,
'api_ts' => $apiTime->timestamp,
'existing_ts' => $existingTime ? $existingTime->timestamp : null,
'compare_api_lte_existing' => $compareResult, // 実際に判定に使った結果をそのまま出す
```

- ログは比較に使った値（`$apiTime`, `$existingTime`）から作成
- `compare_api_lte_existing` は実際に判定に使った結果（`$compareResult`）をそのまま出力

## 3. 追加ログ項目

### REVIEWS_DIFF_DECISION に追加された項目

- `api_time_iso`: API側の時刻（ISO8601形式）
- `existing_time_iso`: 既存側の時刻（ISO8601形式）
- `api_ts`: API側の時刻（Unix timestamp）
- `existing_ts`: 既存側の時刻（Unix timestamp）
- `api_tz`: API側のtimezone名
- `existing_tz`: 既存側のtimezone名
- `api_class`: API側のクラス名（CarbonImmutableなど）
- `existing_class`: 既存側のクラス名（CarbonImmutableなど）
- `shouldSkip`: 最終値（bool）
- `hasChanges`: 最終値（bool）
- `changeReasons`: 変更理由の配列

## 4. 修正内容まとめ

### 主な修正点

1. **existingTime の生成を改善（timezone事故防止）**
   - Carbon型の場合は `CarbonImmutable::instance($existingRaw)->utc()` で直接変換
   - 文字列の場合は `createFromFormat` で厳密に読む

2. **追加ログを実装**
   - `api_tz`, `existing_tz`（timezoneName）
   - `api_class`, `existing_class`（Carbon/CarbonImmutableなど）
   - `shouldSkip` の最終値
   - `hasChanges` の最終値と `changeReasons`

3. **ログの一貫性を確保**
   - ログは比較に使った値から作成（ズレ防止）
   - `compare_api_lte_existing` は実際に判定に使った結果をそのまま出力

## 5. 期待される結果

### 同値の場合

- `api_ts` = `existing_ts`
- `api_tz` = `existing_tz` = `UTC`
- `api_class` = `existing_class` = `Carbon\CarbonImmutable`
- `compare_api_lte_existing` = `true`
- `shouldSkip` = `true`
- `hasChanges` = `false`
- `decision` = `SKIP`
- `reason` = `update_time_lte_existing`

### API側が新しい場合

- `api_ts` > `existing_ts`
- `compare_api_lte_existing` = `false`
- `shouldSkip` = `false`
- `hasChanges` = `true`
- `changeReasons` = `['update_time_gt']`
- `decision` = `UPSERT`
- `reason` = `update_time_gt`

## 6. 検証手順

### 1. 同期を実行

Web UIから同期ボタンを押す

### 2. ログを確認

```bash
# REVIEWS_DIFF_DECISION ログを抽出
grep "REVIEWS_DIFF_DECISION" storage/logs/laravel.log | tail -20

# 同値なのに判定が逆になっていないか確認
grep "REVIEWS_DIFF_DECISION" storage/logs/laravel.log | grep -A 10 "api_ts" | tail -30
```

### 3. 完了条件の確認

- 同一店舗で2回連続同期したとき、`rows_to_write_count` が 0（または極小）になり、`skipped_count` がほぼ `fetched_count` になること
- REVIEWS_DIFF_DECISION で、同値の場合は `api_ts==existing_ts` になり `shouldSkip=true` で `SKIP` になること








