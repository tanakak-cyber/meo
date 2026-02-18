<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shop;
use App\Services\ScrapingBeeFetcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TestInstagramFetch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ig:test-fetch {shop_id : 店舗ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '指定店舗のInstagram一覧ページをScrapingBeeで取得してテスト';

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
        $shopId = $this->argument('shop_id');
        $shop = Shop::find($shopId);

        if (!$shop) {
            $this->error("店舗ID {$shopId} が見つかりません。");
            return 1;
        }

        if ($shop->integration_type !== 'instagram') {
            $this->warn("店舗ID {$shopId} の連携タイプは '{$shop->integration_type}' です。Instagram連携が設定されていません。");
        }

        if (empty($shop->blog_list_url)) {
            $this->error("店舗ID {$shopId} のInstagram一覧URLが設定されていません。");
            return 1;
        }

        $this->info("店舗ID: {$shop->id}");
        $this->info("店舗名: {$shop->name}");
        $this->info("URL: {$shop->blog_list_url}");
        $this->info("");

        try {
            $this->info("ScrapingBeeでHTML取得中...");

            $startTime = microtime(true);
            $html = $this->scrapingBeeFetcher->fetchHtml($shop->blog_list_url, [
                'render_js' => true,
                'wait_for' => '.instagram-gallery-item',
                'wait' => 2000,
                'block_resources' => true,
                'premium_proxy' => false,
            ]);
            $elapsedMs = (microtime(true) - $startTime) * 1000;

            $bytes = strlen($html);
            $this->info("✓ 取得成功");
            $this->info("  サイズ: {$bytes} bytes");
            $this->info("  所要時間: " . round($elapsedMs, 2) . " ms");
            $this->info("");

            // .instagram-gallery-item の存在確認
            $containsGalleryItem = str_contains($html, 'instagram-gallery-item');
            $containsInstagramLink = str_contains($html, 'instagram.com/p/');

            $this->info("HTML内容チェック:");
            $this->info("  .instagram-gallery-item: " . ($containsGalleryItem ? '✓ 存在' : '✗ 見つかりません'));
            $this->info("  instagram.com/p/: " . ($containsInstagramLink ? '✓ 存在' : '✗ 見つかりません'));
            $this->info("");

            // 先頭1000文字を表示
            $preview = mb_substr($html, 0, 1000);
            $this->info("HTML先頭1000文字:");
            $this->line("---");
            $this->line($preview);
            $this->line("---");
            $this->info("");

            // ファイルに保存（storage/app/test-ig-fetch/ ディレクトリ）
            $filename = "test-ig-fetch-shop-{$shop->id}-" . date('Y-m-d-His') . ".html";
            $path = "test-ig-fetch/{$filename}";
            Storage::put($path, $html);
            $this->info("✓ ファイルに保存しました: storage/app/{$path}");

            Log::info('IG_TEST_FETCH_SUCCESS', [
                'shop_id' => $shop->id,
                'url' => $shop->blog_list_url,
                'bytes' => $bytes,
                'ms' => round($elapsedMs, 2),
                'contains_gallery_item' => $containsGalleryItem,
                'contains_instagram_link' => $containsInstagramLink,
                'saved_file' => $path,
            ]);

            return 0;
        } catch (\Exception $e) {
            $errorClass = get_class($e);
            $errorMessage = $e->getMessage();

            $this->error("✗ 取得失敗");
            $this->error("  エラークラス: {$errorClass}");
            $this->error("  メッセージ: {$errorMessage}");

            Log::error('IG_TEST_FETCH_FAIL', [
                'shop_id' => $shop->id,
                'url' => $shop->blog_list_url,
                'error_class' => $errorClass,
                'message' => $errorMessage,
            ]);

            return 1;
        }
    }
}

