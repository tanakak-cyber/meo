<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClearAppOnlyToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gbp:clear-app-only-token {--shop-id= : The ID of the shop to clear App-only token for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear App-only OAuth tokens (refresh_token and access_token) for a shop.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shopId = $this->option('shop-id');

        if (!$shopId) {
            $this->error('ショップIDを指定してください (--shop-id=)。');
            return 1;
        }

        $shop = Shop::find($shopId);

        if (!$shop) {
            $this->error("ショップID {$shopId} が見つかりません。");
            return 1;
        }

        $this->info("==========================================");
        $this->info("ショップ: {$shop->name} (ID: {$shop->id})");
        $this->info("==========================================");
        $this->info("");

        $hasRefreshToken = !empty($shop->gbp_refresh_token);
        $hasAccessToken = !empty($shop->gbp_access_token);

        if (!$hasRefreshToken && !$hasAccessToken) {
            $this->info("既存のトークンはありません。");
            return 0;
        }

        // 既存のアクセストークンがApp-onlyかどうかを確認
        $isAppOnly = false;
        if ($hasAccessToken) {
            $this->info("既存のアクセストークンを検証中...");
            try {
                $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($shop->gbp_access_token);
                $tokenInfoResponse = Http::get($tokenInfoUrl);
                
                if ($tokenInfoResponse->successful()) {
                    $tokenInfoData = $tokenInfoResponse->json();
                    $email = $tokenInfoData['email'] ?? null;
                    
                    if (!$email) {
                        $isAppOnly = true;
                        $this->error("✗ 既存のアクセストークンはApp-only OAuthです（emailなし）");
                    } else {
                        $this->info("✓ 既存のアクセストークンはUser OAuthです (email: {$email})");
                        if ($email !== 'kaunja0501@gmail.com') {
                            $this->warn("⚠ ただし、emailがkaunja0501@gmail.comではありません。");
                        }
                    }
                } else {
                    $this->warn("⚠ 既存のアクセストークンの検証に失敗しました。");
                }
            } catch (\Exception $e) {
                $this->warn("⚠ 既存のアクセストークンの検証中にエラー: " . $e->getMessage());
            }
            $this->info("");
        }

        // リフレッシュトークンからアクセストークンを取得して検証
        if ($hasRefreshToken && !$isAppOnly) {
            $this->info("リフレッシュトークンからアクセストークンを取得して検証中...");
            try {
                $googleService = new \App\Services\GoogleBusinessProfileService();
                $refreshResult = $googleService->refreshAccessToken($shop->gbp_refresh_token);
                
                if ($refreshResult && $refreshResult['access_token']) {
                    $newAccessToken = $refreshResult['access_token'];
                    $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($newAccessToken);
                    $tokenInfoResponse = Http::get($tokenInfoUrl);
                    
                    if ($tokenInfoResponse->successful()) {
                        $tokenInfoData = $tokenInfoResponse->json();
                        $email = $tokenInfoData['email'] ?? null;
                        
                        if (!$email) {
                            $isAppOnly = true;
                            $this->error("✗ リフレッシュトークンから取得したアクセストークンはApp-only OAuthです（emailなし）");
                        } else {
                            $this->info("✓ リフレッシュトークンから取得したアクセストークンはUser OAuthです (email: {$email})");
                            if ($email !== 'kaunja0501@gmail.com') {
                                $this->warn("⚠ ただし、emailがkaunja0501@gmail.comではありません。");
                            }
                        }
                    }
                } else {
                    $errorCode = $refreshResult['error'] ?? 'unknown';
                    if ($errorCode === 'invalid_grant') {
                        $this->warn("⚠ リフレッシュトークンが無効または期限切れです。");
                    } else {
                        $this->warn("⚠ リフレッシュトークンからのアクセストークン取得に失敗しました。");
                    }
                }
            } catch (\Exception $e) {
                $this->warn("⚠ リフレッシュトークンの検証中にエラー: " . $e->getMessage());
            }
            $this->info("");
        }

        if ($isAppOnly || $this->confirm('既存のトークンを削除しますか？', false)) {
            $shop->update([
                'gbp_refresh_token' => null,
                'gbp_access_token' => null,
            ]);
            
            Log::info('GBP_APP_ONLY_TOKEN_CLEARED', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'reason' => $isAppOnly ? 'App-only OAuth detected' : 'Manual clear',
            ]);
            
            $this->info("✓ 既存のトークンを削除しました。");
            $this->info("");
            $this->info("次に、以下のコマンドでOAuth認証URLを生成してください：");
            $this->info("  php artisan gbp:reconnect-oauth --shop-id={$shopId}");
            return 0;
        }

        $this->info("トークンの削除をキャンセルしました。");
        return 0;
    }
}



















