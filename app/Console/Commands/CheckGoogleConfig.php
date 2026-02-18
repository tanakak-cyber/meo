<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckGoogleConfig extends Command
{
    protected $signature = 'google:check-config';
    protected $description = 'Google OAuth設定を確認します';

    public function handle()
    {
        $this->info('=== Google OAuth設定確認 ===');
        $this->newLine();

        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = config('services.google.redirect_uri');

        $this->line('Client ID: ' . ($clientId ?: '❌ 未設定'));
        $this->line('Client Secret: ' . ($clientSecret ? '✅ 設定済み (' . strlen($clientSecret) . '文字)' : '❌ 未設定'));
        $this->line('Redirect URI: ' . ($redirectUri ?: '❌ 未設定'));
        $this->newLine();

        if ($clientId && $clientSecret && $redirectUri) {
            $this->info('✅ すべての設定が完了しています！');
            $this->newLine();
            $this->warn('【重要】API allowlist確認が必要です:');
            $this->line('');
            $this->line('現在、Google Business Profile Reviews APIが404になる原因は、');
            $this->line('API allowlistが付与されたGoogle Cloud Projectと、');
            $this->line('OAuthトークンを発行しているGoogle Cloud Projectが異なるためです。');
            $this->newLine();
            $this->line('確認手順:');
            $this->line('1. Google Cloud Consoleで、My Business APIのallowlist申請を出したプロジェクトIDを確認');
            $this->line('2. 上記のClient IDが、そのallowlist済みプロジェクトに属しているか確認');
            $this->line('   → 「APIとサービス」→「認証情報」から該当のOAuth 2.0クライアントIDを探す');
            $this->line('3. 異なる場合は、allowlist済みプロジェクトでOAuthクライアントを新規作成');
            $this->line('4. .envファイルのGOOGLE_CLIENT_IDとGOOGLE_CLIENT_SECRETを更新');
            $this->line('5. Google連携をやり直して新しいrefresh_tokenを取得');
            $this->newLine();
            $this->line('詳細は GOOGLE_API_SETUP.md を参照してください。');
        } else {
            $this->error('❌ 設定が不完全です。.envファイルを確認してください。');
        }

        return Command::SUCCESS;
    }
}

