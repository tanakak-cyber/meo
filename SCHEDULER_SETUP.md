# Laravel スケジューラー設定ガイド

## 現在の設定状況

✅ `routes/console.php` にスケジューラーが登録済み
- `blog:crawl` コマンドが毎分実行される設定
- 日本時間（Asia/Tokyo）で動作

## Windows での設定方法

### 方法1: タスクスケジューラーを使用（推奨）

1. **タスクスケジューラーを開く**
   - Windows キー + R → `taskschd.msc` と入力

2. **基本タスクの作成**
   - 右側の「基本タスクの作成」をクリック
   - 名前: `Laravel Scheduler`
   - 説明: `Laravel スケジューラーを毎分実行`

3. **トリガーの設定**
   - 「毎日」を選択
   - 開始日時: 今日の日付
   - 時刻: 00:00:00
   - 「繰り返し間隔」を「1分間」に設定
   - 「期間」を「無期限」に設定

4. **操作の設定**
   - 「プログラムの開始」を選択
   - プログラム/スクリプト: `C:\laragon\bin\php\php-8.x.x\php.exe` (Laragon の PHP パス)
   - 引数の追加: `artisan schedule:run`
   - 開始場所: `C:\laragon\www\meo` (プロジェクトのパス)

5. **条件の設定**
   - 「コンピューターを AC 電源で使用している場合のみタスクを実行する」のチェックを外す

6. **設定の確認**
   - 「タスクの実行時に、ユーザーがログオンしているかどうかにかかわらず実行する」にチェック
   - 「最上位の特権で実行する」にチェック（必要に応じて）

### 方法2: バッチファイル + タスクスケジューラー

1. **バッチファイルを作成** (`run-scheduler.bat`)
   ```batch
   @echo off
   cd C:\laragon\www\meo
   C:\laragon\bin\php\php-8.x.x\php.exe artisan schedule:run
   ```

2. **タスクスケジューラーで上記のバッチファイルを毎分実行**

## 動作確認

### スケジューラーの登録状況を確認
```bash
php artisan schedule:list
```

### 手動でスケジューラーを実行（テスト用）
```bash
php artisan schedule:run
```

### ログで確認
- `storage/logs/laravel.log` に `BLOG_CRAWL_START` などのログが記録される
- 設定した時刻（例: 03:00）にログが出力されることを確認

## 注意事項

- タスクスケジューラーが動作している間、PC が起動している必要があります
- サーバー環境の場合は、cron を使用してください
- 開発環境では、手動で `php artisan schedule:run` を実行してテストできます
















