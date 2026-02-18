# GoogleBusinessProfileService::uploadPhoto() 実装設計案

## メソッドシグネチャ

```php
/**
 * Google Business Profile API: locations.media.create
 * ロケーションに写真をアップロード
 * 
 * @param string $accessToken OAuthアクセストークン
 * @param string $locationName ロケーション名（例: "locations/14533069664155190447"）
 * @param string $filePath アップロードする画像ファイルのパス
 * @param array $options オプション（description, sourceUrl等）
 * @return array アップロード結果（mediaItem情報）
 * @throws \Exception
 */
public function uploadPhoto(
    string $accessToken,
    string $locationName,
    string $filePath,
    array $options = []
): array
```

## 実装詳細

### 1. パラメータ

**必須パラメータ:**
- `$accessToken`: OAuthアクセストークン（`refreshAccessToken()`で取得）
- `$locationName`: ロケーション名（`shops.gbp_location_id`の値、例: `"locations/14533069664155190447"`）
- `$filePath`: アップロードする画像ファイルのパス（Laravelの`$request->file('photo')->path()`など）

**オプションパラメータ:**
- `$options['description']`: 写真の説明（オプション）
- `$options['sourceUrl']`: 元のURL（オプション）

### 2. エンドポイント

```
POST https://mybusiness.googleapis.com/v4/{locationName}/media
```

**例:**
```
POST https://mybusiness.googleapis.com/v4/locations/14533069664155190447/media
```

### 3. リクエスト形式（Multipart Upload）

```
Content-Type: multipart/related; boundary="----WebKitFormBoundary7MA4YWxkTrZu0gW"

------WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Type: application/json; charset=UTF-8

{
  "mediaFormat": "PHOTO"
}

------WebKitFormBoundary7MA4YWxkTrZu0gW
Content-Type: image/jpeg

[バイナリデータ]

------WebKitFormBoundary7MA4YWxkTrZu0gW--
```

### 4. 実装コード

