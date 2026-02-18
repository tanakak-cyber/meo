-- 口コミ同期 snapshot_id 検証用SQL
-- 検証観点C: DBでtinkerで確定

-- 1) 同期前に各shopの snapshot_id のばらつきを確認
SELECT 
    shop_id,
    COUNT(*) AS cnt,
    COUNT(DISTINCT snapshot_id) AS distinct_snapshots,
    GROUP_CONCAT(DISTINCT snapshot_id ORDER BY snapshot_id SEPARATOR ', ') AS snapshot_ids
FROM reviews
WHERE shop_id IS NOT NULL
GROUP BY shop_id
ORDER BY shop_id;

-- 2) 特定のshop_idで詳細確認（例: shop_id = 1）
SELECT 
    id,
    shop_id,
    gbp_review_id,
    snapshot_id,
    author_name,
    rating,
    create_time,
    update_time,
    updated_at
FROM reviews
WHERE shop_id = 1  -- 検証したいshop_idに変更
ORDER BY gbp_review_id;

-- 3) 同期実行前後の snapshot_id 変化を確認
-- 同期実行前の時刻をメモ: 例 '2024-01-01 12:00:00'
-- 同期実行後の時刻をメモ: 例 '2024-01-01 12:05:00'

-- 同期実行後に更新されたレコードの snapshot_id を確認
SELECT 
    shop_id,
    gbp_review_id,
    snapshot_id,
    updated_at
FROM reviews
WHERE updated_at >= '2024-01-01 12:00:00'  -- 同期実行前の時刻に変更
  AND updated_at <= '2024-01-01 12:05:00'  -- 同期実行後の時刻に変更
ORDER BY shop_id, updated_at DESC;

-- 4) 同一gbp_review_idで snapshot_id が変化しているか確認
SELECT 
    shop_id,
    gbp_review_id,
    COUNT(DISTINCT snapshot_id) AS snapshot_changes,
    GROUP_CONCAT(DISTINCT snapshot_id ORDER BY snapshot_id SEPARATOR ' -> ') AS snapshot_history
FROM reviews
WHERE shop_id IS NOT NULL
GROUP BY shop_id, gbp_review_id
HAVING COUNT(DISTINCT snapshot_id) > 1  -- snapshot_idが変化しているレコードのみ
ORDER BY shop_id, gbp_review_id;

-- 5) 2回連続同期後の snapshot_id 分布確認
-- 同期を1回→もう1回実行後、distinct_snapshots が増える（or snapshot_idが大量に変化する）なら、
-- snapshot_id が"現物reviews"の更新に使われている可能性が高い
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
HAVING COUNT(DISTINCT snapshot_id) > 1  -- snapshot_idが複数あるshopのみ
ORDER BY distinct_snapshots DESC, shop_id;








