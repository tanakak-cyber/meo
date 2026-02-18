<?php

namespace App\Console\Commands;

use App\Models\OperationPerson;
use Illuminate\Console\Command;

class SetOperatorPasswords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'operator:set-passwords';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '既存のオペレーターにパスワードとメールアドレスを設定します';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $operators = OperationPerson::all();

        if ($operators->isEmpty()) {
            $this->error('オペレーターが見つかりませんでした。');
            return 1;
        }

        $this->info("{$operators->count()}名のオペレーターが見つかりました。");

        foreach ($operators as $index => $operator) {
            $number = $index + 1;
            
            // メールアドレスが未設定の場合、デフォルトのメールアドレスを設定
            if (empty($operator->email)) {
                $operator->email = "operator{$number}@example.com";
            }

            // パスワードを強制的に設定（既存のパスワードを上書き）
            $defaultPassword = "operator{$number}123";
            $operator->setPassword($defaultPassword);

            $operator->save();

            $this->info("✓ {$operator->name} (ID: {$operator->id})");
            $this->line("  メールアドレス: {$operator->email}");
            $this->line("  パスワード: {$defaultPassword}");
        }

        $this->newLine();
        $this->info('すべてのオペレーターにパスワードとメールアドレスを設定しました。');
        $this->warn('本番環境では、設定したパスワードを変更することをお勧めします。');

        return 0;
    }
}

