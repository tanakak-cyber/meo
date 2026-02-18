<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\OpenAIService;

class CompetitorAnalysisController extends Controller
{
    /**
     * 競合分析画面を表示（STEP1）
     */
    public function index()
    {
        // 権限チェック
        $permissions = session('admin_permissions', []);
        if (empty($permissions) || !in_array('competitor-analysis', $permissions)) {
            abort(403, 'この機能にアクセスする権限がありません。');
        }

        return view('meo.competitor-analysis');
    }

    /**
     * 分析結果画面を表示（STEP2）
     */
    public function step2(Request $request)
    {
        // 権限チェック
        $permissions = session('admin_permissions', []);
        if (empty($permissions) || !in_array('competitor-analysis', $permissions)) {
            abort(403, 'この機能にアクセスする権限がありません。');
        }

        // POSTリクエストから分析結果とキーワードを取得
        $analysisJson = $request->input('analysis');
        $keyword = $request->input('keyword');
        
        Log::info('[CompetitorAnalysis] step2 received', [
            'has_analysis' => !empty($analysisJson),
            'keyword_from_request' => $keyword,
            'keyword_from_session' => session('competitor_analysis_keyword'),
        ]);
        
        // キーワードがPOSTにない場合はセッションから取得
        if (!$keyword || trim($keyword) === '') {
            $keyword = session('competitor_analysis_keyword');
            if (!$keyword || trim($keyword) === '') {
                $keyword = '未設定';
            }
        } else {
            // セッションにも保存
            session(['competitor_analysis_keyword' => $keyword]);
        }
        
        if (!$analysisJson) {
            // セッションからも確認
            $analysis = session('competitor_analysis_result');
            if (!$analysis) {
                return redirect()->route('meo.competitor-analysis')
                    ->with('error', '分析結果が見つかりません。再度分析を実行してください。');
            }
        } else {
            $analysis = json_decode($analysisJson, true);
            if (!$analysis) {
                return redirect()->route('meo.competitor-analysis')
                    ->with('error', '分析結果の解析に失敗しました。再度分析を実行してください。');
            }
            // セッションにも保存
            session(['competitor_analysis_result' => $analysis]);
            if ($keyword) {
                session(['competitor_analysis_keyword' => $keyword]);
            }
        }

        return view('meo.competitor-analysis-step2', compact('analysis', 'keyword'));
    }

    /**
     * 競合分析データを保存（API用）
     */
    public function store(Request $request)
    {
        Log::info('[CompetitorAnalysis] STORE HIT', [
            'payload' => $request->all()
        ]);

        // DB保存はしない（仕様）
        // フロントに「保存完了」を返すだけ

        return response()->json([
            'status' => 'ok'
        ]);
    }

