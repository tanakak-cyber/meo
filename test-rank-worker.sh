#!/bin/bash

echo "========================================"
echo "rank-worker.js テスト実行"
echo "========================================"
echo ""

# テストデータを作成
echo "[1/2] テストデータを作成中..."
php artisan test:create-rank-fetch-job
if [ $? -ne 0 ]; then
    echo ""
    echo "エラー: テストデータの作成に失敗しました"
    exit 1
fi

echo ""
echo "[2/2] rank-worker.cjs を実行中..."
echo ""
node rank-worker.cjs

echo ""
echo "========================================"
echo "テスト完了"
echo "========================================"

