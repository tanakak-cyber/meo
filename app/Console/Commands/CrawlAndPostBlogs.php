<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shop;
use App\Models\GbpPost;
use App\Services\GoogleBusinessProfileService;
use App\Services\ScrapingBeeFetcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Symfony\Component\DomCrawler\Crawler;

class CrawlAndPostBlogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:crawl {--force : 時刻チェックをスキップして強制実行}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ブログをクロールして前日の記事をGBPに自動投稿';

    /**
     * ScrapingBeeFetcher instance
     */
    private ScrapingBeeFetcher $scrapingBeeFetcher;

    /**
     * Constructor
     */
    public function __construct(ScrapingBeeFetcher $scrapingBeeFetcher)
    {
        parent::__construct();
        $this->scrapingBeeFetcher = $scrapingBeeFetcher;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('ブログ自動クロール・投稿処理を開始します...');
        $this->info('現在時刻（日本時間）: ' . Carbon::now('Asia/Tokyo')->format('Y-m-d H:i:s'));

        // integration_type='blog' の店舗を取得
        // blog_link_selectorは空欄でもOK（親要素自体がaタグの場合に対応）
        $shops = Shop::where('integration_type', 'blog')
            ->whereNotNull('blog_list_url')
            ->whereNotNull('blog_date_selector')
            ->get();

        $this->info("対象店舗数: {$shops->count()}件");

        $currentTime = Carbon::now('Asia/Tokyo')->format('H:i');
        $processedCount = 0;

        foreach ($shops as $shop) {
            // 現在時刻が blog_crawl_time と一致するか確認
            $crawlTime = null;
            if ($shop->blog_crawl_time) {
                // blog_crawl_time が time 型の場合、H:i:s 形式で返される可能性がある
                // 文字列の場合はそのまま、DateTime の場合は format で変換
                if (is_string($shop->blog_crawl_time)) {
                    // "03:00:00" 形式の場合は "03:00" に変換
                    $crawlTime = substr($shop->blog_crawl_time, 0, 5);
                } else {
                    // DateTime オブジェクトの場合
                    $crawlTime = Carbon::parse($shop->blog_crawl_time)->format('H:i');
                }
            }

            $this->line("店舗ID {$shop->id} ({$shop->name}): 現在時刻={$currentTime}, クロール時刻={$crawlTime}");

            // --force オプションが指定されていない場合のみ時刻チェック
            if (!$this->option('force')) {
                if (!$crawlTime || $crawlTime !== $currentTime) {
                    $this->line("  → スキップ（時刻不一致）");
                    continue; // 時刻が一致しない場合はスキップ
                }
            } else {
                $this->line("  → 強制実行モード（時刻チェックをスキップ）");
            }

            $this->info("店舗ID {$shop->id} ({$shop->name}) のクロールを開始...");
            $this->processShop($shop);
            $processedCount++;
        }

        $this->info("処理完了: {$processedCount}件の店舗を処理しました。");
    }

    /**
     * 店舗ごとの処理
     */
    private function processShop(Shop $shop)
    {
        Log::info('BLOG_CRAWL_START', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'url' => $shop->blog_list_url,
        ]);

        try {
            // HTMLを直接GETしてDOMパース
            $articles = $this->crawlWithHtml($shop);

            $articleCount = count($articles);
            $this->info("記事URL数: {$articleCount}件");

            if (empty($articles)) {
                Log::info('BLOG_CRAWL_NO_ARTICLES', [
                    'shop_id' => $shop->id,
                ]);
                return;
            }

            // 前日の日付を取得（日本時間）
            $yesterday = Carbon::yesterday('Asia/Tokyo')->format('Y-m-d');
            
            Log::info('BLOG_CRAWL_DATE_FILTER', [
                'shop_id' => $shop->id,
                'yesterday' => $yesterday,
                'article_count' => $articleCount,
            ]);

            // 日付が取得できている記事があるかチェック
            $hasDateAwareArticles = false;
            foreach ($articles as $article) {
                $normalizedDate = $this->normalizeDate($article['date'] ?? null);
                if ($normalizedDate !== null) {
                    $hasDateAwareArticles = true;
                    break;
                }
            }

            // 日付が取得できている場合のみ、日付順（降順：新しい順）に並び替え
            if ($hasDateAwareArticles) {
                usort($articles, function ($a, $b) {
                    $dateA = $this->normalizeDate($a['date'] ?? null);
                    $dateB = $this->normalizeDate($b['date'] ?? null);
                    
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
                
                Log::info('BLOG_CRAWL_SORTED_ARTICLES_CHECK', [
                    'shop_id' => $shop->id,
                    'first_article_date' => $articles[0]['date'] ?? 'none',
                    'first_article_url' => $articles[0]['url'] ?? 'none',
                    'first_article_normalized_date' => $this->normalizeDate($articles[0]['date'] ?? null) ?? 'none',
                    'last_article_date' => end($articles)['date'] ?? 'none',
                    'last_article_normalized_date' => $this->normalizeDate(end($articles)['date'] ?? null) ?? 'none',
                    'article_count' => count($articles),
                    'mode' => 'date-aware',
                ]);
            } else {
                Log::info('BLOG_CRAWL_ARTICLES_CHECK', [
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
                // 昨日の記事のみを探す
                foreach ($articles as $article) {
                    if (empty($article['url'])) {
                        continue;
                    }

                    // 日付を正規化（必ず YYYY-MM-DD 形式の文字列または null）
                    $normalizedDate = $this->normalizeDate($article['date'] ?? null);
                    
                    // 前日の記事かチェック（厳密な文字列比較）
                    if ($normalizedDate !== null && $normalizedDate === $yesterday) {
                        // 重複チェック
                        $isDuplicate = $this->checkDuplicateByUrl($shop, $article['url']);
                        
                        if (!$isDuplicate) {
                            $targetArticle = $article;
                            $selectionReason = 'YESTERDAY_ARTICLE';
                            
                            Log::info('BLOG_CRAWL_YESTERDAY_ARTICLE_FOUND', [
                                'shop_id' => $shop->id,
                                'article_url' => $article['url'],
                                'article_date' => $article['date'],
                                'normalized_date' => $normalizedDate,
                                'yesterday' => $yesterday,
                                'date_match' => true,
                            ]);
                            break; // 前日の記事が見つかったらループを抜ける
                        } else {
                            Log::info('BLOG_CRAWL_YESTERDAY_ARTICLE_DUPLICATE', [
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
                    Log::info('BLOG_CRAWL_SKIP_NO_YESTERDAY', [
                        'shop_id' => $shop->id,
                        'yesterday' => $yesterday,
                        'message' => 'No article updated yesterday. (Date-aware)',
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
                    $isDuplicate = $this->checkDuplicateByUrl($shop, $firstArticle['url']);
                    
                    if (!$isDuplicate) {
                        $targetArticle = $firstArticle;
                        $selectionReason = 'FIRST_ARTICLE_UNPOSTED';
                        
                        Log::info('BLOG_CRAWL_FIRST_ARTICLE_UNPOSTED', [
                            'shop_id' => $shop->id,
                            'article_url' => $firstArticle['url'],
                            'article_title' => $firstArticle['title'] ?? null,
                            'message' => 'Top article (first in DOM) is unposted. (Date-unknown)',
                        ]);
                    } else {
                        $skipReason = 'FIRST_ARTICLE_ALREADY_POSTED';
                        Log::info('BLOG_CRAWL_SKIP_FIRST_POSTED', [
                            'shop_id' => $shop->id,
                            'article_url' => $firstArticle['url'],
                            'message' => 'Top article already posted. Skipping to prevent old content backlog. (Date-unknown)',
                        ]);
                    }
                } else {
                    $skipReason = 'NO_ARTICLES';
                    Log::info('BLOG_CRAWL_SKIP_NO_ARTICLES', [
                        'shop_id' => $shop->id,
                        'message' => 'No articles found. (Date-unknown)',
                    ]);
                }
            }

            // 投稿対象が決まった場合のみ投稿処理を実行
            if ($targetArticle) {
                Log::info('BLOG_CRAWL_TARGET_SELECTED', [
                    'shop_id' => $shop->id,
                    'article_url' => $targetArticle['url'],
                    'selection_reason' => $selectionReason,
                    'article_date' => $targetArticle['date'] ?? null,
                    'article_image' => $targetArticle['image'] ?? null,
                    'article_title' => $targetArticle['title'] ?? null,
                ]);

                $this->processArticle($shop, $targetArticle['url'], $targetArticle['date'] ?? null, $targetArticle['image'] ?? null, $targetArticle['title'] ?? null, $targetArticle['text'] ?? null);
            } else {
                // スキップ理由に応じたメッセージを表示
                $skipMessage = match($skipReason) {
                    'NO_YESTERDAY_ARTICLE' => '昨日更新された記事がありません。スキップします。 (Date-aware)',
                    'FIRST_ARTICLE_ALREADY_POSTED' => '先頭記事は既に投稿済みです。過去記事の掘り起こしを防ぐためスキップします。 (Date-unknown)',
                    'NO_ARTICLES' => '記事が見つかりませんでした。',
                    default => '投稿対象がありません。',
                };
                
                Log::info('BLOG_CRAWL_SKIP', [
                    'shop_id' => $shop->id,
                    'skip_reason' => $skipReason,
                    'message' => $skipMessage,
                ]);
                $this->info("  {$skipMessage}");
            }
        } catch (\Exception $e) {
            Log::error('BLOG_CRAWL_EXCEPTION', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 記事ごとの処理
     * 
     * @param Shop $shop
     * @param string $articleUrl
     * @param string|null $articleDateFromList 一覧から取得した日付（優先使用）
     * @param string|null $articleImageFromList 一覧から取得した画像URL（優先使用）
     * @param string|null $articleTitleFromList 一覧から取得したタイトル（優先使用）
     * @param string|null $articleTextFromList 一覧から取得したテキスト（優先使用）
     */
    private function processArticle(Shop $shop, string $articleUrl, ?string $articleDateFromList = null, ?string $articleImageFromList = null, ?string $articleTitleFromList = null, ?string $articleTextFromList = null)
    {
        try {
            // ① article_url が無い → SKIP
            if (empty($articleUrl)) {
                Log::info('BLOG_CRAWL_DECISION', [
                    'url' => null,
                    'raw_date' => $articleDateFromList,
                    'normalized_date' => null,
                    'is_duplicate' => false,
                    'final_post_date' => null,
                    'action' => 'SKIP',
                    'skip_reason' => 'NO_URL',
                ]);
                return;
            }

            $rawDate = $articleDateFromList;
            $html = null;
            
            // 一覧から日付が取得できなかった場合のみ、記事ページから取得
            if (!$rawDate) {
                $html = $this->fetchArticleWithGuzzle($articleUrl, $shop->id);
                if ($html && $shop->blog_date_selector) {
                    $crawler = new Crawler($html);
                    $dateNode = $crawler->filter($shop->blog_date_selector)->first();
                    if ($dateNode->count() > 0) {
                        $rawDate = trim($dateNode->text());
                    }
                }
            }

            // 日付正規化
            $normalizedDate = $this->normalizeDate($rawDate);
            
            // 念のため重複チェック（processShopで既にチェック済みだが、二重投稿を防ぐため）
            $isDuplicate = $this->checkDuplicateByUrl($shop, $articleUrl);
            
            if ($isDuplicate) {
                Log::warning('BLOG_CRAWL_DUPLICATE_DETECTED_IN_PROCESS', [
                    'shop_id' => $shop->id,
                    'url' => $articleUrl,
                    'raw_date' => $rawDate,
                    'normalized_date' => $normalizedDate,
                    'message' => 'processShopで選別された記事が重複していたためスキップ',
                ]);
                return;
            }

            // ⑤ 投稿処理
            // 画像URL取得（一覧から取得した値を優先）
            $imageUrl = $articleImageFromList;
            
            // 本文取得のため、記事ページを取得（まだ取得していない場合）
            if (!$html && ($shop->blog_image_selector || $shop->blog_content_selector)) {
                $html = $this->fetchArticleWithGuzzle($articleUrl, $shop->id);
            }

            // 一覧から画像が取得できなかった場合のみ、記事ページから取得
            if (!$imageUrl && $shop->blog_image_selector && $html) {
                $crawler = new Crawler($html);
                $imageNode = $crawler->filter($shop->blog_image_selector)->first();
                if ($imageNode->count() > 0) {
                    $imageUrl = $imageNode->attr('src') ?? $imageNode->attr('data-src');
                    // 相対URLの場合は絶対URLに変換
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

            // 画像URLが無効な場合は投稿をスキップ
            $skipThisArticle = false;
            if (!$imageUrl || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                logger()->warning('BLOG_SKIP_NO_VALID_IMAGE', [
                    'shop_id' => $shop->id,
                    'article_url' => $articleUrl,
                ]);
                $skipThisArticle = true;
            }

            // 画像が有効な場合のみGBP投稿処理を実行
            if (!$skipThisArticle) {
                // タイトル取得（一覧から取得した値を優先、なければ記事ページから取得）
                $title = $articleTitleFromList;
                if (!$title && $html) {
                    $crawler = new Crawler($html);
                    if ($shop->blog_title_selector) {
                        $titleNode = $crawler->filter($shop->blog_title_selector)->first();
                        if ($titleNode->count() > 0) {
                            $title = trim($titleNode->text());
                        }
                    } else {
                        $titleNode = $crawler->filter('h1')->first();
                        if ($titleNode->count() > 0) {
                            $title = trim($titleNode->text());
                        }
                    }
                }

                // フォールバック: タイトルが無い場合
                if (empty($title)) {
                    $title = '最新の更新情報です。';
                }

                // テキスト取得（一覧から取得した値を優先、なければ記事ページから取得）
                $text = $articleTextFromList;
                if (!$text && $html) {
                    $crawler = new Crawler($html);
                    // 優先度①：設定された blog_content_selector で取得
                    if ($shop->blog_content_selector) {
                        $textNode = $crawler->filter($shop->blog_content_selector)->first();
                        if ($textNode->count() > 0) {
                            $text = trim($textNode->text());
                        }
                    }
                    // 優先度②：フォールバック（h1, h2, title の順で探す）
                    if (!$text) {
                        foreach (['h1', 'h2', 'title'] as $tag) {
                            $textNode = $crawler->filter($tag)->first();
                            if ($textNode->count() > 0) {
                                $text = trim($textNode->text());
                                if ($text) {
                                    Log::info('BLOG_CONTENT_FALLBACK_USED', [
                                        'shop_id' => $shop->id,
                                        'article_url' => $articleUrl,
                                        'fallback_tag' => $tag,
                                        'text' => $text,
                                    ]);
                                    break;
                                }
                            }
                        }
                    }
                }

                // GBP投稿用summaryを生成（テキスト優先、なければタイトル）
                $summary = $this->buildGbpSummary($text ?? $title);
                
                // 最終的なテキスト保証：summaryが空の場合は店舗名を使った定型文をセット
                if (empty($summary)) {
                    $shopName = $shop->gbp_name ?? $shop->name ?? '当店';
                    $summary = "{$shopName} ブログを更新しました";
                    Log::warning('BLOG_SUMMARY_FALLBACK_TO_SHOP_NAME', [
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

                Log::info('BLOG_CRAWL_POSTING', [
                    'shop_id' => $shop->id,
                    'url' => $articleUrl,
                    'raw_date' => $rawDate,
                    'normalized_date' => $normalizedDate,
                    'action' => 'POST',
                ]);

                // Google Business Profile API に投稿
                $this->postToGbp($shop, $articleUrl, $summary, $imageUrl);
            }
        } catch (\Exception $e) {
            Log::error('BLOG_ARTICLE_PROCESS_EXCEPTION', [
                'shop_id' => $shop->id,
                'article_url' => $articleUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Google Business Profile API に投稿
     */
    private function postToGbp(Shop $shop, string $articleUrl, ?string $summary, ?string $imageUrl)
    {
        try {
            // アクセストークンを取得
            $gbpService = new GoogleBusinessProfileService();
            $accessToken = $gbpService->getAccessToken($shop);

            if (!$accessToken) {
                Log::error('BLOG_GBP_POST_ACCESS_TOKEN_FAILED', [
                    'shop_id' => $shop->id,
                    'article_url' => $articleUrl,
                ]);
                return;
            }

            if (!$shop->gbp_account_id || !$shop->gbp_location_id) {
                Log::error('BLOG_GBP_POST_CREDENTIALS_MISSING', [
                    'shop_id' => $shop->id,
                    'article_url' => $articleUrl,
                ]);
                return;
            }

            Log::info('BLOG_GBP_POST_REQUEST', [
                'shop_id' => $shop->id,
                'article_url' => $articleUrl,
                'summary_length' => $summary ? strlen($summary) : 0,
                'has_image' => !empty($imageUrl),
            ]);

            // GBP API に投稿
            $result = $gbpService->createLocalPost(
                $accessToken,
                $shop->gbp_account_id,
                $shop->gbp_location_id,
                $summary,
                $imageUrl,
                $articleUrl,
                $shop  // フォールバック画像用にshopを渡す
            );

            if ($result && isset($result['name'])) {
                $gbpPostId = str_replace('localPosts/', '', $result['name']);

                Log::info('BLOG_GBP_POST_SUCCESS', [
                    'shop_id' => $shop->id,
                    'article_url' => $articleUrl,
                    'gbp_post_id' => $gbpPostId,
                ]);

                // gbp_posts に保存（DB保存はすべてUTCで統一）
                GbpPost::create([
                    'shop_id' => $shop->id,
                    'source_url' => $articleUrl,
                    'source_type' => 'blog',
                    'source_external_id' => null,
                    'gbp_post_id' => $gbpPostId,
                    'gbp_post_name' => $result['name'],
                    'summary' => $summary,
                    'media_url' => $imageUrl,
                    'posted_at' => now(), // UTC保存
                    'create_time' => now(), // UTC保存（記事日付は別途設定可能）
                ]);

                $this->info("投稿成功: {$articleUrl}");
            } else {
                Log::error('BLOG_GBP_POST_FAILED', [
                    'shop_id' => $shop->id,
                    'article_url' => $articleUrl,
                    'result' => $result,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('BLOG_GBP_POST_EXCEPTION', [
                'shop_id' => $shop->id,
                'article_url' => $articleUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * HTMLを直接GETしてDOMパースでブログ一覧を取得
     * WordPress-SSR構成に対応
     */
    private function crawlWithHtml(Shop $shop): array
    {
        $startTime = microtime(true);
        $blogListUrl = $shop->blog_list_url;
        $linkSelector = $shop->blog_link_selector;
        $dateSelector = $shop->blog_date_selector;

        try {
            Log::info('BLOG_FETCH_START', [
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

            Log::info('BLOG_FETCH_OK', [
                'shop_id' => $shop->id,
                'bytes' => $bytes,
                'ms' => round($elapsedMs, 2),
            ]);
            
            // DOM解析を純粋関数として実行
            // linkSelectorが空の場合はnullを渡す
            $articles = $this->parseHtmlArticles(
                $html,
                !empty($linkSelector) ? $linkSelector : null,
                $dateSelector,
                $blogListUrl,
                $shop
            );

            Log::info('BLOG_CRAWL_HTML_RESULT', [
                'shop_id' => $shop->id,
                'article_count' => count($articles),
            ]);

            return $articles;
        } catch (\Throwable $e) {
            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $errorClass = get_class($e);
            $errorMessage = $e->getMessage();

            Log::error('BLOG_FETCH_FAIL', [
                'shop_id' => $shop->id,
                'url' => $blogListUrl,
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
            } elseif (!empty($linkSelector)) {
                // 後方互換：従来のlinkSelectorを起点に
                $items = $crawler->filter($linkSelector);
            } else {
                // linkSelectorも空の場合はエラー
                Log::error('BLOG_CRAWL_NO_SELECTOR', [
                    'shop_id' => $shop->id,
                    'item_selector' => $itemSelector,
                    'link_selector' => $linkSelector,
                ]);
                return [];
            }
            
            Log::info('BLOG_ITEM_CHECK', [
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
                    }
                    // フォールバック：最初のpタグを日付として扱う
                    if (!$date) {
                        $dateNode = $item->filter('p')->first();
                        if ($dateNode->count() > 0) {
                            $date = trim($dateNode->text());
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
                    Log::warning('BLOG_CRAWL_PARSE_ARTICLE_ERROR', [
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
     * Guzzle で記事ページを取得
     */
    private function fetchArticleWithGuzzle(string $articleUrl, int $shopId): ?string
    {
        try {
            $response = Http::timeout(30)->get($articleUrl);

            if (!$response->successful()) {
                Log::warning('BLOG_ARTICLE_FETCH_GUZZLE_FAILED', [
                    'shop_id' => $shopId,
                    'article_url' => $articleUrl,
                    'status_code' => $response->status(),
                ]);
                return null;
            }

            return $response->body();
        } catch (\Exception $e) {
            Log::warning('BLOG_ARTICLE_FETCH_GUZZLE_EXCEPTION', [
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

    /**
     * 日付文字列をYYYY-MM-DD形式に正規化
     * 
     * @param string|null $raw 生の日付文字列
     * @return string|null YYYY-MM-DD形式の日付、またはnull（失敗時）
     */
    private function normalizeDate(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $raw = trim($raw);
        
        // 既にYYYY-MM-DD形式の場合はそのまま返す
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        try {
            // Carbonでパースを試みる
            $date = Carbon::parse($raw);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
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
            // パターン2: YYYY年MM月DD日（例：2025年02月19日）
            elseif (preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日/', $raw, $matches)) {
                $year = (int)$matches[1];
                $month = (int)$matches[2];
                $day = (int)$matches[3];
                
                // デバッグログ（日付正規化の確認用）
                Log::debug('BLOG_NORMALIZE_DATE_YYYY_MM_DD', [
                    'raw' => $raw,
                    'year' => $year,
                    'month' => $month,
                    'day' => $day,
                ]);
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
                    return $date->format('Y-m-d');
                } catch (\Exception $e) {
                    return null;
                }
            }

            return null;
        }
    }

    /**
     * URL一致で重複チェック（日付が無い場合）
     * 
     * @param Shop $shop
     * @param string $articleUrl
     * @return bool 重複している場合true
     */
    private function checkDuplicateByUrl(Shop $shop, string $articleUrl): bool
    {
        try {
            // ① gbp_postsテーブルで確認
            $existingPost = GbpPost::where('shop_id', $shop->id)
                ->where('source_url', $articleUrl)
                ->first();
            
            if ($existingPost) {
                return true;
            }

            // ② GBP APIで既存投稿を取得してURL一致チェック
            if (!$shop->gbp_account_id || !$shop->gbp_location_id) {
                return false;
            }

            $gbpService = new GoogleBusinessProfileService();
            $accessToken = $gbpService->getAccessToken($shop);
            
            if (!$accessToken) {
                return false;
            }

            $localPosts = $gbpService->listLocalPosts(
                $accessToken,
                $shop->gbp_account_id,
                $shop->gbp_location_id
            );

            if (!$localPosts) {
                return false;
            }

            // callToAction.url または summary 内に articleUrl が含まれているか確認
            foreach ($localPosts as $post) {
                // callToAction.url をチェック
                if (isset($post['callToAction']['url']) && $post['callToAction']['url'] === $articleUrl) {
                    return true;
                }
                
                // summary 内にURLが含まれているかチェック
                if (isset($post['summary']) && strpos($post['summary'], $articleUrl) !== false) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error('BLOG_DUPLICATE_CHECK_EXCEPTION', [
                'shop_id' => $shop->id,
                'article_url' => $articleUrl,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

}