    /**
     * OpenAI APIを使用して競合分析を実行
     */
    public function run(Request $request)
    {
        Log::info('[CompetitorAnalysis] AUTH BYPASS - run reached');

        Log::info('[CompetitorAnalysis] REAL RUN HIT');

        // デバッグ：payloadをJSON_PRETTY_PRINTでログ
        Log::info('[CompetitorAnalysis] run payload received (pretty)', [
            'payload' => json_encode($request->all(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'payload_size' => strlen(json_encode($request->all())),
        ]);

        Log::info('[CompetitorAnalysis] run payload received', [
            'payload' => $request->all(),
            'payload_size' => strlen(json_encode($request->all())),
        ]);

        // 権限チェック（一時的にコメントアウト）
        // $permissions = session('admin_permissions', []);
        // if (empty($permissions) || !in_array('competitor-analysis', $permissions)) {
        //     Log::warning('[CompetitorAnalysis] permission denied', [
        //         'permissions' => $permissions,
        //     ]);
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'この機能にアクセスする権限がありません。'
        //     ], 403);
        // }

        try {
            Log::info('[CompetitorAnalysis] validation start');

            $validated = $request->validate([
                'keyword' => 'required|string|max:255',
                'industry_description' => 'required|string',
                'shops' => 'required|array|min:1',
                'shops.*.role' => 'required|string|in:competitor1,competitor2,own',
                'shops.*.shop_name' => 'nullable|string|max:255',
                'shops.*.own_rank' => 'nullable|integer|min:1',
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
                'shops.*.monthly_review_count' => 'nullable|integer|min:0',
                'shops.*.monthly_post_count' => 'nullable|integer|min:0',
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

        // 自社（own）のown_rankは必須
        if (!isset($ownShop['own_rank']) || $ownShop['own_rank'] === null || $ownShop['own_rank'] < 1) {
            Log::error('[CompetitorAnalysis] own_rank missing', [
                'own_rank' => $ownShop['own_rank'] ?? null,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => '自社順位を入力してください。'
            ], 422);
        }

        // 空の店舗名を持つshopを除外（ただし、ownは既に検証済みなので必ず残る）
        $validated['shops'] = array_values(array_filter($validated['shops'], function($shop) {
            return !empty($shop['shop_name']) && trim($shop['shop_name']) !== '';
        }));

        Log::info('[CompetitorAnalysis] shops filtered', [
            'shops_count' => count($validated['shops']),
        ]);

        // 【修正⑤】role判別の安全設計：competitor1/2が複数存在する場合は警告
        $competitor1Shops = collect($validated['shops'])->where('role', 'competitor1');
        $competitor1Count = $competitor1Shops->count();
        if ($competitor1Count > 1) {
            Log::warning('[CompetitorAnalysis] multiple competitor1 shops detected', [
                'count' => $competitor1Count,
                'shop_names' => $competitor1Shops->pluck('shop_name')->toArray(),
            ]);
        }
        
        $competitor2Shops = collect($validated['shops'])->where('role', 'competitor2');
        $competitor2Count = $competitor2Shops->count();
        if ($competitor2Count > 1) {
            Log::warning('[CompetitorAnalysis] multiple competitor2 shops detected', [
                'count' => $competitor2Count,
                'shop_names' => $competitor2Shops->pluck('shop_name')->toArray(),
            ]);
        }

        // 競合店舗数の判定（分析モードの決定）
        $ownShop = collect($validated['shops'])->firstWhere('role', 'own');
        $competitor1Shop = $competitor1Shops->first(); // 最初の1件のみ使用
        $competitor2Shop = $competitor2Shops->first(); // 最初の1件のみ使用
        
        $hasCompetitor1 = $competitor1Shop && !empty($competitor1Shop['shop_name']) && trim($competitor1Shop['shop_name']) !== '';
        $hasCompetitor2 = $competitor2Shop && !empty($competitor2Shop['shop_name']) && trim($competitor2Shop['shop_name']) !== '';
        
        // 分析モードの判定
        if ($hasCompetitor1 && $hasCompetitor2) {
            $analysisMode = 'pattern_a'; // 自社 vs 競合① vs 競合②
        } elseif ($hasCompetitor1) {
            $analysisMode = 'pattern_b'; // 自社 vs 競合①
        } else {
            $analysisMode = 'pattern_c'; // 自社単独分析
        }
        
        Log::info('[CompetitorAnalysis] analysis mode determined', [
            'mode' => $analysisMode,
            'has_competitor1' => $hasCompetitor1,
            'has_competitor2' => $hasCompetitor2,
        ]);
        
        // 分析モードをデータに追加
        $validated['analysis_mode'] = $analysisMode;

        // 【修正⑧】ログ出力：normalized payload、判別結果、月間数値を出力
        if ($ownShop) {
            Log::info('[CompetitorAnalysis] OWN shop data', [
                'shop_name' => $ownShop['shop_name'] ?? null,
                'own_rank' => $ownShop['own_rank'] ?? null,
                'review_count' => $ownShop['review_count'] ?? null,
                'monthly_review_count' => $ownShop['monthly_review_count'] ?? null,
                'monthly_post_count' => $ownShop['monthly_post_count'] ?? null,
                'photo_count' => $ownShop['photo_count'] ?? null,
                'video_count' => $ownShop['video_count'] ?? null,
                'has_menu' => $ownShop['has_menu'] ?? null,
                'has_video' => $ownShop['has_video'] ?? null,
            ]);
        }

        if ($competitor1Shop) {
            Log::info('[CompetitorAnalysis] COMPETITOR1 shop data', [
                'shop_name' => $competitor1Shop['shop_name'] ?? null,
                'review_count' => $competitor1Shop['review_count'] ?? null,
                'monthly_review_count' => $competitor1Shop['monthly_review_count'] ?? null,
                'monthly_post_count' => $competitor1Shop['monthly_post_count'] ?? null,
                'photo_count' => $competitor1Shop['photo_count'] ?? null,
                'video_count' => $competitor1Shop['video_count'] ?? null,
                'has_menu' => $competitor1Shop['has_menu'] ?? null,
                'has_video' => $competitor1Shop['has_video'] ?? null,
            ]);
        }

        if ($competitor2Shop) {
            Log::info('[CompetitorAnalysis] COMPETITOR2 shop data', [
                'shop_name' => $competitor2Shop['shop_name'] ?? null,
                'review_count' => $competitor2Shop['review_count'] ?? null,
                'monthly_review_count' => $competitor2Shop['monthly_review_count'] ?? null,
                'monthly_post_count' => $competitor2Shop['monthly_post_count'] ?? null,
                'photo_count' => $competitor2Shop['photo_count'] ?? null,
                'video_count' => $competitor2Shop['video_count'] ?? null,
                'has_menu' => $competitor2Shop['has_menu'] ?? null,
                'has_video' => $competitor2Shop['has_video'] ?? null,
            ]);
        }

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
     * 【修正④】normalize()の責務整理：数値項目を必ずint型にキャスト
     */
    private function normalize(array $data): array
    {
        // 数値項目のリスト（必ずint型にキャスト）
        $numericFields = [
            'review_count',
            'photo_count',
            'video_count',
            'monthly_review_count',
            'monthly_post_count',
        ];
        
        foreach ($data['shops'] as &$shop) {
            // own_rankは数値として保持（own以外には存在しない/削除）
            if (isset($shop['own_rank'])) {
                if ($shop['role'] === 'own') {
                    $shop['own_rank'] = isset($shop['own_rank']) ? (int)$shop['own_rank'] : null;
                } else {
                    // competitor1/2にown_rankが混入していたら削除
                    unset($shop['own_rank']);
                }
            }

            foreach ($shop as $key => $value) {
                // own_rankは既に処理済みなのでスキップ
                if ($key === 'own_rank') {
                    continue;
                }
                
                // 【修正④】数値項目は必ずint型にキャスト
                if (in_array($key, $numericFields)) {
                    if ($value === '' || $value === null) {
                        $shop[$key] = '__MISSING__';
                    } else {
                        // 文字列型の数値も含めて int に変換
                        $shop[$key] = (int)$value;
                    }
                    continue;
                }
                
                // その他の項目は既存ロジック
                if ($value === '' || $value === null) {
                    $shop[$key] = '__MISSING__';
                }
            }
        }
        return $data;
    }
}

