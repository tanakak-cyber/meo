<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shop;
use App\Services\GoogleBusinessProfileService;

class ReconnectGbpOAuth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gbp:reconnect-oauth {--shop-id= : The ID of the shop to reconnect OAuth for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate OAuth authorization URL for a shop to reconnect Google Business Profile with User OAuth (kaunja0501@gmail.com).';

    protected $googleService;

    public function __construct(GoogleBusinessProfileService $googleService)
    {
        parent::__construct();
        $this->googleService = $googleService;
    }

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

        // 既存のトークン情報を表示
        $hasRefreshToken = !empty($shop->gbp_refresh_token);
        $hasAccessToken = !empty($shop->gbp_access_token);
        
        $this->info("現在のトークン状態:");
        $this->line("  - リフレッシュトークン: " . ($hasRefreshToken ? "あり" : "なし"));
        $this->line("  - アクセストークン: " . ($hasAccessToken ? "あり" : "なし"));
        $this->info("");

        // 既存のApp-onlyリフレッシュトークンを削除するオプション
        if ($hasRefreshToken) {
            $this->warn("⚠ 既存のリフレッシュトークンが保存されています。");
            $this->warn("   App-only OAuthの場合は、OAuth認証前に削除する必要があります。");
            $this->info("");
            
            if ($this->confirm('既存のリフレッシュトークンとアクセストークンを削除しますか？', false)) {
                $shop->update([
                    'gbp_refresh_token' => null,
                    'gbp_access_token' => null,
                ]);
                $this->info("✓ 既存のトークンを削除しました。");
                $this->info("");
            }
        }

        // 既存のトークンがApp-onlyかどうかを確認
        if ($hasAccessToken) {
            $this->info("既存のアクセストークンを検証中...");
            try {
                $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($shop->gbp_access_token);
                $tokenInfoResponse = \Illuminate\Support\Facades\Http::get($tokenInfoUrl);
                
                if ($tokenInfoResponse->successful()) {
                    $tokenInfoData = $tokenInfoResponse->json();
                    $email = $tokenInfoData['email'] ?? null;
                    
                    if ($email === 'kaunja0501@gmail.com') {
                        $this->info("✓ 既存のトークンは有効なUser OAuthです (email: {$email})");
                    } elseif ($email) {
                        $this->warn("⚠ 既存のトークンは別のアカウントです (email: {$email})");
                        $this->warn("  kaunja0501@gmail.com で再認証が必要です。");
                    } else {
                        $this->error("✗ 既存のトークンはApp-only OAuthです（emailなし）");
                        $this->error("  User OAuthで再認証が必要です。");
                    }
                } else {
                    $this->warn("⚠ 既存のアクセストークンの検証に失敗しました。");
                }
            } catch (\Exception $e) {
                $this->warn("⚠ 既存のアクセストークンの検証中にエラー: " . $e->getMessage());
            }
            $this->info("");
        }

        // OAuth認証URLを生成
        $authUrl = $this->googleService->getAuthUrl($shop->id);
        
        $this->info("==========================================");
        $this->info("OAuth認証URL");
        $this->info("==========================================");
        $this->line($authUrl);
        $this->info("");
        $this->info("上記のURLをブラウザで開いて、kaunja0501@gmail.com で認証してください。");
        $this->info("認証後、自動的にコールバックされ、新しいUser OAuthトークンが保存されます。");
        $this->info("");

        return 0;
    }
}

