<?php

namespace App\Console\Commands;

use App\Models\GbpPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteWordPressPosts extends Command
{
    protected $signature = 'wp:delete-posts {shop_id} {--confirm : 確認なしで実行}';
    protected $description = '指定したショップのWordPress投稿を削除する（例: php artisan wp:delete-posts 8）';

    public function handle()
    {
        $shopId = $this->argument('shop_id');
        $noConfirm = $this->option('confirm');

        // 該当する投稿を検索
        $posts = GbpPost::where('shop_id', $shopId)
            ->whereNotNull('wp_post_id')
            ->get();

        if ($posts->isEmpty()) {
            $this->info("該当するWordPress投稿が見つかりませんでした。");
            return 0;
        }

        $this->info("該当するWordPress投稿が見つかりました: {$posts->count()}件");
        
        // 投稿情報を表示
        foreach ($posts as $post) {
            $this->line("  - ID: {$post->id} | WP Post ID: {$post->wp_post_id} | Posted: {$post->posted_at}");
        }

        // 確認
        if (!$noConfirm && !$this->confirm("ショップID {$shopId} のWordPress投稿データを削除しますか？", false)) {
            $this->info("キャンセルしました。");
            return 0;
        }

        // WordPress投稿データを削除（リセット）
        $updated = 0;
        foreach ($posts as $post) {
            $post->update([
                'wp_post_id' => null,
                'wp_post_status' => null,
                'wp_posted_at' => null,
            ]);
            $updated++;
        }

        $this->info("WordPress投稿データを削除しました: {$updated}件");
        $this->info("これで再度投稿テストが可能です。");

        return 0;
    }
}


