@echo off
echo ========================================
echo rank-worker.js テスト実行
echo ========================================
echo.

REM テストデータを作成
echo [1/2] テストデータを作成中...
php artisan test:create-rank-fetch-job
if %errorlevel% neq 0 (
    echo.
    echo エラー: テストデータの作成に失敗しました
    pause
    exit /b 1
)

echo.
echo [2/2] rank-worker.cjs を実行中...
echo.
node rank-worker.cjs

echo.
echo ========================================
echo テスト完了
echo ========================================
pause

