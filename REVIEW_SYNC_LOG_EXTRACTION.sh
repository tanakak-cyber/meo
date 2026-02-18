#!/bin/bash
# 口コミ同期の差分同期検証用ログ抽出スクリプト

LOG_FILE="storage/logs/laravel.log"
SHOP_ID=${1:-1}  # デフォルトは shop_id=1

echo "============================================"
echo "口コミ同期の差分同期検証 - ログ抽出"
echo "============================================"
echo ""

# 1. API取得関連のログ
echo "【1】API取得関連のログ"
echo "--------------------------------------------"
echo "REVIEW_SYNC_API_REQUEST_START:"
grep "REVIEW_SYNC_API_REQUEST_START" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -2 | jq -r '.message' 2>/dev/null || grep "REVIEW_SYNC_API_REQUEST_START" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -2
echo ""

echo "REVIEW_SYNC_API_REQUEST_END:"
grep "REVIEW_SYNC_API_REQUEST_END" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -2 | jq -r '.message' 2>/dev/null || grep "REVIEW_SYNC_API_REQUEST_END" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -2
echo ""

echo "SYNC_REVIEWS_API_COUNT:"
grep "SYNC_REVIEWS_API_COUNT" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -2 | jq -r '.message' 2>/dev/null || grep "SYNC_REVIEWS_API_COUNT" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -2
echo ""

# 2. DB保存関連のログ
echo "【2】DB保存関連のログ"
echo "--------------------------------------------"
echo "REVIEWS_UPSERT_EXECUTED:"
grep "REVIEWS_UPSERT_EXECUTED" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -2 | jq -r '.message' 2>/dev/null || grep "REVIEWS_UPSERT_EXECUTED" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -2
echo ""

# 3. 同期終了ログ
echo "【3】同期終了ログ"
echo "--------------------------------------------"
echo "REVIEW_SAFE_INCREMENTAL_SYNC_END:"
grep "REVIEW_SAFE_INCREMENTAL_SYNC_END" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -2 | jq -r '.message' 2>/dev/null || grep "REVIEW_SAFE_INCREMENTAL_SYNC_END" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -2
echo ""

# 4. スキップ判定のログ（サンプル）
echo "【4】スキップ判定のログ（サンプル5件）"
echo "--------------------------------------------"
echo "REVIEW_SYNC_SKIP_CHECK:"
grep "REVIEW_SYNC_SKIP_CHECK" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -5 | jq -r '.message' 2>/dev/null || grep "REVIEW_SYNC_SKIP_CHECK" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -5
echo ""

# 5. updateTime=null のレビューを確認
echo "【5】updateTime=null のレビューを確認"
echo "--------------------------------------------"
echo "REVIEWS_UPDATE_TIME_PARSED (updateTime=null のサンプル):"
grep "REVIEWS_UPDATE_TIME_PARSED" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | grep -i "null" | tail -3 | jq -r '.message' 2>/dev/null || grep "REVIEWS_UPDATE_TIME_PARSED" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -3
echo ""

# 6. 差分同期の判定
echo "【6】差分同期の判定（1回目 vs 2回目）"
echo "--------------------------------------------"
echo "1回目の同期:"
grep "REVIEW_SAFE_INCREMENTAL_SYNC_END" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -2 | head -1 | jq -r '{fetched_count, total_db_write_count, skipped_count, is_delta_sync}' 2>/dev/null || grep "REVIEW_SAFE_INCREMENTAL_SYNC_END" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -2 | head -1
echo ""

echo "2回目の同期:"
grep "REVIEW_SAFE_INCREMENTAL_SYNC_END" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -1 | jq -r '{fetched_count, total_db_write_count, skipped_count, is_delta_sync}' 2>/dev/null || grep "REVIEW_SAFE_INCREMENTAL_SYNC_END" "$LOG_FILE" | grep "shop_id.*$SHOP_ID" | tail -1
echo ""

echo "============================================"
echo "ログ抽出完了"
echo "============================================"









