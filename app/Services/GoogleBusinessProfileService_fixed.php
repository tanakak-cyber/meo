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
        // User OAuth逕ｨ縺ｮ繧ｹ繧ｳ繝ｼ繝暦ｼ・mail, profile, openid繧貞性繧・・        $this->scopes = [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/business.manage',
        ];
    }

    /**
     * OAuth隱崎ｨｼURL繧堤函謌・     */
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
     * 隱崎ｨｼ繧ｳ繝ｼ繝峨°繧峨い繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ縺ｨ繝ｪ繝輔Ξ繝・す繝･繝医・繧ｯ繝ｳ繧貞叙蠕・     */
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
     * Shop縺九ｉUser OAuth繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ繧貞叙蠕・     * 菫晏ｭ倥＆繧後◆access_token繧剃ｽｿ逕ｨ縺励∵悄髯仙・繧後・蝣ｴ蜷医・refresh_token縺ｧ蜀榊叙蠕・     * App-only繝医・繧ｯ繝ｳ・・mail縺ｪ縺暦ｼ峨・辟｡隕悶＠縺ｦrefresh_token縺ｧ蜀榊叙蠕・     * 
     * @param \App\Models\Shop $shop Shop繝｢繝・Ν
     * @return string|null 繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ・・ser OAuth縲‘mail莉倥″・・     */
    public function getAccessToken(\App\Models\Shop $shop): ?string
    {
        // 菫晏ｭ倥＆繧後◆User OAuth縺ｮaccess_token繧貞━蜈育噪縺ｫ菴ｿ逕ｨ
        if ($shop->gbp_access_token) {
            // tokeninfo縺ｧ譛牙柑諤ｧ縺ｨUser OAuth縺九←縺・°繧堤｢ｺ隱・            try {
                $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($shop->gbp_access_token);
                $tokenInfoResponse = Http::get($tokenInfoUrl);
                
                if ($tokenInfoResponse->successful()) {
                    $tokenInfoData = $tokenInfoResponse->json();
                    $email = $tokenInfoData['email'] ?? null;
                    
                    // App-only繝医・繧ｯ繝ｳ・・mail縺ｪ縺暦ｼ峨・蝣ｴ蜷医・辟｡隕悶＠縺ｦrefresh
                    if (!$email) {
                        Log::warning('GBP_ACCESS_TOKEN_APP_ONLY_DETECTED', [
                            'shop_id' => $shop->id,
                            'message' => 'Saved access_token is App-only (no email). Will refresh.',
                        ]);
                        // 菫晏ｭ倥＆繧後◆繝医・繧ｯ繝ｳ繧偵け繝ｪ繧｢・・pp-only縺ｪ縺ｮ縺ｧ辟｡蜉ｹ・・                        $shop->update(['gbp_access_token' => null]);
                    } elseif ($email) {
                        // User OAuth繝医・繧ｯ繝ｳ・・mail莉倥″・峨〒縲∵怏蜉ｹ譛滄剞縺後∪縺谿九▲縺ｦ縺・ｋ蝣ｴ蜷医・縺昴・縺ｾ縺ｾ菴ｿ逕ｨ
                        $expiresIn = $tokenInfoData['expires_in'] ?? 0;
                        // 譛牙柑譛滄剞縺・蛻・ｻ･荳頑ｮ九▲縺ｦ縺・ｋ蝣ｴ蜷医・菴ｿ逕ｨ
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
                    // tokeninfo蜿門ｾ怜､ｱ謨暦ｼ医ヨ繝ｼ繧ｯ繝ｳ縺檎┌蜉ｹ縺ｪ蜿ｯ閭ｽ諤ｧ・・                    Log::warning('GBP_ACCESS_TOKEN_TOKENINFO_FAILED', [
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

        // access_token縺檎┌蜉ｹ縲∵悄髯仙・繧後√∪縺溘・App-only縺ｮ蝣ｴ蜷医・縲〉efresh_token縺ｧ蜀榊叙蠕・        if ($shop->gbp_refresh_token) {
            Log::info('GBP_ACCESS_TOKEN_REFRESHING', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'has_refresh_token' => true,
            ]);
            
            $refreshResult = $this->refreshAccessToken($shop->gbp_refresh_token);
            
            if ($refreshResult && $refreshResult['access_token']) {
                $newAccessToken = $refreshResult['access_token'];
                
                // 譁ｰ縺励＞access_token縺袈ser OAuth・・mail莉倥″・峨°遒ｺ隱搾ｼ亥ｿ・茨ｼ・                try {
                    $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($newAccessToken);
                    $tokenInfoResponse = Http::get($tokenInfoUrl);
                    
                    if ($tokenInfoResponse->successful()) {
                        $tokenInfoData = $tokenInfoResponse->json();
                        $email = $tokenInfoData['email'] ?? null;
                        
                        if ($email) {
                            // User OAuth縺ｮaccess_token繧剃ｿ晏ｭ・                            $shop->update(['gbp_access_token' => $newAccessToken]);
                            
                            Log::info('GBP_ACCESS_TOKEN_REFRESHED_AND_SAVED', [
                                'shop_id' => $shop->id,
                                'email' => $email,
                            ]);
                            
                            return $newAccessToken;
                        } else {
                            // App-only繝医・繧ｯ繝ｳ・・mail縺ｪ縺暦ｼ峨・蝣ｴ蜷医・菫晏ｭ倥＠縺ｪ縺・                            Log::error('GBP_REFRESHED_TOKEN_APP_ONLY', [
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
                // refreshAccessToken()縺悟､ｱ謨励＠縺溷ｴ蜷・                $errorCode = $refreshResult['error'] ?? 'unknown';
                $errorMessage = $refreshResult['error_message'] ?? '繝ｪ繝輔Ξ繝・す繝･繝医・繧ｯ繝ｳ縺檎┌蜉ｹ縺ｧ縺吶・;
                
                // 繧ｨ繝ｩ繝ｼ繧ｳ繝ｼ繝峨↓蠢懊§縺溯ｩｳ邏ｰ繝｡繝・そ繝ｼ繧ｸ
                if ($errorCode === 'invalid_grant') {
                    $errorMessage = '繝ｪ繝輔Ξ繝・す繝･繝医・繧ｯ繝ｳ縺檎┌蜉ｹ縺ｾ縺溘・譛滄剞蛻・ｌ縺ｧ縺吶０Auth隱崎ｨｼ繧貞・蠎ｦ螳溯｡後＠縺ｦ縺上□縺輔＞縲・;
                } elseif ($errorCode === 'invalid_client') {
                    $errorMessage = 'OAuth繧ｯ繝ｩ繧､繧｢繝ｳ繝郁ｨｭ螳壹′辟｡蜉ｹ縺ｧ縺吶らｮ｡逅・・↓騾｣邨｡縺励※縺上□縺輔＞縲・;
                } elseif ($errorCode === 'exception') {
                    $errorMessage = '繝ｪ繝輔Ξ繝・す繝･繝医・繧ｯ繝ｳ縺ｮ蜿門ｾ嶺ｸｭ縺ｫ繧ｨ繝ｩ繝ｼ縺檎匱逕溘＠縺ｾ縺励◆: ' . ($refreshResult['error_message'] ?? '荳肴・縺ｪ繧ｨ繝ｩ繝ｼ');
                }
                
                Log::error('GBP_ACCESS_TOKEN_REFRESH_FAILED', [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'refresh_result' => $refreshResult,
                ]);
                
                // 繧ｨ繝ｩ繝ｼ繝｡繝・そ繝ｼ繧ｸ繧剃ｿ晏ｭ倥＠縺ｦ縲∝ｾ後〒getAccessToken()縺ｮ蜻ｼ縺ｳ蜃ｺ縺怜・縺ｧ菴ｿ逕ｨ縺ｧ縺阪ｋ繧医≧縺ｫ縺吶ｋ
                // 縺溘□縺励√％繧後・getAccessToken()縺ｮ謌ｻ繧雁､縺系ull縺ｪ縺ｮ縺ｧ縲√お繝ｩ繝ｼ繝｡繝・そ繝ｼ繧ｸ縺ｯ繝ｭ繧ｰ縺ｫ險倬鹸縺輔ｌ繧九・縺ｿ
            }
        }

        // 繧ｨ繝ｩ繝ｼ繝ｭ繧ｰ繧定ｩｳ邏ｰ蛹・        $errorMessage = '繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ縺ｮ蜿門ｾ励↓螟ｱ謨励＠縺ｾ縺励◆縲・;
        $errorDetails = [];
        
        if (empty($shop->gbp_refresh_token)) {
            $errorMessage = '繝ｪ繝輔Ξ繝・す繝･繝医・繧ｯ繝ｳ縺瑚ｨｭ螳壹＆繧後※縺・∪縺帙ｓ縲０Auth隱崎ｨｼ繧貞・蠎ｦ螳溯｡後＠縺ｦ縺上□縺輔＞縲・;
            $errorDetails['reason'] = 'no_refresh_token';
        } else {
            $errorMessage = '繝ｪ繝輔Ξ繝・す繝･繝医・繧ｯ繝ｳ縺九ｉ縺ｮ繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ蜿門ｾ励↓螟ｱ謨励＠縺ｾ縺励◆縲０Auth隱崎ｨｼ繧貞・蠎ｦ螳溯｡後＠縺ｦ縺上□縺輔＞縲・;
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
     * 繝ｪ繝輔Ξ繝・す繝･繝医・繧ｯ繝ｳ縺九ｉ譁ｰ縺励＞繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ繧貞叙蠕・     * User OAuth・・mail莉倥″・峨・繝医・繧ｯ繝ｳ繧定ｿ斐☆
     * 
     * @param string $refreshToken 繝ｪ繝輔Ξ繝・す繝･繝医・繧ｯ繝ｳ
     * @return array|null ['access_token' => string, 'error' => string|null] 縺ｾ縺溘・ null
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
                    // User OAuth遒ｺ隱・ tokeninfo縺ｧemail繧堤｢ｺ隱搾ｼ医が繝励す繝ｧ繝ｳ縲√Ο繧ｰ縺ｮ縺ｿ・・                    try {
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
                        // tokeninfo遒ｺ隱榊､ｱ謨励・辟｡隕厄ｼ医Ο繧ｰ縺ｮ縺ｿ・・                        Log::debug('GBP_REFRESH_TOKEN_TOKENINFO_CHECK_FAILED', [
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
     * Business Profile API v1逕ｨ縺ｮ繧｢繧ｫ繧ｦ繝ｳ繝井ｸ隕ｧ蜿門ｾ・     */
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
     * 蟒・ｭ｢: v4縺ｮaccountId縺ｨ縺・≧讎ょｿｵ縺ｯ荳崎ｦ・     * 
     * Google Business Profile API v4 縺ｧ縺ｯ縲計4縺ｮaccountId縲阪→縺・≧讎ょｿｵ縺ｯ荳崎ｦ√〒縺吶・     * reviews.list 縺ｮ繝ｬ繧ｹ繝昴Φ繧ｹ縺ｫ蜷ｫ縺ｾ繧後ｋ review.name 縺悟髪荳縺ｮ豁｣縺励＞隴伜挨蟄舌〒縺吶・     * 
     * 縺薙・繝｡繧ｽ繝・ラ縺ｯ菴ｿ逕ｨ縺輔ｌ縺ｦ縺・∪縺帙ｓ縺後∝ｾ梧婿莠呈鋤諤ｧ縺ｮ縺溘ａ谿九＠縺ｦ縺・∪縺吶・     * 譁ｰ縺励＞繧ｳ繝ｼ繝峨〒縺ｯ菴ｿ逕ｨ縺励↑縺・〒縺上□縺輔＞縲・     * 
     * @deprecated 縺薙・繝｡繧ｽ繝・ラ縺ｯ菴ｿ逕ｨ縺励↑縺・〒縺上□縺輔＞縲Ｓeview.name繧堤峩謗･菴ｿ逕ｨ縺励※縺上□縺輔＞縲・     */
    public function listAccountsV4(string $accessToken): array
    {
        Log::warning('listAccountsV4() 縺ｯ蟒・ｭ｢縺輔ｌ縺ｾ縺励◆縲Ｓeview.name繧堤峩謗･菴ｿ逕ｨ縺励※縺上□縺輔＞縲・);
        return [];
    }

    /**
     * Google Business Profile API: locations.list
     */
    public function listLocations(string $accessToken, string $accountId): array
    {
        try {
            // accountId縺九ｉ "accounts/" 繝励Ξ繝輔ぅ繝・け繧ｹ繧帝勁蜴ｻ・・B縺ｫ縺ｯ繝励Ξ繝輔ぅ繝・け繧ｹ縺ｪ縺励〒菫晏ｭ倥＆繧後※縺・ｋ縺溘ａ・・            $accountIdClean = str_replace('accounts/', '', $accountId);
            
            // readMask 繝代Λ繝｡繝ｼ繧ｿ繧定ｿｽ蜉・亥ｿ・茨ｼ・ 蠎苓・蜷阪→菴乗園繧貞叙蠕・            // locationKey 縺ｯ辟｡蜉ｹ縺ｪ繝輔ぅ繝ｼ繝ｫ繝峨・縺溘ａ蜑企勁
            // title 縺ｨ storefrontAddress 縺ｯ譛牙柑縺ｪ繝輔ぅ繝ｼ繝ｫ繝・            $readMask = 'name,title,storefrontAddress';
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
     * 蜿｣繧ｳ繝滉ｸ隕ｧ繧貞叙蠕・     * 
     * 豕ｨ諢・ 蜿｣繧ｳ繝溘・譌ｧ My Business API v4 縺ｧ縺ｮ縺ｿ謠蝉ｾ帙＆繧後※縺・∪縺・     * 
     * 豁｣縺励＞繧ｨ繝ｳ繝峨・繧､繝ｳ繝・
     * GET https://mybusiness.googleapis.com/v4/accounts/{gbp_account_id}/locations/{location_id}/reviews
     * 萓・ https://mybusiness.googleapis.com/v4/accounts/100814587656903598763/locations/14533069664155190447/reviews
     */
    public function listReviews(string $accessToken, string $accountId, string $locationId): array
    {
        try {
            // accountId縺ｯ謨ｰ蛟､縺ｮ縺ｿ・井ｾ・ "100814587656903598763"・・            // locationId縺ｯ "locations/14533069664155190447" 縺ｮ蠖｢蠑上〒菫晏ｭ倥＆繧後※縺・ｋ
            // locationId縺九ｉ "locations/" 繝励Ξ繝輔ぅ繝・け繧ｹ繧帝勁蜴ｻ縺励※謨ｰ蛟､縺ｮ縺ｿ縺ｫ縺吶ｋ
            $locationIdClean = str_replace('locations/', '', $locationId);
            
            // URL讒矩: /v4/accounts/{gbp_account_id}/locations/{location_id}/reviews
            // 萓・ /v4/accounts/100814587656903598763/locations/14533069664155190447/reviews
            $url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationIdClean}/reviews";
            
            Log::info('Google Business Profile API locations.reviews.list 繝ｪ繧ｯ繧ｨ繧ｹ繝・, [
                'url' => $url,
                'account_id' => $accountId,
                'location_id' => $locationId,
                'location_id_clean' => $locationIdClean,
            ]);
            
            $response = Http::withToken($accessToken)
                ->get($url);

            Log::info('Google Business Profile API locations.reviews.list 繝ｬ繧ｹ繝昴Φ繧ｹ', [
                'status' => $response->status(),
                'account_id' => $accountId,
                'location_id' => $locationId,
                'location_id_clean' => $locationIdClean,
                'response_body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $reviews = $data['reviews'] ?? [];
                
                // reviews[i].name 繧偵Ο繧ｰ蜃ｺ蜉・                $reviewNames = [];
                foreach ($reviews as $index => $review) {
                    $reviewName = $review['name'] ?? null;
                    $reviewNames[] = [
                        'index' => $index,
                        'name' => $reviewName,
                        'reviewId' => $review['reviewId'] ?? null,
                    ];
                }
                
                Log::info('Google Business Profile API locations.reviews.list 謌仙粥', [
                    'account_id' => $accountId,
                    'location_id' => $locationId,
                    'response_keys' => array_keys($data ?? []),
                    'reviews_count' => count($reviews),
                    'review_names' => $reviewNames,
                ]);
                return $data;
            }

            Log::error('Google Business Profile API locations.reviews.list failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'account_id' => $accountId,
                'location_id' => $locationId,
                'location_id_clean' => $locationIdClean,
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Google Business Profile API locations.reviews.list exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'account_id' => $accountId,
                'location_id' => $locationId,
            ]);
            return [];
        }
    }

    /**
     * Google Business Profile API: locations.media.list
     * 蜀咏悄繝ｻ蜍慕判荳隕ｧ繧貞叙蠕・     * 
     * @param string $accessToken OAuth繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ
     * @param string $accountId 繧｢繧ｫ繧ｦ繝ｳ繝・D・域焚蛟､縺ｮ縺ｿ縲∽ｾ・ "100814587656903598763"・・     * @param string $locationId 繝ｭ繧ｱ繝ｼ繧ｷ繝ｧ繝ｳID・域焚蛟､縺ｮ縺ｿ縲∽ｾ・ "14533069664155190447"・・     * @return array 繝｡繝・ぅ繧｢荳隕ｧ縺ｮ繝ｬ繧ｹ繝昴Φ繧ｹ
     */
    public function listMedia(string $accessToken, string $accountId, string $locationId): array
    {
        try {
            // locationId縺九ｉ "locations/" 繝励Ξ繝輔ぅ繝・け繧ｹ繧帝勁蜴ｻ
            $locationIdClean = str_replace('locations/', '', $locationId);
            
            // 豁｣縺励＞繧ｨ繝ｳ繝峨・繧､繝ｳ繝・ /v4/accounts/{accountId}/locations/{locationId}/media
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
     * 蜿｣繧ｳ繝溘↓霑比ｿ｡繧帝∽ｿ｡
     * 
     * 豁｣縺励＞繧ｨ繝ｳ繝峨・繧､繝ｳ繝・
     * PUT https://mybusiness.googleapis.com/v4/{review.name}/reply
     * 
     * 驥崎ｦ・
     * - review.name 縺ｯ reviews.list 縺ｮ繝ｬ繧ｹ繝昴Φ繧ｹ縺ｫ蜷ｫ縺ｾ繧後ｋ蛟､・井ｾ・ "accounts/100814587656903598763/locations/14533069664155190447/reviews/AbFv..."・・     * - review.name 繧偵◎縺ｮ縺ｾ縺ｾ v4 縺ｮ繝代せ縺ｫ逶ｴ邨・     * - v4縺ｮaccountId縺ｨ縺・≧讎ょｿｵ縺ｯ荳崎ｦ・     * - 繝｡繧ｽ繝・ラ縺ｯ PUT・・OST 縺ｧ縺ｯ縺ｪ縺・ｼ・     * - 譛ｫ蟆ｾ縺ｯ /reply
     */
    public function replyToReview(string $accessToken, string $reviewName, string $replyText): array
    {
        try {
            // review.name 繧偵◎縺ｮ縺ｾ縺ｾ v4 縺ｮ繝代せ縺ｫ逶ｴ邨・            // 萓・ "accounts/100814587656903598763/locations/14533069664155190447/reviews/AbFv..."
            // 竊・"https://mybusiness.googleapis.com/v4/accounts/100814587656903598763/locations/14533069664155190447/reviews/AbFv.../reply"
            $url = "https://mybusiness.googleapis.com/v4/{$reviewName}/reply";
            
            $requestBody = [
                'comment' => $replyText,
            ];
            
            // curl縺ｧ蜀咲樟縺ｧ縺阪ｋ蠖｢縺ｧ繝ｭ繧ｰ蜃ｺ蜉幢ｼ亥娼縺冗峩蜑搾ｼ・            $curlCommand = sprintf(
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
            
            // curl縺ｧ蜀咲樟縺ｧ縺阪ｋ蠖｢縺ｧ繝ｭ繧ｰ蜃ｺ蜉幢ｼ・ull縺ｫ縺励↑縺・ｼ・            Log::info('GBP_REPLY_RESPONSE', [
                'status' => $responseStatus,
                'response_body' => $responseBodyRaw, // null縺ｫ縺励↑縺・ｼ育函縺ｮ繝ｬ繧ｹ繝昴Φ繧ｹ・・                'response_body_json' => $responseBody,
                'review_name' => $reviewName,
            ]);
            
            // 謌仙粥譚｡莉ｶ: HTTP 200 縺ｧOK
            // 繝ｬ繧ｹ繝昴Φ繧ｹ讒矩: { "comment": "...", "updateTime": "..." }
            // reviewReply 繝ｩ繝・ヱ繝ｼ縺ｯ蟄伜惠縺励↑縺・            if ($response->successful()) {
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
     * STEP3縺ｮ500繧ｨ繝ｩ繝ｼ繧定ｨｺ譁ｭ縺吶ｋ・亥・繧雁・縺大・逅・ｼ・     * 
     * @param string $accessToken OAuth繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ
     * @param string $accountId 繧｢繧ｫ繧ｦ繝ｳ繝・D
     * @param string $locationId 繝ｭ繧ｱ繝ｼ繧ｷ繝ｧ繝ｳID
     * @param string $resourceName 繝ｪ繧ｽ繝ｼ繧ｹ蜷・     * @param string $authHeaderMasked 繝槭せ繧ｯ縺輔ｌ縺蘗uthorization繝倥ャ繝繝ｼ・医Ο繧ｰ逕ｨ・・     * @return array 險ｺ譁ｭ邨先棡
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
        // 蛻・ｊ蛻・￠蜃ｦ逅・: Location縺ｮ譖ｴ譁ｰ蜿ｯ蜷ｦ繧但PI縺ｧ蜿門ｾ・        // ============================================
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
            // 200縺瑚ｿ斐▲縺溘ｉbody繧剃ｸｸ縺斐→INFO繝ｭ繧ｰ縺ｫ谿九☆
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
            // 403/404縺ｪ繧画ｨｩ髯・or account/location荳肴紛蜷・            Log::error('GBP_DIAGNOSTIC_A_LOCATION_GET_FAILED', [
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
        // 蛻・ｊ蛻・￠蜃ｦ逅・: category繧貞､峨∴縺ｦmedia.create繧貞・隧ｦ陦・        // ============================================
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
                // 縺ｩ繧後°1縺､縺ｧ繧・00縺ｫ縺ｪ繧後・謌仙粥
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
        // 蛻・ｊ蛻・￠蜃ｦ逅・: media.list縺碁壹ｋ縺狗｢ｺ隱・        // ============================================
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
            // 200縺ｪ繧峨罫ead縺ｯOK縲…reate縺縺第ｭｻ縺ｬ縲・ Location縺ｮwrite tier蝠城｡後・蜿ｯ閭ｽ諤ｧ縺碁ｫ倥＞
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
            // 403/404縺ｪ繧峨詣rite莉･蜑阪↓讓ｩ髯・邏蝉ｻ倥￠蝠城｡後・            Log::error('GBP_DIAGNOSTIC_C_MEDIA_LIST_FAILED', [
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

        // 險ｺ譁ｭ邨先棡繧偵∪縺ｨ繧√※繝ｭ繧ｰ蜃ｺ蜉・        Log::warning('GBP_DIAGNOSTIC_COMPLETE', [
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
     * 謚慕ｨｿ・・ocal Posts・我ｸ隕ｧ繧貞叙蠕・     * 
     * @param string $accessToken OAuth繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ
     * @param string $accountId 繧｢繧ｫ繧ｦ繝ｳ繝・D・域焚蛟､縺ｮ縺ｿ縲∽ｾ・ "100814587656903598763"・・     * @param string $locationId 繝ｭ繧ｱ繝ｼ繧ｷ繝ｧ繝ｳID・域焚蛟､縺ｮ縺ｿ縲∽ｾ・ "14533069664155190447"・・     * @return array 謚慕ｨｿ荳隕ｧ縺ｮ繝ｬ繧ｹ繝昴Φ繧ｹ
     */
    public function listLocalPosts(string $accessToken, string $accountId, string $locationId): array
    {
        try {
            // locationId縺九ｉ "locations/" 繝励Ξ繝輔ぅ繝・け繧ｹ繧帝勁蜴ｻ
            $locationIdClean = str_replace('locations/', '', $locationId);
            
            // 豁｣縺励＞繧ｨ繝ｳ繝峨・繧､繝ｳ繝・ /v4/accounts/{accountId}/locations/{locationId}/localPosts
            $url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationIdClean}/localPosts";
            
            // 繝ｪ繧ｯ繧ｨ繧ｹ繝域ュ蝣ｱ繧偵Ο繧ｰ蜃ｺ蜉幢ｼ・edia縺ｨ蜷後§邊貞ｺｦ・・            Log::info('LOCAL_POSTS_LIST_REQUEST', [
                'url' => $url,
                'account_id' => $accountId,
                'location_id' => $locationId,
                'location_id_clean' => $locationIdClean,
            ]);
            
            $response = Http::withToken($accessToken)
                ->get($url);

            $status = $response->status();
            $body = $response->body();
            $bodyJson = $response->json();

            // 繝ｬ繧ｹ繝昴Φ繧ｹ諠・ｱ繧偵Ο繧ｰ蜃ｺ蜉幢ｼ・TTP繧ｹ繝・・繧ｿ繧ｹ縺ｨ繝ｬ繧ｹ繝昴Φ繧ｹ蜈ｨ譁・ｼ・            Log::info('LOCAL_POSTS_LIST_RESPONSE', [
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
                
                $count = count($localPosts);
                
                // URL豈碑ｼ・畑縺ｫcallToAction.url繧呈歓蜃ｺ縺励※繝ｭ繧ｰ蜃ｺ蜉・                $urls = [];
                foreach ($localPosts as $post) {
                    if (isset($post['callToAction']['url'])) {
                        $urls[] = $post['callToAction']['url'];
                    }
                }
                
                Log::info('LOCAL_POSTS_LIST_SUCCESS', [
                    'account_id' => $accountId,
                    'location_id' => $locationId,
                    'local_posts_count' => $count,
                    'call_to_action_urls' => $urls,
                ]);
                
                // URL豈碑ｼ・Ο繧ｸ繝・け逕ｨ縺ｫlocalPosts驟榊・繧定ｿ斐☆
                return $localPosts;
            }

            // 繧ｨ繝ｩ繝ｼ縺ｮ蝣ｴ蜷医・隧ｳ邏ｰ繧偵Ο繧ｰ蜃ｺ蜉・            Log::error('LOCAL_POSTS_LIST_FAILED', [
                'status_code' => $status,
                'response_body' => $body,
                'response_body_json' => $bodyJson,
                'account_id' => $accountId,
                'location_id' => $locationId,
                'location_id_clean' => $locationIdClean,
                'url' => $url,
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('LOCAL_POSTS_LIST_EXCEPTION', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'account_id' => $accountId ?? null,
                'location_id' => $locationId ?? null,
            ]);
            return [];
        }
    }

    /**
     * Google Business Profile API: locations.localPosts.create
     * 謚慕ｨｿ・・ocal Post・峨ｒ菴懈・
     * 
     * @param string $accessToken OAuth繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ
     * @param string $accountId 繧｢繧ｫ繧ｦ繝ｳ繝・D・域焚蛟､縺ｮ縺ｿ・・     * @param string $locationId 繝ｭ繧ｱ繝ｼ繧ｷ繝ｧ繝ｳID・域焚蛟､縺ｮ縺ｿ縲〕ocations/繝励Ξ繝輔ぅ繝・け繧ｹ縺ｪ縺暦ｼ・     * @param string|null $summary 謚慕ｨｿ縺ｮ譛ｬ譁・ｼ医ち繧､繝医Ν縺ｮ縺ｿ・・     * @param string|null $imageUrl 逕ｻ蜒酋RL・医が繝励す繝ｧ繝ｳ・・     * @param string|null $articleUrl 險倅ｺ偽RL・・allToAction逕ｨ・・     * @return array|null 菴懈・縺輔ｌ縺滓兜遞ｿ縺ｮ諠・ｱ縲√∪縺溘・null・医お繝ｩ繝ｼ譎ゑｼ・     */
    public function createLocalPost(string $accessToken, string $accountId, string $locationId, ?string $summary = null, ?string $imageUrl = null, ?string $articleUrl = null): ?array
    {
        try {
            // locationId縺九ｉ "locations/" 繝励Ξ繝輔ぅ繝・け繧ｹ繧帝勁蜴ｻ
            $locationIdClean = str_replace('locations/', '', $locationId);
            
            $url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationIdClean}/localPosts";
            
            $payload = [
                'topicType' => 'STANDARD',
            ];
            
            if ($summary) {
                $payload['summary'] = $summary;
            }
            
            // 譛牙柑URL縺ｮ蝣ｴ蜷医・縺ｿmedia繧貞性繧√ｋ
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
            
            Log::error('LOCAL_POST_CREATE_FAILED', [
                'status_code' => $status,
                'response_body' => $body,
                'response_body_json' => $bodyJson,
                'account_id' => $accountId,
                'location_id' => $locationId,
                'url' => $url,
            ]);
            
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
}
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
     * Shop縺九ｉUser OAuth繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ繧貞叙蠕・     * 菫晏ｭ倥＆繧後◆access_token繧剃ｽｿ逕ｨ縺励∵悄髯仙・繧後・蝣ｴ蜷医・refresh_token縺ｧ蜀榊叙蠕・     * App-only繝医・繧ｯ繝ｳ・・mail縺ｪ縺暦ｼ峨・辟｡隕悶＠縺ｦrefresh_token縺ｧ蜀榊叙蠕・     * 
     * @param \App\Models\Shop $shop Shop繝｢繝・Ν
     * @return string|null 繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ・・ser OAuth縲‘mail莉倥″・・     */
    public function getAccessToken(\App\Models\Shop $shop): ?string
    {
        // 菫晏ｭ倥＆繧後◆User OAuth縺ｮaccess_token繧貞━蜈育噪縺ｫ菴ｿ逕ｨ
        if ($shop->gbp_access_token) {
            // tokeninfo縺ｧ譛牙柑諤ｧ縺ｨUser OAuth縺九←縺・°繧堤｢ｺ隱・            try {
                $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($shop->gbp_access_token);
                $tokenInfoResponse = Http::get($tokenInfoUrl);
                
                if ($tokenInfoResponse->successful()) {
                    $tokenInfoData = $tokenInfoResponse->json();
                    $email = $tokenInfoData['email'] ?? null;
                    
                    // App-only繝医・繧ｯ繝ｳ・・mail縺ｪ縺暦ｼ峨・蝣ｴ蜷医・辟｡隕悶＠縺ｦrefresh
                    if (!$email) {
                        Log::warning('GBP_ACCESS_TOKEN_APP_ONLY_DETECTED', [
                            'shop_id' => $shop->id,
                            'message' => 'Saved access_token is App-only (no email). Will refresh.',
                        ]);
                        // 菫晏ｭ倥＆繧後◆繝医・繧ｯ繝ｳ繧偵け繝ｪ繧｢・・pp-only縺ｪ縺ｮ縺ｧ辟｡蜉ｹ・・                        $shop->update(['gbp_access_token' => null]);
                    } elseif ($email) {
                        // User OAuth繝医・繧ｯ繝ｳ・・mail莉倥″・峨〒縲∵怏蜉ｹ譛滄剞縺後∪縺谿九▲縺ｦ縺・ｋ蝣ｴ蜷医・縺昴・縺ｾ縺ｾ菴ｿ逕ｨ
                        $expiresIn = $tokenInfoData['expires_in'] ?? 0;
                        // 譛牙柑譛滄剞縺・蛻・ｻ･荳頑ｮ九▲縺ｦ縺・ｋ蝣ｴ蜷医・菴ｿ逕ｨ
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
