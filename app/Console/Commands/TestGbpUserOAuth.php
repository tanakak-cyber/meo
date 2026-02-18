<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoogleBusinessProfileService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;

class TestGbpUserOAuth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gbp:test-user-oauth 
                            {--shop-id= : Shop ID to test}
                            {--account-id=100814587656903598763 : Account ID to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test User OAuth token and verify success conditions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shopId = $this->option('shop-id');
        $accountId = $this->option('account-id');

        if (!$shopId) {
            $this->error("--shop-id を指定してください。");
            return 1;
        }

        $shop = \App\Models\Shop::find($shopId);
        if (!$shop) {
            $this->error("Shop ID {$shopId} が見つかりません。");
            return 1;
        }

        $this->info("==========================================");
        $this->info("User OAuth テスト開始");
        $this->info("==========================================");
        $this->info("Shop ID: {$shopId}");
        $this->info("Shop Name: {$shop->name}");
        $this->info("Account ID: {$accountId}");
        $this->info("");

        $googleService = new GoogleBusinessProfileService();

        // アクセストークンを取得
        $this->info("【1】アクセストークンを取得中...");
        $accessToken = $googleService->getAccessToken($shop);
        
        if (!$accessToken) {
            $this->error("❌ アクセストークンの取得に失敗しました。");
            return 1;
        }
        $this->info("✓ アクセストークンを取得しました");
        $this->info("");

        // ============================================
        // 成功条件1: tokeninfoにemailが表示される
        // ============================================
        $this->info("==========================================");
        $this->info("【成功条件1】tokeninfoでemail確認");
        $this->info("==========================================");
        
        $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($accessToken);
        $this->info("URL: {$tokenInfoUrl}");
        $this->info("");

        try {
            $tokenInfoResponse = Http::get($tokenInfoUrl);
            $tokenInfoStatusCode = $tokenInfoResponse->status();
            $tokenInfoBody = $tokenInfoResponse->body();
            $tokenInfoData = json_decode($tokenInfoBody, true);

            $this->info("HTTP Status: {$tokenInfoStatusCode}");
            $this->info("");

            if ($tokenInfoStatusCode === 200 && $tokenInfoData !== null) {
                $email = $tokenInfoData['email'] ?? null;
                $this->info("レスポンスJSON:");
                $prettyJson = json_encode($tokenInfoData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                $this->line($prettyJson);
                $this->info("");

                if ($email === 'kaunja0501@gmail.com') {
                    $this->info("✅ 成功条件1: tokeninfoにemail: \"kaunja0501@gmail.com\"が表示される");
                } elseif ($email) {
                    $this->warn("⚠ 成功条件1: emailは表示されますが、期待値と異なります");
                    $this->warn("   期待値: kaunja0501@gmail.com");
                    $this->warn("   実際の値: {$email}");
                } else {
                    $this->error("❌ 成功条件1: tokeninfoにemailが含まれていません（App-only OAuthの可能性）");
                }
            } else {
                $this->error("❌ tokeninfoの取得に失敗しました");
                $this->error("HTTP Status: {$tokenInfoStatusCode}");
                $this->error("Response: {$tokenInfoBody}");
            }
        } catch (\Exception $e) {
            $this->error("❌ tokeninfo確認中にエラー: " . $e->getMessage());
        }

        $this->info("");

        // ============================================
        // 成功条件2: GET /v4/accounts/{accountId}/locations が 200
        // ============================================
        $this->info("==========================================");
        $this->info("【成功条件2】GET /v4/accounts/{accountId}/locations が 200");
        $this->info("==========================================");
        
        $locationsUrl = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations?readMask=name";
        $this->info("URL: {$locationsUrl}");
        $this->info("");

        $client = new Client(['http_errors' => false]);

        try {
            $locationsResponse = $client->request('GET', $locationsUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $locationsStatusCode = $locationsResponse->getStatusCode();
            $locationsBody = $locationsResponse->getBody()->getContents();
            $locationsData = json_decode($locationsBody, true);

            $this->info("HTTP Status: {$locationsStatusCode}");
            $this->info("");

            if ($locationsStatusCode === 200) {
                $this->info("✅ 成功条件2: GET /v4/accounts/{$accountId}/locations が 200");
                $this->info("");
                $this->info("レスポンスJSON:");
                $prettyJson = json_encode($locationsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                $this->line($prettyJson);
                $this->info("");

                if (isset($locationsData['locations'])) {
                    $locationCount = count($locationsData['locations']);
                    $this->info("取得されたLocation数: {$locationCount}");
                    
                    // 特定のlocationが含まれているか確認
                    $targetLocation = "accounts/{$accountId}/locations/14533069664155190447";
                    $found = false;
                    foreach ($locationsData['locations'] as $location) {
                        if (($location['name'] ?? null) === $targetLocation) {
                            $found = true;
                            break;
                        }
                    }
                    
                    if ($found) {
                        $this->info("✓ 指定のLocation ({$targetLocation}) が見つかりました");
                    } else {
                        $this->warn("⚠ 指定のLocation ({$targetLocation}) は見つかりませんでした");
                    }
                }
            } else {
                $this->error("❌ 成功条件2: GET /v4/accounts/{$accountId}/locations が 200 ではありません");
                $this->error("HTTP Status: {$locationsStatusCode}");
                $this->error("Response: {$locationsBody}");
            }
        } catch (\Exception $e) {
            $this->error("❌ locations取得中にエラー: " . $e->getMessage());
        }

        $this->info("");

        // ============================================
        // 成功条件3: POST /media が成功する
        // ============================================
        $this->info("==========================================");
        $this->info("【成功条件3】POST /media が成功する");
        $this->info("==========================================");
        $this->warn("注意: 実際の写真アップロードをテストするには、テスト用のJPEGファイルが必要です。");
        $this->info("");

        // テスト用の小さなJPEGファイルを作成（10x10ピクセルの最小JPEG）
        $testImagePath = storage_path('app/test_image.jpg');
        
        if (!file_exists($testImagePath)) {
            $this->info("テスト用のJPEGファイルを作成中...");
            
            // GDライブラリが使える場合は実際にJPEGを作成
            if (function_exists('imagecreatetruecolor') && function_exists('imagejpeg')) {
                $img = imagecreatetruecolor(10, 10);
                $white = imagecolorallocate($img, 255, 255, 255);
                imagefill($img, 0, 0, $white);
                imagejpeg($img, $testImagePath, 90);
                imagedestroy($img);
                $this->info("テスト用JPEGファイルを作成しました: {$testImagePath}");
            } else {
                $this->warn("⚠ GDライブラリが利用できません。テスト用JPEGファイルの作成をスキップします。");
            }
        }

        if (!file_exists($testImagePath)) {
            $this->warn("⚠ テスト用JPEGファイルの作成に失敗しました。実際の写真アップロードはスキップします。");
            $this->info("実際の写真アップロードをテストするには、Web UIから写真をアップロードしてください。");
        } else {
            $this->info("テスト用JPEGファイルを使用してアップロードをテストします...");
            $this->info("");

            try {
                // location_idを取得
                $locationId = null;
                if ($shop->gbp_location_id) {
                    // "locations/14533069664155190447" の形式から "14533069664155190447" を抽出
                    $locationId = str_replace('locations/', '', $shop->gbp_location_id);
                }

                if (!$locationId) {
                    $this->warn("⚠ Shopにgbp_location_idが設定されていません。アップロードテストをスキップします。");
                } else {
                    $fullLocationName = "accounts/{$accountId}/locations/{$locationId}";
                    $this->info("Location: {$fullLocationName}");
                    $this->info("");

                    // 写真アップロードを実行
                    $result = $googleService->uploadPhoto(
                        $accessToken,
                        $fullLocationName,
                        $testImagePath
                    );

                    if (isset($result['status']) && $result['status'] === 'API_UPLOAD_UNSUPPORTED') {
                        $this->error("❌ 成功条件3: POST /media が失敗しました（API_UPLOAD_UNSUPPORTED）");
                        $this->error("理由: " . ($result['reason'] ?? 'Unknown'));
                    } elseif (isset($result['name']) || isset($result['googleUrl'])) {
                        $this->info("✅ 成功条件3: POST /media が成功しました");
                        $this->info("");
                        $this->info("アップロード結果:");
                        $prettyJson = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                        $this->line($prettyJson);
                    } else {
                        $this->warn("⚠ 成功条件3: POST /media の結果が不明です");
                        $this->info("結果:");
                        $prettyJson = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                        $this->line($prettyJson);
                    }
                }
            } catch (\Exception $e) {
                $this->error("❌ 成功条件3: POST /media 中にエラーが発生しました");
                $this->error("エラー: " . $e->getMessage());
            }
        }

        $this->info("");
        $this->info("==========================================");
        $this->info("テスト完了");
        $this->info("==========================================");

        return 0;
    }
}

