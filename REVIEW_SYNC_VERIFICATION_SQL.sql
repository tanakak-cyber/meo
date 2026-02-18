-- 口コミ同期の差分同期検証用SQLクエリ

-- ============================================
-- 1. テスト用店舗の確認
-- ============================================
SELECT 
    id,
    name,
    gbp_location_id,
    gbp_account_id,
    last_reviews_synced_at,
    created_at,
    updated_at
FROM shops
WHERE gbp_location_id IS NOT NULL
  AND gbp_account_id IS NOT NULL
ORDER BY id
LIMIT 5;

-- ============================================
-- 2. 1回目の同期実行前の状態確認
-- ============================================
-- 現在時刻をメモ（この時刻を @sync_start_time に設定）
SELECT NOW() AS sync_start_time_1st;

-- 現在のレビュー数を確認
SELECT 
    shop_id,
    COUNT(*) AS current_reviews_count,
    COUNT(CASE WHEN update_time IS NULL THEN 1 END) AS null_update_time_count,
    COUNT(CASE WHEN update_time IS NOT NULL THEN 1 END) AS not_null_update_time_count
FROM reviews
WHERE shop_id = 1  -- テスト用店舗IDに置き換え
GROUP BY shop_id;

-- last_reviews_synced_at を確認
SELECT 
    id,
    name,
    last_reviews_synced_at,
    TIMESTAMPDIFF(SECOND, last_reviews_synced_at, NOW()) AS seconds_since_last_sync
FROM shops
WHERE id = 1;  -- テスト用店舗IDに置き換え

-- ============================================
-- 3. 1回目の同期実行後の確認
-- ============================================
-- 同期実行前の時刻を設定（Step 2でメモした時刻）
SET @sync_start_time_1st = '2024-01-15 10:00:00';  -- 実際の時刻に置き換え

-- 更新されたレコード数（1回目）
SELECT 
    COUNT(*) AS updated_rows_1st,
    shop_id
FROM reviews
WHERE shop_id = 1  -- テスト用店舗IDに置き換え
  AND updated_at >= @sync_start_time_1st
GROUP BY shop_id;

-- 新規追加されたレコード数（1回目）
SELECT 
    COUNT(*) AS created_rows_1st,
    shop_id
FROM reviews
WHERE shop_id = 1  -- テスト用店舗IDに置き換え
  AND created_at >= @sync_start_time_1st
GROUP BY shop_id;

-- last_reviews_synced_at の更新確認（1回目）
SELECT 
    id,
    name,
    last_reviews_synced_at,
    TIMESTAMPDIFF(SECOND, @sync_start_time_1st, last_reviews_synced_at) AS seconds_after_sync_start
FROM shops
WHERE id = 1;  -- テスト用店舗IDに置き換え

-- ============================================
-- 4. 2回目の同期実行前の状態確認
-- ============================================
-- 現在時刻をメモ（この時刻を @sync_start_time_2nd に設定）
SELECT NOW() AS sync_start_time_2nd;

-- レビュー数の変化確認
SELECT 
    shop_id,
    COUNT(*) AS reviews_count_before_2nd_sync
FROM reviews
WHERE shop_id = 1  -- テスト用店舗IDに置き換え
GROUP BY shop_id;

-- ============================================
-- 5. 2回目の同期実行後の確認
-- ============================================
-- 同期実行前の時刻を設定（Step 4でメモした時刻）
SET @sync_start_time_2nd = '2024-01-15 10:05:00';  -- 実際の時刻に置き換え

-- 更新されたレコード数（2回目）
SELECT 
    COUNT(*) AS updated_rows_2nd,
    shop_id
FROM reviews
WHERE shop_id = 1  -- テスト用店舗IDに置き換え
  AND updated_at >= @sync_start_time_2nd
GROUP BY shop_id;

-- 新規追加されたレコード数（2回目）
SELECT 
    COUNT(*) AS created_rows_2nd,
    shop_id
FROM reviews
WHERE shop_id = 1  -- テスト用店舗IDに置き換え
  AND created_at >= @sync_start_time_2nd
GROUP BY shop_id;

-- last_reviews_synced_at の更新確認（2回目）
SELECT 
    id,
    name,
    last_reviews_synced_at,
    TIMESTAMPDIFF(SECOND, @sync_start_time_2nd, last_reviews_synced_at) AS seconds_after_sync_start_2nd
FROM shops
WHERE id = 1;  -- テスト用店舗IDに置き換え

-- ============================================
-- 6. update_time=null のレビューを確認
-- ============================================
SELECT 
    shop_id,
    COUNT(*) AS total_reviews,
    COUNT(CASE WHEN update_time IS NULL THEN 1 END) AS null_update_time_count,
    COUNT(CASE WHEN update_time IS NOT NULL THEN 1 END) AS not_null_update_time_count,
    ROUND(COUNT(CASE WHEN update_time IS NULL THEN 1 END) * 100.0 / COUNT(*), 2) AS null_update_time_percentage
FROM reviews
WHERE shop_id = 1  -- テスト用店舗IDに置き換え
GROUP BY shop_id;

-- update_time=null のレビューのサンプル
SELECT 
    id,
    shop_id,
    gbp_review_id,
    create_time,
    update_time,
    created_at,
    updated_at
FROM reviews
WHERE shop_id = 1  -- テスト用店舗IDに置き換え
  AND update_time IS NULL
LIMIT 10;

-- ============================================
-- 7. 差分同期の判定（1回目 vs 2回目）
-- ============================================
-- 1回目と2回目の更新件数を比較
SELECT 
    '1st sync' AS sync_round,
    COUNT(*) AS updated_rows
FROM reviews
WHERE shop_id = 1  -- テスト用店舗IDに置き換え
  AND updated_at >= @sync_start_time_1st
  AND updated_at < @sync_start_time_2nd

UNION ALL

SELECT 
    '2nd sync' AS sync_round,
    COUNT(*) AS updated_rows
FROM reviews
WHERE shop_id = 1  -- テスト用店舗IDに置き換え
  AND updated_at >= @sync_start_time_2nd;

-- ============================================
-- 8. 全件更新の疑いがある場合の確認
-- ============================================
-- 2回目の同期で更新されたレビューのupdate_time分布
SELECT 
    DATE(update_time) AS update_date,
    COUNT(*) AS count
FROM reviews
WHERE shop_id = 1  -- テスト用店舗IDに置き換え
  AND updated_at >= @sync_start_time_2nd
GROUP BY DATE(update_time)
ORDER BY update_date DESC;









