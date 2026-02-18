# Google Business Profile API 連携実装レポート

## 実施内容

### 1. テストデータの整理

#### 実施内容
- `CleanupTestDataSeeder` を作成
- 「カウンジャー小岩店」以外の店舗とその関連データを削除する機能を実装

#### 削除対象テーブル
- `shops` (カウンジャー小岩店以外)
- `meo_keywords` (削除対象店舗のもの)
- `meo_rank_logs` (削除対象店舗のもの)
- `reviews` (削除対象店舗のもの)
- `gbp_locations` (存在する場合、削除対象店舗のもの)
- `gbp_reviews` (存在する場合、削除対象店舗のもの)
- `gbp_photos` (存在する場合、削除対象店舗のもの)
- `gbp_posts` (存在する場合、削除対象店舗のもの)
- `kpi_logs` (存在する場合、削除対象店舗のもの)

#### 実行方法
```bash
php artisan db:seed --class=CleanupTestDataSeeder
```

### 2. Google連携の実装

#### 実装した機能

##### ① OAuth認証フロー
- **認証URL生成**: `GoogleBusinessProfileService::getAuthUrl()`
  - Google OAuth 2.0認証URLを生成
  - スコープ: `https://www.googleapis.com/auth/business.manage`
  - `state`パラメータにshop_idを埋め込み

##### ② トークン取得・管理
- **認証コードからトークン取得**: `GoogleBusinessProfileService::getTokensFromCode()`
  - アクセストークンとリフレッシュトークンを取得
  - リフレッシュトークンを`shops.gbp_refresh_token`に保存
- **トークンリフレッシュ**: `GoogleBusinessProfileService::refreshAccessToken()`
  - リフレッシュトークンから新しいアクセストークンを取得

##### ③ Google Business Profile API呼び出し
- **accounts.list**: `GoogleBusinessProfileService::listAccounts()`
  - API URL: `https://mybusinessaccountmanagement.googleapis.com/v1/accounts`
  - アカウント一覧を取得
- **locations.list**: `GoogleBusinessProfileService::listLocations()`
  - API URL: `https://mybusinessbusinessinformation.googleapis.com/v1/accounts/{accountId}/locations`
  - ロケーション一覧を取得

##### ④ データベース保存
- **gbp_locationsテーブル**: マイグレーション作成済み
  - `shop_id`, `location_id`, `account_id`, `name`, `address`, `phone_number`, `website`, `latitude`, `longitude`, `metadata`
- **GbpLocationモデル**: 作成済み
- **Shopモデル**: `gbpLocations()`リレーション追加

#### ルーティング

```php
// Google連携開始
GET /shops/{shop}/connect

// OAuthコールバック
GET /shops/google/callback
```

#### コントローラー

- **`ShopController::connect()`**: Google OAuth認証URLにリダイレクト
- **`ShopController::googleCallback()`**: OAuthコールバック処理
  1. 認証コードからトークン取得
  2. リフレッシュトークンを保存
  3. accounts.list を実行
  4. locations.list を実行
  5. gbp_locations に保存
  6. shops.gbp_location_id に最初のロケーションIDを保存

### 3. 設定ファイル

#### `config/services.php`
```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/shops/google/callback'),
],
```

#### `.env` に必要な設定
```
GOOGLE_CLIENT_ID=your_client_id
GOOGLE_CLIENT_SECRET=your_client_secret
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/shops/google/callback
```

### 4. 現在の連携状態

#### 実装済み
- ✅ OAuth認証フロー
- ✅ トークン取得・保存
- ✅ Google Business Profile API呼び出し（accounts.list, locations.list）
- ✅ gbp_locationsテーブルへの保存
- ✅ エラーログ出力

#### 未実装（今後の拡張）
- ⏳ トークンの自動リフレッシュ
- ⏳ 口コミ同期（gbp_reviews）
- ⏳ 写真アップロード（gbp_photos）
- ⏳ 投稿管理（gbp_posts）

### 5. トラブルシューティング

#### 確認ポイント

1. **OAuth認証URLが生成されているか**
   - `ShopController::connect()` のログを確認
   - `storage/logs/laravel.log` に "Google OAuth認証URLを生成" が記録されているか

2. **トークンが取得できているか**
   - `ShopController::googleCallback()` のログを確認
   - `shops.gbp_refresh_token` に値が保存されているか

3. **API呼び出しが成功しているか**
   - `GoogleBusinessProfileService` のログを確認
   - HTTPステータスコードとレスポンスボディを確認

4. **データが保存されているか**
   - `gbp_locations` テーブルにデータが存在するか
   - `shops.gbp_location_id` に値が設定されているか

#### よくあるエラー

1. **`invalid_client`**: GOOGLE_CLIENT_ID または GOOGLE_CLIENT_SECRET が間違っている
2. **`redirect_uri_mismatch`**: Google Cloud Consoleで設定したリダイレクトURIと一致していない
3. **`access_denied`**: ユーザーが認証をキャンセルした
4. **`403 Forbidden`**: APIのスコープが不足している、またはAPIが有効化されていない

### 6. 次のステップ

1. **マイグレーション実行**
   ```bash
   php artisan migrate
   ```

2. **テストデータ整理**
   ```bash
   php artisan db:seed --class=CleanupTestDataSeeder
   ```

3. **Google Cloud Console設定**
   - OAuth 2.0 クライアントIDを作成
   - リダイレクトURIを設定: `http://127.0.0.1:8000/shops/google/callback`
   - Google Business Profile APIを有効化

4. **.env設定**
   - `GOOGLE_CLIENT_ID` と `GOOGLE_CLIENT_SECRET` を設定

5. **連携テスト**
   - 店舗詳細画面で「Google連携」ボタンをクリック
   - Google認証画面で認証
   - コールバック後の結果を確認






















