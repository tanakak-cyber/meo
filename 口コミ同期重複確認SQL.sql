-- ============================================
-- 口コミ同期重複確認SQL
-- ============================================

-- 1. 重複の実態確認（shop_id + gbp_review_id で重複してるか）
-- UNIQUE制約があるため、理論的には重複は発生しないが、制約が効いていない可能性を確認
SELECT 
    shop_id,
    gbp_review_id,
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(id ORDER BY id) as review_ids,
    MIN(created_at) as first_created,
    MAX(created_at) as last_created
FROM reviews
WHERE gbp_review_id IS NOT NULL
GROUP BY shop_id, gbp_review_id
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC, shop_id, gbp_review_id;

-- 2. 最新の重複例（どの口コミIDが何件に増えてるか）
-- 重複しているレビューの詳細情報
SELECT 
    r.id,
    r.shop_id,
    s.name as shop_name,
    r.gbp_review_id,
    r.snapshot_id,
    r.author_name,
    r.rating,
    r.create_time,
    r.update_time,
    r.created_at,
    r.updated_at
FROM reviews r
INNER JOIN shops s ON r.shop_id = s.id
WHERE (r.shop_id, r.gbp_review_id) IN (
    SELECT shop_id, gbp_review_id
    FROM reviews
    WHERE gbp_review_id IS NOT NULL
    GROUP BY shop_id, gbp_review_id
    HAVING COUNT(*) > 1
)
ORDER BY r.shop_id, r.gbp_review_id, r.created_at DESC;

-- 3. UNIQUE制約の確認（MySQLのSHOW INDEX等）
-- reviewsテーブルのインデックス一覧
SHOW INDEX FROM reviews;

-- UNIQUE制約の詳細確認
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    ORDINAL_POSITION,
    CONSTRAINT_TYPE
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
    ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
    AND tc.TABLE_SCHEMA = kcu.TABLE_SCHEMA
WHERE tc.TABLE_SCHEMA = DATABASE()
  AND tc.TABLE_NAME = 'reviews'
  AND tc.CONSTRAINT_TYPE = 'UNIQUE'
ORDER BY tc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION;

-- reviews_shop_id_gbp_review_id_unique の存在確認
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    ORDINAL_POSITION
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'reviews'
  AND CONSTRAINT_NAME = 'reviews_shop_id_gbp_review_id_unique'
ORDER BY ORDINAL_POSITION;

-- 4. 追加の検証SQL

-- 4-1. 同じgbp_review_idが異なるshop_idで存在するか確認
SELECT 
    gbp_review_id,
    COUNT(DISTINCT shop_id) as shop_count,
    GROUP_CONCAT(DISTINCT shop_id) as shop_ids,
    GROUP_CONCAT(DISTINCT CONCAT(shop_id, ':', id) ORDER BY shop_id) as shop_review_ids
FROM reviews
WHERE gbp_review_id IS NOT NULL
GROUP BY gbp_review_id
HAVING COUNT(DISTINCT shop_id) > 1
ORDER BY shop_count DESC, gbp_review_id;

-- 4-2. update_timeがnullのレビュー数
SELECT 
    COUNT(*) as total_count,
    COUNT(CASE WHEN update_time IS NULL THEN 1 END) as null_update_time_count,
    COUNT(CASE WHEN update_time IS NOT NULL THEN 1 END) as not_null_update_time_count,
    ROUND(COUNT(CASE WHEN update_time IS NULL THEN 1 END) * 100.0 / COUNT(*), 2) as null_percentage
FROM reviews;

-- 4-3. 最新の同期で更新されたレビュー（updated_atが最近）
SELECT 
    r.id,
    r.shop_id,
    s.name as shop_name,
    r.gbp_review_id,
    r.update_time,
    r.updated_at,
    TIMESTAMPDIFF(SECOND, r.update_time, r.updated_at) as time_diff_seconds,
    CASE 
        WHEN r.update_time IS NULL THEN 'update_time is NULL'
        WHEN TIMESTAMPDIFF(SECOND, r.update_time, r.updated_at) > 0 THEN 'updated_at is newer'
        WHEN TIMESTAMPDIFF(SECOND, r.update_time, r.updated_at) < 0 THEN 'update_time is newer'
        ELSE 'same'
    END as time_comparison
FROM reviews r
INNER JOIN shops s ON r.shop_id = s.id
WHERE r.updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY r.updated_at DESC
LIMIT 100;

-- 4-4. 同じgbp_review_idで複数のsnapshot_idが存在するか確認
SELECT 
    shop_id,
    gbp_review_id,
    COUNT(DISTINCT snapshot_id) as snapshot_count,
    GROUP_CONCAT(DISTINCT snapshot_id ORDER BY snapshot_id) as snapshot_ids,
    MIN(created_at) as first_created,
    MAX(created_at) as last_created
FROM reviews
WHERE gbp_review_id IS NOT NULL
GROUP BY shop_id, gbp_review_id
HAVING COUNT(DISTINCT snapshot_id) > 1
ORDER BY snapshot_count DESC, shop_id, gbp_review_id;

-- 4-5. 店舗ごとのレビュー数と重複可能性
SELECT 
    s.id as shop_id,
    s.name as shop_name,
    COUNT(r.id) as total_reviews,
    COUNT(DISTINCT r.gbp_review_id) as unique_gbp_review_ids,
    COUNT(r.id) - COUNT(DISTINCT r.gbp_review_id) as potential_duplicates,
    COUNT(CASE WHEN r.update_time IS NULL THEN 1 END) as null_update_time_count
FROM shops s
LEFT JOIN reviews r ON s.id = r.shop_id
WHERE s.gbp_location_id IS NOT NULL
GROUP BY s.id, s.name
HAVING potential_duplicates > 0 OR null_update_time_count > 0
ORDER BY potential_duplicates DESC, null_update_time_count DESC;

-- 4-6. 最新の同期実行履歴（snapshot_idから）
SELECT 
    gs.id as snapshot_id,
    gs.shop_id,
    s.name as shop_name,
    gs.synced_at,
    gs.reviews_count,
    COUNT(r.id) as actual_reviews_count,
    COUNT(DISTINCT r.gbp_review_id) as unique_gbp_review_ids
FROM gbp_snapshots gs
INNER JOIN shops s ON gs.shop_id = s.id
LEFT JOIN reviews r ON gs.id = r.snapshot_id
WHERE gs.synced_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY gs.id, gs.shop_id, s.name, gs.synced_at, gs.reviews_count
ORDER BY gs.synced_at DESC
LIMIT 50;









