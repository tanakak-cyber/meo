<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InstagramGraphService
{
    /**
     * Instagram Graph APIのベースURL
     */
    private const BASE_URL = 'https://graph.instagram.com';

    /**
     * 最近のメディアを取得（最大5件、IMAGEのみ整形）
     * 
     * @param Shop $shop
     * @return array メディア情報の配列（timestampはCarbon(Asia/Tokyo)）
     */
    public function getRecentMedia(Shop $shop): array
    {
        if (!$shop->instagram_user_id || !$shop->instagram_access_token) {
            Log::warning('INSTAGRAM_MEDIA_FETCH_MISSING_CREDENTIALS', [
                'shop_id' => $shop->id,
                'has_user_id' => !empty($shop->instagram_user_id),
                'has_access_token' => !empty($shop->instagram_access_token),
            ]);
            return [];
        }

        // トークンの有効期限チェック
        if ($shop->instagram_token_expires_at && Carbon::parse($shop->instagram_token_expires_at)->isPast()) {
            Log::error('IG_TOKEN_EXPIRED', [
                'shop_id' => $shop->id,
                'expires_at' => $shop->instagram_token_expires_at,
            ]);
            return [];
        }

        try {
            // Instagram Graph API: GET /{user-id}/media
            // limit=5 で軽量化、ページングなし
            $url = self::BASE_URL . "/{$shop->instagram_user_id}/media";
            $params = [
                'fields' => 'id,caption,media_type,media_url,permalink,timestamp',
                'limit' => 5,
                'access_token' => $shop->instagram_access_token,
            ];

            Log::info('INSTAGRAM_MEDIA_FETCH_REQUEST', [
                'shop_id' => $shop->id,
                'user_id' => $shop->instagram_user_id,
                'limit' => 5,
            ]);

            $response = Http::timeout(30)->get($url, $params);

            if (!$response->successful()) {
                Log::error('INSTAGRAM_MEDIA_FETCH_FAILED', [
                    'shop_id' => $shop->id,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();
            $mediaItems = $data['data'] ?? [];

            Log::info('INSTAGRAM_MEDIA_FETCH_SUCCESS', [
                'shop_id' => $shop->id,
                'media_count' => count($mediaItems),
            ]);

            // データ形式を統一（IMAGEのみ整形）
            $formattedMedia = [];
            foreach ($mediaItems as $item) {
                $mediaType = $item['media_type'] ?? '';
                
                // VIDEO / REELS は無視
                if (in_array($mediaType, ['VIDEO', 'REELS'])) {
                    continue;
                }

                // timestampをAsia/Tokyoに変換
                $timestamp = null;
                if (!empty($item['timestamp'])) {
                    try {
                        $timestamp = Carbon::parse($item['timestamp'], 'UTC')
                            ->setTimezone('Asia/Tokyo');
                    } catch (\Exception $e) {
                        Log::warning('INSTAGRAM_TIMESTAMP_PARSE_FAILED', [
                            'shop_id' => $shop->id,
                            'timestamp' => $item['timestamp'],
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }
                } else {
                    // timestampがない場合はスキップ
                    continue;
                }

                $caption = $this->removeHashtags($item['caption'] ?? '');
                // trim後空ならフォールバック文字列
                if (trim($caption) === '') {
                    $caption = 'Instagram投稿';
                }

                // IMAGE のみ対象
                if ($mediaType === 'IMAGE') {
                    $formattedMedia[] = [
                        'id' => $item['id'] ?? '',
                        'caption' => $caption,
                        'media_type' => $mediaType,
                        'media_url' => $item['media_url'] ?? '',
                        'permalink' => $item['permalink'] ?? '',
                        'timestamp' => $timestamp,
                    ];
                }
                // CAROUSEL_ALBUM の場合は先頭画像のみ使用
                elseif ($mediaType === 'CAROUSEL_ALBUM') {
                    // 先頭画像を取得するため、childrenエンドポイントを呼び出す
                    $firstImage = $this->getFirstImageFromCarousel($item['id'], $shop->instagram_access_token);
                    if ($firstImage) {
                        $formattedMedia[] = [
                            'id' => $item['id'] ?? '',
                            'caption' => $caption,
                            'media_type' => $mediaType,
                            'media_url' => $firstImage,
                            'permalink' => $item['permalink'] ?? '',
                            'timestamp' => $timestamp,
                        ];
                    }
                }
            }

            return $formattedMedia;
        } catch (\Exception $e) {
            Log::error('INSTAGRAM_MEDIA_FETCH_EXCEPTION', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * カルーセルアルバムから先頭画像を取得
     * 
     * @param string $mediaId メディアID
     * @param string $accessToken アクセストークン
     * @return string|null 先頭画像のURL、取得失敗時はnull
     */
    private function getFirstImageFromCarousel(string $mediaId, string $accessToken): ?string
    {
        try {
            $url = self::BASE_URL . "/{$mediaId}/children";
            $params = [
                'fields' => 'id,media_type,media_url',
                'access_token' => $accessToken,
            ];

            $response = Http::timeout(30)->get($url, $params);

            if (!$response->successful()) {
                Log::warning('INSTAGRAM_CAROUSEL_FETCH_FAILED', [
                    'media_id' => $mediaId,
                    'status_code' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();
            $children = $data['data'] ?? [];

            // 最初のIMAGEタイプを探す
            foreach ($children as $child) {
                if (($child['media_type'] ?? '') === 'IMAGE') {
                    return $child['media_url'] ?? null;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('INSTAGRAM_CAROUSEL_FETCH_EXCEPTION', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * ハッシュタグを削除（改行を壊さない設計）
     * 
     * @param string $text テキスト
     * @return string ハッシュタグを削除したテキスト
     */
    private function removeHashtags(string $text): string
    {
        // 正規表現: /#[^\s#]+/u
        // 改行を壊さないように処理
        $text = preg_replace('/#[^\s#]+/u', '', $text);
        
        // 不要な連続空白を整理（改行は保持）
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // 行頭・行末の空白を削除（改行は保持）
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $text = implode("\n", $lines);
        
        // 空行を1行にまとめる（連続改行を1つに）
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        return trim($text);
    }
}
