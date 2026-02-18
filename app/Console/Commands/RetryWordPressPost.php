<?php

namespace App\Console\Commands;

use App\Models\GbpPost;
use App\Jobs\PostToWordPressJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RetryWordPressPost extends Command
{
    protected $signature = 'wp:retry {shop_id} {date} {time?}';
    protected $description = 'WordPress投稿を再実行する（例: php artisan wp:retry 8 "2026-02-13" "19:41"）';

    public function handle()
    {
        $shopId = $this->argument('shop_id');
        $date = $this->argument('date');
        $time = $this->argument('time') ?? '00:00';

        // 日時をパース
        try {
            $targetDate = Carbon::parse("{$date} {$time}", 'Asia/Tokyo');
        } catch (\Exception $e) {
            $this->error("日時の形式が正しくありません: {$date} {$time}");
            return 1;
        }

        // 該当する投稿を検索（±5分の範囲）
        $startDate = $targetDate->copy()->subMinutes(5);
        $endDate = $targetDate->copy()->addMinutes(5);

        $posts = GbpPost::where('shop_id', $shopId)
            ->whereBetween('posted_at', [$startDate, $endDate])
            ->orderBy('posted_at', 'desc')
            ->get();

        if ($posts->isEmpty()) {
            $this->error("該当する投稿が見つかりませんでした。");
            $this->info("検索範囲: {$startDate->format('Y-m-d H:i:s')} ～ {$endDate->format('Y-m-d H:i:s')}");
            return 1;
        }

        $this->info("該当する投稿が見つかりました:");
        foreach ($posts as $post) {
            $this->line("ID: {$post->id} | Posted: {$post->posted_at} | WP Status: " . ($post->wp_post_status ?? 'null') . " | WP Post ID: " . ($post->wp_post_id ?? 'null'));
            $this->line("Summary: " . mb_substr($post->summary ?? '', 0, 50) . "...");
            $this->line("---");
        }

        // 最初の投稿を再実行
        $post = $posts->first();
        
        if (!$this->confirm("投稿ID {$post->id} を再実行しますか？", true)) {
            $this->info("キャンセルしました。");
            return 0;
        }

        // wp_post_id と wp_post_status をリセット
        $post->update([
            'wp_post_id' => null,
            'wp_post_status' => null,
            'wp_posted_at' => null,
        ]);

        $this->info("ステータスをリセットしました。");

        // PostToWordPressJob をdispatch
        PostToWordPressJob::dispatch($shopId, $post->id);

        $this->info("WordPress投稿Jobをキューに追加しました。");
        $this->info("投稿ID: {$post->id}");
        $this->info("ショップID: {$shopId}");

        return 0;
    }
}


