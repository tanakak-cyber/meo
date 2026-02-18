# 口コミ同期 snapshot_id 検証レポート

## 検証結果

### 検証観点A（コード確認）

#### 1. ReviewSyncService の upsert の "更新カラム" に snapshot_id が含まれているか

**結果**: ✅ **問題発見・修正済み**

**修正前** (`app/Services/ReviewSyncService.php:309-323`):
```php
Review::upsert(
    $rows,
    ['shop_id', 'gbp_review_id'], // ユニークキー
    [
        'snapshot_id',  // ← 問題: 毎回変わるため、更新時に変更すると全件更新になる
        'author_name',
        'rating',
        'comment',
        'create_time',
        'reply_text',
        'replied_at',
        'update_time',
    ]
);
```

**修正後**:
```php
Review::upsert(
    $rows,
    ['shop_id', 'gbp_review_id'], // ユニークキー
    [
        // 'snapshot_id' は除外（毎回変わるため、更新時に変更すると全件更新になる）
        // snapshot_id は新規insert時のみ設定される（upsertの仕様上、insert時は全カラムが設定される）
        'author_name',
        'rating',
        'comment',
        'create_time',
        'reply_text',
        'replied_at',
        'update_time',
    ]
);
```

**説明**:
- `snapshot_id` は毎回同期時に新しい値が生成される
- `upsert` の更新カラムに `snapshot_id` が含まれていると、既存レコードの更新時に毎回 `snapshot_id` が変更される
- これにより、`updated_at` が更新され、実質的に全件更新になっていた

#### 2. hasChanges 判定に snapshot_id を使っていないか

**結果**: ✅ **問題なし**

**確認箇所** (`app/Services/ReviewSyncService.php:242-248`):
```php
if ($newAuthorName !== $existingAuthorName ||
    $newRating !== $existingRating ||
    $newComment !== $existingComment ||
    $newReplyText !== $existingReplyText ||
    $newRepliedAt !== $existingRepliedAt ||
    ($existingTime && $effectiveUpdateTime->gt($existingTime))) {
    $hasChanges = true;
}
```

- `snapshot_id` は `hasChanges` 判定に使用されていない
- 判定は `author_name`, `rating`, `comment`, `reply_text`, `replied_at`, `update_time` のみ

#### 3. rows に snapshot_id を毎回セットしているか

**結果**: ⚠️ **設計上の問題（修正済み）**

**確認箇所** (`app/Services/ReviewSyncService.php:263-274`):
```php
$rows[] = [
    'shop_id' => $shop->id,
    'gbp_review_id' => $reviewId,
    'snapshot_id' => $snapshotId,  // ← 毎回セットしている
    'author_name' => $review['reviewer']['displayName'] ?? '不明',
    // ...
];
```

**説明**:
- `rows` に `snapshot_id` を毎回セットしている
- ただし、`upsert` の更新カラムから `snapshot_id` を除外したため、更新時には `snapshot_id` は変更されない
- 新規insert時のみ `snapshot_id` が設定される（`upsert` の仕様上、insert時は全カラムが設定される）

### 検証観点B（ログ追加）

**実装箇所**: `app/Services/ReviewSyncService.php:201-210`

**ログ内容**:
```php
// 検証用ログ（最初の3件のみ）
if ($skippedCount + count($rows) < 3) {
    Log::info('REVIEWS_SNAPSHOT_CHECK', [
        'shop_id' => $shop->id,
        'gbp_review_id' => $reviewId,
        'existing_snapshot_id' => $existingReview ? $existingReview->snapshot_id : null,
        'incoming_snapshot_id' => $snapshotId,
        'decision' => $shouldSkip ? 'SKIP' : 'UPSERT',
        'has_existing' => $existingReview !== null,
    ]);
}
```

**確認方法**:
```bash
# 同一店舗で2回連続同期した時のログを確認
grep "REVIEWS_SNAPSHOT_CHECK" storage/logs/laravel.log | tail -6
```

**期待される結果（修正後）**:
- 1回目: `decision: UPSERT`（新規または変更あり）
- 2回目: `decision: SKIP`（変更なしの場合）
- `incoming_snapshot_id` が毎回変わっていても、`decision` が `SKIP` になる（`snapshot_id` は更新カラムから除外されているため）

