<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\GbpPost;
use App\Services\GoogleBusinessProfileService;
use App\Jobs\PostToWordPressJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class InstagramPostService
{
    private GoogleBusinessProfileService $gbpService;

    public function __construct(GoogleBusinessProfileService $gbpService)
    {
        $this->gbpService = $gbpService;
    }

    /**
     * 重複チェック（source_type='instagram' + source_external_id=permalink）
     * 
     * @param Shop $shop
     * @param string $permalink Instagram permalink
     * @return bool 重複している場合true
     */
    public function checkDuplicate(Shop $shop, string $permalink): bool
    {
        try {
            return GbpPost::where('shop_id', $shop->id)
                ->where('source_type', 'instagram')
                ->where('source_external_id', $permalink)
                ->exists();
        } catch (\Exception $e) {
            Log::error('IG_DUPLICATE_CHECK_EXCEPTION', [
                'shop_id' => $shop->id,
                'permalink' => $permalink,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 記事ごとの処理
     * 
     * @param Shop $shop
     * @param string $permalink Instagram permalink
     * @param string|null $articleImageFromList 一覧から取得した画像URL
     * @param string|null $articleTitleFromList 一覧から取得したタイトル
     * @param string|null $articleTextFromList 一覧から取得したテキスト
     * @return GbpPost|null 投稿成功時はGbpPostインスタンス、失敗時はnull
     */
    public function processArticle(Shop $shop, string $permalink, ?string $articleImageFromList = null, ?string $articleTitleFromList = null, ?string $articleTextFromList = null): ?GbpPost
    {
        try {
            // ① permalink が無い → SKIP
            if (empty($permalink)) {
                Log::info('IG_CRAWL_DECISION', [
                    'permalink' => null,
                    'is_duplicate' => false,
                    'action' => 'SKIP',
                    'skip_reason' => 'NO_PERMALINK',
                ]);
                return null;
            }

            // 念のため重複チェック（二重投稿を防ぐため）
            $isDuplicate = $this->checkDuplicate($shop, $permalink);
            
            if ($isDuplicate) {
                Log::warning('IG_CRAWL_DUPLICATE_DETECTED_IN_PROCESS', [
                    'shop_id' => $shop->id,
                    'permalink' => $permalink,
                    'message' => '記事が重複していたためスキップ',
                ]);
                return null;
            }

            // Instagram permalink ページを取得
            $html = $this->fetchArticleWithGuzzle($permalink, $shop->id);
            
            // 画像URL取得（一覧から取得した値を優先、なければInstagramページから取得）
            $imageUrl = $articleImageFromList;
            
            if (!$imageUrl && $html) {
                $crawler = new Crawler($html);
                // <img src> を取得
                $imageNode = $crawler->filter('img')->first();
                if ($imageNode->count() > 0) {
                    $imageUrl = $imageNode->attr('src') ?? $imageNode->attr('data-src');
                }
            }

            // フォールバック: 画像が無い場合は店舗別ダミー画像URLを使用
            if (!$imageUrl) {
                $imageUrl = $shop->blog_fallback_image_url;
            }

            // 画像URLが無効な場合は投稿をスキップ
            $skipThisArticle = false;
            if (!$imageUrl || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                Log::warning('IG_SKIP_NO_VALID_IMAGE', [
                    'shop_id' => $shop->id,
                    'permalink' => $permalink,
                ]);
                $skipThisArticle = true;
            }

            // 画像が有効な場合のみGBP投稿処理を実行
            if ($skipThisArticle) {
                return null;
            }

            // タイトル取得（一覧から取得した値を優先、なければInstagramページから取得）
            $title = $articleTitleFromList;
            if (!$title && $html) {
                $crawler = new Crawler($html);
                // og:title を取得
                $ogTitleNode = $crawler->filter('meta[property="og:title"]')->first();
                if ($ogTitleNode->count() > 0) {
                    $title = $ogTitleNode->attr('content');
                }
            }

            // フォールバック: タイトルが無い場合
            if (empty($title)) {
                $title = '最新の更新情報です。';
            }

            // テキスト取得（一覧から取得した値を優先、なければInstagramページから取得）
            $text = $articleTextFromList;
            if (!$text && $html) {
                $crawler = new Crawler($html);
                // og:description を取得
                $ogDescNode = $crawler->filter('meta[property="og:description"]')->first();
                if ($ogDescNode->count() > 0) {
                    $text = $ogDescNode->attr('content');
                }
            }

            // GBP投稿用summaryを生成（テキスト優先、なければタイトル）
            $summary = $this->buildGbpSummary($text ?? $title);
            
            // 最終的なテキスト保証：summaryが空の場合は店舗名を使った定型文をセット
            if (empty($summary)) {
                $shopName = $shop->gbp_name ?? $shop->name ?? '当店';
                $summary = "{$shopName} Instagramを更新しました";
                Log::warning('IG_SUMMARY_FALLBACK_TO_SHOP_NAME', [
                    'shop_id' => $shop->id,
                    'permalink' => $permalink,
                    'shop_name' => $shopName,
                    'summary' => $summary,
                ]);
            }

            Log::info('IG_SUMMARY_FINAL', [
                'shop_id' => $shop->id,
                'length' => mb_strlen($summary),
                'summary' => $summary,
            ]);

            Log::info('IG_CRAWL_POSTING', [
                'shop_id' => $shop->id,
                'permalink' => $permalink,
                'action' => 'POST',
            ]);

            // Google Business Profile API に投稿
            return $this->postToGbp($shop, $permalink, $summary, $imageUrl);
        } catch (\Exception $e) {
            Log::error('IG_ARTICLE_PROCESS_EXCEPTION', [
                'shop_id' => $shop->id,
                'permalink' => $permalink,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Google Business Profile API に投稿
     * 
     * @param Shop $shop
     * @param string $permalink Instagram permalink
     * @param string|null $summary GBP投稿用summary
     * @param string|null $imageUrl 画像URL
     * @return GbpPost|null 投稿成功時はGbpPostインスタンス、失敗時はnull
     */
    public function postToGbp(Shop $shop, string $permalink, ?string $summary, ?string $imageUrl): ?GbpPost
    {
        try {
            // アクセストークンを取得
            $accessToken = $this->gbpService->getAccessToken($shop);

            if (!$accessToken) {
                Log::error('IG_GBP_POST_ACCESS_TOKEN_FAILED', [
                    'shop_id' => $shop->id,
                    'permalink' => $permalink,
                ]);
                return null;
            }

            if (!$shop->gbp_account_id || !$shop->gbp_location_id) {
                Log::error('IG_GBP_POST_CREDENTIALS_MISSING', [
                    'shop_id' => $shop->id,
                    'permalink' => $permalink,
                ]);
                return null;
            }

            Log::info('IG_GBP_POST_REQUEST', [
                'shop_id' => $shop->id,
                'permalink' => $permalink,
                'summary_length' => $summary ? strlen($summary) : 0,
                'has_image' => !empty($imageUrl),
            ]);

            // GBP API に投稿
            $result = $this->gbpService->createLocalPost(
                $accessToken,
                $shop->gbp_account_id,
                $shop->gbp_location_id,
                $summary,
                $imageUrl,
                $permalink,
                $shop  // フォールバック画像用にshopを渡す
            );

            if ($result && isset($result['name'])) {
                $gbpPostId = str_replace('localPosts/', '', $result['name']);

                Log::info('IG_GBP_POST_SUCCESS', [
                    'shop_id' => $shop->id,
                    'permalink' => $permalink,
                    'gbp_post_id' => $gbpPostId,
                ]);

                // gbp_posts に保存（DB保存はすべてUTCで統一）
                $gbpPost = GbpPost::create([
                    'shop_id' => $shop->id,
                    'source_url' => $permalink,
                    'source_type' => 'instagram',
                    'source_external_id' => $permalink, // Instagram permalink を保存
                    'gbp_post_id' => $gbpPostId,
                    'gbp_post_name' => $result['name'],
                    'summary' => $summary,
                    'media_url' => $imageUrl,
                    'posted_at' => now(), // UTC保存
                    'create_time' => now(), // UTC保存
                ]);

                // WordPress投稿が有効な場合、キューにジョブを追加
                if (
                    $shop->wp_post_enabled
                    && empty($gbpPost->wp_post_id)
                    && $gbpPost->wp_post_status !== 'success'
                ) {
                    Log::info('WP_POST_JOB_DISPATCH', [
                        'shop_id' => $shop->id,
                        'gbp_post_id' => $gbpPost->id,
                    ]);
                    PostToWordPressJob::dispatch($shop->id, $gbpPost->id);
                }

                return $gbpPost;
            } else {
                Log::error('IG_GBP_POST_FAILED', [
                    'shop_id' => $shop->id,
                    'permalink' => $permalink,
                    'result' => $result,
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('IG_GBP_POST_EXCEPTION', [
                'shop_id' => $shop->id,
                'permalink' => $permalink,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Guzzle で記事ページを取得
     * 
     * @param string $articleUrl 記事URL
     * @param int $shopId 店舗ID
     * @return string|null HTML文字列、取得失敗時はnull
     */
    private function fetchArticleWithGuzzle(string $articleUrl, int $shopId): ?string
    {
        try {
            $response = Http::timeout(30)->get($articleUrl);

            if (!$response->successful()) {
                Log::warning('IG_ARTICLE_FETCH_GUZZLE_FAILED', [
                    'shop_id' => $shopId,
                    'article_url' => $articleUrl,
                    'status_code' => $response->status(),
                ]);
                return null;
            }

            return $response->body();
        } catch (\Exception $e) {
            Log::warning('IG_ARTICLE_FETCH_GUZZLE_EXCEPTION', [
                'shop_id' => $shopId,
                'article_url' => $articleUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * GBP投稿用summaryを生成（テキストまたはタイトル）
     * 
     * @param string|null $text 記事テキストまたはタイトル
     * @return string GBP投稿用summary
     */
    private function buildGbpSummary(?string $text): string
    {
        return trim($text ?? '');
    }
}