```php
/**
 * Google Business Profile API: locations.media.create
 * ロケーションに写真をアップロード
 * 
 * @param string $accessToken OAuthアクセストークン
 * @param string $locationName ロケーション名（例: "locations/14533069664155190447"）
 * @param string $filePath アップロードする画像ファイルのパス
 * @param array $options オプション（description, sourceUrl等）
 * @return array アップロード結果（mediaItem情報）
 */
public function uploadPhoto(
    string $accessToken,
    string $locationName,
    string $filePath,
    array $options = []
): array
{
    try {
        // ファイル存在確認
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        // ファイルサイズ確認（推奨: 5MB以下、最大: 75MB）
        $fileSize = filesize($filePath);
        $maxSize = 75 * 1024 * 1024; // 75MB
        if ($fileSize > $maxSize) {
            throw new \Exception("File size exceeds 75MB limit: {$fileSize} bytes");
        }

        // MIMEタイプ確認
        $mimeType = mime_content_type($filePath);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes)) {
            throw new \Exception("Unsupported image type: {$mimeType}");
        }

        // ファイル読み込み
        $imageData = file_get_contents($filePath);
        if ($imageData === false) {
            throw new \Exception("Failed to read file: {$filePath}");
        }

        // エンドポイント構築
        // locationNameは "locations/14533069664155190447" の形式
        $url = "https://mybusiness.googleapis.com/v4/{$locationName}/media";

        // Multipart リクエストボディ構築
        $boundary = '----WebKitFormBoundary' . uniqid();
        $multipartBody = $this->buildMultipartBodyForPhoto(
            $imageData,
            $mimeType,
            $options,
            $boundary
        );

        // ログ出力（リクエスト直前）
        Log::info('GBP_PHOTO_UPLOAD_REQUEST', [
            'method' => 'POST',
            'url' => $url,
            'location_name' => $locationName,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'options' => $options,
        ]);

        // HTTP リクエスト送信
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => "multipart/related; boundary=\"{$boundary}\"",
            ])
            ->withBody($multipartBody, "multipart/related; boundary=\"{$boundary}\"")
            ->post($url);

        $responseStatus = $response->status();
        $responseBodyRaw = $response->body();
        $responseBody = $response->json();

        // ログ出力（レスポンス）
        Log::info('GBP_PHOTO_UPLOAD_RESPONSE', [
            'status' => $responseStatus,
            'location_name' => $locationName,
            'response_body' => $responseBodyRaw,
            'response_body_json' => $responseBody,
        ]);

        // 成功判定
        if ($response->successful()) {
            Log::info('GBP_PHOTO_UPLOAD_SUCCESS', [
                'location_name' => $locationName,
                'media_name' => $responseBody['name'] ?? null,
                'media_url' => $responseBody['mediaUrl'] ?? null,
                'google_url' => $responseBody['googleUrl'] ?? null,
            ]);

            return [
                'name' => $responseBody['name'] ?? null,
                'mediaUrl' => $responseBody['mediaUrl'] ?? null,
                'googleUrl' => $responseBody['googleUrl'] ?? null,
                'thumbnailUrl' => $responseBody['thumbnailUrl'] ?? null,
                'createTime' => $responseBody['createTime'] ?? null,
                'mediaFormat' => $responseBody['mediaFormat'] ?? null,
                'widthPixels' => $responseBody['widthPixels'] ?? null,
                'heightPixels' => $responseBody['heightPixels'] ?? null,
            ];
        }

        // エラーハンドリング
        $errorMessage = $responseBody['error']['message'] ?? 'Unknown error';
        Log::error('GBP_PHOTO_UPLOAD_FAILED', [
            'status' => $responseStatus,
            'location_name' => $locationName,
            'response_body' => $responseBodyRaw,
            'error_message' => $errorMessage,
        ]);

        throw new \Exception("Google Business Profile API photo upload failed: {$errorMessage}");

    } catch (\Exception $e) {
        Log::error('GBP_PHOTO_UPLOAD_EXCEPTION', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'location_name' => $locationName,
            'file_path' => $filePath,
        ]);
        throw $e;
    }
}

/**
 * Multipart リクエストボディを構築
 * 
 * @param string $imageData 画像のバイナリデータ
 * @param string $mimeType MIMEタイプ
 * @param array $options オプション
 * @param string $boundary バウンダリ文字列
 * @return string Multipart リクエストボディ
 */
private function buildMultipartBodyForPhoto(
    string $imageData,
    string $mimeType,
    array $options,
    string $boundary
): string
{
    $eol = "\r\n";

    // メタデータ部（JSON）
    $metadata = [
        'mediaFormat' => 'PHOTO',
    ];

    if (isset($options['description'])) {
        $metadata['description'] = $options['description'];
    }

    if (isset($options['sourceUrl'])) {
        $metadata['sourceUrl'] = $options['sourceUrl'];
    }

    $body = '';
    
    // メタデータパート
    $body .= "--{$boundary}{$eol}";
    $body .= "Content-Type: application/json; charset=UTF-8{$eol}";
    $body .= "{$eol}";
    $body .= json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . $eol;

    // 画像データパート
    $body .= "--{$boundary}{$eol}";
    $body .= "Content-Type: {$mimeType}{$eol}";
    $body .= "Content-Transfer-Encoding: binary{$eol}";
    $body .= "{$eol}";
    $body .= $imageData . $eol;

    // 終了
    $body .= "--{$boundary}--{$eol}";

    return $body;
}

/**
 * 複数の写真をアップロード
 * 
 * @param string $accessToken OAuthアクセストークン
 * @param string $locationName ロケーション名
 * @param array $filePaths アップロードする画像ファイルのパスの配列
 * @param array $options オプション
 * @return array アップロード結果の配列（各要素に 'success', 'data', 'error' を含む）
 */
public function uploadPhotos(
    string $accessToken,
    string $locationName,
    array $filePaths,
    array $options = []
): array
{
    $results = [];

    foreach ($filePaths as $index => $filePath) {
        try {
            $result = $this->uploadPhoto($accessToken, $locationName, $filePath, $options);
            $results[] = [
                'success' => true,
                'filePath' => $filePath,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            Log::error('GBP_PHOTO_UPLOAD_MULTI_FAILED', [
                'file_path' => $filePath,
                'index' => $index,
                'error' => $e->getMessage(),
            ]);
            $results[] = [
                'success' => false,
                'filePath' => $filePath,
                'error' => $e->getMessage(),
            ];
        }
    }

    return $results;
}
```

