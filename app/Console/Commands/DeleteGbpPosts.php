<?php

namespace App\Console\Commands;

use App\Models\GbpPost;
use Illuminate\Console\Command;

class DeleteGbpPosts extends Command
{
    protected $signature = 'gbp:delete-posts {shop_id} {--date= : 指定日付の投稿のみ削除（例: 2026-02-13）} {--confirm : 確認なしで実行}';
    protected $description = '指定したショップのGBP投稿（Instagram投稿）を削除する（例: php artisan gbp:delete-posts 8）';

    public function handle()
    {
        $shopId = $this->argument('shop_id');
        $date = $this->option('date');
        $noConfirm = $this->option('confirm');

        // 該当する投稿を検索
        $query = GbpPost::where('shop_id', $shopId);
        
        if ($date) {
            try {
                $dateStart = \Carbon\Carbon::parse($date)->startOfDay();
                $dateEnd = \Carbon\Carbon::parse($date)->endOfDay();
                $query->whereBetween('posted_at', [$dateStart, $dateEnd]);
                $this->info("日付フィルタ: {$date}");
            } catch (\Exception $e) {
                $this->error("日付の形式が正しくありません: {$date}");
                return 1;
            }
        }

        $posts = $query->get();

        if ($posts->isEmpty()) {
            $this->info("該当するGBP投稿が見つかりませんでした。");
            return 0;
        }

        $this->info("該当するGBP投稿が見つかりました: {$posts->count()}件");
        
        // 投稿情報を表示
        foreach ($posts as $post) {
            $summary = mb_substr($post->summary ?? '', 0, 50);
            $this->line("  - ID: {$post->id} | Posted: {$post->posted_at} | Summary: {$summary}...");
            if ($post->wp_post_id) {
                $this->line("    → WordPress投稿済み (WP Post ID: {$post->wp_post_id})");
            }
        }

        // 確認
        if (!$noConfirm && !$this->confirm("ショップID {$shopId} のGBP投稿を削除しますか？", false)) {
            $this->info("キャンセルしました。");
            return 0;
        }

        // GBP投稿を削除
        $deleted = 0;
        foreach ($posts as $post) {
            $post->delete();
            $deleted++;
        }

        $this->info("GBP投稿を削除しました: {$deleted}件");
        $this->info("これで再度Instagram投稿テストが可能です。");

        return 0;
    }
}
