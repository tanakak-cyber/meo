<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\GbpPost;
use App\Services\GoogleBusinessProfileService;
use App\Services\ScrapingBeeFetcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Symfony\Component\DomCrawler\Crawler;

class BlogTestController extends Controller
{
    /**
     * ScrapingBeeFetcher instance
     */
    private ScrapingBeeFetcher $scrapingBeeFetcher;

    /**
     * Constructor
     */
    public function __construct(ScrapingBeeFetcher $scrapingBeeFetcher)
    {
        $this->scrapingBeeFetcher = $scrapingBeeFetcher;
    }
    /**
     * ブログクロールテストを実行
     */
    public function run(Request $request, Shop $shop)
    {
        logger()->info('BLOG_TEST_START', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
        ]);

        try {

            // 設定チェック
            if (empty($shop->blog_list_url)) {
                Log::error('BLOG_TEST_ERROR', [
                    'shop_id' => $shop->id,
                    'error' => '記事一覧URLが設定されていません',
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => '記事一覧URLが設定されていません',
                ], 400);
            }

            // 記事リンクセレクターが空欄の場合は、親要素自体がaタグの場合に対応
            // バリデーションは不要（空欄でもOK）

            if (empty($shop->blog_date_selector)) {
                Log::error('BLOG_TEST_ERROR', [
                    'shop_id' => $shop->id,
                    'error' => '日付セレクターが設定されていません',
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => '日付セレクターが設定されていません',
                ], 400);
            }

            // HTMLを直接GETしてDOMパース
            $articles = $this->crawlWithHtml($shop);

            if (empty($articles)) {
                Log::error('BLOG_TEST_NO_ARTICLES_FOUND', [
                    'shop_id' => $shop->id,
                    'url' => $shop->blog_list_url,
                    'selector' => $shop->blog_link_selector,
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => '記事が見つかりませんでした',
                ], 404);
            }

            // 前日の日付を取得（日本時間）
            $yesterday = Carbon::yesterday('Asia/Tokyo')->format('Y-m-d');
            $today = Carbon::now('Asia/Tokyo')->format('Y-m-d');
            
            Log::info('BLOG_TEST_DATE_FILTER', [
                'shop_id' => $shop->id,
                'yesterday' => $yesterday,
                'today' => $today,
                'timezone' => 'Asia/Tokyo',
                'current_datetime' => Carbon::now('Asia/Tokyo')->toDateTimeString(),
            ]);

            // 日付が取得できている記事があるかチェック
            $hasDateAwareArticles = false;
            foreach ($articles as $article) {
                $normalizedDate = $this->normalizeDate($article['date'] ?? null, $shop->id);
                if ($normalizedDate !== null) {
                    $hasDateAwareArticles = true;
                    break;
                }
            }

            // 日付が取得できている場合のみ、日付順（降順：新しい順）に並び替え
            if ($hasDateAwareArticles) {
                usort($articles, function ($a, $b) use ($shop) {
                    $dateA = $this->normalizeDate($a['date'] ?? null, $shop->id);
                    $dateB = $this->normalizeDate($b['date'] ?? null, $shop->id);
                    
                    // 日付が取得できない記事は最後に回す
                    if (!$dateA && !$dateB) {
                        return 0;
                    }
                    if (!$dateA) {
                        return 1; // $a を後ろに
                    }
                    if (!$dateB) {
                        return -1; // $b を後ろに
                    }
                    
                    // 日付で降順ソート（新しい順）
                    return strcmp($dateB, $dateA);
                });
                
                Log::info('BLOG_TEST_SORTED_ARTICLES_CHECK', [
                    'shop_id' => $shop->id,
                    'first_article_date' => $articles[0]['date'] ?? 'none',
                    'first_article_url' => $articles[0]['url'] ?? 'none',
                    'first_article_normalized_date' => $this->normalizeDate($articles[0]['date'] ?? null, $shop->id) ?? 'none',
                    'last_article_date' => end($articles)['date'] ?? 'none',
                    'last_article_normalized_date' => $this->normalizeDate(end($articles)['date'] ?? null, $shop->id) ?? 'none',
                    'article_count' => count($articles),
                    'mode' => 'date-aware',
                ]);
            } else {
                Log::info('BLOG_TEST_ARTICLES_CHECK', [
                    'shop_id' => $shop->id,
                    'first_article_url' => $articles[0]['url'] ?? 'none',
                    'article_count' => count($articles),
                    'mode' => 'date-unknown',
                ]);
            }

            $targetArticle = null;
            $selectionReason = null;
            $skipReason = null;

            // ============================================
            // ケース1：日付が取得できている場合
            // ============================================
            if ($hasDateAwareArticles) {
                // デバッグ用：全記事の日付情報をログ出力
                $dateDebugInfo = [];
                foreach ($articles as $index => $article) {
                    $rawDate = $article['date'] ?? null;
                    $normalizedDate = $this->normalizeDate($rawDate, $shop->id);
                    $dateDebugInfo[] = [
                        'index' => $index,
                        'url' => $article['url'] ?? 'no_url',
                        'raw_date' => $rawDate,
                        'normalized_date' => $normalizedDate,
                        'matches_yesterday' => $normalizedDate === $yesterday,
                    ];
                }
                
                Log::info('BLOG_TEST_DATE_DEBUG', [
                    'shop_id' => $shop->id,
                    'yesterday' => $yesterday,
                    'article_count' => count($articles),
                    'date_info' => $dateDebugInfo,
                ]);
                
                // 昨日の記事のみを探す
                foreach ($articles as $article) {
                    if (empty($article['url'])) {
                        continue;
                    }

                    // 日付を正規化（必ず YYYY-MM-DD 形式の文字列または null）
                    $normalizedDate = $this->normalizeDate($article['date'] ?? null, $shop->id);
                    
                    // 前日の記事かチェック（厳密な文字列比較）
                    if ($normalizedDate !== null && $normalizedDate === $yesterday) {
                        // 重複チェック
                        $isDuplicate = GbpPost::where('shop_id', $shop->id)
                            ->where('source_url', $article['url'])
                            ->exists();
                        
                        if (!$isDuplicate) {
                            $targetArticle = $article;
                            $selectionReason = 'YESTERDAY_ARTICLE';
                            
                            Log::info('BLOG_TEST_YESTERDAY_ARTICLE_FOUND', [
                                'shop_id' => $shop->id,
                                'article_url' => $article['url'],
                                'article_date' => $article['date'],
                                'normalized_date' => $normalizedDate,
                                'yesterday' => $yesterday,
                                'date_match' => true,
                            ]);
                            break; // 前日の記事が見つかったらループを抜ける
                        } else {
                            Log::info('BLOG_TEST_YESTERDAY_ARTICLE_DUPLICATE', [
                                'shop_id' => $shop->id,
                                'article_url' => $article['url'],
                                'normalized_date' => $normalizedDate,
                            ]);
                        }
                    }
                }

                // 昨日の記事が見つからなかった場合、処理を終了
                if (!$targetArticle) {
                    $skipReason = 'NO_YESTERDAY_ARTICLE';
                    Log::info('BLOG_TEST_SKIP_NO_YESTERDAY', [
                        'shop_id' => $shop->id,
                        'yesterday' => $yesterday,
                        'message' => 'No article updated yesterday. (Date-aware)',
                        'all_article_dates' => array_map(function($article) use ($shop) {
                            return [
                                'url' => $article['url'] ?? 'no_url',
                                'raw_date' => $article['date'] ?? 'no_date',
                                'normalized_date' => $this->normalizeDate($article['date'] ?? null, $shop->id) ?? 'normalize_failed',
                            ];
                        }, $articles),
                    ]);
                }
            }
            // ============================================
            // ケース2：日付が取得できない場合
            // ============================================
            else {
                // 記事配列の最初の1件目のみを判定対象
                if (!empty($articles) && !empty($articles[0]['url'])) {
                    $firstArticle = $articles[0];
                    
                    // 重複チェック
                    $isDuplicate = GbpPost::where('shop_id', $shop->id)
                        ->where('source_url', $firstArticle['url'])
                        ->exists();
                    
                    if (!$isDuplicate) {
                        $targetArticle = $firstArticle;
                        $selectionReason = 'FIRST_ARTICLE_UNPOSTED';
                        
                        Log::info('BLOG_TEST_FIRST_ARTICLE_UNPOSTED', [
                            'shop_id' => $shop->id,
                            'article_url' => $firstArticle['url'],
                            'article_title' => $firstArticle['title'] ?? null,
                            'message' => 'Top article (first in DOM) is unposted. (Date-unknown)',
                        ]);
                    } else {
                        $skipReason = 'FIRST_ARTICLE_ALREADY_POSTED';
                        Log::info('BLOG_TEST_SKIP_FIRST_POSTED', [
                            'shop_id' => $shop->id,
                            'article_url' => $firstArticle['url'],
                            'message' => 'Top article already posted. Skipping to prevent old content backlog. (Date-unknown)',
                        ]);
                    }
                } else {
                    $skipReason = 'NO_ARTICLES';
                    Log::info('BLOG_TEST_SKIP_NO_ARTICLES', [
                        'shop_id' => $shop->id,
                        'message' => 'No articles found. (Date-unknown)',
                    ]);
                }
            }

            // 投稿対象が決まった場合のみ投稿処理を実行
            if (!$targetArticle) {
                // スキップ理由に応じたメッセージを返す
                $skipMessage = match($skipReason) {
                    'NO_YESTERDAY_ARTICLE' => '昨日更新された記事がありません。スキップします。 (Date-aware)',
                    'FIRST_ARTICLE_ALREADY_POSTED' => '先頭記事は既に投稿済みです。過去記事の掘り起こしを防ぐためスキップします。 (Date-unknown)',
                    'NO_ARTICLES' => '記事が見つかりませんでした。',
                    default => '投稿対象がありません。',
                };
                
                Log::info('BLOG_TEST_SKIP', [
                    'shop_id' => $shop->id,
                    'skip_reason' => $skipReason,
                    'message' => $skipMessage,
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => $skipMessage,
                ], 404);
            }

            $articleUrl = $targetArticle['url'] ?? null;
            $articleDate = $targetArticle['date'] ?? null;
            $articleImage = $targetArticle['image'] ?? null;
            $articleTitle = $targetArticle['title'] ?? null;
            $articleText = $targetArticle['text'] ?? null; // 一覧ページから取得したテキスト

            if (!$articleUrl) {
                Log::error('BLOG_TEST_NO_VALID_ARTICLE', [
                    'shop_id' => $shop->id,
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => '有効な記事URLが見つかりませんでした',
                ], 404);
            }

            Log::info('BLOG_TEST_ARTICLE_FOUND', [
                'shop_id' => $shop->id,
                'article_url' => $articleUrl,
                'article_date' => $articleDate,
                'article_image' => $articleImage,
                'article_title' => $articleTitle,
                'article_text' => $articleText,
                'selection_reason' => $selectionReason,
                'normalized_date' => $this->normalizeDate($articleDate),
            ]);

            // 記事ページを取得（本文取得のため）
            $articleHtml = $this->fetchArticleWithGuzzle($articleUrl, $shop->id);

            // 画像URL取得（一覧から取得した値を優先）
            $imageUrl = $articleImage;
            if (!$imageUrl && $shop->blog_image_selector && $articleHtml) {
                $articleCrawler = new Crawler($articleHtml);
                $imageNode = $articleCrawler->filter($shop->blog_image_selector)->first();
                if ($imageNode->count() > 0) {
                    $imageUrl = $imageNode->attr('src') ?? $imageNode->attr('data-src');
                    if ($imageUrl && strpos($imageUrl, 'http') !== 0) {
                        $articleBaseUrl = parse_url($articleUrl, PHP_URL_SCHEME) . '://' . parse_url($articleUrl, PHP_URL_HOST);
                        if (strpos($imageUrl, '/') === 0) {
                            $imageUrl = rtrim($articleBaseUrl, '/') . $imageUrl;
                        } else {
                            $imageUrl = rtrim($articleBaseUrl, '/') . '/' . ltrim($imageUrl, '/');
                        }
                    }
                }
            }

            // フォールバック: 画像が無い場合は店舗別ダミー画像URLを使用
            if (!$imageUrl) {
                $imageUrl = $shop->blog_fallback_image_url;
            }

            // 本文取得（記事ページから取得）
            $content = $articleText;
            if (!$content && $articleHtml) {
                $articleCrawler = new Crawler($articleHtml);
                // 優先度①：設定された blog_content_selector で取得
                if ($shop->blog_content_selector) {
                    $contentNode = $articleCrawler->filter($shop->blog_content_selector)->first();
                    if ($contentNode->count() > 0) {
                        $content = trim($contentNode->text());
                    }
                }
                // 優先度②：フォールバック（h1, h2, title の順で探す）
                if (!$content) {
                    foreach (['h1', 'h2', 'title'] as $tag) {
                        $contentNode = $articleCrawler->filter($tag)->first();
                        if ($contentNode->count() > 0) {
                            $content = trim($contentNode->text());
                            if ($content) {
                                Log::info('BLOG_TEST_CONTENT_FALLBACK_USED', [
                                    'shop_id' => $shop->id,
                                    'article_url' => $articleUrl,
                                    'fallback_tag' => $tag,
                                    'content' => $content,
                                ]);
                                break;
                            }
                        }
                    }
                }
            }

            // GBP API に投稿
            $gbpService = new GoogleBusinessProfileService();
            $accessToken = $gbpService->getAccessToken($shop);

            if (!$accessToken) {
                Log::error('BLOG_TEST_ACCESS_TOKEN_FAILED', [
                    'shop_id' => $shop->id,
                    'has_refresh_token' => !empty($shop->gbp_refresh_token),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Google Business Profile のアクセストークン取得に失敗しました',
                ], 500);
            }

            if (!$shop->gbp_account_id || !$shop->gbp_location_id) {
                Log::error('BLOG_TEST_CREDENTIALS_MISSING', [
                    'shop_id' => $shop->id,
                    'has_account_id' => !empty($shop->gbp_account_id),
                    'has_location_id' => !empty($shop->gbp_location_id),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Google Business Profile の認証情報が設定されていません',
                ], 400);
            }

            // GBP投稿用summaryを生成（テキスト優先、なければタイトル）
            $summary = $this->buildGbpSummary($content ?? $articleTitle);
            
            // 最終的なテキスト保証：summaryが空の場合は店舗名を使った定型文をセット
            if (empty($summary)) {
                $shopName = $shop->gbp_name ?? $shop->name ?? '当店';
                $summary = "{$shopName} ブログを更新しました";
                Log::warning('BLOG_TEST_SUMMARY_FALLBACK_TO_SHOP_NAME', [
                    'shop_id' => $shop->id,
                    'article_url' => $articleUrl,
                    'shop_name' => $shopName,
                    'summary' => $summary,
                ]);
            }

            Log::info('GBP_SUMMARY_FINAL', [
                'shop_id' => $shop->id,
                'length' => mb_strlen($summary),
                'summary' => $summary,
            ]);

            Log::info('BLOG_TEST_GBP_POST_REQUEST', [
                'shop_id' => $shop->id,
                'article_url' => $articleUrl,
                'summary_length' => mb_strlen($summary),
                'has_image' => !empty($imageUrl),
            ]);

            $result = $gbpService->createLocalPost(
                $accessToken,
                $shop->gbp_account_id,
                $shop->gbp_location_id,
                $summary,
                $imageUrl,
                $articleUrl,
                $shop  // フォールバック画像用にshopを渡す
            );

            if (!$result || !isset($result['name'])) {
                Log::error('BLOG_TEST_GBP_POST_FAILED', [
                    'shop_id' => $shop->id,
                    'article_url' => $articleUrl,
                    'result' => $result,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Google Business Profile への投稿に失敗しました',
                ], 500);
            }

            $gbpPostId = str_replace('localPosts/', '', $result['name']);

            Log::info('BLOG_TEST_GBP_POST_SUCCESS', [
                'shop_id' => $shop->id,
                'article_url' => $articleUrl,
                'gbp_post_id' => $gbpPostId,
            ]);

            // gbp_posts に保存（テスト用なので重複チェックなし）
            GbpPost::create([
                'shop_id' => $shop->id,
                'source_url' => $articleUrl,
                'gbp_post_id' => $gbpPostId,
                'gbp_post_name' => $result['name'],
                'summary' => $summary,
                'media_url' => $imageUrl,
                'posted_at' => Carbon::now(),
                'create_time' => Carbon::now(),
            ]);

            return response()->json([
                'status' => 'success',
                'article_url' => $articleUrl,
                'gbp_post_id' => $gbpPostId,
            ]);
        } catch (\Exception $e) {
            Log::error('BLOG_TEST_EXCEPTION', [
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
     * HTMLを直接GETしてDOMパースでブログ一覧を取得
     * WordPress-SSR構成に対応
     */
    private function crawlWithHtml(Shop $shop): array
    {
        $startTime = microtime(true);
        try {
            // セレクタを事前に取得（DOM解析内で$shopを使わないため）
            $blogListUrl = $shop->blog_list_url;
            $linkSelector = $shop->blog_link_selector;
            $dateSelector = $shop->blog_date_selector;

            Log::info('BLOG_TEST_FETCH_START', [
                'shop_id' => $shop->id,
                'url' => $blogListUrl,
            ]);

            // ScrapingBee経由でHTML取得（ブログは基本render_js=false、必要に応じてオプション化可能）
            $html = $this->scrapingBeeFetcher->fetchHtml($blogListUrl, [
                'render_js' => false, // ブログは通常JS不要
                'block_resources' => true,
                'premium_proxy' => false,
            ]);

            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $bytes = strlen($html);

            Log::info('BLOG_TEST_FETCH_OK', [
                'shop_id' => $shop->id,
                'bytes' => $bytes,
                'ms' => round($elapsedMs, 2),
            ]);
            
            // DOM解析を純粋関数として実行
            $articles = $this->parseHtmlArticles(
                $html,
                $linkSelector,
                $dateSelector,
                $blogListUrl,
                $shop
            );

            Log::info('BLOG_TEST_HTML_RESULT', [
                'shop_id' => $shop->id,
                'article_count' => count($articles),
            ]);

            return $articles;
        } catch (\Throwable $e) {
            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $errorClass = get_class($e);
            $errorMessage = $e->getMessage();

            Log::error('BLOG_TEST_FETCH_FAIL', [
                'shop_id' => $shop->id,
                'url' => $blogListUrl ?? 'unknown',
                'error_class' => $errorClass,
                'message' => $errorMessage,
                'ms' => round($elapsedMs, 2),
            ]);

            Log::error('BLOG_HTML_PARSE_FAILED', [
                'shop_id' => $shop->id,
                'message' => $errorMessage,
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * HTMLをパースして記事情報を抽出（純粋関数）
     * 
     * @param string $html HTML文字列
     * @param string|null $linkSelector リンクセレクタ（nullable：投稿ブロック自体がaタグの場合）
     * @param string|null $dateSelector 日付セレクタ（オプション）
     * @param string $baseUrl ベースURL
     * @param Shop $shop 店舗オブジェクト（セレクタ取得用）
     * @return array 記事情報の配列
     */
    private function parseHtmlArticles(string $html, ?string $linkSelector, ?string $dateSelector, string $baseUrl, Shop $shop): array
    {
        try {
            $crawler = new Crawler($html);

            Log::info('BLOG_TEST_PARSE', [
                'link_selector' => $linkSelector,
                'date_selector' => $dateSelector,
                'method' => 'html',
            ]);

            // ベースURLを取得
            $baseUrlParsed = parse_url($baseUrl, PHP_URL_SCHEME) . '://' . parse_url($baseUrl, PHP_URL_HOST);
            
            // セレクタを事前に取得
            $itemSelector = $shop->blog_item_selector;
            $titleSelector = $shop->blog_title_selector;
            $imageSelector = $shop->blog_image_selector;
            $contentSelector = $shop->blog_content_selector;
            
            // 起点決定：blog_item_selectorがあればそれを使用、なければ後方互換でlinkSelectorを使用
            if ($itemSelector) {
                $items = $crawler->filter($itemSelector);
            } else {
                // 後方互換：従来のlinkSelectorを起点に
                $items = $crawler->filter($linkSelector);
            }
            
            Log::info('BLOG_TEST_ITEM_CHECK', [
                'shop_id' => $shop->id,
                'item_selector' => $itemSelector ?: $linkSelector,
                'item_count' => $items->count(),
            ]);
            
            // 投稿ブロックから記事情報を抽出
            $articles = $items->each(function (Crawler $item) use ($baseUrlParsed, $linkSelector, $dateSelector, $titleSelector, $imageSelector, $contentSelector, $shop) {
                try {
                    // URL取得
                    $href = null;
                    
                    if (empty($linkSelector)) {
                        // 投稿ブロック自体が aタグ想定
                        if ($item->count() > 0) {
                            $node = $item->getNode(0);
                            if ($node && $node->nodeName === 'a') {
                                $href = $item->attr('href');
                            }
                        }
                    } else {
                        // linkSelectorが設定されている場合
                        $linkNode = $item->filter($linkSelector);
                        if ($linkNode->count() > 0) {
                            $href = $linkNode->first()->attr('href');
                        }
                    }
                    
                    if (!$href) {
                        return null;
                    }
                    
                    // 相対URLの場合は絶対URLに変換
                    if (strpos($href, 'http') !== 0) {
                        if (strpos($href, '/') === 0) {
                            $href = rtrim($baseUrlParsed, '/') . $href;
                        } else {
                            $href = rtrim($baseUrlParsed, '/') . '/' . ltrim($href, '/');
                        }
                    }
                    
                    // タイトル取得：item内から取得
                    $title = null;
                    if ($titleSelector) {
                        $titleNode = $item->filter($titleSelector)->first();
                        $title = $titleNode->count() > 0 ? trim($titleNode->text()) : null;
                    }
                    // フォールバック：h3, h2, h1 の順で探す
                    if (!$title) {
                        foreach (['h3', 'h2', 'h1'] as $tag) {
                            $titleNode = $item->filter($tag)->first();
                            if ($titleNode->count() > 0) {
                                $title = trim($titleNode->text());
                                break;
                            }
                        }
                    }
                    
                    // 画像取得：item内から取得
                    $image = null;
                    if ($imageSelector) {
                        $imgNode = $item->filter($imageSelector)->first();
                        if ($imgNode->count() > 0) {
                            $image = $imgNode->attr('src')
                                ?? $imgNode->attr('data-src')
                                ?? $imgNode->attr('data-lazy-src');
                        }
                    }
                    // フォールバック：img タグを直接探す
                    if (!$image) {
                        $imgNode = $item->filter('img')->first();
                        if ($imgNode->count() > 0) {
                            $image = $imgNode->attr('src')
                                ?? $imgNode->attr('data-src')
                                ?? $imgNode->attr('data-lazy-src');
                        }
                    }
                    
                    // 画像URLを絶対URLに変換
                    if ($image && strpos($image, 'http') !== 0) {
                        if (strpos($image, '/') === 0) {
                            $image = rtrim($baseUrlParsed, '/') . $image;
                        } else {
                            $image = rtrim($baseUrlParsed, '/') . '/' . ltrim($image, '/');
                        }
                    }
                    
                    // 日付取得：item内から取得
                    $date = null;
                    if ($dateSelector) {
                        $dateNode = $item->filter($dateSelector)->first();
                        $date = $dateNode->count() > 0 ? trim($dateNode->text()) : null;
                        if ($date) {
                            Log::debug('BLOG_TEST_DATE_EXTRACTED', [
                                'shop_id' => $shop->id,
                                'url' => $href,
                                'date_selector' => $dateSelector,
                                'raw_date' => $date,
                                'date_length' => strlen($date),
                            ]);
                        } else {
                            // 店舗ID4の場合は詳細ログ
                            if ($shop->id === 4) {
                                Log::warning('BLOG_TEST_DATE_NOT_FOUND', [
                                    'shop_id' => $shop->id,
                                    'url' => $href,
                                    'date_selector' => $dateSelector,
                                    'item_html_snippet' => mb_substr($item->html(), 0, 200),
                                ]);
                            }
                        }
                    }
                    // フォールバック：最初のpタグを日付として扱う
                    if (!$date) {
                        $dateNode = $item->filter('p')->first();
                        if ($dateNode->count() > 0) {
                            $date = trim($dateNode->text());
                            if ($date) {
                                Log::debug('BLOG_TEST_DATE_FALLBACK', [
                                    'shop_id' => $shop->id,
                                    'url' => $href,
                                    'raw_date' => $date,
                                ]);
                            }
                        }
                    }
                    
                    // テキスト取得：item内から取得
                    $text = null;
                    if ($contentSelector) {
                        $textNode = $item->filter($contentSelector)->first();
                        $text = $textNode->count() > 0 ? trim($textNode->text()) : null;
                    }
                    // フォールバック①：item内の img タグの alt 属性を取得
                    if (!$text) {
                        $imgNode = $item->filter('img')->first();
                        if ($imgNode->count() > 0) {
                            $alt = $imgNode->attr('alt');
                            if ($alt && trim($alt) !== '') {
                                $text = trim($alt);
                            }
                        }
                    }
                    // フォールバック②：item内のテキストを取得
                    if (!$text) {
                        $text = trim($item->text());
                    }
                    
                    return [
                        'url' => $href,
                        'title' => $title,
                        'image' => $image,
                        'date' => $date,
                        'text' => $text, // テキストも返す（summary用）
                    ];
                } catch (\Throwable $e) {
                    Log::warning('BLOG_TEST_PARSE_ARTICLE_ERROR', [
                        'shop_id' => $shop->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return null;
                }
            });

            return array_filter($articles);
        } catch (\Throwable $e) {
            Log::error('BLOG_HTML_PARSE_FAILED', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
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

    /**
     * Guzzle で記事ページを取得
     */
    private function fetchArticleWithGuzzle(string $articleUrl, int $shopId): ?string
    {
        try {
            $response = Http::timeout(30)->get($articleUrl);

            if (!$response->successful()) {
                Log::warning('BLOG_TEST_FETCH_ARTICLE_GUZZLE_FAILED', [
                    'shop_id' => $shopId,
                    'article_url' => $articleUrl,
                    'status_code' => $response->status(),
                ]);
                return null;
            }

            Log::info('BLOG_TEST_FETCH_ARTICLE_GUZZLE_SUCCESS', [
                'shop_id' => $shopId,
                'article_url' => $articleUrl,
                'html_length' => strlen($response->body()),
            ]);

            return $response->body();
        } catch (\Exception $e) {
            Log::warning('BLOG_TEST_FETCH_ARTICLE_GUZZLE_EXCEPTION', [
                'shop_id' => $shopId,
                'article_url' => $articleUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 日付文字列をYYYY-MM-DD形式に正規化
     * 
     * @param string|null $raw 生の日付文字列
     * @param int|null $shopId 店舗ID（デバッグログ用）
     * @return string|null YYYY-MM-DD形式の日付、またはnull（失敗時）
     */
    private function normalizeDate(?string $raw, ?int $shopId = null): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $raw = trim($raw);
        $originalRaw = $raw;
        
        // 既にYYYY-MM-DD形式の場合はそのまま返す
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        try {
            // Carbonでパースを試みる
            $date = Carbon::parse($raw);
            $normalized = $date->format('Y-m-d');
            
            // 店舗ID4の場合は詳細ログ
            if ($shopId === 4) {
                Log::debug('BLOG_TEST_NORMALIZE_DATE_SUCCESS', [
                    'shop_id' => $shopId,
                    'raw_date' => $originalRaw,
                    'normalized_date' => $normalized,
                    'method' => 'Carbon::parse',
                ]);
            }
            
            return $normalized;
        } catch (\Exception $e) {
            // 店舗ID4の場合は詳細ログ
            if ($shopId === 4) {
                Log::debug('BLOG_TEST_NORMALIZE_DATE_CARBON_FAILED', [
                    'shop_id' => $shopId,
                    'raw_date' => $originalRaw,
                    'error' => $e->getMessage(),
                ]);
            }
            // パース失敗時は正規表現で抽出を試みる
            $year = null;
            $month = null;
            $day = null;

            // パターン1: YYYY/MM/DD, YYYY.MM.DD, YYYY-MM-DD
            if (preg_match('/(\d{4})[\/\.\-](\d{1,2})[\/\.\-](\d{1,2})/', $raw, $matches)) {
                $year = (int)$matches[1];
                $month = (int)$matches[2];
                $day = (int)$matches[3];
            }
            // パターン2: YYYY年MM月DD日（修正：2桁の月・日にも対応）
            elseif (preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日/', $raw, $matches)) {
                $year = (int)$matches[1];
                $month = (int)$matches[2];
                $day = (int)$matches[3];
            }
            // パターン3: MM/DD/YYYY
            elseif (preg_match('/(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{4})/', $raw, $matches)) {
                $year = (int)$matches[3];
                $month = (int)$matches[1];
                $day = (int)$matches[2];
            }
            // パターン4: 英語表記 (Feb 1, 2025, 1 Feb 2025)
            elseif (preg_match('/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[\s,]+(\d{1,2})[\s,]+(\d{4})/i', $raw, $matches)) {
                $monthMap = [
                    'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
                    'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
                    'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
                ];
                $month = $monthMap[strtolower($matches[1])] ?? null;
                $day = (int)$matches[2];
                $year = (int)$matches[3];
            }
            elseif (preg_match('/(\d{1,2})[\s,]+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[\s,]+(\d{4})/i', $raw, $matches)) {
                $monthMap = [
                    'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
                    'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
                    'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
                ];
                $day = (int)$matches[1];
                $month = $monthMap[strtolower($matches[2])] ?? null;
                $year = (int)$matches[3];
            }

            if ($year && $month && $day) {
                try {
                    $date = Carbon::create($year, $month, $day);
                    $normalized = $date->format('Y-m-d');
                    
                    // 店舗ID4の場合は詳細ログ
                    if ($shopId === 4) {
                        Log::debug('BLOG_TEST_NORMALIZE_DATE_SUCCESS', [
                            'shop_id' => $shopId,
                            'raw_date' => $originalRaw,
                            'normalized_date' => $normalized,
                            'method' => 'regex_extraction',
                            'year' => $year,
                            'month' => $month,
                            'day' => $day,
                        ]);
                    }
                    
                    return $normalized;
                } catch (\Exception $e) {
                    Log::warning('BLOG_TEST_NORMALIZE_DATE_FAILED', [
                        'raw' => $originalRaw,
                        'year' => $year,
                        'month' => $month,
                        'day' => $day,
                        'error' => $e->getMessage(),
                    ]);
                    return null;
                }
            }

            // 店舗ID4の場合は詳細ログ
            if ($shopId === 4) {
                Log::warning('BLOG_TEST_NORMALIZE_DATE_NO_MATCH', [
                    'shop_id' => $shopId,
                    'raw' => $originalRaw,
                    'raw_length' => strlen($originalRaw),
                    'raw_bytes' => bin2hex(substr($originalRaw, 0, 50)),
                ]);
            } else {
                Log::warning('BLOG_TEST_NORMALIZE_DATE_NO_MATCH', [
                    'raw' => $originalRaw,
                ]);
            }
            return null;
        }
    }

}

