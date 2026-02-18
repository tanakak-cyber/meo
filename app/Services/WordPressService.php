<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WordPressService
{
    /**
     * WordPress REST APIで投稿を作成
     * 
     * @param Shop $shop 店舗オブジェクト
     * @param array $payload 投稿データ
     * @return array|null 投稿成功時はレスポンス配列、失敗時はnull
     */
    public function createPost(Shop $shop, array $payload): ?array
    {
        try {
            // WordPressサイトのURLを取得（設定から取得、またはshopに保存されている場合）
            $wpUrl = $this->getWordPressUrl($shop);
            if (!$wpUrl) {
                Log::error('WP_POST_URL_MISSING', [
                    'shop_id' => $shop->id,
                ]);
                return null;
            }

            // Application Password認証
            $username = $this->getWordPressUsername($shop);
            $appPassword = $this->getWordPressAppPassword($shop);
            
            if (!$username || !$appPassword) {
                Log::error('WP_POST_CREDENTIALS_MISSING', [
                    'shop_id' => $shop->id,
                    'has_username' => !empty($username),
                    'has_app_password' => !empty($appPassword),
                ]);
                return null;
            }

            // Application Passwordから空白を除去
            $appPassword = str_replace(' ', '', $appPassword);

            // 投稿タイプを取得（デフォルト: post）
            $postType = trim($shop->wp_post_type ?? '');
            if (empty($postType)) {
                $postType = 'post';
            }
            
            // 投稿ステータスを取得（デフォルト: publish）
            $postStatus = $shop->wp_post_status ?? 'publish';

            // WordPress REST APIエンドポイント（?rest_route= 形式を使用）
            $endpoint = $this->buildRestUrl($shop, '/wp/v2/' . $postType);

            // Basic認証の生成（空白除去後のパスワードを使用）
            $basic = base64_encode($username . ':' . $appPassword);

            // POST前の認証デバッグログ
            Log::info('WP_AUTH_DEBUG', [
                'shop_id' => $shop->id,
                'username' => $username,
                'password_length' => strlen($appPassword),
                'basic_preview' => substr($basic, 0, 20),
            ]);

            // 画像アップロード処理（image_url が存在する場合）
            $featuredMediaId = null;
            $mediaSourceUrl = null;
            if (!empty($payload['image_url'])) {
                $mediaResult = $this->uploadMedia($shop, $payload['image_url']);
                
                if ($mediaResult) {
                    $featuredMediaId = $mediaResult['id'];
                    $mediaSourceUrl = $mediaResult['source_url'];
                    
                    Log::info('WP_MEDIA_UPLOAD_SUCCESS', [
                        'shop_id' => $shop->id,
                        'featured_media_id' => $featuredMediaId,
                        'source_url' => $mediaSourceUrl,
                        'image_url' => $payload['image_url'],
                    ]);
                } else {
                    Log::warning('WP_MEDIA_UPLOAD_FAILED', [
                        'shop_id' => $shop->id,
                        'image_url' => $payload['image_url'],
                        'message' => '画像アップロードに失敗しましたが、投稿は続行します',
                    ]);
                }
            }

            // スラッグをランダム8桁数字で生成
            $slug = str_pad(random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

            // 本文内の画像URLを置き換え（mediaSourceUrlが存在する場合）
            $finalContent = $payload['content'] ?? '';
            if ($mediaSourceUrl) {
                $title = $payload['title'] ?? '';
                
                // 既存の画像タグ（Instagram URLを含む）を削除
                // <img src="https://www.instagram.com/..." または <img src="https://instagram.com/..." を削除
                $finalContent = preg_replace('/<p>\s*<img[^>]*src=["\']https?:\/\/(www\.)?instagram\.com[^"\']*["\'][^>]*>.*?<\/p>/is', '', $finalContent);
                $finalContent = preg_replace('/<img[^>]*src=["\']https?:\/\/(www\.)?instagram\.com[^"\']*["\'][^>]*>/is', '', $finalContent);
                
                // 本文先頭にWordPressのメディアURLを使用した画像を追加
                $finalContent = "<p><img src=\"{$mediaSourceUrl}\" alt=\"{$title}\" /></p>\n\n" . trim($finalContent);
            }

            // 投稿データの準備
            $postPayload = [
                'title' => $payload['title'] ?? '',
                'content' => $finalContent,
                'status' => $postStatus,
                'slug' => $slug,
            ];

            // categories を設定（空でない場合のみ）
            $categoryIds = $payload['categories'] ?? [];
            if (!empty($categoryIds)) {
                $postPayload['categories'] = $categoryIds;
            }

            // featured_media を設定（アップロード成功時のみ）
            if ($featuredMediaId) {
                $postPayload['featured_media'] = $featuredMediaId;
            }

            Log::info('WP_POST_REQUEST', [
                'shop_id' => $shop->id,
                'endpoint' => $endpoint,
                'post_type' => $postType,
                'post_status' => $postStatus,
                'title_length' => mb_strlen($payload['title'] ?? ''),
                'content_length' => mb_strlen($payload['content'] ?? ''),
                'has_image_url' => !empty($payload['image_url']),
                'featured_media_id' => $featuredMediaId,
            ]);

            // ④ users/me を事前チェック（?rest_route= 形式を使用）
            $checkEndpoint = $this->buildRestUrl($shop, '/wp/v2/users/me');
            $check = Http::withBasicAuth($username, $appPassword)
                ->timeout(10)
                ->get($checkEndpoint);

            Log::info('WP_AUTH_CHECK', [
                'shop_id' => $shop->id,
                'check_endpoint' => $checkEndpoint,
                'status' => $check->status(),
                'body' => $check->body(),
            ]);

            // ⑤ 強制ログ追加（投稿直前）
            Log::info('WP_ENDPOINT_FINAL', [
                'shop_id' => $shop->id,
                'endpoint' => $endpoint,
            ]);

            // ② 低レベル cURL debug を有効化
            // LaravelのwithBasicAuthを使用してBasic認証でリクエスト
            $response = Http::withBasicAuth($username, $appPassword)
                ->withOptions([
                    'debug' => true,
                    'verify' => false,
                ])
                ->timeout(30)
                ->post($endpoint, $postPayload);

            // ③ レスポンス完全ログ
            Log::info('WP_RESPONSE_DEBUG', [
                'shop_id' => $shop->id,
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ]);

            if (!$response->successful()) {
                $statusCode = $response->status();
                $responseBody = $response->body();
                
                // 401エラーの場合は詳細ログを出力
                if ($statusCode === 401) {
                    Log::error('WP_AUTH_FAILURE_DETAIL', [
                        'shop_id' => $shop->id,
                        'status_code' => $statusCode,
                        'endpoint' => $endpoint,
                        'response_headers' => $response->headers(),
                        'response_body' => $responseBody,
                        'message' => '認証に失敗しました。Application PasswordまたはBasic認証が正しく設定されているか確認してください。',
                    ]);
                }
                
                // 404エラーの場合は投稿タイプが存在しない可能性をログに明記
                if ($statusCode === 404) {
                    Log::error('WP_POST_FAILED_POST_TYPE_NOT_FOUND', [
                        'shop_id' => $shop->id,
                        'status_code' => $statusCode,
                        'post_type' => $postType,
                        'endpoint' => $endpoint,
                        'response_body' => $responseBody,
                        'message' => '投稿タイプが存在しない可能性があります。WordPress側でこの投稿タイプが登録されているか確認してください。',
                    ]);
                } else {
                    Log::error('WP_POST_FAILED', [
                        'shop_id' => $shop->id,
                        'status_code' => $statusCode,
                        'post_type' => $postType,
                        'endpoint' => $endpoint,
                        'response_body' => $responseBody,
                    ]);
                }
                return null;
            }

            $result = $response->json();

            Log::info('WP_POST_SUCCESS', [
                'shop_id' => $shop->id,
                'wp_post_id' => $result['id'] ?? null,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('WP_POST_EXCEPTION', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * WordPressサイトのURLを取得
     * 
     * @param Shop $shop
     * @return string|null
     */
    private function getWordPressUrl(Shop $shop): ?string
    {
        return $shop->wp_base_url;
    }

    /**
     * WordPress REST APIエンドポイントを構築
     * ?rest_route= 形式を使用（WAF回避のため）
     * 
     * @param Shop $shop
     * @param string $path REST APIパス（例: /wp/v2/posts）
     * @return string
     */
    private function buildRestUrl(Shop $shop, string $path): string
    {
        $base = rtrim($shop->wp_base_url, '/');
        // パスの先頭に / がない場合は追加
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        return $base . '/?rest_route=' . $path;
    }

    /**
     * WordPressユーザー名を取得
     * 
     * @param Shop $shop
     * @return string|null
     */
    private function getWordPressUsername(Shop $shop): ?string
    {
        return $shop->wp_username;
    }

    /**
     * WordPress Application Passwordを取得
     * 
     * @param Shop $shop
     * @return string|null
     */
    private function getWordPressAppPassword(Shop $shop): ?string
    {
        // encrypted castにより自動的に復号化される
        return $shop->wp_app_password;
    }

    /**
     * WordPress REST APIから投稿タイプ一覧を取得
     * 
     * @param Shop $shop 店舗オブジェクト
     * @return array 投稿タイプの配列 ['post' => '投稿', 'page' => '固定ページ', ...]
     */
    public function getPostTypes(Shop $shop): array
    {
        try {
            // WordPressサイトのURLを取得
            $wpUrl = $this->getWordPressUrl($shop);
            if (!$wpUrl) {
                Log::error('WP_GET_POST_TYPES_URL_MISSING', [
                    'shop_id' => $shop->id,
                ]);
                return [];
            }

            // Application Password認証
            $username = $this->getWordPressUsername($shop);
            $appPassword = $this->getWordPressAppPassword($shop);
            
            if (!$username || !$appPassword) {
                Log::error('WP_GET_POST_TYPES_CREDENTIALS_MISSING', [
                    'shop_id' => $shop->id,
                    'has_username' => !empty($username),
                    'has_app_password' => !empty($appPassword),
                ]);
                return [];
            }

            // Application Passwordから空白を除去
            $appPassword = str_replace(' ', '', $appPassword);

            // WordPress REST APIエンドポイント（?rest_route= 形式を使用）
            $endpoint = $this->buildRestUrl($shop, '/wp/v2/types');

            Log::info('WP_GET_POST_TYPES_REQUEST', [
                'shop_id' => $shop->id,
                'endpoint' => $endpoint,
            ]);

            Log::info('WP_AUTH_DEBUG', [
                'username' => $username,
                'password_length' => strlen($appPassword),
            ]);

            // Authorizationヘッダーの生成とデバッグログ
            $authString = $username . ':' . $appPassword;
            $encodedAuth = base64_encode($authString);

            Log::info('WP_AUTH_HEADER_DEBUG', [
                'shop_id' => $shop->id,
                'username' => $username,
                'password_length' => strlen($appPassword),
                'auth_header_preview' => substr($encodedAuth, 0, 10) . '...',
            ]);

            // Basic認証でリクエスト（明示的にAuthorizationヘッダーを設定）
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $encodedAuth,
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->get($endpoint);

            if (!$response->successful()) {
                Log::error('WP_GET_POST_TYPES_FAILED', [
                    'shop_id' => $shop->id,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);
                return [];
            }

            $result = $response->json();

            if (!is_array($result)) {
                Log::error('WP_GET_POST_TYPES_INVALID_RESPONSE', [
                    'shop_id' => $shop->id,
                    'response_type' => gettype($result),
                ]);
                return [];
            }

            // 投稿タイプ抽出条件:
            // 1. rest_base が存在する
            // 2. slug が 'attachment' ではない
            $postTypes = [];
            foreach ($result as $slug => $type) {
                if (
                    isset($type['rest_base']) &&
                    $slug !== 'attachment'
                ) {
                    $name = $type['name'] ?? $slug;
                    $postTypes[$slug] = $name;
                }
            }

            Log::info('WP_GET_POST_TYPES_SUCCESS', [
                'shop_id' => $shop->id,
                'post_types_count' => count($postTypes),
                'post_types' => array_keys($postTypes),
            ]);

            Log::info('WP_FILTERED_POST_TYPES', [
                'shop_id' => $shop->id,
                'post_types' => $postTypes
            ]);

            return $postTypes;
        } catch (\Exception $e) {
            Log::error('WP_GET_POST_TYPES_EXCEPTION', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * WordPressデバッグエンドポイントをテスト
     * Authorizationヘッダーが正しく届いているか確認する
     * 
     * @param Shop $shop 店舗オブジェクト
     * @return array|null レスポンス配列、失敗時はnull
     */
    public function testAuthDebug(Shop $shop): ?array
    {
        try {
            // WordPressサイトのURLを取得
            $wpUrl = $this->getWordPressUrl($shop);
            if (!$wpUrl) {
                Log::error('WP_AUTH_DEBUG_URL_MISSING', [
                    'shop_id' => $shop->id,
                ]);
                return null;
            }

            // Application Password認証
            $username = $this->getWordPressUsername($shop);
            $appPassword = $this->getWordPressAppPassword($shop);
            
            if (!$username || !$appPassword) {
                Log::error('WP_AUTH_DEBUG_CREDENTIALS_MISSING', [
                    'shop_id' => $shop->id,
                    'has_username' => !empty($username),
                    'has_app_password' => !empty($appPassword),
                ]);
                return null;
            }

            // Application Passwordから空白を除去
            $appPassword = str_replace(' ', '', $appPassword);

            // WordPressデバッグエンドポイント（?rest_route= 形式を使用）
            $endpoint = $this->buildRestUrl($shop, '/debug/v1/auth');

            // Authorizationヘッダーの生成とデバッグログ
            $authString = $username . ':' . $appPassword;
            $encodedAuth = base64_encode($authString);

            Log::info('WP_AUTH_DEBUG_REQUEST', [
                'shop_id' => $shop->id,
                'endpoint' => $endpoint,
                'username' => $username,
                'password_length' => strlen($appPassword),
                'auth_header_preview' => substr($encodedAuth, 0, 10) . '...',
            ]);

            // Basic認証でリクエスト（明示的にAuthorizationヘッダーを設定）
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $encodedAuth,
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->get($endpoint);

            Log::info('WP_AUTH_DEBUG_RESPONSE', [
                'shop_id' => $shop->id,
                'status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (!$response->successful()) {
                Log::error('WP_AUTH_DEBUG_FAILED', [
                    'shop_id' => $shop->id,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('WP_AUTH_DEBUG_EXCEPTION', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * WordPress REST APIでメディア（画像）をアップロード
     * 
     * @param Shop $shop 店舗オブジェクト
     * @param string $imageUrl 画像URL
     * @return array|null アップロード成功時は['id' => int, 'source_url' => string]、失敗時はnull
     */
    public function uploadMedia(Shop $shop, string $imageUrl): ?array
    {
        try {
            // WordPressサイトのURLを取得
            $wpUrl = $this->getWordPressUrl($shop);
            if (!$wpUrl) {
                Log::error('WP_MEDIA_UPLOAD_URL_MISSING', [
                    'shop_id' => $shop->id,
                ]);
                return null;
            }

            // Application Password認証
            $username = $this->getWordPressUsername($shop);
            $appPassword = $this->getWordPressAppPassword($shop);
            
            if (!$username || !$appPassword) {
                Log::error('WP_MEDIA_UPLOAD_CREDENTIALS_MISSING', [
                    'shop_id' => $shop->id,
                    'has_username' => !empty($username),
                    'has_app_password' => !empty($appPassword),
                ]);
                return null;
            }

            // Application Passwordから空白を除去
            $appPassword = str_replace(' ', '', $appPassword);

            // WordPress REST APIエンドポイント（?rest_route= 形式を使用）
            $endpoint = $this->buildRestUrl($shop, '/wp/v2/media');

            Log::info('WP_MEDIA_UPLOAD_REQUEST', [
                'shop_id' => $shop->id,
                'endpoint' => $endpoint,
                'image_url' => $imageUrl,
            ]);

            // 画像をダウンロード
            $imageResponse = Http::timeout(30)->get($imageUrl);
            
            if (!$imageResponse->successful()) {
                Log::error('WP_MEDIA_UPLOAD_IMAGE_DOWNLOAD_FAILED', [
                    'shop_id' => $shop->id,
                    'image_url' => $imageUrl,
                    'status_code' => $imageResponse->status(),
                ]);
                return null;
            }

            $imageContent = $imageResponse->body();
            $imageMimeType = $imageResponse->header('Content-Type') ?? 'image/jpeg';
            
            // ファイル名を取得（URLから）
            $urlPath = parse_url($imageUrl, PHP_URL_PATH);
            $filename = basename($urlPath) ?: 'image.jpg';
            
            // 拡張子がない場合は MIME タイプから推測
            if (!pathinfo($filename, PATHINFO_EXTENSION)) {
                $extension = $this->getExtensionFromMimeType($imageMimeType);
                $filename = pathinfo($filename, PATHINFO_FILENAME) . '.' . $extension;
            }

            // WordPress REST APIでメディアをアップロード
            // multipart/form-data で送信
            $response = Http::withBasicAuth($username, $appPassword)
                ->attach('file', $imageContent, $filename)
                ->post($endpoint, [
                    'title' => $filename,
                    'alt_text' => $filename,
                ]);

            if (!$response->successful()) {
                Log::error('WP_MEDIA_UPLOAD_FAILED', [
                    'shop_id' => $shop->id,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'image_url' => $imageUrl,
                ]);
                return null;
            }

            $result = $response->json();
            $mediaId = $result['id'] ?? null;
            $sourceUrl = $result['source_url'] ?? null;

            if (!$mediaId || !$sourceUrl) {
                Log::error('WP_MEDIA_UPLOAD_NO_ID_OR_URL', [
                    'shop_id' => $shop->id,
                    'response' => $result,
                    'has_id' => !empty($mediaId),
                    'has_source_url' => !empty($sourceUrl),
                ]);
                return null;
            }

            return [
                'id' => (int)$mediaId,
                'source_url' => $sourceUrl,
            ];
        } catch (\Exception $e) {
            Log::error('WP_MEDIA_UPLOAD_EXCEPTION', [
                'shop_id' => $shop->id,
                'image_url' => $imageUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * MIMEタイプから拡張子を取得
     * 
     * @param string $mimeType
     * @return string
     */
    private function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ];

        return $mimeMap[$mimeType] ?? 'jpg';
    }

    /**
     * カテゴリ名からWordPressのカテゴリIDを取得（存在しない場合は作成）
     * 
     * @param Shop $shop 店舗オブジェクト
     * @param string $categoryName カテゴリ名
     * @return int|null カテゴリID、失敗時はnull
     */
    public function getOrCreateCategory(Shop $shop, string $categoryName): ?int
    {
        try {
            // WordPressサイトのURLを取得
            $wpUrl = $this->getWordPressUrl($shop);
            if (!$wpUrl) {
                return null;
            }

            // Application Password認証
            $username = $this->getWordPressUsername($shop);
            $appPassword = $this->getWordPressAppPassword($shop);
            
            if (!$username || !$appPassword) {
                return null;
            }

            // Application Passwordから空白を除去
            $appPassword = str_replace(' ', '', $appPassword);

            // 既存カテゴリを検索
            $searchEndpoint = $this->buildRestUrl($shop, '/wp/v2/categories');
            $searchResponse = Http::withBasicAuth($username, $appPassword)
                ->timeout(10)
                ->get($searchEndpoint, [
                    'search' => $categoryName,
                    'per_page' => 100,
                ]);

            if ($searchResponse->successful()) {
                $categories = $searchResponse->json();
                
                // 完全一致するカテゴリを探す
                if (is_array($categories)) {
                    foreach ($categories as $category) {
                        if (isset($category['name']) && $category['name'] === $categoryName) {
                            return (int)$category['id'];
                        }
                    }
                }
            }

            // カテゴリが存在しない場合は新規作成
            $createEndpoint = $this->buildRestUrl($shop, '/wp/v2/categories');
            $createResponse = Http::withBasicAuth($username, $appPassword)
                ->timeout(10)
                ->post($createEndpoint, [
                    'name' => $categoryName,
                ]);

            if ($createResponse->successful()) {
                $result = $createResponse->json();
                return isset($result['id']) ? (int)$result['id'] : null;
            }

            // 400エラーでcode=term_existsの場合、既存カテゴリのIDを取得
            if ($createResponse->status() === 400) {
                $errorData = $createResponse->json();
                
                if (isset($errorData['code']) && $errorData['code'] === 'term_exists') {
                    // 既存カテゴリのterm_idを取得
                    $termId = $errorData['data']['term_id'] ?? null;
                    
                    if ($termId) {
                        Log::info('WP_CATEGORY_ALREADY_EXISTS', [
                            'shop_id' => $shop->id,
                            'category_name' => $categoryName,
                            'term_id' => $termId,
                        ]);
                        return (int)$termId;
                    }
                }
            }

            Log::error('WP_CATEGORY_CREATE_FAILED', [
                'shop_id' => $shop->id,
                'category_name' => $categoryName,
                'status_code' => $createResponse->status(),
                'response_body' => $createResponse->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('WP_CATEGORY_GET_OR_CREATE_EXCEPTION', [
                'shop_id' => $shop->id,
                'category_name' => $categoryName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