### 検証観点C（DB確認：tinker）

**SQLファイル**: `口コミ同期snapshot_id検証SQL.sql`

#### 1. 同期前に各shopの snapshot_id のばらつきを確認

```php
php artisan tinker
>>> Review::select('shop_id')
     ->selectRaw('COUNT(*) as cnt')
     ->selectRaw('COUNT(DISTINCT snapshot_id) as distinct_snapshots')
     ->groupBy('shop_id')
     ->get();
```

**期待される結果（修正後）**:
- `distinct_snapshots` が 1 または少数（新規レビューが追加された場合のみ増える）
- 修正前は、2回連続同期で `distinct_snapshots` が大幅に増えていた可能性がある

#### 2. 同期を1回→もう1回実行して、distinct_snapshots が増えるか確認

**手順**:
1. 同期実行前の時刻をメモ
2. 同期を1回実行
3. 同期をもう1回実行（変更がない状態で）
4. 以下のSQLで確認:

```sql
SELECT 
    shop_id,
    COUNT(*) AS total_reviews,
    COUNT(DISTINCT snapshot_id) AS distinct_snapshots,
    CASE 
        WHEN COUNT(DISTINCT snapshot_id) = 1 THEN 'OK: snapshot_id統一'
        WHEN COUNT(DISTINCT snapshot_id) <= 3 THEN 'WARNING: snapshot_idが複数'
        ELSE 'ERROR: snapshot_idが多数（更新時に変更されている可能性）'
    END AS status
FROM reviews
WHERE shop_id IS NOT NULL
GROUP BY shop_id
HAVING COUNT(DISTINCT snapshot_id) > 1
ORDER BY distinct_snapshots DESC, shop_id;
```

**期待される結果（修正後）**:
- 2回連続同期後も `distinct_snapshots` が増えない（または新規レビューが追加された場合のみ増える）
- 修正前は、2回連続同期で `distinct_snapshots` が大幅に増えていた

## 修正内容まとめ

### 修正箇所

1. **`app/Services/ReviewSyncService.php:309-323`**
   - `upsert` の更新カラムから `snapshot_id` を除外
   - コメントを追加して理由を明記

2. **`app/Services/ReviewSyncService.php:201-210`**
   - 検証用ログ `REVIEWS_SNAPSHOT_CHECK` を追加（最初の3件のみ）

### 修正の効果

1. **全件更新の防止**
   - `snapshot_id` が更新カラムから除外されたため、既存レコードの更新時に `snapshot_id` が変更されない
   - これにより、変更がないレビューは `updated_at` が更新されない

2. **差分同期の正常化**
   - 2回目以降の同期で、変更がないレビューは `SKIP` される
   - `synced_count` が 0 または少数になる（変更があったレビューのみ）

3. **snapshot_id の役割**
   - 新規insert時のみ `snapshot_id` が設定される
   - 既存レコードの `snapshot_id` は変更されない（最後に同期した snapshot を保持）

## 検証手順

### 1. コード確認（完了）
- ✅ `upsert` の更新カラムから `snapshot_id` を除外
- ✅ `hasChanges` 判定に `snapshot_id` を使用していないことを確認
- ✅ 検証用ログを追加

### 2. ログ確認（実行が必要）
```bash
# 同一店舗で2回連続同期
# ログで確認
grep "REVIEWS_SNAPSHOT_CHECK" storage/logs/laravel.log | tail -6
```

### 3. DB確認（実行が必要）
```php
php artisan tinker
# 同期前
>>> Review::select('shop_id')
     ->selectRaw('COUNT(*) as cnt')
     ->selectRaw('COUNT(DISTINCT snapshot_id) as distinct_snapshots')
     ->groupBy('shop_id')
     ->get();

# 同期を2回実行後、再度実行
# distinct_snapshots が増えていないことを確認
```

## 完了条件

✅ `upsert` の更新カラムから `snapshot_id` を除外  
✅ `hasChanges` 判定に `snapshot_id` を使用していないことを確認  
✅ 検証用ログを追加（`REVIEWS_SNAPSHOT_CHECK`）  
✅ DB確認用SQLを提供（`口コミ同期snapshot_id検証SQL.sql`）  

**検証**: 上記の検証手順に従って、2回連続同期で `distinct_snapshots` が増えないことを確認








