<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoogleBusinessProfileService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class CheckGbpLocations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gbp:check-locations {--account-id=100814587656903598763 : Account ID to check} {--shop-id= : Shop ID to get access token from} {--access-token= : Direct access token (overrides shop-id)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check which locations are owned by a Google Business Profile account';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accountId = $this->option('account-id');
        $shopId = $this->option('shop-id');
        $accessToken = $this->option('access-token');

        // アクセストークンの取得
        if (!$accessToken && $shopId) {
            $this->info("Shop IDからアクセストークンを取得します...");
            $shop = \App\Models\Shop::find($shopId);
            
            if (!$shop) {
                $this->error("Shop ID {$shopId} が見つかりません。");
                return 1;
            }

            if (!$shop->gbp_refresh_token) {
                $this->error("Shop ID {$shopId} にリフレッシュトークンが保存されていません。");
                return 1;
            }

            $gbpService = new GoogleBusinessProfileService();
            $refreshResult = $gbpService->refreshAccessToken($shop->gbp_refresh_token);
            if (!$refreshResult || !$refreshResult['access_token']) {
                $errorMsg = $refreshResult['error_message'] ?? 'リフレッシュトークンが無効です。';
                $this->error("ショップ {$shop->name} (ID: {$shop->id}): アクセストークンの取得に失敗しました。 ({$errorMsg})");
                continue;
            }
            $accessToken = $refreshResult['access_token'];
                $this->error("アクセストークンの取得に失敗しました。");
                return 1;
            }

            $this->info("アクセストークンを取得しました。");
        } elseif (!$accessToken) {
            $this->error("--shop-id または --access-token のいずれかを指定してください。");
            return 1;
        }

        // トークン情報を確認
        $this->info("==========================================");
        $this->info("OAuthトークン情報確認");
        $this->info("==========================================");
        $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($accessToken);
        $this->info("URL: {$tokenInfoUrl}");
        $this->info("");

        $client = new Client(['http_errors' => false]);

        try {
            $tokenInfoResponse = $client->request('GET', $tokenInfoUrl);
            $tokenInfoStatusCode = $tokenInfoResponse->getStatusCode();
            $tokenInfoBody = $tokenInfoResponse->getBody()->getContents();
            $tokenInfoData = json_decode($tokenInfoBody, true);

            if ($tokenInfoStatusCode === 200 && $tokenInfoData !== null) {
                $this->info("HTTP Status: {$tokenInfoStatusCode}");
                $this->info("");
                $this->info("トークン情報:");
                $prettyTokenInfo = json_encode($tokenInfoData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                $this->line($prettyTokenInfo);
                $this->info("");

                // スコープの確認
                if (isset($tokenInfoData['scope'])) {
                    $scopes = explode(' ', $tokenInfoData['scope']);
                    $this->info("スコープ一覧:");
                    foreach ($scopes as $scope) {
                        $this->info("  - {$scope}");
                    }
                    $this->info("");

                    // business.manageスコープの確認
                    $hasBusinessManage = false;
                    foreach ($scopes as $scope) {
                        if (strpos($scope, 'business.manage') !== false) {
                            $hasBusinessManage = true;
                            break;
                        }
                    }

                    if ($hasBusinessManage) {
                        $this->info("✓ business.manage スコープが含まれています");
                    } else {
                        $this->warn("⚠ business.manage スコープが含まれていません");
                    }
                    $this->info("");
                }

                // 有効期限の確認
                if (isset($tokenInfoData['expires_in'])) {
                    $expiresIn = (int)$tokenInfoData['expires_in'];
                    $expiresAt = now()->addSeconds($expiresIn);
                    $this->info("有効期限: {$expiresIn}秒後 ({$expiresAt->format('Y-m-d H:i:s')})");
                    $this->info("");
                }
            } else {
                $this->warn("トークン情報の取得に失敗しました。");
                $this->warn("HTTP Status: {$tokenInfoStatusCode}");
                $this->warn("Response: {$tokenInfoBody}");
                $this->info("");
            }
        } catch (\Exception $e) {
            $this->warn("トークン情報の確認中にエラーが発生しました: " . $e->getMessage());
            $this->info("");
        }

        // APIリクエスト
        $url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations?readMask=name";
        
        $this->info("==========================================");
        $this->info("APIリクエスト");
        $this->info("==========================================");
        $this->info("URL: {$url}");
        $this->info("Method: GET");
        $this->info("Authorization: Bearer " . substr($accessToken, 0, 20) . "...");
        $this->info("");

        try {
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            $this->info("==========================================");
            $this->info("HTTPステータスコード");
            $this->info("==========================================");
            $this->info("Status: {$statusCode}");
            $this->info("");

            $this->info("==========================================");
            $this->info("レスポンスJSON (Raw)");
            $this->info("==========================================");
            
            // JSONを整形して表示
            $jsonData = json_decode($body, true);
            if ($jsonData !== null) {
                $prettyJson = json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                $this->line($prettyJson);
            } else {
                $this->line($body);
            }
            $this->info("");

            // 特定のlocationが含まれているかチェック
            $targetLocation = "accounts/{$accountId}/locations/14533069664155190447";
            $this->info("==========================================");
            $this->info("Location検証");
            $this->info("==========================================");
            $this->info("検索対象: {$targetLocation}");
            $this->info("");

            if ($jsonData !== null && isset($jsonData['locations'])) {
                $locations = $jsonData['locations'];
                $this->info("取得されたLocation数: " . count($locations));
                $this->info("");

                $found = false;
                foreach ($locations as $index => $location) {
                    $locationName = $location['name'] ?? null;
                    $this->info("Location #" . ($index + 1) . ": {$locationName}");
                    
                    if ($locationName === $targetLocation) {
                        $found = true;
                        $this->info("  ✓ 一致しました！");
                    }
                }
                $this->info("");

                if ($found) {
                    $this->info("==========================================");
                    $this->info("結果: 指定のLocationが見つかりました");
                    $this->info("==========================================");
                } else {
                    $this->warn("==========================================");
                    $this->warn("結果: 指定のLocationは見つかりませんでした");
                    $this->warn("==========================================");
                }
            } else {
                $this->warn("レスポンスに 'locations' キーが含まれていません。");
            }

            return 0;

        } catch (RequestException $e) {
            $this->error("==========================================");
            $this->error("リクエストエラー");
            $this->error("==========================================");
            $this->error("Message: " . $e->getMessage());
            
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                
                $this->error("HTTP Status: {$statusCode}");
                $this->error("Response Body: {$body}");
            }
            
            return 1;
        } catch (\Exception $e) {
            $this->error("==========================================");
            $this->error("予期しないエラー");
            $this->error("==========================================");
            $this->error("Message: " . $e->getMessage());
            $this->error("Trace: " . $e->getTraceAsString());
            
            return 1;
        }
    }
}

