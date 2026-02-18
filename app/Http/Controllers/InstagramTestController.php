<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\InstagramPostService;
use App\Services\ScrapingBeeFetcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class InstagramTestController extends Controller
{
    /**
     * ScrapingBeeFetcher instance
     */
    private ScrapingBeeFetcher $scrapingBeeFetcher;

    /**
     * InstagramPostService instance
     */
    private InstagramPostService $instagramPostService;

    /**
     * Constructor
     */
    public function __construct(ScrapingBeeFetcher $scrapingBeeFetcher, InstagramPostService $instagramPostService)
    {
        $this->scrapingBeeFetcher = $scrapingBeeFetcher;
        $this->instagramPostService = $instagramPostService;
    }

    /**
     * Instagramクロールテストを実行
     */
    public function run(Request $request, Shop $shop)
    {
        Log::info('IG_TEST_START', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
        ]);

        try {
            // 設定チェック
            if (empty($shop->blog_list_url)) {
                Log::error('IG_TEST_ERROR', [
                    'shop_id' => $shop->id,
                    'error' => 'Instagram一覧URLが設定されていません',
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Instagram一覧URLが設定されていません',
                ], 400);
            }

            if (empty($shop->blog_link_selector)) {
                Log::error('IG_TEST_ERROR', [
                    'shop_id' => $shop->id,
                    'error' => '投稿リンクセレクターが設定されていません',
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => '投稿リンクセレクターが設定されていません',
                ], 400);
            }

            // HTMLを直接GETしてDOMパース（1件目のみ取得）
            $article = $this->crawlWithHtml($shop);

            if (empty($article) || empty($article['permalink'])) {
                Log::error('IG_TEST_NO_ARTICLE_FOUND', [
                    'shop_id' => $shop->id,
                    'url' => $shop->blog_list_url,
                    'selector' => $shop->blog_link_selector,
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Instagram投稿が見つかりませんでした',
                ], 404);
            }

            Log::info('IG_TEST_ARTICLE_FOUND', [
                'shop_id' => $shop->id,
                'permalink' => $article['permalink'],
                'has_image' => !empty($article['image']),
                'has_text' => !empty($article['text']),
            ]);

            // 重複チェック（source_type='instagram' + source_external_id=permalink）
            $isDuplicate = $this->instagramPostService->checkDuplicate($shop, $article['permalink']);

            if ($isDuplicate) {
                Log::info('IG_TEST_DUPLICATE', [
                    'shop_id' => $shop->id,
                    'permalink' => $article['permalink'],
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'この投稿は既に投稿済みです',
                ], 409);
            }

            // Instagram投稿処理を実行
            $gbpPost = $this->instagramPostService->processArticle(
                $shop,
                $article['permalink'],
                $article['image'] ?? null,
                $article['title'] ?? null,
                $article['text'] ?? null
            );

            if (!$gbpPost) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Google Business Profile への投稿に失敗しました',
                ], 500);
            }

            $gbpPostId = $gbpPost->gbp_post_id;

            return response()->json([
                'status' => 'success',
                'article_url' => $article['permalink'],
                'gbp_post_id' => $gbpPostId,
            ]);
        } catch (\Exception $e) {
            Log::error('IG_TEST_EXCEPTION', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'エラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * HTMLを直接GETしてDOMパースでInstagram一覧を取得
     * 1件目のみ取得
     * ScrapingBee経由でJSレンダリング済みHTMLを取得
     */
    private function crawlWithHtml(Shop $shop): ?array
    {
        $startTime = microtime(true);
        $blogListUrl = $shop->blog_list_url;
        $linkSelector = $shop->blog_link_selector;

        try {
            Log::info('IG_TEST_FETCH_START', [
                'shop_id' => $shop->id,
                'url' => $blogListUrl,
            ]);

            // 1回目：画像優先（img[src]がセットされた状態を保証）
            $html = $this->scrapingBeeFetcher->fetchHtml($blogListUrl, [
                'render_js' => true,
                'wait_for' => '.instagram-gallery-item__media[src]',
                'wait' => 3000,
                'block_resources' => true,
                'premium_proxy' => false,
            ]);

            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $bytes = strlen($html);

            Log::info('IG_TEST_FETCH_OK', [
                'shop_id' => $shop->id,
                'bytes' => $bytes,
                'ms' => round($elapsedMs, 2),
            ]);

            // HTML内容を確認
            $hasGalleryItemMedia = str_contains($html, 'instagram-gallery-item__media');
            $hasSrcAttr = str_contains($html, 'src="');
            $hasAriaLabel = str_contains($html, 'aria-label');

            Log::debug('IG_WAIT_CHECK', [
                'shop_id' => $shop->id,
                'has_img_src' => $hasGalleryItemMedia,
                'has_src_attr' => $hasSrcAttr,
                'has_aria_label' => $hasAriaLabel,
            ]);

            // フォールバック：instagram-gallery-item__mediaはあるがsrc="がない場合、再リクエスト
            if ($hasGalleryItemMedia && !$hasSrcAttr) {
                Log::info('IG_TEST_FETCH_FALLBACK', [
                    'shop_id' => $shop->id,
                    'reason' => 'instagram-gallery-item__media exists but src=" not found',
                ]);

                // 2回目：aria-label優先
                $html = $this->scrapingBeeFetcher->fetchHtml($blogListUrl, [
                    'render_js' => true,
                    'wait_for' => '.instagram-gallery-item--cols-3[aria-label]',
                    'wait' => 3000,
                    'block_resources' => true,
                    'premium_proxy' => false,
                ]);

                $elapsedMs = (microtime(true) - $startTime) * 1000;
                $bytes = strlen($html);

                Log::info('IG_TEST_FETCH_FALLBACK_OK', [
                    'shop_id' => $shop->id,
                    'bytes' => $bytes,
                    'ms' => round($elapsedMs, 2),
                ]);

                // 再チェック
                $hasGalleryItemMedia = str_contains($html, 'instagram-gallery-item__media');
                $hasSrcAttr = str_contains($html, 'src="');
                $hasAriaLabel = str_contains($html, 'aria-label');

                Log::debug('IG_WAIT_CHECK_AFTER_FALLBACK', [
                    'shop_id' => $shop->id,
                    'has_img_src' => $hasGalleryItemMedia,
                    'has_src_attr' => $hasSrcAttr,
                    'has_aria_label' => $hasAriaLabel,
                ]);
            }

            // HTML内容を確認
            Log::info('IG_HTML_CHECK', [
                'shop_id' => $shop->id,
                'url' => $blogListUrl,
                'html_length' => $bytes,
                'contains_gallery_item' => str_contains($html, 'instagram-gallery-item'),
                'contains_instagram_link' => str_contains($html, 'instagram.com/p/'),
            ]);

            // DOM解析を実行（1件目のみ）
            $article = $this->parseHtmlArticles(
                $html,
                $linkSelector,
                $blogListUrl,
                $shop
            );

            return $article;
        } catch (\Throwable $e) {
            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $errorClass = get_class($e);
            $errorMessage = $e->getMessage();

            Log::error('IG_TEST_FETCH_FAIL', [
                'shop_id' => $shop->id,
                'url' => $blogListUrl,
                'error_class' => $errorClass,
                'message' => $errorMessage,
                'ms' => round($elapsedMs, 2),
            ]);

            Log::error('IG_TEST_HTML_PARSE_FAILED', [
                'shop_id' => $shop->id,
                'message' => $errorMessage,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * HTMLをパースして記事情報を抽出
     * Instagram permalink を抽出する（1件目のみ）
     * .instagram-gallery-item を起点に処理
     * 
     * @param string $html HTML文字列
     * @param string $linkSelector リンクセレクタ（未使用、互換性のため保持）
     * @param string $baseUrl ベースURL
     * @param Shop $shop 店舗オブジェクト（セレクタ取得用）
     * @return array|null 記事情報、取得失敗時はnull
     */
    private function parseHtmlArticles(string $html, string $linkSelector, string $baseUrl, Shop $shop): ?array
    {
        try {
            $crawler = new Crawler($html);

            // ベースURLを取得
            $baseUrlParsed = parse_url($baseUrl, PHP_URL_SCHEME) . '://' . parse_url($baseUrl, PHP_URL_HOST);
            
            // 1投稿単位の親要素セレクターを取得（デフォルト: .instagram-gallery-item）
            $itemSelector = $shop->instagram_item_selector ?: '.instagram-gallery-item';
            
            $items = $crawler->filter($itemSelector);
            
            Log::info('IG_TEST_ITEM_CHECK', [
                'shop_id' => $shop->id,
                'item_selector' => $itemSelector,
                'item_count' => $items->count(),
            ]);
            
            if ($items->count() === 0) {
                Log::warning('IG_TEST_ITEM_NOT_FOUND', [
                    'shop_id' => $shop->id,
                    'selector' => $itemSelector,
                ]);
                return null;
            }
            
            $item = $items->first();
            
            // permalink: a.instagram-gallery-item__icon--link から取得
            $permalinkNode = $item->filter('a.instagram-gallery-item__icon--link')->first();
            $permalink = $permalinkNode->count() > 0 ? $permalinkNode->attr('href') : null;
            
            // permalinkが見つからない場合、item内のaタグから探す
            if (!$permalink) {
                $item->filter('a')->each(function (Crawler $aNode) use (&$permalink) {
                    if ($permalink) {
                        return; // 既に見つかったら終了
                    }
                    $aHref = $aNode->attr('href');
                    if ($aHref && preg_match('#https?://(www\.)?instagram\.com/p/[^/]+/#', $aHref)) {
                        $permalink = $aHref;
                    }
                });
            }
            
            if (!$permalink) {
                Log::warning('IG_TEST_NO_PERMALINK', [
                    'shop_id' => $shop->id,
                ]);
                return null;
            }
            
            // image: img.instagram-gallery-item__media から取得
            $imgNode = $item->filter('img.instagram-gallery-item__media')->first();
            $image = $imgNode->count() > 0
                ? ($imgNode->attr('src') ?? $imgNode->attr('data-src'))
                : null;
            
            // 画像URLを絶対URLに変換
            if ($image && strpos($image, 'http') !== 0) {
                if (strpos($image, '/') === 0) {
                    $image = rtrim($baseUrlParsed, '/') . $image;
                } else {
                    $image = rtrim($baseUrlParsed, '/') . '/' . ltrim($image, '/');
                }
            }
            
            // text: itemのaria-labelから取得
            $text = $item->attr('aria-label');
            if ($text) {
                // "Instagram Image: " プレフィックスを除去
                $text = trim(preg_replace('/^Instagram Image:\s*/', '', $text));
                if (empty($text)) {
                    $text = null;
                }
            }
            
            // 本文取得結果をログ出力
            Log::info('IG_TEST_TEXT_EXTRACTION_RESULT', [
                'shop_id' => $shop->id,
                'has_text' => !empty($text),
                'text_length' => $text ? mb_strlen($text) : 0,
                'text_preview' => $text ? mb_substr($text, 0, 100) : null,
            ]);
            
            return [
                'permalink' => $permalink,
                'title' => null, // Instagramではタイトルは不要
                'image' => $image,
                'text' => $text,
            ];
        } catch (\Throwable $e) {
            Log::error('IG_TEST_HTML_PARSE_FAILED', [
                'shop_id' => $shop->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

}

