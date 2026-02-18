<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

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
            $newAccessToken = $this->refreshAccessToken($shop->gbp_refresh_token);
            
            if ($newAccessToken) {
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
            }
        }

        Log::error('GBP_ACCESS_TOKEN_UNAVAILABLE', [
            'shop_id' => $shop->id,
            'has_access_token' => !empty($shop->gbp_access_token),
            'has_refresh_token' => !empty($shop->gbp_refresh_token),
        ]);

        return null;
    }

    /**
     * リフレッシュトークンから新しいアクセストークンを取得
     * User OAuth（email付き）のトークンを返す
     */
    public function refreshAccessToken(string $refreshToken): ?string
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

                return $accessToken;
            }

            Log::error('Google OAuth token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Google OAuth token refresh exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Google Business Profile API: accounts.list (v1)
     * アカウント一覧を取得
     */
    public function listAccounts(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://mybusiness.googleapis.com/v1/accounts');

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
     * Google Business Profile API: locations.list (v4)
     * ロケーション一覧を取得
     */
    public function listLocations(string $accessToken, string $accountId): array
    {
        try {
            $url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations";
            
            $response = Http::withToken($accessToken)
                ->get($url, [
                    'readMask' => 'name,title,storefrontAddress',
                ]);

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
     * Google Business Profile API: locations.media.list
     * 写真・動画一覧を取得
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
     * Google Business Profile API: reviews.list
     * 口コミ一覧を取得
     */
    public function listReviews(string $accessToken, string $accountId, string $locationId): array
    {
        try {
            // locationIdから "locations/" プレフィックスを除去
            $locationIdClean = str_replace('locations/', '', $locationId);
            
            $url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationIdClean}/reviews";
            
            $response = Http::withToken($accessToken)
                ->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Google Business Profile API reviews.list failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'account_id' => $accountId,
                'location_id' => $locationId,
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Google Business Profile API reviews.list exception', [
                'message' => $e->getMessage(),
                'account_id' => $accountId,
                'location_id' => $locationId,
            ]);
            return [];
        }
    }

    /**
     * Google Business Profile API: reviews.reply
     * 口コミに返信
     */
    public function replyToReview(string $accessToken, string $reviewName, string $comment): array
    {
        try {
            $url = "https://mybusiness.googleapis.com/v4/{$reviewName}/reply";
            
            $response = Http::withToken($accessToken)
                ->asJson()
                ->post($url, [
                    'comment' => $comment,
                ]);

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

}


















