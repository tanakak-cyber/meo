<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpenAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class CompetitorAnalysisApiController extends Controller
{
    /**
     * 競合分析データを保存（セッション/キャッシュに保存してSTEP2に引き渡す）
     */
    public function store(Request $request)
    {
        Log::info('[CompetitorAnalysis] API reached (store)', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'payload' => $request->all(),
        ]);

        // 権限チェック
        $permissions = session('admin_permissions', []);
        if (empty($permissions) || !in_array('competitor-analysis', $permissions)) {
            Log::warning('[CompetitorAnalysis] permission denied (store)', [
                'permissions' => $permissions,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'この機能にアクセスする権限がありません。'
            ], 403);
        }

        $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'shops' => 'required|array|min:1',
            'shops.*.role' => 'required|string|in:competitor1,competitor2,own',
            'shops.*.shop_name' => 'required|string|max:255',
            'shops.*.opening_date' => 'nullable|string|in:ある,なし',
            'shops.*.address' => 'nullable|string|in:ある,なし',
            'shops.*.phone' => 'nullable|string|in:ある,なし',
            'shops.*.website' => 'nullable|string|in:ある,なし',
            'shops.*.sns_links' => 'nullable|string|in:ある,なし',
            'shops.*.website_nap_match' => 'nullable|string|in:している,していない',
            'shops.*.sns_nap_match' => 'nullable|string|in:している,していない',
            'shops.*.service_area' => 'nullable|string|max:255',
            'shops.*.business_owner_info' => 'nullable|string|in:ある,なし',
            'shops.*.main_category' => 'nullable|string|max:255',
            'shops.*.sub_category' => 'nullable|string|max:255',
            'shops.*.business_description' => 'nullable|string',
            'shops.*.review_count' => 'nullable|integer|min:0',
            'shops.*.review_rating' => 'nullable|string|max:255',
            'shops.*.review_frequency' => 'nullable|string|max:255',
            'shops.*.post_frequency' => 'nullable|string|max:255',
            'shops.*.photo_count' => 'nullable|integer|min:0',
            'shops.*.photo_atmosphere' => 'nullable|string|in:わかる,わからない',
            'shops.*.has_video' => 'nullable|string|in:ある,なし',
            'shops.*.video_count' => 'nullable|integer|min:0',
            'shops.*.review_story_type' => 'nullable|string|in:はい,いいえ',
            'shops.*.has_menu' => 'nullable|string|in:ある,なし',
            'shops.*.menu_genre' => 'nullable|string|in:ある,なし',
            'shops.*.menu_photo' => 'nullable|string|in:ある,なし',
            'shops.*.menu_description' => 'nullable|string',
            'shops.*.price_display' => 'nullable|string|in:ある,なし',
            'shops.*.reservation_link' => 'nullable|string|in:ある,なし',
            'shops.*.service_link' => 'nullable|string|in:ある,なし',
            'shops.*.business_hours' => 'nullable|string|in:ある,なし',
            'shops.*.last_order' => 'nullable|string|in:ある,ない',
            'shops.*.entry_period' => 'nullable|string|in:ある,なし',
            'shops.*.qa' => 'nullable|string|in:ある,なし',
            'shops.*.barrier_free' => 'nullable|string|in:ある,なし',
            'shops.*.plan' => 'nullable|string|in:ある,なし',
            'shops.*.pet' => 'nullable|string|in:ある,なし',
            'shops.*.child' => 'nullable|string|in:ある,なし',
            'shops.*.customer_segment' => 'nullable|string|in:ある,なし',
            'shops.*.payment_method' => 'nullable|string|in:ある,なし',
            'shops.*.feature' => 'nullable|string|in:ある,なし',
            'shops.*.meal' => 'nullable|string|in:ある,なし',
            'shops.*.parking' => 'nullable|string|in:ある,なし',
        ]);

        // 自社（own）の店舗名は必須
        $ownShop = collect($validated['shops'])->firstWhere('role', 'own');
        if (!$ownShop || empty($ownShop['shop_name']) || trim($ownShop['shop_name']) === '') {
            return response()->json([
                'success' => false,
                'message' => '自社の店舗名を入力してください。'
            ], 422);
        }

        // 空の店舗名を持つshopを除外
        $validated['shops'] = array_filter($validated['shops'], function($shop) {
            return !empty($shop['shop_name']) && trim($shop['shop_name']) !== '';
        });

        // セッションIDをキーとしてキャッシュに保存（30分間有効）
        $sessionId = session()->getId();
        $cacheKey = "competitor_analysis:{$sessionId}";
        
        Cache::put($cacheKey, $validated, now()->addMinutes(30));

        Log::info('[CompetitorAnalysis] data saved', [
            'session_id' => $sessionId,
            'keyword' => $validated['keyword'],
            'shops_count' => count($validated['shops']),
            'cache_key' => $cacheKey,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'データを保存しました。',
            'cache_key' => $cacheKey,
        ]);
    }

    /**
     * OpenAI APIを使用して競合分析を実行
     */
    public function run(Request $request)
    {
        Log::info('[CompetitorAnalysis] run payload received', [
            'payload' => $request->all(),
            'payload_size' => strlen(json_encode($request->all())),
        ]);

        // 権限チェック
        $permissions = session('admin_permissions', []);
        if (empty($permissions) || !in_array('competitor-analysis', $permissions)) {
            Log::warning('[CompetitorAnalysis] permission denied', [
                'permissions' => $permissions,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'この機能にアクセスする権限がありません。'
            ], 403);
        }

        try {
            Log::info('[CompetitorAnalysis] validation start');

            $validated = $request->validate([
            'keyword' => 'required|string|max:255',
            'shops' => 'required|array|min:1',
            'shops.*.role' => 'required|string|in:competitor1,competitor2,own',
            'shops.*.shop_name' => 'nullable|string|max:255',
            'shops.*.opening_date' => 'nullable|string|in:ある,なし',
            'shops.*.address' => 'nullable|string|in:ある,なし',
            'shops.*.phone' => 'nullable|string|in:ある,なし',
            'shops.*.website' => 'nullable|string|in:ある,なし',
            'shops.*.sns_links' => 'nullable|string|in:ある,なし',
            'shops.*.website_nap_match' => 'nullable|string|in:している,していない',
            'shops.*.sns_nap_match' => 'nullable|string|in:している,していない',
            'shops.*.service_area' => 'nullable|string|max:255',
            'shops.*.business_owner_info' => 'nullable|string|in:ある,なし',
            'shops.*.main_category' => 'nullable|string|max:255',
            'shops.*.sub_category' => 'nullable|string|max:255',
            'shops.*.business_description' => 'nullable|string',
            'shops.*.review_count' => 'nullable|integer|min:0',
            'shops.*.review_rating' => 'nullable|string|max:255',
            'shops.*.review_frequency' => 'nullable|string|max:255',
            'shops.*.post_frequency' => 'nullable|string|max:255',
            'shops.*.photo_count' => 'nullable|integer|min:0',
            'shops.*.photo_atmosphere' => 'nullable|string|in:わかる,わからない',
            'shops.*.has_video' => 'nullable|string|in:ある,なし',
            'shops.*.video_count' => 'nullable|integer|min:0',
            'shops.*.review_story_type' => 'nullable|string|in:はい,いいえ',
            'shops.*.has_menu' => 'nullable|string|in:ある,なし',
            'shops.*.menu_genre' => 'nullable|string|in:ある,なし',
            'shops.*.menu_photo' => 'nullable|string|in:ある,なし',
            'shops.*.menu_description' => 'nullable|string',
            'shops.*.price_display' => 'nullable|string|in:ある,なし',
            'shops.*.reservation_link' => 'nullable|string|in:ある,なし',
            'shops.*.service_link' => 'nullable|string|in:ある,なし',
            'shops.*.business_hours' => 'nullable|string|in:ある,なし',
            'shops.*.last_order' => 'nullable|string|in:ある,ない',
            'shops.*.entry_period' => 'nullable|string|in:ある,なし',
            'shops.*.qa' => 'nullable|string|in:ある,なし',
            'shops.*.barrier_free' => 'nullable|string|in:ある,なし',
            'shops.*.plan' => 'nullable|string|in:ある,なし',
            'shops.*.pet' => 'nullable|string|in:ある,なし',
            'shops.*.child' => 'nullable|string|in:ある,なし',
            'shops.*.customer_segment' => 'nullable|string|in:ある,なし',
            'shops.*.payment_method' => 'nullable|string|in:ある,なし',
            'shops.*.feature' => 'nullable|string|in:ある,なし',
            'shops.*.meal' => 'nullable|string|in:ある,なし',
            'shops.*.parking' => 'nullable|string|in:ある,なし',
            ]);

            Log::info('[CompetitorAnalysis] validation passed', [
                'validated' => $validated,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('[CompetitorAnalysis] validation failed', [
                'errors' => $e->errors(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[CompetitorAnalysis] validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        // 自社（own）の店舗名は必須
        $ownShop = collect($validated['shops'])->firstWhere('role', 'own');
        if (!$ownShop || empty($ownShop['shop_name']) || trim($ownShop['shop_name']) === '') {
            Log::error('[CompetitorAnalysis] own shop name missing');
            return response()->json([
                'status' => 'error',
                'message' => '自社の店舗名を入力してください。'
            ], 422);
        }

        // 空の店舗名を持つshopを除外
        $validated['shops'] = array_values(array_filter($validated['shops'], function($shop) {
            return !empty($shop['shop_name']) && trim($shop['shop_name']) !== '';
        }));

        Log::info('[CompetitorAnalysis] shops filtered', [
            'shops_count' => count($validated['shops']),
        ]);

        // 欠損値を正規化
        $normalized = $this->normalize($validated);

        try {
            $openAIService = new OpenAIService();
            $analysis = $openAIService->analyzeCompetitor($normalized);

            Log::info('[CompetitorAnalysis] AI execution success', [
                'keyword' => $validated['keyword'],
                'analysis_keys' => array_keys($analysis),
            ]);

            return response()->json([
                'status' => 'ok',
                'analysis' => $analysis,
            ]);

        } catch (\Throwable $e) {
            Log::error('[CompetitorAnalysis] fatal error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 'error',
                'error' => 'internal_error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 欠損値を正規化（空文字・nullを"__MISSING__"に変換）
     */
    private function normalize(array $data): array
    {
        foreach ($data['shops'] as &$shop) {
            foreach ($shop as $key => $value) {
                if ($value === '' || $value === null) {
                    $shop[$key] = '__MISSING__';
                }
                // number項目は0のまま（review_count, photo_count, video_countは0も有効な値）
                // radio項目は空文字・nullのみ"__MISSING__"に変換
            }
        }
        return $data;
    }
}

