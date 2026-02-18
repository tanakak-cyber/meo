<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shop;
use App\Services\InstagramPostService;
use App\Services\ScrapingBeeFetcher;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Symfony\Component\DomCrawler\Crawler;

class CrawlAndPostInstagram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instagram:crawl {--force : 時刻チェックをスキップして強制実行}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Instagramをクロールして前日の投稿をGBPに自動投稿';

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
        parent::__construct();
        $this->scrapingBeeFetcher = $scrapingBeeFetcher;
        $this->instagramPostService = $instagramPostService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Instagram自動クロール・投稿処理を開始します...');
        $this->info('現在時刻（日本時間）: ' . Carbon::now('Asia/Tokyo')->format('Y-m-d H:i:s'));

        // integration_type='instagram' の店舗を取得
        // ブログクロールと同じセレクタを使用（blog_list_url, blog_link_selector, blog_date_selector）
        $shops = Shop::where('integration_type', 'instagram')
            ->whereNotNull('blog_list_url')
            ->whereNotNull('blog_link_selector')
            ->whereNotNull('blog_date_selector')
            ->get();

        $this->info("対象店舗数: {$shops->count()}件");

        $currentTime = Carbon::now('Asia/Tokyo')->format('H:i');
        $processedCount = 0;

        foreach ($shops as $shop) {
            // 現在時刻が instagram_crawl_time と一致するか確認
            $crawlTime = null;
            if ($shop->instagram_crawl_time) {
                // instagram_crawl_time を安全にパースして H:i 形式に変換
                $crawlTime = Carbon::parse($shop->instagram_crawl_time)->format('H:i');
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
        Log::info('IG_CRAWL_START', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'url' => $shop->blog_list_url,
        ]);

        try {
            // HTMLを直接GETしてDOMパース（1件目のみ取得）
            $article = $this->crawlWithHtml($shop);

            if (empty($article) || empty($article['permalink'])) {
                Log::info('IG_NO_ARTICLE', [
                    'shop_id' => $shop->id,
                ]);
                $this->info("  → Instagram投稿が見つかりませんでした");
                return;
            }

            Log::info('IG_CRAWL_ARTICLE_FOUND', [
                'shop_id' => $shop->id,
                'permalink' => $article['permalink'],
                'has_image' => !empty($article['image']),
            ]);

            // 重複チェック（source_type='instagram' + source_external_id=permalink）
            $isDuplicate = $this->instagramPostService->checkDuplicate($shop, $article['permalink']);
            
            if ($isDuplicate) {
                Log::info('IG_CRAWL_DUPLICATE_SKIP', [
                    'shop_id' => $shop->id,
                    'permalink' => $article['permalink'],
                ]);
                $this->info("  → 既に投稿済みのためスキップ");
                return;
            }

            // 投稿対象が決まった場合のみ投稿処理を実行
            Log::info('IG_CRAWL_TARGET_SELECTED', [
                'shop_id' => $shop->id,
                'permalink' => $article['permalink'],
                'article_image' => $article['image'] ?? null,
            ]);

            $gbpPost = $this->instagramPostService->processArticle($shop, $article['permalink'], $article['image'] ?? null, $article['title'] ?? null, $article['text'] ?? null);
            
            if ($gbpPost) {
                $this->info("投稿成功: {$article['permalink']}");
            }
        } catch (\Exception $e) {
            Log::error('IG_CRAWL_EXCEPTION', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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
            Log::info('IG_FETCH_START', [
                'shop_id' => $shop->id,
                'url' => $blogListUrl,
            ]);

            // ScrapingBee経由でHTML取得（Instagram用オプション）
            $html = $this->scrapingBeeFetcher->fetchHtml($blogListUrl, [
                'render_js' => true,
                'wait_for' => '.instagram-gallery-item',
                'wait' => 2000,
                'block_resources' => true,
                'premium_proxy' => false,
            ]);

            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $bytes = strlen($html);

            Log::info('IG_FETCH_OK', [
                'shop_id' => $shop->id,
                'bytes' => $bytes,
                'ms' => round($elapsedMs, 2),
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

            Log::error('IG_FETCH_FAIL', [
                'shop_id' => $shop->id,
                'url' => $blogListUrl,
                'error_class' => $errorClass,
                'message' => $errorMessage,
                'ms' => round($elapsedMs, 2),
            ]);

            Log::error('IG_HTML_PARSE_FAILED', [
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
            
            Log::info('IG_ITEM_CHECK', [
                'shop_id' => $shop->id,
                'item_selector' => $itemSelector,
                'item_count' => $items->count(),
            ]);
            
            if ($items->count() === 0) {
                Log::warning('IG_ITEM_NOT_FOUND', [
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
                Log::warning('IG_NO_PERMALINK', [
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
            Log::info('IG_TEXT_EXTRACTION_RESULT', [
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
            Log::error('IG_HTML_PARSE_FAILED', [
                'shop_id' => $shop->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }


    /**
     * 日付文字列をYYYY-MM-DD形式に正規化
     * ブログクロールと同じロジック
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
            // パターン2: YYYY年MM月DD日
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
            // パターン4: 英語表記
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
}
