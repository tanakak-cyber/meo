<?php

namespace App\Console\Commands;

use App\Models\GbpPost;
use Illuminate\Console\Command;

class ResetWordPressPosts extends Command
{
    protected $signature = 'wp:reset {shop_id} {--all : すべてのWordPress投稿データをリセット}';
    protected $description = '指定したショップのWordPress投稿データをリセットする（例: php artisan wp:reset 8）';

    public function handle()
    {
        $shopId = $this->argument('shop_id');
        $resetAll = $this->option('all');

        // 該当する投稿を検索
        $query = GbpPost::where('shop_id', $shopId);
        
        if (!$resetAll) {
            // wp_post_idが存在するもののみ
            $query->whereNotNull('wp_post_id');
        }

        $posts = $query->get();

        if ($posts->isEmpty()) {
            $this->info("該当する投稿が見つかりませんでした。");
            return 0;
        }

        $this->info("該当する投稿が見つかりました: {$posts->count()}件");
        
        // 確認
        if (!$this->confirm("ショップID {$shopId} のWordPress投稿データをリセットしますか？", true)) {
            $this->info("キャンセルしました。");
            return 0;
        }

        // WordPress投稿データをリセット
        $updated = 0;
        foreach ($posts as $post) {
            $post->update([
                'wp_post_id' => null,
                'wp_post_status' => null,
                'wp_posted_at' => null,
            ]);
            $updated++;
        }

        $this->info("WordPress投稿データをリセットしました: {$updated}件");
        $this->info("これで再度投稿テストが可能です。");

        return 0;
    }
}


