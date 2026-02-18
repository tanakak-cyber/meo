<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Models\GbpPost;
use App\Services\WordPressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PostToWordPressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $shopId;
    protected int $gbpPostId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $shopId, int $gbpPostId)
    {
        $this->shopId = $shopId;
        $this->gbpPostId = $gbpPostId;
    }

    /**
     * Execute the job.
     */
    public function handle(WordPressService $wordPressService): void
    {
        try {
            $shop = Shop::find($this->shopId);
            if (!$shop) {
                Log::error('WP_POST_JOB_SHOP_NOT_FOUND', [
                    'shop_id' => $this->shopId,
                    'gbp_post_id' => $this->gbpPostId,
                ]);
                return;
            }

            $gbpPost = GbpPost::find($this->gbpPostId);
            if (!$gbpPost) {
                Log::error('WP_POST_JOB_GBP_POST_NOT_FOUND', [
                    'shop_id' => $this->shopId,
                    'gbp_post_id' => $this->gbpPostId,
                ]);
                return;
            }

            // 既にWordPress投稿済み（成功）の場合はスキップ
            if ($gbpPost->wp_post_id && $gbpPost->wp_post_status === 'success') {
                Log::info('WP_POST_JOB_ALREADY_POSTED', [
                    'shop_id' => $this->shopId,
                    'gbp_post_id' => $this->gbpPostId,
                    'wp_post_id' => $gbpPost->wp_post_id,
                ]);
                return;
            }

            // テキストを整形
            $text = $gbpPost->summary ?? '';
            
            // [カテゴリ] 形式を抽出（本文全体から）
            $categories = [];
            $categoryPattern = '/\[(.*?)\]/';
            
            // 本文全体からカテゴリを抽出
            if (preg_match_all($categoryPattern, $text, $matches)) {
                foreach ($matches[1] as $category) {
                    $categoryName = trim($category);
                    if (!empty($categoryName)) {
                        $categories[] = $categoryName;
                    }
                }
            }
            
            // カテゴリ部分を削除
            $text = preg_replace($categoryPattern, '', $text);
            $text = trim($text);
            
            // 1行目をタイトル、2行目以降を本文に分割
            $lines = explode("\n", trim($text));
            $title = !empty($lines) ? trim($lines[0]) : '最新の更新情報';
            $content = count($lines) > 1 ? implode("\n", array_slice($lines, 1)) : '';

            // カテゴリIDを取得または作成
            $categoryIds = [];
            foreach ($categories as $categoryName) {
                $categoryId = $wordPressService->getOrCreateCategory($shop, $categoryName);
                if ($categoryId) {
                    $categoryIds[] = $categoryId;
                }
            }

            // 記事末尾に「詳細はInstagramにて」リンクを追加
            $instagramLink = $gbpPost->source_url ?? '';
            if ($instagramLink) {
                $content .= "\n\n<p><a href=\"{$instagramLink}\" target=\"_blank\" rel=\"noopener noreferrer\">詳細はInstagramにて</a></p>";
            }

            Log::info('WP_POST_JOB_PROCESSING', [
                'shop_id' => $this->shopId,
                'gbp_post_id' => $this->gbpPostId,
                'title' => $title,
                'content_length' => mb_strlen($content),
                'categories' => $categories,
                'category_ids' => $categoryIds,
            ]);

            // WordPress REST APIで投稿（image_url を渡す）
            $result = $wordPressService->createPost($shop, [
                'title' => $title,
                'content' => $content,
                'categories' => $categoryIds,
                'image_url' => $gbpPost->media_url, // 画像URLを渡す
            ]);

            // 投稿成功後、本文内の画像URLをWordPressのメディアURLに置き換え
            // ただし、createPost内で既に処理されているため、ここでは不要
            // もし必要であれば、createPostの戻り値にmedia_source_urlを含める

            if ($result && isset($result['id'])) {
                $wpPostId = $result['id'];

                // gbp_postsにwp_post_id、wp_posted_at、wp_post_statusを保存
                $gbpPost->update([
                    'wp_post_id' => $wpPostId,
                    'wp_posted_at' => now(),
                    'wp_post_status' => 'success',
                ]);

                Log::info('WP_POST_JOB_SUCCESS', [
                    'shop_id' => $this->shopId,
                    'gbp_post_id' => $this->gbpPostId,
                    'wp_post_id' => $wpPostId,
                ]);
            } else {
                // 投稿失敗時
                $gbpPost->update([
                    'wp_post_status' => 'failed',
                ]);

                Log::error('WP_POST_JOB_FAILED', [
                    'shop_id' => $this->shopId,
                    'gbp_post_id' => $this->gbpPostId,
                    'result' => $result,
                ]);
            }
        } catch (\Exception $e) {
            // 例外発生時もwp_post_statusを'failed'に更新
            if (isset($gbpPost)) {
                $gbpPost->update([
                    'wp_post_status' => 'failed',
                ]);
            }

            Log::error('WP_POST_JOB_EXCEPTION', [
                'shop_id' => $this->shopId,
                'gbp_post_id' => $this->gbpPostId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // エラーが発生しても再実行可能にするため、例外を再スローしない
            // 必要に応じて、failed_jobsテーブルに記録される
        }
    }

}

