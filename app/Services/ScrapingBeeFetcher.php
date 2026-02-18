<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ScrapingBeeFetcher
{
    private string $apiKey;
    private Client $client;
    private bool $renderJsDefault;
    private int $timeoutMs;
    private int $waitMsDefault;
    private bool $blockResourcesDefault;

    public function __construct()
    {
        $this->apiKey = config('services.scrapingbee.api_key', env('SCRAPINGBEE_API_KEY', ''));
        $this->client = new Client([
            'base_uri' => 'https://app.scrapingbee.com/api/v1',
            'timeout' => (int)(config('services.scrapingbee.timeout_ms', env('SCRAPINGBEE_TIMEOUT_MS', 140000)) / 1000),
        ]);
        $this->renderJsDefault = filter_var(
            config('services.scrapingbee.render_js_default', env('SCRAPINGBEE_RENDER_JS_DEFAULT', true)),
            FILTER_VALIDATE_BOOLEAN
        );
        $this->timeoutMs = (int)config('services.scrapingbee.timeout_ms', env('SCRAPINGBEE_TIMEOUT_MS', 140000));
        $this->waitMsDefault = (int)config('services.scrapingbee.wait_ms_default', env('SCRAPINGBEE_WAIT_MS_DEFAULT', 0));
        $this->blockResourcesDefault = filter_var(
            config('services.scrapingbee.block_resources_default', env('SCRAPINGBEE_BLOCK_RESOURCES_DEFAULT', true)),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * ScrapingBee APIを使用してHTMLを取得
     * 
     * @param string $url 取得対象URL
     * @param array $options オプション
     *   - render_js: bool (default: SCRAPINGBEE_RENDER_JS_DEFAULT)
     *   - wait_for: string|null CSSセレクタ（レンダリング待機用）
     *   - wait: int|null 待機時間（ミリ秒）
     *   - block_resources: bool (default: SCRAPINGBEE_BLOCK_RESOURCES_DEFAULT)
     *   - premium_proxy: bool (default: false)
     *   - timeout: int|null タイムアウト（ミリ秒、default: SCRAPINGBEE_TIMEOUT_MS）
     * @return string HTML文字列
     * @throws \Exception
     */
    public function fetchHtml(string $url, array $options = []): string
    {
        $startTime = microtime(true);

        try {
            $renderJs = $options['render_js'] ?? $this->renderJsDefault;
            $waitFor = $options['wait_for'] ?? null;
            $wait = $options['wait'] ?? $this->waitMsDefault;
            $blockResources = $options['block_resources'] ?? $this->blockResourcesDefault;
            $premiumProxy = $options['premium_proxy'] ?? false;
            $timeout = $options['timeout'] ?? $this->timeoutMs;

            // クエリパラメータを構築
            $query = [
                'api_key' => $this->apiKey,
                'url' => $url,
            ];

            if ($renderJs) {
                $query['render_js'] = 'true';
            }

            if ($waitFor) {
                $query['wait_for'] = $waitFor;
            }

            if ($wait > 0) {
                $query['wait'] = $wait;
            }

            if ($blockResources) {
                $query['block_resources'] = 'true';
            }

            if ($premiumProxy) {
                $query['premium_proxy'] = 'true';
            }

            // タイムアウトはクエリパラメータではなく、Guzzleのtimeoutで設定
            // ただし、ScrapingBee API側にもtimeoutパラメータがある場合は追加可能

            $response = $this->client->get('', [
                'query' => $query,
            ]);

            $html = $response->getBody()->getContents();
            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $bytes = strlen($html);

            // 成功ログ（APIキーは含めない）
            Log::debug('SCRAPINGBEE_FETCH_SUCCESS', [
                'url' => $url,
                'bytes' => $bytes,
                'ms' => round($elapsedMs, 2),
                'render_js' => $renderJs,
                'wait_for' => $waitFor,
                'wait' => $wait,
            ]);

            return $html;
        } catch (RequestException $e) {
            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $errorClass = get_class($e);
            $errorMessage = $e->getMessage();

            // APIキーが含まれている可能性があるため、メッセージをサニタイズ
            $errorMessage = $this->sanitizeErrorMessage($errorMessage);

            // ログ出力（APIキーは絶対に出さない）
            Log::error('SCRAPINGBEE_FETCH_FAIL', [
                'url' => $url,
                'error_class' => $errorClass,
                'message' => $errorMessage,
                'ms' => round($elapsedMs, 2),
            ]);

            throw new \Exception("ScrapingBee fetch failed: {$errorMessage}", $e->getCode(), $e);
        } catch (\Exception $e) {
            $elapsedMs = (microtime(true) - $startTime) * 1000;
            $errorClass = get_class($e);
            $errorMessage = $this->sanitizeErrorMessage($e->getMessage());

            // ログ出力（APIキーは絶対に出さない）
            Log::error('SCRAPINGBEE_FETCH_FAIL', [
                'url' => $url,
                'error_class' => $errorClass,
                'message' => $errorMessage,
                'ms' => round($elapsedMs, 2),
            ]);

            throw new \Exception("ScrapingBee fetch failed: {$errorMessage}", $e->getCode(), $e);
        }
    }

    /**
     * エラーメッセージからAPIキーを除去
     * 
     * @param string $message
     * @return string
     */
    private function sanitizeErrorMessage(string $message): string
    {
        // APIキーが含まれている可能性がある文字列を除去
        if ($this->apiKey && strlen($this->apiKey) > 0) {
            $message = str_replace($this->apiKey, '[REDACTED]', $message);
        }

        // URLパラメータにapi_keyが含まれている可能性があるため、一般的なパターンも除去
        $message = preg_replace('/api_key=[^&\s]+/', 'api_key=[REDACTED]', $message);
        $message = preg_replace('/["\']?api_key["\']?\s*[:=]\s*["\']?[^"\'\s]+["\']?/', 'api_key=[REDACTED]', $message);

        return $message;
    }
}