## 使用例（ShopController::uploadPhotos() での使用）

```php
public function uploadPhotos(Request $request)
{
    $request->validate([
        'shop_id' => 'required|exists:shops,id',
        'photos.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:76800', // 75MB
    ]);

    $shop = Shop::findOrFail($request->shop_id);
    
    // 契約終了日を確認
    if (!$shop->isContractActive()) {
        return response()->json([
            'success' => false,
            'message' => '契約が終了している店舗への写真アップロードはできません。',
        ], 403);
    }

    // Google連携情報の確認
    if (!$shop->gbp_location_id || !$shop->gbp_refresh_token) {
        return response()->json([
            'success' => false,
            'message' => 'Google連携情報が不足しています。',
        ], 400);
    }

    $googleService = new GoogleBusinessProfileService();

    try {
        // アクセストークンをリフレッシュ
        $accessToken = $googleService->refreshAccessToken($shop->gbp_refresh_token);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'アクセストークンの取得に失敗しました。',
            ], 500);
        }

        // 一時ファイルパスを取得
        $filePaths = [];
        foreach ($request->file('photos') as $photo) {
            $filePaths[] = $photo->path();
        }

        // Google Business Profile API にアップロード
        $results = $googleService->uploadPhotos(
            $accessToken,
            $shop->gbp_location_id, // "locations/14533069664155190447" の形式
            $filePaths
        );

        // 成功した写真をDBに保存（必要に応じて）
        $successCount = 0;
        $failedCount = 0;

        foreach ($results as $result) {
            if ($result['success']) {
                // DBに保存する場合はここで実装
                // Photo::create([...]);
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$successCount}枚の写真をアップロードしました。" . 
                        ($failedCount > 0 ? " {$failedCount}枚のアップロードに失敗しました。" : ''),
            'results' => $results,
        ]);

    } catch (\Exception $e) {
        Log::error('PHOTO_UPLOAD_EXCEPTION', [
            'shop_id' => $shop->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => '写真のアップロード中にエラーが発生しました。',
        ], 500);
    }
}
```

## 注意事項

1. **LaravelのHTTPクライアントの制限:**
   - Laravelの`Http`ファサードはMultipartリクエストを直接サポートしていない可能性があります
   - その場合は、`GuzzleHttp\Client`を直接使用するか、`attach()`メソッドを使用する必要があります

2. **代替実装（Guzzle使用）:**
   ```php
   use GuzzleHttp\Client;
   use GuzzleHttp\RequestOptions;

   $client = new Client();
   $response = $client->post($url, [
       'headers' => [
           'Authorization' => "Bearer {$accessToken}",
       ],
       RequestOptions::MULTIPART => [
           [
               'name' => 'metadata',
               'contents' => json_encode(['mediaFormat' => 'PHOTO']),
               'headers' => ['Content-Type' => 'application/json'],
           ],
           [
               'name' => 'media',
               'contents' => fopen($filePath, 'r'),
               'filename' => basename($filePath),
               'headers' => ['Content-Type' => $mimeType],
           ],
       ],
   ]);
   ```

3. **エラーハンドリング:**
   - ネットワークエラー
   - ファイルサイズ超過
   - 不正な画像形式
   - OAuthトークン期限切れ
   - APIレート制限

4. **ログ出力:**
   - リクエスト前後で詳細なログを出力
   - エラー時はスタックトレースも記録






















