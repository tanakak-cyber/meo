<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\GoogleBusinessProfileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchGbpAccountIdV4 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gbp:fetch-account-id-v4 
                            {--shop-id= : 特定の店舗IDを指定}
                            {--force : 既存のgbp_account_id_v4を強制的に上書き}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '【廃止】v4のaccountIdという概念は不要です。このコマンドは使用しないでください。';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->error('このコマンドは廃止されました。');
        $this->error('v4のaccountIdという概念は不要です。');
        $this->error('reviews.list のレスポンスに含まれる review.name が唯一の正しい識別子です。');
        return 1;
        
        // 以下は実行されません（後方互換性のため残しています）
        $googleService = new GoogleBusinessProfileService();
        $force = $this->option('force');
        
        // 店舗を取得
        $shopId = $this->option('shop-id');
        if ($shopId) {
            $shops = Shop::where('id', $shopId)->get();
        } else {
            // リフレッシュトークンが設定されている店舗のみ
            $query = Shop::whereNotNull('gbp_refresh_token');
            
            // --forceオプションがない場合は、gbp_account_id_v4が未設定の店舗のみ
            if (!$force) {
                $query->where(function ($q) {
                    $q->whereNull('gbp_account_id_v4')
                      ->orWhere('gbp_account_id_v4', '');
                });
            }
            
            $shops = $query->get();
        }

        if ($shops->isEmpty()) {
            $this->info('処理対象の店舗がありません。');
            return 0;
        }

        $this->info("処理対象店舗数: {$shops->count()}件");

        $successCount = 0;
        $failCount = 0;

        foreach ($shops as $shop) {
            $this->line("店舗ID: {$shop->id}, 店舗名: {$shop->name}");

            if (!$shop->gbp_refresh_token) {
                $this->warn("  → リフレッシュトークンが設定されていません。スキップします。");
                $failCount++;
                continue;
            }

            try {
                // アクセストークンを取得
                $refreshResult = $googleService->refreshAccessToken($shop->gbp_refresh_token);
                if (!$refreshResult || !$refreshResult['access_token']) {
                    $errorMsg = $refreshResult['error_message'] ?? 'リフレッシュトークンが無効です。';
                    $this->error("ショップ {$shop->name} (ID: {$shop->id}): アクセストークンの取得に失敗しました。 ({$errorMsg})");
                    continue;
                }
                $accessToken = $refreshResult['access_token'];
                    $this->error("  → アクセストークンの取得に失敗しました。");
                    $failCount++;
                    continue;
                }

                // 重要: My Business Account Management APIからaccountsを取得
                // GET https://mybusinessaccountmanagement.googleapis.com/v1/accounts を実際に呼び出す
                // v1のaccountIdから文字列連結して生成してはいけない
                $accountsResponse = $googleService->listAccountsV4($accessToken);

                if (empty($accountsResponse) || !isset($accountsResponse['accounts'])) {
                    $this->error("  → アカウントの取得に失敗しました。");
                    $this->line("  → レスポンス: " . json_encode($accountsResponse, JSON_UNESCAPED_UNICODE));
                    $failCount++;
                    continue;
                }

                $accounts = $accountsResponse['accounts'] ?? [];
                
                if (empty($accounts)) {
                    $this->error("  → アカウントが見つかりませんでした。");
                    $failCount++;
                    continue;
                }

                // 最初のアカウントを使用
                $account = $accounts[0];
                $accountIdV4 = $account['name'] ?? null; // "accounts/123456789012345678" の形式

                if (!$accountIdV4) {
                    $this->error("  → アカウントIDが取得できませんでした。");
                    $this->line("  → アカウントデータ: " . json_encode($account, JSON_UNESCAPED_UNICODE));
                    $failCount++;
                    continue;
                }

                // 重要: v1のaccountIdと比較して、異なることを確認
                $v1AccountId = $shop->gbp_account_id;
                if ($v1AccountId && str_replace('accounts/', '', $accountIdV4) === $v1AccountId) {
                    $this->warn("  → 警告: v4のaccountIdがv1と同じです。正しい値が取得できていない可能性があります。");
                    $this->line("  → v1: {$v1AccountId}, v4: {$accountIdV4}");
                }

                // v4用のアカウントIDを保存（APIから取得した値をそのまま保存）
                $shop->update([
                    'gbp_account_id_v4' => $accountIdV4,
                ]);

                $this->info("  → 成功: gbp_account_id_v4 = {$accountIdV4}");
                if ($v1AccountId) {
                    $this->line("  → v1 accountId: {$v1AccountId} (比較用)");
                }
                $successCount++;

                Log::info('GBPアカウントID(v4)を取得して保存', [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'gbp_account_id_v4' => $accountIdV4,
                ]);

            } catch (\Exception $e) {
                $this->error("  → エラー: {$e->getMessage()}");
                $failCount++;
                
                Log::error('GBPアカウントID(v4)取得エラー', [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->newLine();
        $this->info("処理完了: 成功 {$successCount}件, 失敗 {$failCount}件");

        return 0;
    }
}

