# 口コミ同期の差分同期検証 - テスト手順

## テスト実行前の準備

### 1. ログファイルの確認
```bash
# Laravelのログファイルを確認
tail -f storage/logs/laravel.log
```

### 2. テスト用店舗の確認
```sql
-- テスト用店舗を確認（gbp_location_id と gbp_account_id が設定されている店舗）
SELECT id, name, gbp_location_id, gbp_account_id, last_reviews_synced_at
FROM shops
WHERE gbp_location_id IS NOT NULL
  AND gbp_account_id IS NOT NULL
LIMIT 1;
```

## テスト実行手順

### Step 1: 1回目の同期実行前の状態確認

```sql
-- 現在時刻をメモ（例: 2024-01-15 10:00:00）
SELECT NOW() AS sync_start_time;

-- 現在のレビュー数を確認
SELECT COUNT(*) AS current_reviews_count
FROM reviews
WHERE shop_id = 1; -- テスト用店舗IDに置き換え

-- last_reviews_synced_at を確認
SELECT id, name, last_reviews_synced_at
FROM shops
WHERE id = 1; -- テスト用店舗IDに置き換え
```

### Step 2: 1回目の同期実行

**方法1: Web UIから実行**
- `/reviews` または `/operator/reviews` にアクセス
- 店舗を選択して「口コミ・写真・投稿同期」ボタンをクリック

**方法2: コマンドラインから実行（テスト用）**
```bash
# 直接コントローラを呼び出す（テスト用）
php artisan tinker
>>> $shop = App\Models\Shop::find(1);
>>> $controller = new App\Http\Controllers\ReviewsController();
>>> $request = new Illuminate\Http\Request(['shop_id' => 1]);
>>> $controller->sync($request);
```

### Step 3: 1回目の同期後のログ確認

**確認すべきログキー**:
1. `REVIEW_SYNC_API_REQUEST_START` - APIリクエスト開始
2. `REVIEW_SYNC_API_REQUEST_END` - APIリクエスト終了
3. `SYNC_REVIEWS_API_COUNT` - API取得件数
4. `REVIEWS_UPSERT_EXECUTED` - DB書き込み結果
5. `REVIEW_SAFE_INCREMENTAL_SYNC_END` - 同期終了

**ログから確認すべき値**:
- `fetched_count`: APIから取得したレビュー件数
- `upsert_inserted_count`: 新規追加数
- `upsert_updated_count`: 更新数
- `skipped_count`: スキップ数
- `total_db_write_count`: DB書き込み総数

### Step 4: 1回目の同期後のSQL確認

```sql
-- 同期実行前の時刻を指定（Step 1でメモした時刻）
SET @sync_start_time = '2024-01-15 10:00:00'; -- 実際の時刻に置き換え

-- 更新されたレコード数
SELECT COUNT(*) AS updated_rows
FROM reviews
WHERE shop_id = 1 -- テスト用店舗IDに置き換え
  AND updated_at >= @sync_start_time;

-- 新規追加されたレコード数
SELECT COUNT(*) AS created_rows
FROM reviews
WHERE shop_id = 1 -- テスト用店舗IDに置き換え
  AND created_at >= @sync_start_time;

-- last_reviews_synced_at の更新確認
SELECT id, name, last_reviews_synced_at
FROM shops
WHERE id = 1; -- テスト用店舗IDに置き換え
```

### Step 5: 2回目の同期実行（間を空けずに）

**注意**: 1回目の同期完了後、すぐに2回目を実行（レビューが増えていない状態で）

### Step 6: 2回目の同期後のログ確認

**1回目と2回目を比較**:
- `fetched_count(1回目)` vs `fetched_count(2回目)`
- `total_db_write_count(1回目)` vs `total_db_write_count(2回目)`
- `skipped_count(1回目)` vs `skipped_count(2回目)`

### Step 7: 2回目の同期後のSQL確認

```sql
-- 2回目の同期実行前の時刻を指定
SET @sync_start_time_2 = '2024-01-15 10:05:00'; -- 実際の時刻に置き換え

-- 更新されたレコード数（2回目）
SELECT COUNT(*) AS updated_rows_2nd
FROM reviews
WHERE shop_id = 1 -- テスト用店舗IDに置き換え
  AND updated_at >= @sync_start_time_2;

-- 新規追加されたレコード数（2回目）
SELECT COUNT(*) AS created_rows_2nd
FROM reviews
WHERE shop_id = 1 -- テスト用店舗IDに置き換え
  AND created_at >= @sync_start_time_2;
```

## 判定基準

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

## ログの抽出方法

### 特定のログキーを抽出

```bash
# REVIEW_SYNC_API_REQUEST_END を抽出
grep "REVIEW_SYNC_API_REQUEST_END" storage/logs/laravel.log | tail -2

# REVIEWS_UPSERT_EXECUTED を抽出
grep "REVIEWS_UPSERT_EXECUTED" storage/logs/laravel.log | tail -2

# REVIEW_SAFE_INCREMENTAL_SYNC_END を抽出
grep "REVIEW_SAFE_INCREMENTAL_SYNC_END" storage/logs/laravel.log | tail -2
```

### JSON形式で整形

```bash
# jq がインストールされている場合
grep "REVIEW_SAFE_INCREMENTAL_SYNC_END" storage/logs/laravel.log | tail -2 | jq -r '.message'
```









