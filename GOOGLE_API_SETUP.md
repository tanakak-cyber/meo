# Google Business Profile API 設定確認手順

## 問題
Google Business Profile Reviews APIが404になる原因は、API allowlistが付与されたGoogle Cloud Projectと、OAuthトークンを発行しているGoogle Cloud Projectが異なるためです。

## 確認・修正手順

### 1. Google Cloud Console で allowlist 申請を出したプロジェクトIDを確認

1. [Google Cloud Console](https://console.cloud.google.com/) にログイン
2. プロジェクト一覧から、**My Business API の allowlist 申請を出したプロジェクト**を確認
3. そのプロジェクトの**プロジェクトID**をメモ

### 2. 現在の Laravel 設定を確認

現在の設定ファイルを確認：
- `.env` ファイルの `GOOGLE_CLIENT_ID` と `GOOGLE_CLIENT_SECRET`
- `config/services.php` の設定

現在の設定値：
```
GOOGLE_CLIENT_ID=1073326337940-jumq6la2tq7n7ufbrprcg7fhg5uhb4tl.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=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
```

### 3. OAuth クライアントのプロジェクトを確認

1. [Google Cloud Console](https://console.cloud.google.com/) にログイン
2. 上記の `GOOGLE_CLIENT_ID` がどのプロジェクトに属しているか確認
   - 「APIとサービス」→「認証情報」から該当のOAuth 2.0クライアントIDを探す
   - そのクライアントが属するプロジェクトIDを確認

### 4. プロジェクトが異なる場合の修正手順

#### 4-1. allowlist 済みプロジェクトで OAuth クライアントを作成

1. allowlist 申請を出したプロジェクトを選択
2. 「APIとサービス」→「認証情報」に移動
3. 「認証情報を作成」→「OAuth クライアントID」を選択
4. アプリケーションの種類: 「ウェブアプリケーション」
5. 承認済みのリダイレクトURI に以下を追加：
   ```
   http://127.0.0.1:8000/shops/google/callback
   ```
   （本番環境の場合は、実際のドメインに変更）
6. 「作成」をクリック
7. **新しい Client ID と Client Secret をコピー**

#### 4-2. .env ファイルを更新

`.env` ファイルを開き、以下を更新：

```env
GOOGLE_CLIENT_ID=新しいClientID
GOOGLE_CLIENT_SECRET=新しいClientSecret
```

#### 4-3. 設定を反映

```bash
php artisan config:clear
```

#### 4-4. Google連携をやり直す

1. 店舗詳細画面で「Google連携」ボタンをクリック
2. Google認証を完了
3. ロケーション選択画面で店舗を選択
4. 新しい `refresh_token` が保存される

### 5. 確認

同期ボタンを押して、口コミが取得できることを確認してください。

## 注意事項

- **プロジェクトIDが一致していることが重要です**
- allowlist 申請を出したプロジェクトと、OAuth クライアントを作成したプロジェクトが同じである必要があります
- プロジェクトが異なる場合、必ず allowlist 済みプロジェクトで OAuth クライアントを新規作成してください






















