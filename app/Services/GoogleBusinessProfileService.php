<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Carbon\CarbonImmutable;
use App\Models\GbpPost;

class GoogleBusinessProfileService
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $scopes;

    public function __construct()
    {
        $this->clientId = config('services.google.client_id');
        $this->clientSecret = config('services.google.client_secret');
        $this->redirectUri = config('services.google.redirect_uri');
        // User OAuth用のスコープ（email, profile, openidを含む）
        $this->scopes = [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/business.manage',
        ];
    }

    /**
     * OAuth認証URLを生成
     */
    public function getAuthUrl($shopId): string
    {
        $state = base64_encode(json_encode(['shop_id' => $shopId]));
        
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * 認証コードからアクセストークンとリフレッシュトークンを取得
     */
    public function getTokensFromCode(string $code): array
    {
        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'access_token' => $data['access_token'] ?? null,
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'expires_in' => $data['expires_in'] ?? null,
                ];
            }

            Log::error('Google OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Google OAuth token exchange exception', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * ShopからUser OAuthアクセストークンを取得
     * 保存されたaccess_tokenを使用し、期限切れの場合はrefresh_tokenで再取得
     * App-onlyトークン（emailなし）は無視してrefresh_tokenで再取得
     * 
     * @param \App\Models\Shop $shop Shopモデル
     * @return string|null アクセストークン（User OAuth、email付き）
     */
    public function getAccessToken(\App\Models\Shop $shop): ?string
    {
        // testing環境では実際のOAuthリフレッシュ処理をスキップ
        if (app()->environment('testing')) {
            return 'fake-access-token';
        }

        // 保存されたUser OAuthのaccess_tokenを優先的に使用
        if ($shop->gbp_access_token) {
            // tokeninfoで有効性とUser OAuthかどうかを確認
            try {
                $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($shop->gbp_access_token);
                $tokenInfoResponse = Http::get($tokenInfoUrl);
                
                if ($tokenInfoResponse->successful()) {
                    $tokenInfoData = $tokenInfoResponse->json();
                    $email = $tokenInfoData['email'] ?? null;
                    
                    // App-onlyトークン（emailなし）の場合は無視してrefresh
                    if (!$email) {
                        Log::warning('GBP_ACCESS_TOKEN_APP_ONLY_DETECTED', [
                            'shop_id' => $shop->id,
                            'message' => 'Saved access_token is App-only (no email). Will refresh.',
                        ]);
                        // 保存されたトークンをクリア（App-onlyなので無効）
                        $shop->update(['gbp_access_token' => null]);
                    } elseif ($email) {
                        // User OAuthトークン（email付き）で、有効期限がまだ残っている場合はそのまま使用
                        $expiresIn = $tokenInfoData['expires_in'] ?? 0;
                        // 有効期限が5分以上残っている場合は使用
                        if ($expiresIn > 300) {
                            Log::info('GBP_ACCESS_TOKEN_VALID', [
                                'shop_id' => $shop->id,
                                'email' => $email,
                                'expires_in' => $expiresIn,
                            ]);
                            return $shop->gbp_access_token;
                        } else {
                            Log::info('GBP_ACCESS_TOKEN_EXPIRING_SOON', [
                                'shop_id' => $shop->id,
                                'email' => $email,
                                'expires_in' => $expiresIn,
                                'message' => 'Access token expires soon, will refresh',
                            ]);
                        }
                    }
                } else {
                    // tokeninfo取得失敗（トークンが無効な可能性）
                    Log::warning('GBP_ACCESS_TOKEN_TOKENINFO_FAILED', [
                        'shop_id' => $shop->id,
                        'status' => $tokenInfoResponse->status(),
                        'message' => 'Tokeninfo check failed, will refresh',
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('GBP_ACCESS_TOKEN_VALIDATION_FAILED', [
                    'shop_id' => $shop->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // access_tokenが無効、期限切れ、またはApp-onlyの場合は、refresh_tokenで再取得
        if ($shop->gbp_refresh_token) {
            Log::info('GBP_ACCESS_TOKEN_REFRESHING', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'has_refresh_token' => true,
            ]);
            
            $refreshResult = $this->refreshAccessToken($shop->gbp_refresh_token);
            
            if ($refreshResult && $refreshResult['access_token']) {
                $newAccessToken = $refreshResult['access_token'];
                
                // 新しいaccess_tokenがUser OAuth（email付き）か確認（必須）
                try {
                    $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($newAccessToken);
                    $tokenInfoResponse = Http::get($tokenInfoUrl);
                    
                    if ($tokenInfoResponse->successful()) {
                        $tokenInfoData = $tokenInfoResponse->json();
                        $email = $tokenInfoData['email'] ?? null;
                        
                        if ($email) {
                            // User OAuthのaccess_tokenを保存
                            $shop->update(['gbp_access_token' => $newAccessToken]);
                            
                            Log::info('GBP_ACCESS_TOKEN_REFRESHED_AND_SAVED', [
                                'shop_id' => $shop->id,
                                'email' => $email,
                            ]);
                            
                            return $newAccessToken;
                        } else {
                            // App-onlyトークン（emailなし）の場合は保存しない
                            Log::error('GBP_REFRESHED_TOKEN_APP_ONLY', [
                                'shop_id' => $shop->id,
                                'message' => 'Refreshed token is App-only (no email). Not saved.',
                                'tokeninfo_data' => $tokenInfoData,
                            ]);
                            
                            return null;
                        }
                    } else {
                        Log::error('GBP_REFRESHED_TOKEN_TOKENINFO_FAILED', [
                            'shop_id' => $shop->id,
                            'status' => $tokenInfoResponse->status(),
                            'body' => $tokenInfoResponse->body(),
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('GBP_REFRESHED_TOKEN_VALIDATION_FAILED', [
                        'shop_id' => $shop->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                // refreshAccessToken()が失敗した場合
                $errorCode = $refreshResult['error'] ?? 'unknown';
                $errorMessage = $refreshResult['error_message'] ?? 'リフレッシュトークンが無効です。';
                
                // エラーコードに応じた詳細メッセージ
                if ($errorCode === 'invalid_grant') {
                    $errorMessage = 'リフレッシュトークンが無効または期限切れです。OAuth認証を再度実行してください。';
                } elseif ($errorCode === 'invalid_client') {
                    $errorMessage = 'OAuthクライアント設定が無効です。管理者に連絡してください。';
                } elseif ($errorCode === 'exception') {
                    $errorMessage = 'リフレッシュトークンの取得中にエラーが発生しました: ' . ($refreshResult['error_message'] ?? '不明なエラー');
                }
                
                Log::error('GBP_ACCESS_TOKEN_REFRESH_FAILED', [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'refresh_result' => $refreshResult,
                ]);
                
                // エラーメッセージを保存して、後でgetAccessToken()の呼び出し元で使用できるようにする
                // ただし、これはgetAccessToken()の戻り値がnullなので、エラーメッセージはログに記録されるのみ
            }
        }

        // エラーログを詳細化
        $errorMessage = 'アクセストークンの取得に失敗しました。';
        $errorDetails = [];
        
        if (empty($shop->gbp_refresh_token)) {
            $errorMessage = 'リフレッシュトークンが設定されていません。OAuth認証を再度実行してください。';
            $errorDetails['reason'] = 'no_refresh_token';
        } else {
            $errorMessage = 'リフレッシュトークンからのアクセストークン取得に失敗しました。OAuth認証を再度実行してください。';
            $errorDetails['reason'] = 'refresh_failed';
        }
        
        Log::error('GBP_ACCESS_TOKEN_UNAVAILABLE', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'has_access_token' => !empty($shop->gbp_access_token),
            'has_refresh_token' => !empty($shop->gbp_refresh_token),
            'error_details' => $errorDetails,
        ]);

        return null;
    }

    /**
     * リフレッシュトークンから新しいアクセストークンを取得
     * User OAuth（email付き）のトークンを返す
     * 
     * @param string $refreshToken リフレッシュトークン
     * @return array|null ['access_token' => string, 'error' => string|null] または null
     */
    public function refreshAccessToken(string $refreshToken): ?array
    {
        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $accessToken = $data['access_token'] ?? null;

                if ($accessToken) {
                    // User OAuth確認: tokeninfoでemailを確認（オプション、ログのみ）
                    try {
                        $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($accessToken);
                        $tokenInfoResponse = Http::get($tokenInfoUrl);
                        
                        if ($tokenInfoResponse->successful()) {
                            $tokenInfoData = $tokenInfoResponse->json();
                            $email = $tokenInfoData['email'] ?? null;
                            
                            if ($email) {
                                Log::info('GBP_REFRESH_TOKEN_USER_OAUTH_CONFIRMED', [
                                    'email' => $email,
                                    'has_user_oauth' => true,
                                ]);
                            } else {
                                Log::warning('GBP_REFRESH_TOKEN_APP_ONLY_OAUTH', [
                                    'tokeninfo_data' => $tokenInfoData,
                                    'has_user_oauth' => false,
                                    'message' => 'App-only OAuth detected (no email in tokeninfo)',
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        // tokeninfo確認失敗は無視（ログのみ）
                        Log::debug('GBP_REFRESH_TOKEN_TOKENINFO_CHECK_FAILED', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                return [
                    'access_token' => $accessToken,
                    'error' => null,
                ];
            }

            $errorBody = $response->json();
            $errorCode = $errorBody['error'] ?? 'unknown_error';
            $errorMessage = $errorBody['error_description'] ?? $errorBody['error'] ?? 'Unknown error';
            
            Log::error('Google OAuth token refresh failed', [
                'status' => $response->status(),
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'error_description' => $errorBody['error_description'] ?? null,
                'error_body' => $errorBody,
                'response_body' => $response->body(),
                'client_id_prefix' => substr($this->clientId, 0, 20) . '...',
            ]);

            return [
                'access_token' => null,
                'error' => $errorCode,
                'error_message' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Google OAuth token refresh exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'access_token' => null,
                'error' => 'exception',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Google Business Profile API: accounts.list (v1)
     * Business Profile API v1用のアカウント一覧取得
     */
    public function listAccounts(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts');

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Google Business Profile API accounts.list failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Google Business Profile API accounts.list exception', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * 廃止: v4のaccountIdという概念は不要
     * 
     * Google Business Profile API v4 では「v4のaccountId」という概念は不要です。
     * reviews.list のレスポンスに含まれる review.name が唯一の正しい識別子です。
     * 
     * このメソッドは使用されていませんが、後方互換性のため残しています。
     * 新しいコードでは使用しないでください。
     * 
     * @deprecated このメソッドは使用しないでください。review.nameを直接使用してください。
     */
    public function listAccountsV4(string $accessToken): array
    {
        Log::warning('listAccountsV4() は廃止されました。review.nameを直接使用してください。');
        return [];
    }

    /**
     * Google Business Profile API: locations.list
     * Location ID (CSV用の18桁程度の数値) も抽出して返す
     */
    public function listLocations(string $accessToken, string $accountId): array
    {
        try {
            // accountIdから "accounts/" プレフィックスを除去（DBにはプレフィックスなしで保存されているため）
            $accountIdClean = str_replace('accounts/', '', $accountId);
            
            // readMask パラメータを追加（必須）- 店舗名と住所を取得
            // locationKey は無効なフィールドのため削除
            // title と storefrontAddress は有効なフィールド
            $readMask = 'name,title,storefrontAddress';
            $url = "https://mybusinessbusinessinformation.googleapis.com/v1/accounts/{$accountIdClean}/locations?readMask={$readMask}";
            
            $response = Http::withToken($accessToken)
                ->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Google Business Profile API locations.list failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'account_id' => $accountId,
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Google Business Profile API locations.list exception', [
                'message' => $e->getMessage(),
                'account_id' => $accountId,
            ]);
            return [];
        }
    }

    /**
     * Google Business Profile API: locations.reviews.list
     * 口コミ一覧を取得（ページネーション対応）
     * 
     * 注意: 口コミは旧 My Business API v4 でのみ提供されています
     * 
     * 正しいエンドポイント:
     * GET https://mybusiness.googleapis.com/v4/accounts/{gbp_account_id}/locations/{location_id}/reviews
     * 例: https://mybusiness.googleapis.com/v4/accounts/100814587656903598763/locations/14533069664155190447/reviews
     * 
     * @param string $accessToken OAuthアクセストークン
     * @param string $accountId アカウントID（数値のみ）
     * @param string $locationId ロケーションID（locations/プレフィックス付き）
     * @param int|null $shopId 店舗ID（ログ用、オプション）
     * @return array レビュー一覧のレスポンス（全件取得、フィルタリングは呼び出し側で実施）
     */
    public function listReviews(string $accessToken, string $accountId, string $locationId, ?int $shopId = null, ?string $pageToken = null, int $pageSize = 100): array
    {
        $apiStartTime = microtime(true);
        $pageCount = 0;
        $maxPages = 50; // 無限ループ防止（任意で調整）
        
        try {
            // accountIdは数値のみ（例: "100814587656903598763"）
            // locationIdは "locations/14533069664155190447" の形式で保存されている
            // locationIdから "locations/" プレフィックスを除去して数値のみにする
            $locationIdClean = str_replace('locations/', '', $locationId);
            
            // URL構造: /v4/accounts/{gbp_account_id}/locations/{location_id}/reviews
            // 例: /v4/accounts/100814587656903598763/locations/14533069664155190447/reviews
            $baseUrl = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationIdClean}/reviews";
            
            // APIリクエストパラメータ（差分取得の有無を確認）
            // 注意: GBP API v4 の reviews.list には updateTime フィルタがないため、全件取得
            
            Log::info('REVIEW_SYNC_API_REQUEST_START', [
                'shop_id' => $shopId,
                'account_id' => $accountId,
                'location_id' => $locationId,
                'location_id_clean' => $locationIdClean,
                'url' => $baseUrl,
                'page_size' => $pageSize,
                'page_token' => $pageToken ? substr($pageToken, 0, 20) . '...' : null,
                'has_delta_filter' => false, // updateTime フィルタなし = 全件取得
            ]);
            
            // 単一ページ取得（呼び出し側でループ制御）
            $url = $baseUrl;
            $queryParams = ['pageSize' => $pageSize];
            if ($pageToken) {
                $queryParams['pageToken'] = $pageToken;
            }
            $url .= '?' . http_build_query($queryParams);
            
            $pageCount++;
            if ($pageCount >= $maxPages) {
                \Log::warning('REVIEW_SYNC_MAX_PAGES_REACHED', [
                    'maxPages' => $maxPages,
                    'pageCount' => $pageCount,
                    'shop_id' => $shopId ?? null,
                    'account_id' => $accountId ?? null,
                    'location_id' => $locationId ?? null,
                ]);
                return [
                    'reviews' => [],
                    'nextPageToken' => null,
                    'averageRating' => null,
                    'totalReviewCount' => null,
                ];
            }
            
            $response = Http::withToken($accessToken)
                ->get($url);

            $apiElapsedMs = (microtime(true) - $apiStartTime) * 1000;
            
            if (!$response->successful()) {
                Log::error('Google Business Profile API locations.reviews.list failed', [
                    'shop_id' => $shopId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'account_id' => $accountId,
                    'location_id' => $locationId,
                    'location_id_clean' => $locationIdClean,
                    'page_token' => $pageToken ? substr($pageToken, 0, 20) . '...' : null,
                ]);
                
                // エラー時は空配列を返す
                return [
                    'reviews' => [],
                    'nextPageToken' => null,
                    'averageRating' => null,
                    'totalReviewCount' => null,
                ];
            }
            
            $data = $response->json();
            $reviewsThisPage = $data['reviews'] ?? [];
            $nextPageToken = $data['nextPageToken'] ?? null;
            $averageRating = $data['averageRating'] ?? null;
            $totalReviewCount = $data['totalReviewCount'] ?? null;
            
            // ページネーションログ
            Log::info('GBP_REVIEWS_LIST_PAGINATION', [
                'shop_id' => $shopId,
                'page_size' => $pageSize,
                'fetched_this_page' => count($reviewsThisPage),
                'has_next' => $nextPageToken !== null,
                'next_page_token' => $nextPageToken ? substr($nextPageToken, 0, 20) . '...' : null,
            ]);
            
            // 既存仕様を壊さない形式で返す（単一ページ）
            return [
                'reviews' => $reviewsThisPage,
                'nextPageToken' => $nextPageToken,
                'averageRating' => $averageRating,
                'totalReviewCount' => $totalReviewCount,
            ];
            
        } catch (\Exception $e) {
            Log::error('Google Business Profile API locations.reviews.list exception', [
                'shop_id' => $shopId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'account_id' => $accountId,
                'location_id' => $locationId,
            ]);
            
            // エラー時は空配列を返す（既存のエラーハンドリングに合わせる）
            return [
                'reviews' => [],
                'nextPageToken' => null,
                'averageRating' => null,
                'totalReviewCount' => null,
            ];
        }
    }

    /**
     * Google Business Profile API: locations.media.list
     * 写真・動画一覧を取得（全件取得、フィルタリングは呼び出し側で実施）
     * 
     * @param string $accessToken OAuthアクセストークン
     * @param string $accountId アカウントID（数値のみ、例: "100814587656903598763"）
     * @param string $locationId ロケーションID（数値のみ、例: "14533069664155190447"）
     * @return array メディア一覧のレスポンス
     */
    public function listMedia(string $accessToken, string $accountId, string $locationId): array
    {
        try {
            // locationIdから "locations/" プレフィックスを除去
            $locationIdClean = str_replace('locations/', '', $locationId);
            
            // 正しいエンドポイント: /v4/accounts/{accountId}/locations/{locationId}/media
            // 注意: 全件取得し、フィルタリングは呼び出し側（ReportController）で実施
            $url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationIdClean}/media";
            
            Log::info('GBP_MEDIA_LIST_REQUEST', [
                'account_id' => $accountId,
                'location_id' => $locationId,
                'location_id_clean' => $locationIdClean,
                'url' => $url,
            ]);
            
            $response = Http::withToken($accessToken)
                ->get($url);

            $status = $response->status();
            $body = $response->body();

            if ($response->successful()) {
                $data = $response->json();
                $mediaItems = $data['mediaItems'] ?? [];
                $totalCount = $data['totalMediaItemCount'] ?? 0;
                
                Log::info('GBP_MEDIA_LIST_SUCCESS', [
                    'account_id' => $accountId,
                    'location_id' => $locationId,
                    'total_media_item_count' => $totalCount,
                    'media_items_count' => count($mediaItems),
                ]);
                
                return $data;
            }

            Log::error('GBP_MEDIA_LIST_FAILED', [
                'status' => $status,
                'body' => $body,
                'account_id' => $accountId,
                'location_id' => $locationId,
                'url' => $url,
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('GBP_MEDIA_LIST_EXCEPTION', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'account_id' => $accountId ?? null,
                'location_id' => $locationId ?? null,
            ]);
            return [];
        }
    }

    /**
     * Google Business Profile API: locations.reviews.reply
     * 口コミに返信を送信
     * 
     * 正しいエンドポイント:
     * PUT https://mybusiness.googleapis.com/v4/{review.name}/reply
     * 
     * 重要:
     * - review.name は reviews.list のレスポンスに含まれる値（例: "accounts/100814587656903598763/locations/14533069664155190447/reviews/AbFv..."）
     * - review.name をそのまま v4 のパスに直結
     * - v4のaccountIdという概念は不要
     * - メソッドは PUT（POST ではない）
     * - 末尾は /reply
     */
    public function replyToReview(string $accessToken, string $reviewName, string $replyText): array
    {
        try {
            // review.name をそのまま v4 のパスに直結
            // 例: "accounts/100814587656903598763/locations/14533069664155190447/reviews/AbFv..."
            // → "https://mybusiness.googleapis.com/v4/accounts/100814587656903598763/locations/14533069664155190447/reviews/AbFv.../reply"
            $url = "https://mybusiness.googleapis.com/v4/{$reviewName}/reply";
            
            $requestBody = [
                'comment' => $replyText,
            ];
            
            // curlで再現できる形でログ出力（叩く直前）
            $curlCommand = sprintf(
                "curl -X PUT '%s' \\\n  -H 'Authorization: Bearer %s' \\\n  -H 'Content-Type: application/json' \\\n  -d '%s'",
                $url,
                substr($accessToken, 0, 20) . '...',
                json_encode($requestBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            
            Log::info('GBP_REPLY_REQUEST', [
                'method' => 'PUT',
                'url' => $url,
                'body' => $requestBody,
                'review_name' => $reviewName,
                'curl_command' => $curlCommand,
            ]);
            
            $response = Http::withToken($accessToken)
                ->put($url, $requestBody);
            
            $responseStatus = $response->status();
            $responseBodyRaw = $response->body();
            $responseBody = $response->json();
            
            // curlで再現できる形でログ出力（nullにしない）
            Log::info('GBP_REPLY_RESPONSE', [
                'status' => $responseStatus,
                'response_body' => $responseBodyRaw, // nullにしない（生のレスポンス）
                'response_body_json' => $responseBody,
                'review_name' => $reviewName,
            ]);
            
            // 成功条件: HTTP 200 でOK
            // レスポンス構造: { "comment": "...", "updateTime": "..." }
            // reviewReply ラッパーは存在しない
            if ($response->successful()) {
                Log::info('GBP_REPLY_SUCCESS', [
                    'review_name' => $reviewName,
                    'reply_comment' => $responseBody['comment'] ?? null,
                    'update_time' => $responseBody['updateTime'] ?? null,
                ]);
                return $responseBody;
            }
            
            Log::error('GBP_REPLY_FAILED', [
                'status' => $responseStatus,
                'response_body' => $responseBodyRaw,
                'review_name' => $reviewName,
            ]);
            
            return [];
        } catch (\Exception $e) {
            Log::error('GBP_REPLY_EXCEPTION', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'review_name' => $reviewName,
            ]);
            return [];
        }
    }

    /**
     * STEP3の500エラーを診断する（切り分け処理）
     * 
     * @param string $accessToken OAuthアクセストークン
     * @param string $accountId アカウントID
     * @param string $locationId ロケーションID
     * @param string $resourceName リソース名
     * @param string $authHeaderMasked マスクされたAuthorizationヘッダー（ログ用）
     * @return array 診断結果
     */
    private function diagnoseStep3Failure(
        string $accessToken,
        string $accountId,
        string $locationId,
        string $resourceName,
        string $authHeaderMasked
    ): array {
        $results = [
            'diagnostic_a_location_get' => null,
            'diagnostic_b_category_retry' => null,
            'diagnostic_c_media_list' => null,
        ];

        // ============================================
        // 切り分け処理A: Locationの更新可否をAPIで取得
        // ============================================
        $fullLocationName = "accounts/{$accountId}/locations/{$locationId}";
        $locationGetUrl = "https://mybusiness.googleapis.com/v4/{$fullLocationName}";
        
        Log::info('GBP_DIAGNOSTIC_A_LOCATION_GET_REQUEST', [
            'step' => 'DIAGNOSTIC_A',
            'method' => 'GET',
            'request_url' => $locationGetUrl,
            'request_headers' => [
                'Authorization' => $authHeaderMasked,
            ],
        ]);

        $locationGetRes = Http::withToken($accessToken)
            ->get($locationGetUrl);

        $locationGetStatus = $locationGetRes->status();
        $locationGetBody = $locationGetRes->body();

        if ($locationGetStatus === 200) {
            // 200が返ったらbodyを丸ごとINFOログに残す
            $locationGetBodyJson = $locationGetRes->json();
            Log::info('GBP_DIAGNOSTIC_A_LOCATION_GET_SUCCESS', [
                'step' => 'DIAGNOSTIC_A',
                'http_status' => $locationGetStatus,
                'response_body' => $locationGetBody,
                'response_body_json' => $locationGetBodyJson,
            ]);
            $results['diagnostic_a_location_get'] = [
                'status' => $locationGetStatus,
                'success' => true,
                'message' => 'Location accessible',
            ];
        } elseif (in_array($locationGetStatus, [403, 404])) {
            // 403/404なら権限 or account/location不整合
            Log::error('GBP_DIAGNOSTIC_A_LOCATION_GET_FAILED', [
                'step' => 'DIAGNOSTIC_A',
                'http_status' => $locationGetStatus,
                'response_body' => $locationGetBody,
                'message' => 'Permission or account/location mismatch',
            ]);
            $results['diagnostic_a_location_get'] = [
                'status' => $locationGetStatus,
                'success' => false,
                'message' => 'Permission or account/location mismatch',
            ];
        } else {
            Log::warning('GBP_DIAGNOSTIC_A_LOCATION_GET_UNEXPECTED', [
                'step' => 'DIAGNOSTIC_A',
                'http_status' => $locationGetStatus,
                'response_body' => $locationGetBody,
            ]);
            $results['diagnostic_a_location_get'] = [
                'status' => $locationGetStatus,
                'success' => false,
                'message' => 'Unexpected status',
            ];
        }

        // ============================================
        // 切り分け処理B: categoryを変えてmedia.createを再試行
        // ============================================
        $categories = ['EXTERIOR', 'INTERIOR', 'LOGO', 'COVER'];
        $createUrl = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationId}/media";
        
        $categoryResults = [];
        $categorySuccess = false;

        $successfulResponse = null;
        $successfulCategory = null;

        foreach ($categories as $category) {
            $payload = [
                'mediaFormat' => 'PHOTO',
                'locationAssociation' => [
                    'category' => $category,
                ],
                'dataRef' => [
                    'resourceName' => $resourceName,
                ],
            ];

            Log::info('GBP_DIAGNOSTIC_B_CATEGORY_RETRY_REQUEST', [
                'step' => 'DIAGNOSTIC_B',
                'category' => $category,
                'method' => 'POST',
                'request_url' => $createUrl,
                'request_headers' => [
                    'Authorization' => $authHeaderMasked,
                    'Content-Type' => 'application/json',
                ],
                'request_body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $categoryRes = Http::withToken($accessToken)
                ->asJson()
                ->post($createUrl, $payload);

            $categoryStatus = $categoryRes->status();
            $categoryBody = $categoryRes->body();

            Log::info('GBP_DIAGNOSTIC_B_CATEGORY_RETRY_RESPONSE', [
                'step' => 'DIAGNOSTIC_B',
                'category' => $category,
                'http_status' => $categoryStatus,
                'response_body' => $categoryBody,
            ]);

            $categoryResults[$category] = [
                'status' => $categoryStatus,
                'success' => $categoryRes->successful(),
            ];

            if ($categoryRes->successful()) {
                // どれか1つでも200になれば成功
                $categorySuccess = true;
                $successfulCategory = $category;
                $successfulResponse = $categoryRes->json();
                Log::info('GBP_DIAGNOSTIC_B_CATEGORY_RETRY_SUCCESS', [
                    'step' => 'DIAGNOSTIC_B',
                    'category' => $category,
                    'http_status' => $categoryStatus,
                ]);
                break;
            }
        }

        $results['diagnostic_b_category_retry'] = [
            'success' => $categorySuccess,
            'successful_category' => $successfulCategory,
            'successful_response' => $successfulResponse,
            'results' => $categoryResults,
        ];

        // ============================================
        // 切り分け処理C: media.listが通るか確認
        // ============================================
        $mediaListUrl = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationId}/media?pageSize=1";
        
        Log::info('GBP_DIAGNOSTIC_C_MEDIA_LIST_REQUEST', [
            'step' => 'DIAGNOSTIC_C',
            'method' => 'GET',
            'request_url' => $mediaListUrl,
            'request_headers' => [
                'Authorization' => $authHeaderMasked,
            ],
        ]);

        $mediaListRes = Http::withToken($accessToken)
            ->get($mediaListUrl);

        $mediaListStatus = $mediaListRes->status();
        $mediaListBody = $mediaListRes->body();

        if ($mediaListStatus === 200) {
            // 200なら「readはOK、createだけ死ぬ」= Locationのwrite tier問題の可能性が高い
            Log::info('GBP_DIAGNOSTIC_C_MEDIA_LIST_SUCCESS', [
                'step' => 'DIAGNOSTIC_C',
                'http_status' => $mediaListStatus,
                'response_body' => $mediaListBody,
                'message' => 'Read OK, create fails - possible write tier issue',
            ]);
            $results['diagnostic_c_media_list'] = [
                'status' => $mediaListStatus,
                'success' => true,
                'message' => 'Read OK, create fails - possible write tier issue',
            ];
        } elseif (in_array($mediaListStatus, [403, 404])) {
            // 403/404なら「write以前に権限/紐付け問題」
            Log::error('GBP_DIAGNOSTIC_C_MEDIA_LIST_FAILED', [
                'step' => 'DIAGNOSTIC_C',
                'http_status' => $mediaListStatus,
                'response_body' => $mediaListBody,
                'message' => 'Permission/binding issue before write',
            ]);
            $results['diagnostic_c_media_list'] = [
                'status' => $mediaListStatus,
                'success' => false,
                'message' => 'Permission/binding issue before write',
            ];
        } else {
            Log::warning('GBP_DIAGNOSTIC_C_MEDIA_LIST_UNEXPECTED', [
                'step' => 'DIAGNOSTIC_C',
                'http_status' => $mediaListStatus,
                'response_body' => $mediaListBody,
            ]);
            $results['diagnostic_c_media_list'] = [
                'status' => $mediaListStatus,
                'success' => false,
                'message' => 'Unexpected status',
            ];
        }

        // 診断結果をまとめてログ出力
        Log::warning('GBP_DIAGNOSTIC_COMPLETE', [
            'account_id' => $accountId,
            'location_id' => $locationId,
            'resource_name' => $resourceName,
            'diagnostic_results' => $results,
            'summary' => [
                'location_get' => $results['diagnostic_a_location_get']['message'] ?? 'Unknown',
                'category_retry_success' => $results['diagnostic_b_category_retry']['success'] ?? false,
                'media_list' => $results['diagnostic_c_media_list']['message'] ?? 'Unknown',
            ],
        ]);

        return $results;
    }

    /**
     * Google Business Profile API: locations.localPosts.list
     * 投稿（Local Posts）一覧を取得（全件取得、フィルタリングは呼び出し側で実施）
     * 
     * @param string $accessToken OAuthアクセストークン
     * @param string $accountId アカウントID（数値のみ、例: "100814587656903598763"）
     * @param string $locationId ロケーションID（数値のみ、例: "14533069664155190447"）
     * @param string|null $pageToken ページトークン（オプション）
     * @return array ['localPosts' => array, 'nextPageToken' => string|null]
     */
    public function listLocalPosts(string $accessToken, string $accountId, string $locationId, ?string $pageToken = null): array
    {
        try {
            // locationIdから "locations/" プレフィックスを除去
            $locationIdClean = str_replace('locations/', '', $locationId);
            
            // 正しいエンドポイント: /v4/accounts/{accountId}/locations/{locationId}/localPosts
            $url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationIdClean}/localPosts?pageSize=20";
            if ($pageToken) {
                $url .= "&pageToken=" . urlencode($pageToken);
            }
            
            Log::info('LOCAL_POSTS_LIST_REQUEST', [
                'url' => $url,
                'account_id' => $accountId,
                'location_id' => $locationId,
                'location_id_clean' => $locationIdClean,
                'page_size' => 20,
                'page_token' => $pageToken ? 'present' : null,
            ]);
            
            $response = Http::withToken($accessToken)
                ->get($url);

            $status = $response->status();
            $body = $response->body();
            $bodyJson = $response->json();

            Log::info('LOCAL_POSTS_LIST_RESPONSE', [
                'status_code' => $status,
                'response_body' => $body,
                'response_body_json' => $bodyJson,
                'account_id' => $accountId,
                'location_id' => $locationId,
                'location_id_clean' => $locationIdClean,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $localPosts = $data['localPosts'] ?? [];
                $nextPageToken = $data['nextPageToken'] ?? null;
                
                Log::info('LOCAL_POSTS_LIST_SUCCESS', [
                    'account_id' => $accountId,
                    'location_id' => $locationId,
                    'local_posts_count' => count($localPosts),
                    'has_next_page' => $nextPageToken !== null,
                ]);
                
                return [
                    'localPosts' => $localPosts,
                    'nextPageToken' => $nextPageToken,
                ];
            } else {
                // エラーの場合は詳細をログ出力
                Log::error('LOCAL_POSTS_LIST_FAILED', [
                    'status_code' => $status,
                    'response_body' => $body,
                    'response_body_json' => $bodyJson,
                    'account_id' => $accountId,
                    'location_id' => $locationId,
                    'location_id_clean' => $locationIdClean,
                    'url' => $url,
                ]);
                return ['localPosts' => [], 'nextPageToken' => null];
            }
        } catch (\Exception $e) {
            Log::error('LOCAL_POSTS_LIST_EXCEPTION', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'account_id' => $accountId ?? null,
                'location_id' => $locationId ?? null,
            ]);
            return ['localPosts' => [], 'nextPageToken' => null];
        }
    }

    /**
     * Google Business Profile API: locations.localPosts.create
     * 投稿（Local Post）を作成
     * 
     * @param string $accessToken OAuthアクセストークン
     * @param string $accountId アカウントID（数値のみ）
     * @param string $locationId ロケーションID（数値のみ、locations/プレフィックスなし）
     * @param string|null $summary 投稿の本文（タイトルのみ）
     * @param string|null $imageUrl 画像URL（オプション）
     * @param string|null $articleUrl 記事URL（callToAction用）
     * @param \App\Models\Shop|null $shop 店舗モデル（フォールバック画像用、オプション）
     * @return array|null 作成された投稿の情報、またはnull（エラー時）
     */
    public function createLocalPost(string $accessToken, string $accountId, string $locationId, ?string $summary = null, ?string $imageUrl = null, ?string $articleUrl = null, ?\App\Models\Shop $shop = null): ?array
    {
        try {
            // locationIdから "locations/" プレフィックスを除去
            $locationIdClean = str_replace('locations/', '', $locationId);
            
            $url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationIdClean}/localPosts";
            
            // WordPressのサムネイルURLを正規化（オリジナル画像URLに変換）
            $originalImageUrl = $imageUrl;
            if ($imageUrl && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $normalizedImageUrl = $this->normalizeImageUrl($imageUrl);
                if ($normalizedImageUrl !== $imageUrl) {
                    Log::info('LOCAL_POST_IMAGE_URL_NORMALIZED', [
                        'original' => $imageUrl,
                        'normalized' => $normalizedImageUrl,
                    ]);
                    $imageUrl = $normalizedImageUrl;
                }
            }
            
            $payload = [
                'topicType' => 'STANDARD',
            ];
            
            if ($summary) {
                $payload['summary'] = $summary;
            }
            
            // 有効URLの場合のみmediaを含める
            if ($imageUrl && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $payload['media'] = [
                    [
                        'mediaFormat' => 'PHOTO',
                        'sourceUrl' => $imageUrl,
                    ]
                ];
            }
            
            if ($articleUrl) {
                $payload['callToAction'] = [
                    'actionType' => 'LEARN_MORE',
                    'url' => $articleUrl,
                ];
            }
            
            Log::info('LOCAL_POST_CREATE_REQUEST', [
                'url' => $url,
                'account_id' => $accountId,
                'location_id' => $locationId,
                'location_id_clean' => $locationIdClean,
                'summary_length' => $summary ? strlen($summary) : 0,
                'has_image' => !empty($imageUrl),
                'image_url' => $imageUrl,
            ]);
            
            $response = Http::withToken($accessToken)
                ->post($url, $payload);
            
            $status = $response->status();
            $body = $response->body();
            $bodyJson = $response->json();
            
            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('LOCAL_POST_CREATE_SUCCESS', [
                    'account_id' => $accountId,
                    'location_id' => $locationId,
                    'gbp_post_name' => $data['name'] ?? null,
                    'gbp_post_id' => isset($data['name']) ? str_replace('localPosts/', '', $data['name']) : null,
                ]);
                
                return $data;
            }
            
            // エラーレスポンスを解析
            $isImageSizeError = false;
            $errorMessage = '';
            $subErrorCode = null;
            
            if (isset($bodyJson['error'])) {
                $errorMessage = $bodyJson['error']['message'] ?? '';
                $subErrorCode = $bodyJson['error']['status'] ?? null;
                
                // 画像サイズエラーの判定
                // Google APIのエラーメッセージに "Image too small" や "INVALID_ARGUMENT" が含まれる場合
                // または subErrorCode が 21 の場合（画像サイズ関連エラー）
                if (
                    stripos($errorMessage, 'Image too small') !== false ||
                    stripos($errorMessage, 'INVALID_ARGUMENT') !== false ||
                    (isset($bodyJson['error']['details']) && is_array($bodyJson['error']['details']))
                ) {
                    // エラー詳細を確認
                    foreach ($bodyJson['error']['details'] ?? [] as $detail) {
                        if (isset($detail['@type']) && strpos($detail['@type'], 'ErrorInfo') !== false) {
                            $reason = $detail['reason'] ?? '';
                            $domain = $detail['domain'] ?? '';
                            
                            // 画像サイズ関連のエラーコードを確認
                            if (
                                $reason === 'INVALID_ARGUMENT' ||
                                (isset($detail['metadata']) && isset($detail['metadata']['subErrorCode']) && $detail['metadata']['subErrorCode'] == 21)
                            ) {
                                $isImageSizeError = true;
                                break;
                            }
                        }
                    }
                }
            }
            
            Log::error('LOCAL_POST_CREATE_FAILED', [
                'status_code' => $status,
                'response_body' => $body,
                'response_body_json' => $bodyJson,
                'account_id' => $accountId,
                'location_id' => $locationId,
                'url' => $url,
                'is_image_size_error' => $isImageSizeError,
                'error_message' => $errorMessage,
                'sub_error_code' => $subErrorCode,
            ]);
            
            // 画像サイズエラーの場合、フォールバック画像で再試行
            if ($isImageSizeError && $shop && $shop->blog_fallback_image_url) {
                Log::info('LOCAL_POST_FALLBACK_IMAGE_RETRY', [
                    'account_id' => $accountId,
                    'location_id' => $locationId,
                    'original_image_url' => $originalImageUrl,
                    'fallback_image_url' => $shop->blog_fallback_image_url,
                ]);
                
                // フォールバック画像で再試行
                $fallbackPayload = [
                    'topicType' => 'STANDARD',
                ];
                
                if ($summary) {
                    $fallbackPayload['summary'] = $summary;
                }
                
                $fallbackPayload['media'] = [
                    [
                        'mediaFormat' => 'PHOTO',
                        'sourceUrl' => $shop->blog_fallback_image_url,
                    ]
                ];
                
                if ($articleUrl) {
                    $fallbackPayload['callToAction'] = [
                        'actionType' => 'LEARN_MORE',
                        'url' => $articleUrl,
                    ];
                }
                
                $fallbackResponse = Http::withToken($accessToken)
                    ->post($url, $fallbackPayload);
                
                if ($fallbackResponse->successful()) {
                    $fallbackData = $fallbackResponse->json();
                    
                    Log::info('LOCAL_POST_CREATE_SUCCESS_WITH_FALLBACK', [
                        'account_id' => $accountId,
                        'location_id' => $locationId,
                        'gbp_post_name' => $fallbackData['name'] ?? null,
                        'gbp_post_id' => isset($fallbackData['name']) ? str_replace('localPosts/', '', $fallbackData['name']) : null,
                        'original_image_url' => $originalImageUrl,
                        'fallback_image_url' => $shop->blog_fallback_image_url,
                    ]);
                    
                    return $fallbackData;
                } else {
                    Log::error('LOCAL_POST_CREATE_FAILED_WITH_FALLBACK', [
                        'status_code' => $fallbackResponse->status(),
                        'response_body' => $fallbackResponse->body(),
                        'account_id' => $accountId,
                        'location_id' => $locationId,
                    ]);
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('LOCAL_POST_CREATE_EXCEPTION', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'account_id' => $accountId ?? null,
                'location_id' => $locationId ?? null,
            ]);
            return null;
        }
    }
    
    /**
     * WordPressのサムネイルURLをオリジナル画像URLに変換
     * 例: ...-150x150.png → ...png
     * 
     * @param string $imageUrl 画像URL
     * @return string 正規化された画像URL
     */
    private function normalizeImageUrl(string $imageUrl): string
    {
        // WordPressのサムネイルサイズ指定パターンを削除
        // 例: image-150x150.jpg, image-300x200.png など
        $normalized = preg_replace('/-(\d+)x(\d+)(\.[a-zA-Z0-9]+)$/', '$3', $imageUrl);
        
        return $normalized;
    }

    /**
     * 投稿を同期してDBに保存する
     * 
     * @param \App\Models\Shop $shop
     * @param string $sinceDate 〇月〇日以降のみ同期（Y-m-d形式、JST）
     * @return array ['inserted' => int, 'updated' => int]
     */
    public function syncLocalPostsAndSave(\App\Models\Shop $shop, string $sinceDate = null): array
    {
        if (!$shop->gbp_location_id || !$shop->gbp_account_id) {
            Log::warning('GBP_POST_SYNC_MISSING_IDS', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        $accessToken = $this->getAccessToken($shop);
        if (!$accessToken) {
            Log::error('GBP_POST_SYNC_NO_ACCESS_TOKEN', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        // sinceDateをUTCに変換
        $sinceUtc = $sinceDate 
            ? CarbonImmutable::parse($sinceDate, 'Asia/Tokyo')->startOfDay()->timezone('UTC')
            : null;

        // DBの最新create_timeを取得
        $latestDbCreateTime = GbpPost::where('shop_id', $shop->id)
            ->max('create_time');

        Log::info('GBP_POST_SYNC_LATEST_DB_TIME', [
            'shop_id' => $shop->id,
            'latest_db_create_time' => $latestDbCreateTime,
            'since_date' => $sinceDate,
            'since_utc' => $sinceUtc ? $sinceUtc->toIso8601String() : null,
        ]);

        // APIから投稿一覧を取得（ページングループ対応）
        $allApiPosts = [];
        $nextPageToken = null;
        $pageCount = 0;
        $maxPages = 50;
        $shouldStop = false;

        do {
            $pageCount++;
            
            // 無限ループ防止
            if ($pageCount > $maxPages) {
                Log::warning('GBP_POST_SYNC_MAX_PAGES_REACHED', [
                    'shop_id' => $shop->id,
                    'max_pages' => $maxPages,
                ]);
                break;
            }

            // 1ページ取得
            $response = $this->listLocalPosts($accessToken, $shop->gbp_account_id, $shop->gbp_location_id, $nextPageToken);
            
            if (empty($response['localPosts'])) {
                break;
            }

            $apiPosts = $response['localPosts'] ?? [];
            $nextPageToken = $response['nextPageToken'] ?? null;

            // STANDARD投稿のみ抽出し、sinceDateで打ち切り
            foreach ($apiPosts as $post) {
                // topicTypeチェック
                if (($post['topicType'] ?? 'STANDARD') !== 'STANDARD') {
                    continue;
                }

                // sinceDateによる打ち切り判定（createTime基準）
                if ($sinceUtc !== null && isset($post['createTime'])) {
                    $postTime = CarbonImmutable::parse($post['createTime'], 'UTC');
                    if ($postTime->lessThan($sinceUtc)) {
                        $shouldStop = true;
                        Log::info('GBP_POST_SYNC_STOPPED_BY_SINCE', [
                            'shop_id' => $shop->id,
                            'since_date' => $sinceDate,
                            'stopped_at' => $postTime->toDateTimeString(),
                            'page' => $pageCount,
                        ]);
                        break; // 古いデータに到達したら打ち切り（APIは最新順で返る前提）
                    }
                }

                $allApiPosts[] = $post;
            }

            // ページループも停止
            if ($shouldStop) {
                break;
            }

        } while ($nextPageToken !== null);

        if (empty($allApiPosts)) {
            Log::info('GBP_POST_SYNC_EMPTY', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        $standardPosts = $allApiPosts;

        // 新規投稿のみ抽出（$apiTime > $latestDbCreateTime）
        $newPosts = collect($standardPosts)->filter(function ($post) use ($latestDbCreateTime) {
            if (!isset($post['createTime'])) {
                return false; // createTimeがない投稿はスキップ
            }

            $apiTime = CarbonImmutable::parse($post['createTime'], 'UTC')->format('Y-m-d H:i:s');
            
            // 最新DB時刻がない場合、またはAPI時刻が最新DB時刻より新しい場合のみ対象
            return !$latestDbCreateTime || $apiTime > $latestDbCreateTime;
        });

        if (empty($standardPosts)) {
            Log::info('GBP_POST_SYNC_NO_STANDARD_POSTS', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'api_posts_count' => count($apiPosts),
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        // 新規投稿のみ抽出（$apiTime > $latestDbCreateTime）
        $newPosts = collect($standardPosts)->filter(function ($post) use ($latestDbCreateTime) {
            if (!isset($post['createTime'])) {
                return false; // createTimeがない投稿はスキップ
            }

            $apiTime = CarbonImmutable::parse($post['createTime'], 'UTC')->format('Y-m-d H:i:s');
            
            // 最新DB時刻がない場合、またはAPI時刻が最新DB時刻より新しい場合のみ対象
            return !$latestDbCreateTime || $apiTime > $latestDbCreateTime;
        });

        if ($newPosts->isEmpty()) {
            Log::info('GBP_POST_SYNC_NO_NEW_POSTS', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'api_posts_count' => count($apiPosts),
                'standard_posts_count' => count($standardPosts),
                'new_posts_count' => 0,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        // bulk insert用のデータを準備
        $now = now();
        $insertData = $newPosts->map(function ($post) use ($shop, $now) {
            $gbpPostName = $post['name'] ?? null;
            if (!$gbpPostName) {
                return null;
            }

            $gbpPostId = basename($gbpPostName);
            $createTime = null;
            if (isset($post['createTime'])) {
                $createTime = CarbonImmutable::parse($post['createTime'], 'UTC')->format('Y-m-d H:i:s');
            }

            return [
                'shop_id' => $shop->id,
                'gbp_post_id' => $gbpPostId,
                'gbp_post_name' => $gbpPostName,
                'summary' => $post['summary'] ?? null,
                'create_time' => $createTime,
                'media_url' => isset($post['media']) && is_array($post['media']) && count($post['media']) > 0 
                    ? ($post['media'][0]['sourceUrl'] ?? null) 
                    : null,
                'is_deleted' => false,
                'fetched_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->filter()->values()->toArray();

        if (empty($insertData)) {
            Log::info('GBP_POST_SYNC_NO_VALID_POSTS', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'new_posts_count' => $newPosts->count(),
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        // bulk insert実行
        try {
            GbpPost::insert($insertData);
            
            Log::info('GBP_POST_SYNC_COMPLETE', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'api_posts_count' => count($apiPosts),
                'standard_posts_count' => count($standardPosts),
                'new_posts_count' => $newPosts->count(),
                'inserted_count' => count($insertData),
            ]);

            Log::info('SYNC_WITH_SINCE_FILTER', [
                'shop_id' => $shop->id,
                'since_date' => $sinceDate,
                'type' => 'post',
            ]);

            return [
                'inserted' => count($insertData),
                'updated' => 0,
            ];
        } catch (\Exception $e) {
            Log::error('GBP_POST_SYNC_INSERT_ERROR', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'insert_data_count' => count($insertData),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }
    }

    /**
     * 期間内の投稿数を集計する
     * 
     * @param \App\Models\Shop $shop
     * @param string $fromJst JST日付文字列（例: '2026-01-01'）
     * @param string $toJst JST日付文字列（例: '2026-01-31'）
     * @return int
     */
    public function countPostsByPeriod(\App\Models\Shop $shop, string $fromJst, string $toJst): int
    {
        $fromUtc = CarbonImmutable::parse($fromJst, 'Asia/Tokyo')
            ->startOfDay()
            ->timezone('UTC');

        $toUtc = CarbonImmutable::parse($toJst, 'Asia/Tokyo')
            ->endOfDay()
            ->timezone('UTC');

        return GbpPost::where('shop_id', $shop->id)
            ->where('is_deleted', false)
            ->whereBetween('create_time', [
                $fromUtc->format('Y-m-d H:i:s'),
                $toUtc->format('Y-m-d H:i:s'),
            ])
            ->count();
    }

    /**
     * 月の日別投稿数を集計する
     * 
     * @param \App\Models\Shop $shop
     * @param int $year
     * @param int $month
     * @return \Illuminate\Support\Collection key: 'Y-m-d', value: count
     */
    public function getDailyPostCounts(\App\Models\Shop $shop, int $year, int $month): \Illuminate\Support\Collection
    {
        $startJst = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Asia/Tokyo');
        $endJst = $startJst->endOfMonth();

        $startUtc = $startJst->timezone('UTC');
        $endUtc = $endJst->endOfDay()->timezone('UTC');

        $posts = GbpPost::where('shop_id', $shop->id)
            ->where('is_deleted', false)
            ->whereBetween('create_time', [
                $startUtc->format('Y-m-d H:i:s'),
                $endUtc->format('Y-m-d H:i:s'),
            ])
            ->get(['create_time']);

        return $posts->groupBy(function ($post) {
            return $post->create_time
                ->timezone('Asia/Tokyo')
                ->format('Y-m-d');
        })->map->count();
    }

}