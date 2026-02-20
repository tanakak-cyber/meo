<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\MeoKeyword;
use App\Models\MeoRankLog;
use App\Models\Review;
use App\Models\Photo;
use App\Models\GbpPost;
use App\Models\GbpSnapshot;
use App\Models\SyncBatch;
use App\Services\GoogleBusinessProfileService;
use App\Services\GbpInsightsService;
use App\Services\ReviewSyncService;
use App\Jobs\SyncShopDataJob;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Bus;
use Mpdf\Mpdf;

class ReportController extends Controller
{
    public function index(Request $request)
    {
\Log::info('REPORT_INDEX_AUTH_CHECK', [
    'auth_user' => \Illuminate\Support\Facades\Auth::user(),
    'auth_id' => \Illuminate\Support\Facades\Auth::id(),
    'session_operator_id' => session('operator_id'),
    'session_id' => session()->getId(),
]);
        // オペレーターの場合は担当店舗のみをフィルタ（operator_shopsテーブルを使用）
        $user = Auth::user();
        $operatorId = session('operator_id');
        $status = null;
        
        


if (!$user || !$user->is_admin) {
            // オペレーターの場合
            if ($user && !$user->is_admin && $user->operator_id) {
                $operatorId = $user->operator_id;
            }
            if ($operatorId) {
                // operator_shopsテーブルから担当店舗IDを取得
                $assignedShopIds = \App\Models\OperatorShop::where('operator_id', $operatorId)
                    ->pluck('shop_id')
                    ->toArray();
                
                if (!empty($assignedShopIds)) {
                    $query = Shop::with('meoKeywords.rankLogs')
                        ->whereIn('id', $assignedShopIds);
                } else {
                    // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
                    $query = Shop::with('meoKeywords.rankLogs')
                        ->where('operation_person_id', $operatorId);
                }
            } else {
                $query = Shop::with('meoKeywords.rankLogs');
            }
        } else {
            // 店舗ステータスで絞り込み（セッションから取得、なければデフォルトは'active'）
            $status = $request->get('status');
            if ($status) {
                // リクエストで指定された場合はセッションに保存
                session(['shop_status_filter' => $status]);
            } else {
                // セッションから取得、なければデフォルトは'active'（契約中店舗のみ）
                $status = session('shop_status_filter', 'active');
            }
            
            $today = Carbon::today();
            
            $query = Shop::with('meoKeywords.rankLogs');
            
            // 顧客閲覧範囲によるフィルタリング
            if ($user && $user->is_admin) {
                if ($user->customer_scope === 'own') {
                    $query->where('created_by', $user->id);
                }
                // 'all' の場合はフィルタリングなし
            } else {
                // 一般ユーザー（非管理者）の場合は自分の顧客のみ
                if ($user) {
                    $query->where('created_by', $user->id);
                }
            }
            
            if ($status === 'active') {
                // 契約中店舗のみ
                $query->where(function ($q) use ($today) {
                    $q->whereNull('contract_end_date')
                      ->orWhere('contract_end_date', '>=', $today);
                });
            } elseif ($status === 'expired') {
                // 契約終了店舗のみ
                $query->whereNotNull('contract_end_date')
                      ->where('contract_end_date', '<', $today);
            }
            // 'all' の場合はフィルタリングなし
        }
        
        // 平均順位の良い順にソート
        $shops = $query->get()
            ->map(function ($shop) {
                $ranks = $shop->meoKeywords
                    ->flatMap(function ($keyword) {
                        return $keyword->rankLogs()->whereNotNull('position')->pluck('position');
                    });
                
                $shop->average_rank = $ranks->isEmpty() ? null : $ranks->average();
                return $shop;
            })
            ->sortBy(function ($shop) {
                return $shop->average_rank ?? 999; // nullは最後に
            })
            ->values();
        
        // 同期対象の店舗を取得（GBP連携済みの契約中店舗のみ）
        $today = Carbon::today();
        $shopsForSyncQuery = Shop::whereNotNull('gbp_location_id')
            ->where(function ($q) use ($today) {
                $q->whereNull('contract_end_date')
                  ->orWhere('contract_end_date', '>=', $today);
            });
        
        // 顧客閲覧範囲によるフィルタリング
        if ($user && $user->is_admin) {
            if ($user->customer_scope === 'own') {
                $shopsForSyncQuery->where('created_by', $user->id);
            }
            // 'all' の場合はフィルタリングなし
        } else {
            // 一般ユーザー（非管理者）の場合は自分の顧客のみ
            if ($user) {
                $shopsForSyncQuery->where('created_by', $user->id);
            }
        }
        
        // オペレーターの場合は担当店舗のみをフィルタ（operator_shopsテーブルを使用、なければoperation_person_idでフォールバック）
        if ($operatorId) {
            $assignedShopIds = \App\Models\OperatorShop::where('operator_id', $operatorId)
                ->pluck('shop_id')
                ->toArray();
            
            if (!empty($assignedShopIds)) {
                $shopsForSyncQuery->whereIn('id', $assignedShopIds);
            } else {
                // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
                $shopsForSyncQuery->where('operation_person_id', $operatorId);
            }
        }
        
        $shopsForSync = $shopsForSyncQuery->get();
        
        // 絞り込み用のマスタデータを取得
        $operationPersons = \App\Models\OperationPerson::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        
        // セッションから同期日付を取得（次回まで保持）
        $syncStartDate = session('sync_start_date');
        $syncEndDate = session('sync_end_date');
        
        return view('reports.index', compact('shops', 'status', 'shopsForSync', 'operationPersons', 'syncStartDate', 'syncEndDate'));
    }

    public function show(Request $request, $shopId)
    {
        Log::info('ReportController::show 開始', [
            'shop_id' => $shopId,
            'from' => $request->get('from'),
            'to' => $request->get('to'),
        ]);

        $shop = Shop::findOrFail($shopId);
        
        // 顧客閲覧範囲による権限チェック（UI設定と統一）
$user = Auth::user();

if ($user) {
    // 「自分の顧客のみ」のときだけ制限
    if ($user->customer_scope === 'own') {
        if ($shop->created_by !== $user->id) {
            abort(403, 'この店舗を閲覧する権限がありません。');
        }
    }
    // customer_scope = all の場合は全店舗閲覧可能（重要）
}
        
        $googleService = new GoogleBusinessProfileService();
        
        // オペレーターの場合は自分の担当店舗のみアクセス可能（operator_shopsテーブルを使用、なければoperation_person_idでフォールバック）
        $operatorId = session('operator_id');
        if (!$user || !$user->is_admin) {
            // オペレーターの場合
            if ($user && !$user->is_admin && $user->operator_id) {
                $operatorId = $user->operator_id;
            }
            if ($operatorId) {
                $isAssigned = \App\Models\OperatorShop::where('operator_id', $operatorId)
                    ->where('shop_id', $shop->id)
                    ->exists();
                
                // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
                if (!$isAssigned) {
                    if ($shop->operation_person_id != $operatorId) {
                        abort(403, 'この店舗のレポートにアクセスする権限がありません。');
                    }
                }
            }
        }
        
        // 期間の取得（セッションから取得、なければデフォルトは前月1か月分）
        // リクエストパラメータがある場合はセッションに保存
        $requestFrom = $request->get('from');
        $requestTo = $request->get('to');
        
        if ($requestFrom !== null) {
            session(['report_period_from' => $requestFrom]);
        }
        if ($requestTo !== null) {
            session(['report_period_to' => $requestTo]);
        }
        
        // デフォルト期間：当月1日～当月末
$defaultFrom = Carbon::now('Asia/Tokyo')->startOfMonth()->format('Y-m-d');
$defaultTo = Carbon::now('Asia/Tokyo')->endOfMonth()->format('Y-m-d');

        
        $from = $requestFrom ?? session('report_period_from', $defaultFrom);
        $to = $requestTo ?? session('report_period_to', $defaultTo);
        
        $fromDate = Carbon::parse($from, 'Asia/Tokyo')->startOfDay();
        $toDate = Carbon::parse($to, 'Asia/Tokyo')->endOfDay();
        
        // キーワードを平均順位の良い順に取得
        // SQLiteの日付比較のため、文字列形式で比較
        $fromStr = $fromDate->format('Y-m-d');
        $toStr = $toDate->format('Y-m-d');
        
        $keywords = $shop->meoKeywords()
            ->with(['rankLogs' => function ($query) use ($fromStr, $toStr) {
                // SQLiteでは文字列比較を使用
                $query->where('checked_at', '>=', $fromStr)
                      ->where('checked_at', '<=', $toStr)
                      ->orderBy('checked_at');
            }])
            ->get()
            ->map(function ($keyword) use ($fromDate, $toDate) {
                // 既にロードされたrankLogsをフィルタリング
                $ranks = $keyword->rankLogs
                    ->filter(function ($log) use ($fromDate, $toDate) {
                        $logDate = $log->checked_at instanceof \Carbon\Carbon 
                            ? $log->checked_at 
                            : \Carbon\Carbon::parse($log->checked_at);
                        return $logDate->gte($fromDate) && $logDate->lte($toDate) && !is_null($log->position);
                    })
                    ->pluck('position');
                $keyword->average_rank = $ranks->isEmpty() ? null : $ranks->average();
                return $keyword;
            })
            ->sortBy(function ($keyword) {
                return $keyword->average_rank ?? 999;
            })
            ->values();

        // 日付範囲の生成
        $dates = [];
        $currentDate = $fromDate->copy();
        while ($currentDate <= $toDate) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->addDay();
        }

        // キーワードごとの日別順位データ
        $rankData = [];
        foreach ($keywords as $keyword) {
            $keywordRanks = [];
            foreach ($dates as $date) {
                $log = $keyword->rankLogs->first(function ($log) use ($date) {
                    return $log->checked_at->format('Y-m-d') === $date;
                });
                $keywordRanks[$date] = $log ? $log->position : null;
            }
            $rankData[$keyword->id] = [
                'keyword' => $keyword->keyword,
                'ranks' => $keywordRanks,
            ];
        }

        // KPIサマリーの計算（DBから直接取得）
        // 指定期間のデータ
        // JSTの日付範囲をUTCに変換
        $currentPeriodStart = $fromDate->copy()->utc();
        $currentPeriodEnd = $toDate->copy()->utc();
        
        // 前月同期間のデータ
        $prevPeriodStart = $currentPeriodStart->copy()->subMonth();
        $prevPeriodEnd = $currentPeriodEnd->copy()->subMonth();
        
        // 口コミ数：該当期間で投稿された口コミ数（ユニーク：gbp_review_idで重複排除）
        // create_time はUTCで保存されているので、UTCで比較
        $currentReviewIds = DB::table('reviews')
            ->where('shop_id', $shop->id)
            ->whereBetween('create_time', [$currentPeriodStart->format('Y-m-d H:i:s'), $currentPeriodEnd->format('Y-m-d H:i:s')])
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('gbp_review_id')
            ->pluck('id');
        
        $currentReviewCount = count($currentReviewIds);
        
        $prevReviewIds = DB::table('reviews')
            ->where('shop_id', $shop->id)
            ->whereBetween('create_time', [$prevPeriodStart->format('Y-m-d H:i:s'), $prevPeriodEnd->format('Y-m-d H:i:s')])
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('gbp_review_id')
                ->pluck('id');
            
            $prevReviewCount = count($prevReviewIds);
            
            $reviewMoM = $prevReviewCount > 0 
                ? round((($currentReviewCount - $prevReviewCount) / $prevReviewCount) * 100, 1)
                : ($currentReviewCount > 0 ? 100 : 0);

            // 評価点数：該当期間で投稿された口コミの評価の平均値（ユニーク）
            $currentRating = Review::whereIn('id', $currentReviewIds)
                ->avg('rating');
            $prevRating = Review::whereIn('id', $prevReviewIds)
                ->avg('rating');
            $ratingMoM = $prevRating > 0 
                ? round((($currentRating - $prevRating) / $prevRating) * 100, 1)
                : ($currentRating > 0 ? 100 : 0);

            // 返信率：該当期間で投稿された口コミに対して返信した口コミの割合（ユニーク）
            $repliedReviews = Review::whereIn('id', $currentReviewIds)
                ->whereNotNull('reply_text')
                ->count();
            $replyRate = $currentReviewCount > 0 
                ? round(($repliedReviews / $currentReviewCount) * 100, 1)
                : 0;

        // 有効投稿数（Google評価対象）：Service経由で集計
        $currentPostCount = $googleService->countPostsByPeriod($shop, $from, $to);
        
        // 前月同期間の有効投稿数は、前月時点での投稿数を取得（簡易的に現在の値を使用）
        // 注: Step 1では一旦現在の投稿数を使用（Step 2以降で履歴管理を実装）
        $prevPostCount = $currentPostCount;
            
            $postCount = $currentPostCount;
        $postMoM = 0; // Step 1では一旦0とする
            
            // 写真数：該当期間でGBPに投稿された数（ユニーク：gbp_media_idで重複排除）
            $currentPhotoIds = DB::table('photos')
                ->where('shop_id', $shop->id)
                ->whereBetween('create_time', [$currentPeriodStart->format('Y-m-d H:i:s'), $currentPeriodEnd->format('Y-m-d H:i:s')])
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('gbp_media_id')
                ->pluck('id');
            
            $currentPhotoCount = count($currentPhotoIds);
            
            $prevPhotoIds = DB::table('photos')
                ->where('shop_id', $shop->id)
                ->whereBetween('create_time', [$prevPeriodStart->format('Y-m-d H:i:s'), $prevPeriodEnd->format('Y-m-d H:i:s')])
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('gbp_media_id')
                ->pluck('id');
            
            $prevPhotoCount = count($prevPhotoIds);
            
            $photoMoM = $prevPhotoCount > 0 
                ? round((($currentPhotoCount - $prevPhotoCount) / $prevPhotoCount) * 100, 1)
                : ($currentPhotoCount > 0 ? 100 : 0);
        
        // KPI取得元をログに記録
        Log::info('REPORT_KPI_SOURCE', [
            'source' => 'db',
            'shop_id' => $shop->id,
            'current_review_count' => $currentReviewCount,
            'prev_review_count' => $prevReviewCount,
            'current_rating' => $currentRating,
            'prev_rating' => $prevRating,
            'reply_rate' => $replyRate,
            'current_photo_count' => $currentPhotoCount,
            'prev_photo_count' => $prevPhotoCount,
            'current_post_count' => $currentPostCount,
        ]);

        // GBP Insights データを取得（API経由、DB保存）
        $insightsService = new GbpInsightsService();
        $insightsData = null;
        $insightsMetrics = [];
        $insightsKeywords = [];
        
        Log::info('GBP Insights 条件チェック', [
            'shop_id' => $shop->id,
            'has_gbp_location_id' => !empty($shop->gbp_location_id),
            'gbp_location_id' => $shop->gbp_location_id,
            'has_gbp_refresh_token' => !empty($shop->gbp_refresh_token),
        ]);
        
        if ($shop->gbp_location_id && $shop->gbp_refresh_token) {
            try {
                Log::info('GBP Insights処理開始', [
                    'shop_id' => $shop->id,
                    'from' => $from,
                    'to' => $to,
                ]);
                
                // デバッグ用: 直接クエリを発行してデータの存在を確認
                $debugQuery = \App\Models\GbpInsight::where('shop_id', $shop->id)
                    ->whereBetween('from_date', [$from, $to])
                    ->where('period_type', 'daily')
                    ->whereNotNull('impressions');
                
                Log::info('GBP Insights デバッグ: 直接クエリ', [
                    'shop_id' => $shop->id,
                    'from' => $from,
                    'to' => $to,
                    'sql' => $debugQuery->toSql(),
                    'bindings' => $debugQuery->getBindings(),
                    'count' => $debugQuery->count(),
                    'records' => $debugQuery->get()->map(function($record) {
                        return [
                            'id' => $record->id,
                            'from_date' => $record->from_date?->format('Y-m-d'),
                            'to_date' => $record->to_date?->format('Y-m-d'),
                            'period_type' => $record->period_type,
                            'impressions' => $record->impressions,
                        ];
                    }),
                ]);
                
                // まずDBから取得を試みる
                $insightsData = $insightsService->getInsightsFromDb($shop, $from, $to);
                
                Log::info('GBP Insights DB取得結果', [
                    'shop_id' => $shop->id,
                    'has_data' => !empty($insightsData),
                    'insights_data' => $insightsData,
                ]);
                
                // 注意: Performance API による取得ロジックは削除されました。
                // CSVデータを受け入れるための新設計に移行予定です。
                // DBにない場合は null を返す
                if (!$insightsData) {
                    Log::info('GBP Insights DB取得: データなし（API取得は削除済み）', [
                        'shop_id' => $shop->id,
                    ]);
                }
                
                if ($insightsData) {
                    Log::info('GBP Insights formatMetrics開始', [
                        'shop_id' => $shop->id,
                    ]);
                    $insightsMetrics = $insightsService->formatMetrics($insightsData);
                    $insightsKeywords = $insightsService->formatKeywords($insightsData);
                    
                    // 日別データを取得（グラフ・テーブル表示用）
                    $dailyInsights = $insightsService->getDailyInsights($shop, $from, $to);
                    $insightsMetrics['daily'] = $dailyInsights['daily'];
                    $insightsMetrics['daily_total'] = $dailyInsights['total'];
                    
                    Log::info('GBP Insights formatMetrics完了', [
                        'shop_id' => $shop->id,
                        'metrics' => $insightsMetrics,
                    ]);
                } else {
                    Log::warning('GBP Insights データが取得できませんでした', [
                        'shop_id' => $shop->id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('GBP Insights取得エラー', [
                    'shop_id' => $shop->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } else {
            Log::info('GBP Insights スキップ', [
                'shop_id' => $shop->id,
                'has_location_id' => !empty($shop->gbp_location_id),
                'has_refresh_token' => !empty($shop->gbp_refresh_token),
            ]);
        }

        // 絞り込み用のマスタデータを取得
        $operationPersons = \App\Models\OperationPerson::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        
        // セッションから同期日付を取得（次回まで保持）
        $syncStartDate = session('sync_start_date');
        $syncEndDate = session('sync_end_date');

        // GBP グラフデータを生成（完全に独立した変数名）
        $gbp_impressions_final_clean = [];
        if (!empty($insightsMetrics) && isset($insightsMetrics['daily'])) {
            foreach ($dates as $date) {
                $dateKey = is_string($date) ? $date : $date->format('Y-m-d');
                // daily配列から直接取得（なければ0）
                $gbp_impressions_final_clean[] = $insightsMetrics['daily'][$dateKey]['impressions'] ?? 0;
            }
        }

        return view('reports.show', compact(
            'shop',
            'keywords',
            'dates',
            'rankData',
            'from',
            'to',
            'currentReviewCount',
            'reviewMoM',
            'currentRating',
            'ratingMoM',
            'replyRate',
            'postCount',
            'currentPhotoCount',
            'photoMoM',
            'insightsMetrics',
            'gbp_impressions_final_clean',
            'insightsKeywords',
            'operationPersons',
            'syncStartDate',
            'syncEndDate'
        ));
    }

    public function syncAll(Request $request)
    {
        // ★緊急修正：オペレーター最優先判定（ログアウト防止）
        $sessionOperatorId = session('operator_id');
        $user = \Illuminate\Support\Facades\Auth::user();

        // オペレーターでログインしている場合はAuth::user()がnullでも正常扱い
        if ($sessionOperatorId) {
            $operatorId = (int) $sessionOperatorId;
        } else {
            $operatorId = $operatorId ?? session('operator_id');
        }

   // ★追加（これが本命）
    $isOperator = !empty($operatorId);

        // 完全未ログインのみログイン画面へ
        if (!$operatorId && !$user) {
            return redirect('/operator/login');
        }
        // ★ここまで追加（既存コードは一切削除しない）
        $shopId = $request->input('shop_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $sinceDate = $request->input('since_date') ?? now()->subMonths(2)->format('Y-m-d');
        $today = Carbon::today();
        
        // 日付が指定された場合はセッションに保存（次回まで保持）
        if ($startDate !== null) {
            session(['sync_start_date' => $startDate]);
        }
        if ($endDate !== null) {
            session(['sync_end_date' => $endDate]);
        }
        
        // 期間指定のバリデーション（JSTの日付をUTCに変換）
        $startDateCarbon = $startDate ? Carbon::parse($startDate, 'Asia/Tokyo')->startOfDay()->utc() : null;
        $endDateCarbon = $endDate ? Carbon::parse($endDate, 'Asia/Tokyo')->endOfDay()->utc() : null;
        
        if ($startDateCarbon && $endDateCarbon && $startDateCarbon->gt($endDateCarbon)) {
            $routeName = session('operator_id') ? 'operator.reports.index' : 'reports.index';
            return back()->with('error', '開始日が終了日より後になっています。');
        }
        
        // オペレーション担当で絞り込み
        $operationPersonId = $request->input('operation_person_id');
        
// operator_id を取得（customer_scope対応）
$user = Auth::user();

if ($isOperator) {
    $user = null; // オペレーター時はAuthロジックを無効化
}

// customer_scopeを安全に正規化（nullは'all'扱い）
$customerScope = $user ? strtolower(trim($user->customer_scope ?? 'own')) : 'operator';
$isAdmin = (bool) ($user->is_admin ?? false);

// customer_scope='all' は管理者扱い
$shouldTreatAsAdmin = $isAdmin;

$operatorId = $operatorId ?? session('operator_id');

if (!$shouldTreatAsAdmin) {
    // オペレーターのみ operator_id を使う
    if ($user && $user->operator_id) {
        $operatorId = $user->operator_id;
    } elseif (session('operator_id')) {
        $operatorId = session('operator_id');
    }
}

// デバッグ（これが出ればcontroller到達してる）
\Log::info('REPORT_SYNCALL_SCOPE', [
    'user_id' => $user->id ?? null,
    'is_admin' => $isAdmin,
    'customer_scope' => $customerScope,
    'should_treat_as_admin' => $shouldTreatAsAdmin,
    'operator_id_used' => $operatorId,
]);

        
        if ($shopId === 'all') {
            // 全店舗同期の場合、契約中の店舗のみを取得
            $shopQuery = Shop::whereNotNull('gbp_location_id')
                ->where(function ($query) use ($today) {
                    $query->whereNull('contract_end_date')
                          ->orWhere('contract_end_date', '>=', $today);
                });

// ★最優先：オペレーターの場合は担当店舗のみに強制制限（超重要）
if (!empty($operatorId)) {
    $shopQuery->where(function ($q) use ($operatorId) {
        $q->where('operation_person_id', $operatorId)
          ->orWhereIn('id', \App\Models\OperatorShop::where('operator_id', $operatorId)->pluck('shop_id'));
    });
}            

// 顧客閲覧範囲によるフィルタリング（customer_scope優先）
if ($user) {
    $customerScope = $user ? strtolower(trim($user->customer_scope ?? 'own')) : 'operator';
    if ($customerScope === 'own') {
        $shopQuery->where('created_by', $user->id);
    }
    // 'all' の場合はフィルタリングなし
}

            
            // オペレーターがログインしている場合は、自分の担当店舗のみ
            if ($operatorId) {
                $assignedShopIds = \App\Models\OperatorShop::where('operator_id', $operatorId)
                    ->pluck('shop_id')
                    ->toArray();
                
                if (!empty($assignedShopIds)) {
                    $shopQuery->whereIn('id', $assignedShopIds);
                } else {
                    $shopQuery->whereRaw('1 = 0');
                }
            }
            
            // オペレーション担当で絞り込み
            if ($operationPersonId) {
                $shopQuery->where('operation_person_id', $operationPersonId);
            }
            
            $shops = $shopQuery->get();
        } else {
            // 個別店舗同期
        $shop = Shop::findOrFail($shopId);
        
        // 顧客閲覧範囲による権限チェック
        $user = Auth::user();
        if ($user && $user->is_admin) {
            if ($user->customer_scope === 'own' && $shop->created_by !== $user->id) {
                abort(403, 'この店舗を閲覧する権限がありません。');
            }
        } elseif ($user && !$user->is_admin) {
            if ($shop->created_by !== $user->id) {
                abort(403, 'この店舗を閲覧する権限がありません。');
            }
        }
        
        // オペレーターの場合は自分の担当店舗のみアクセス可能
        if ($operatorId) {
                $isAssigned = \App\Models\OperatorShop::where('operator_id', $operatorId)
                    ->where('shop_id', $shop->id)
                    ->exists();
                
                if (!$isAssigned) {
                    $routeName = $operatorId ? 'operator.reports.index' : 'reports.index';
                    return back()->with('error', 'この店舗を同期する権限がありません。');
                }
            }
            
            $shops = collect([$shop]);
        }
        
        // 店舗IDの配列を取得
        $shopIds = $shops->pluck('id')->toArray();
        $shopCount = count($shopIds);

        // 1店舗の場合は直列処理、複数店舗の場合は並列処理
        if ($shopCount <= 1) {
            // ✅ 1店舗は直列処理
            $googleService = new GoogleBusinessProfileService();
            $totalReviewsChanged = 0;
            $totalPostsSynced = 0;
            $totalPhotosInserted = 0;
            $totalPhotosUpdated = 0;
            $errors = [];
            $shopResults = []; // 店舗ごとの結果を保存
            
            foreach ($shops as $shop) {
            try {
                // 店舗が契約中か確認
                if (!$shop->isContractActive()) {
                    $errors[] = "{$shop->name}: 契約が終了している店舗の同期はできません。";
                    continue;
                }
                
                // Google連携が完了しているか確認
                if (!$shop->gbp_location_id || !$shop->gbp_refresh_token) {
                    $errors[] = "{$shop->name}: Google連携が完了していません。";
                    continue;
                }
                
                // アクセストークンを取得
                $accessToken = $googleService->getAccessToken($shop);
                
                if (!$accessToken) {
                    $errors[] = "{$shop->name}: アクセストークンの取得に失敗しました。";
                    continue;
                }
                
                // スナップショットを作成
                $currentUserId = \App\Helpers\AuthHelper::getCurrentUserId();
                $snapshot = GbpSnapshot::create([
                    'shop_id' => $shop->id,
                    'user_id' => $currentUserId,
                    'synced_by_operator_id' => $operatorId,
                    'synced_at' => now(),
                    'sync_params' => [
                        'start_date' => $startDateCarbon?->format('Y-m-d'),
                        'end_date' => $endDateCarbon?->format('Y-m-d'),
                    ],
                ]);
                
                // 口コミを同期
                Log::info('SYNC_REVIEWS__CALLSITE', [
                    'method' => __FUNCTION__,
                    'line' => __LINE__,
                    'shop_id' => $shop->id,
                ]);
                $reviewsChanged = $this->syncReviews($shop, $accessToken, $googleService, $snapshot->id, $sinceDate);
                
                // 写真を同期（完全差分同期）
                $photoResult = $this->syncPhotos($shop, $accessToken, $googleService, $snapshot->id, $sinceDate);
                
                // 投稿を同期（Service経由で保存）
                $postResult = $googleService->syncLocalPostsAndSave($shop, $sinceDate);
                $postsCount = $postResult['inserted'] + $postResult['updated'];
                
                // スナップショットの数を更新（写真は追加+更新の合計）
                $snapshot->update([
                    'photos_count' => $photoResult['inserted'] + $photoResult['updated'],
                    'reviews_count' => $reviewsChanged,
                    'posts_count' => $postsCount,
                ]);
                
                $totalReviewsChanged += $reviewsChanged;
                $totalPhotosInserted += $photoResult['inserted'];
                $totalPhotosUpdated += $photoResult['updated'];
                $totalPostsSynced += $postsCount;
                
                // 店舗ごとの結果を保存
                $shopResults[] = [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'reviews_changed' => $reviewsChanged,
                    'photos_inserted' => $photoResult['inserted'],
                    'photos_updated' => $photoResult['updated'],
                    'posts_synced' => $postsCount,
                ];
                
                Log::info('口コミ・写真・投稿同期完了', [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'operator_id' => $operatorId,
                    'reviews_changed' => $reviewsChanged,
                    'photos_inserted' => $photoResult['inserted'],
                    'photos_updated' => $photoResult['updated'],
                    'posts_synced' => $postsCount,
                ]);
                
            } catch (\Exception $e) {
                $errors[] = "{$shop->name}: " . $e->getMessage();
                Log::error('口コミ・写真の同期エラー', [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'operator_id' => $operatorId,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            }
        } else {
            // ✅ 複数店舗は並列処理（最大3並列）
            // 同期バッチレコードを作成
            $syncBatch = SyncBatch::create([
                'type' => 'all', // 口コミ・写真・投稿すべて
                'total_shops' => $shopCount,
                'status' => 'running',
                'started_at' => now(),
            ]);

            // すべてのジョブを1つのバッチにまとめて並列実行
            $jobs = [];
            foreach ($shopIds as $shopId) {
                $jobs[] = new SyncShopDataJob(
                    $shopId,
                    $sinceDate,
                    $operatorId,
                    $startDateCarbon?->format('Y-m-d'),
                    $endDateCarbon?->format('Y-m-d'),
                    $syncBatch->id
                );
            }
            
            // 1つのバッチとしてディスパッチ（Laravelが内部的に並列実行を制御）
            Bus::batch($jobs)->dispatch();

            $routeName = $operatorId ? 'operator.reports.index' : 'reports.index';
            return redirect()->route($routeName)
                ->with('success', "{$shopCount}店舗の同期処理を開始しました。バックグラウンドで実行されます。")
                ->with('sync_batch_id', $syncBatch->id);
        }
        
        $periodInfo = '';
        if ($startDateCarbon || $endDateCarbon) {
            $startStr = $startDateCarbon ? $startDateCarbon->format('Y-m-d') : '全期間';
            $endStr = $endDateCarbon ? $endDateCarbon->format('Y-m-d') : '全期間';
            $periodInfo = "（期間: {$startStr} ～ {$endStr}）";
        }
        
        $routeName = $operatorId ? 'operator.reports.index' : 'reports.index';
        
        // 口コミの変更件数に基づいてメッセージを生成
        if ($totalReviewsChanged > 0) {
            $reviewsMessage = "口コミ {$totalReviewsChanged}件を更新しました";
        } else {
            $reviewsMessage = "口コミの変更はありませんでした";
        }
        
        // 写真のメッセージを構築
        $photoMessages = [];
        if ($totalPhotosInserted > 0) {
            $photoMessages[] = "写真 {$totalPhotosInserted}件を追加しました";
        }
        if ($totalPhotosUpdated > 0) {
            $photoMessages[] = "写真 {$totalPhotosUpdated}件を更新しました";
        }
        if (empty($photoMessages)) {
            $photoMessages[] = "写真の変更はありませんでした";
        }
        
        // 投稿のメッセージを構築
        $postMessages = [];
        if ($totalPostsSynced > 0) {
            $postMessages[] = "投稿 {$totalPostsSynced}件を同期しました";
        } else {
            $postMessages[] = "投稿の変更はありませんでした";
        }
        
        // 詳細情報を計算（新規追加と更新を集計）
        $totalInserted = $totalPhotosInserted; // 写真の新規追加のみ（口コミは更新のみ）
        $totalUpdated = $totalReviewsChanged + $totalPhotosUpdated; // 口コミの更新 + 写真の更新
        
        // メッセージを「結果: 新規追加 X件、更新 Y件」という形式に変更
        $resultMessage = "結果: 新規追加 {$totalInserted}件、更新 {$totalUpdated}件";
        if (!empty($periodInfo)) {
            $resultMessage .= " {$periodInfo}";
        }
        
        // 詳細メッセージを構築（ポップアップ用）
        $allMessages = array_merge([$reviewsMessage], $photoMessages, $postMessages);
        $detailMessage = implode("、", $allMessages) . "{$periodInfo}。";
        if (!empty($errors)) {
            $detailMessage .= "\n\nエラー: " . implode("\n", $errors);
        }
        
        // 詳細情報をセッションに保存
        $syncDetails = [
            'reviews_changed' => $totalReviewsChanged,
            'photos_inserted' => $totalPhotosInserted,
            'photos_updated' => $totalPhotosUpdated,
            'posts_synced' => $totalPostsSynced,
            'total_inserted' => $totalInserted,
            'total_updated' => $totalUpdated,
            'errors' => $errors ?? [],
            'period_info' => $periodInfo,
            'detail_message' => $detailMessage,
            'shop_results' => $shopResults ?? [], // 店舗ごとの結果
        ];
        
        $message = $resultMessage;
        
        if ($errors) {
            $message .= "\n\nエラー: " . implode("\n", $errors);
            return redirect()->route($routeName)
                ->with('warning', $message)
                ->with('sync_details', $syncDetails);
        } else {
            return redirect()->route($routeName)
                ->with('success', $message)
                ->with('sync_details', $syncDetails);
        }
    }






    public function sync(Request $request, $shopId)
    {
        // 経路の可視化ログ
        Log::info('SYNC_REVIEWS_PATH', ['path' => 'ReportController']);
        
        Log::info('SYNC_ENTRY_POINT', [
            'shop_id' => $shopId ?? null,
        ]);
        
        // テスト: グローバルスコープ無視で件数確認
        $countWithoutScopes = Review::withoutGlobalScopes()
            ->where('shop_id', $shopId)
            ->count();
        Log::info('REVIEW_COUNT_TEST', [
            'shop_id' => $shopId,
            'count_without_scopes' => $countWithoutScopes,
            'count_normal' => Review::where('shop_id', $shopId)->count(),
            'count_via_relation' => Shop::find($shopId)?->reviews()->count() ?? 0,
        ]);
        
        // テスト: is_deletedカラムの確認（reviewsテーブルに存在する場合）
        try {
            $deletedCount = DB::table('reviews')
                ->where('shop_id', $shopId)
                ->whereNotNull('deleted_at')
                ->count();
            $isDeletedCount = DB::table('reviews')
                ->where('shop_id', $shopId)
                ->where('is_deleted', 1)
                ->count();
            $isDeletedNullCount = DB::table('reviews')
                ->where('shop_id', $shopId)
                ->whereNull('is_deleted')
                ->count();
            $isDeletedZeroCount = DB::table('reviews')
                ->where('shop_id', $shopId)
                ->where('is_deleted', 0)
                ->count();
            
            Log::info('REVIEW_DB_COLUMN_TEST', [
                'shop_id' => $shopId,
                'deleted_at_not_null' => $deletedCount,
                'is_deleted_1' => $isDeletedCount,
                'is_deleted_null' => $isDeletedNullCount,
                'is_deleted_0' => $isDeletedZeroCount,
                'total_db_count' => DB::table('reviews')->where('shop_id', $shopId)->count(),
            ]);
        } catch (\Exception $e) {
            Log::info('REVIEW_DB_COLUMN_TEST_ERROR', [
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
            ]);
        }
        
        $shop = Shop::findOrFail($shopId);
        
        // 顧客閲覧範囲による権限チェック
        $user = Auth::user();
        if ($user && $user->is_admin) {
            if ($user->customer_scope === 'own' && $shop->created_by !== $user->id) {
                abort(403, 'この店舗を閲覧する権限がありません。');
            }
        } elseif ($user && !$user->is_admin) {
            if ($shop->created_by !== $user->id) {
                abort(403, 'この店舗を閲覧する権限がありません。');
            }
        }
        







// operator_id を取得（customer_scope対応・統一権限版）
$user = Auth::user();

// customer_scopeを安全に正規化（null・空白・大文字対策）
$customerScope = $user ? strtolower(trim($user->customer_scope ?? 'all')) : 'all';
$isAdmin = (bool) ($user->is_admin ?? false);

// customer_scope = 'all' は管理者扱い（最重要）
$shouldTreatAsAdmin = $isAdmin || ($customerScope === 'all');

// デバッグログ（/reports用）
\Log::info('REPORT_SYNC_PERMISSION_CHECK', [
    'user_id' => $user->id ?? null,
    'is_admin' => $isAdmin,
    'customer_scope' => $customerScope,
    'should_treat_as_admin' => $shouldTreatAsAdmin,
    'session_operator_id' => session('operator_id'),
]);

if ($shouldTreatAsAdmin) {
    // 管理者 or 全顧客スコープ → 全店舗同期可能
   $operatorId = $operatorId ?? session('operator_id');
} else {
    // オペレーターは自分のoperator_id必須
    if ($user && $user->operator_id) {
        $operatorId = $user->operator_id;
    } else {
        $operatorId = session('operator_id');
    }

    // 担当店舗のみアクセス可能
    if ($operatorId) {
        $isAssigned = \App\Models\OperatorShop::where('operator_id', $operatorId)
            ->where('shop_id', $shop->id)
            ->exists();

        if (!$isAssigned) {
            abort(403, 'この店舗を同期する権限がありません。');
        }
    } else {
        abort(403, 'オペレーターIDが設定されていません。');
    }
}




        
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $sinceDate = $request->input('since_date') ?? now()->subMonths(2)->format('Y-m-d');
        $from = $request->input('from');
        $to = $request->input('to');
        $today = Carbon::today();
        
        // 日付が指定された場合はセッションに保存（次回まで保持）
        if ($startDate !== null) {
            session(['sync_start_date' => $startDate]);
        }
        if ($endDate !== null) {
            session(['sync_end_date' => $endDate]);
        }
        
        // 期間指定のバリデーション（JSTの日付をUTCに変換）
        $startDateCarbon = $startDate ? Carbon::parse($startDate, 'Asia/Tokyo')->startOfDay()->utc() : null;
        $endDateCarbon = $endDate ? Carbon::parse($endDate, 'Asia/Tokyo')->endOfDay()->utc() : null;
        
        if ($startDateCarbon && $endDateCarbon && $startDateCarbon->gt($endDateCarbon)) {
            $routeName = $operatorId ? 'operator.reports.show' : 'reports.show';
            return redirect()->route($routeName, ['shop' => $shop->id, 'from' => $from, 'to' => $to])
                ->with('error', '開始日が終了日より後になっています。');
        }
        
        // オペレーション担当で絞り込み
        $operationPersonId = $request->input('operation_person_id');
        
        // 店舗が契約中か確認
        if (!$shop->isContractActive()) {
            $routeName = $operatorId ? 'operator.reports.show' : 'reports.show';
            return redirect()->route($routeName, ['shop' => $shop->id, 'from' => $from, 'to' => $to])
                ->with('error', '契約が終了している店舗の同期はできません。');
        }
        
        // Google連携が完了しているか確認
        if (!$shop->gbp_location_id || !$shop->gbp_refresh_token) {
            $routeName = $operatorId ? 'operator.reports.show' : 'reports.show';
            return redirect()->route($routeName, ['shop' => $shop->id, 'from' => $from, 'to' => $to])
                ->with('error', 'Google連携が完了していません。');
        }
        
        try {
            $googleService = new GoogleBusinessProfileService();
            $accessToken = $googleService->getAccessToken($shop);
            
            if (!$accessToken) {
                $routeName = $operatorId ? 'operator.reports.show' : 'reports.show';
                return redirect()->route($routeName, ['shop' => $shop->id, 'from' => $from, 'to' => $to])
                    ->with('error', 'アクセストークンの取得に失敗しました。');
            }
            
            // スナップショットを作成
            $currentUserId = \App\Helpers\AuthHelper::getCurrentUserId();
            $snapshot = GbpSnapshot::create([
                'shop_id' => $shop->id,
                'user_id' => $currentUserId,
                'synced_by_operator_id' => $operatorId, // 同期実行者（ログ用、nullable）
                'synced_at' => now(),
                'sync_params' => [
                    'start_date' => $startDateCarbon?->format('Y-m-d'),
                    'end_date' => $endDateCarbon?->format('Y-m-d'),
                ],
            ]);
            
            \Log::info('SYNC_REVIEWS_CALL_START', [
                'shop_id' => $shop->id,
                'snapshot_id' => $snapshot->id,
                'method' => __METHOD__,
            ]);
            
            // 口コミを同期
            Log::info('SYNC_REVIEWS__CALLSITE', [
                'method' => __FUNCTION__,
                'line' => __LINE__,
                'shop_id' => $shop->id,
            ]);
            $reviewsChanged = $this->syncReviews($shop, $accessToken, $googleService, $snapshot->id, $sinceDate);
            
            // 写真を同期（完全差分同期）
            $photoResult = $this->syncPhotos($shop, $accessToken, $googleService, $snapshot->id, $sinceDate);
            
            // 投稿を同期（Service経由で保存）
            $postResult = $googleService->syncLocalPostsAndSave($shop, $sinceDate);
            $postsCount = ($postResult['inserted'] ?? 0) + ($postResult['updated'] ?? 0);
            
            // スナップショットの数を更新（写真は追加+更新の合計）
            $snapshot->update([
                'photos_count' => $photoResult['inserted'] + $photoResult['updated'],
                'reviews_count' => $reviewsChanged,
                'posts_count' => $postsCount,
            ]);
            
            Log::info('口コミ・写真・投稿同期完了', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'operator_id' => $operatorId,
                'reviews_changed' => $reviewsChanged,
                'photos_inserted' => $photoResult['inserted'],
                'photos_updated' => $photoResult['updated'],
                'posts_synced' => $postsCount,
            ]);
            
            $periodInfo = '';
            if ($startDateCarbon || $endDateCarbon) {
                $startStr = $startDateCarbon ? $startDateCarbon->format('Y-m-d') : '全期間';
                $endStr = $endDateCarbon ? $endDateCarbon->format('Y-m-d') : '全期間';
                $periodInfo = "（期間: {$startStr} ～ {$endStr}）";
            }
            
            // 口コミのメッセージを構築
            $reviewMessages = [];
            if ($reviewsChanged > 0) {
                $reviewMessages[] = "口コミ {$reviewsChanged}件を更新しました";
            } else {
                $reviewMessages[] = "口コミの変更はありませんでした";
            }
            
            // 写真のメッセージを構築
            $photoMessages = [];
            if ($photoResult['inserted'] > 0) {
                $photoMessages[] = "写真 {$photoResult['inserted']}件を追加しました";
            }
            if ($photoResult['updated'] > 0) {
                $photoMessages[] = "写真 {$photoResult['updated']}件を更新しました";
            }
            if (empty($photoMessages)) {
                $photoMessages[] = "写真の変更はありませんでした";
            }
            
            // 投稿のメッセージを構築
            $postMessages = [];
            if ($postsCount > 0) {
                $postMessages[] = "投稿 {$postsCount}件を同期しました";
            } else {
                $postMessages[] = "投稿の変更はありませんでした";
            }
            
            // すべてのメッセージを結合（投稿のメッセージも必ず含める）
            $allMessages = array_merge($reviewMessages, $photoMessages, $postMessages);
            $message = implode("、", $allMessages) . "{$periodInfo}。";
            
            $routeName = $operatorId ? 'operator.reports.show' : 'reports.show';
            return redirect()->route($routeName, ['shop' => $shop->id, 'from' => $from, 'to' => $to])
                ->with('success', $message);
                
        } catch (\Exception $e) {
            Log::error('口コミ・写真の同期エラー', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'operator_id' => $operatorId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $routeName = $operatorId ? 'operator.reports.show' : 'reports.show';
            return redirect()->route($routeName, ['shop' => $shop->id, 'from' => $from, 'to' => $to])
                ->with('error', "同期エラー: " . $e->getMessage());
        }
    }

    /**
     * 口コミを同期（ReviewSyncServiceを使用）
     */
    private function syncReviews(Shop $shop, string $accessToken, GoogleBusinessProfileService $googleService, int $snapshotId, string $sinceDate): int
    {
        // ReviewSyncServiceを使用して差分同期を実行
        $reviewSyncService = new ReviewSyncService();
        $result = $reviewSyncService->syncShop($shop, $accessToken, $googleService, $snapshotId, $sinceDate);
        
        return $result['synced_count'];
    }

    /**
     * 口コミを同期（増分同期対応）- 旧実装（削除予定）
     * @deprecated ReviewSyncServiceを使用してください
     */
    private function syncReviews_OLD(Shop $shop, string $accessToken, GoogleBusinessProfileService $googleService, int $snapshotId, ?Carbon $startDate = null, ?Carbon $endDate = null): int
    {
        // 1) syncReviews が実行された証拠タグ
        Log::info('SYNC_REVIEWS__CODE_VERSION', [
            'file' => __FILE__,
            'line' => __LINE__,
            'git_head' => trim(@shell_exec('git rev-parse --short HEAD') ?: 'unknown'),
            'php_sapi' => php_sapi_name(),
            'shop_id' => $shop->id,
        ]);
        
        $operatorId = session('operator_id');
        
        if (!$shop->gbp_location_id) {
            Log::warning('口コミ同期: gbp_location_idが設定されていません', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'operator_id' => $operatorId,
            ]);
            return 0;
        }

        if (!$shop->gbp_account_id) {
            Log::warning('口コミ同期: gbp_account_idが設定されていません', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'operator_id' => $operatorId,
            ]);
            return 0;
        }
        
        // 最終同期日時を取得（増分同期用）
        $lastSyncedAt = $shop->last_reviews_synced_at;
        
        // 安全な増分同期開始ログ
        Log::info('REVIEW_SAFE_INCREMENTAL_SYNC_START', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'operator_id' => $operatorId,
            'snapshot_id' => $snapshotId,
            'gbp_account_id' => $shop->gbp_account_id,
            'gbp_location_id' => $shop->gbp_location_id,
            'last_synced_at' => $lastSyncedAt ? $lastSyncedAt->toIso8601String() : null,
            'is_full_sync' => is_null($lastSyncedAt),
        ]);

        // 全件取得（startDateフィルタは使用しない）
        $apiStartTime = microtime(true);
        $reviewsResponse = $googleService->listReviews($accessToken, $shop->gbp_account_id, $shop->gbp_location_id, $shop->id);
        $apiElapsedMs = (microtime(true) - $apiStartTime) * 1000;
        
        if (empty($reviewsResponse)) {
            Log::warning('口コミの取得に失敗: レスポンスが空', [
                'shop_id' => $shop->id,
            ]);
            return 0;
        }

        // レスポンスが Response オブジェクトの場合は json() で配列に変換
        if ($reviewsResponse instanceof \Illuminate\Http\Client\Response) {
            $reviewsData = $reviewsResponse->json();
        } else {
            $reviewsData = $reviewsResponse;
        }
        
        $reviews = $reviewsData['reviews'] ?? [];
        $fetchedCount = is_array($reviews) ? count($reviews) : 0;
        
        // ① API取得直後（差分判定用ログ）
        Log::info('SYNC_REVIEWS_API_COUNT', [
            'shop_id' => $shop->id,
            'account_id' => $shop->gbp_account_id,
            'location_id' => $shop->gbp_location_id,
            'fetched_count' => $fetchedCount,
            'pages' => 1, // GBP API v4 はページネーションなし
            'api_elapsed_ms' => round($apiElapsedMs, 2),
            'is_delta_fetch' => false, // 全件取得
        ]);
        
        // 2) APIの生レビュー1件のキー確認（createTime/updateTime）
        if (!empty($reviews) && isset($reviews[0])) {
            Log::info('REVIEWS_API_SAMPLE_KEYS', [
                'shop_id' => $shop->id,
                'keys' => array_keys($reviews[0]),
                'createTime' => $reviews[0]['createTime'] ?? null,
                'updateTime' => $reviews[0]['updateTime'] ?? null,
                'reviewReply' => isset($reviews[0]['reviewReply']) ? array_keys($reviews[0]['reviewReply']) : null,
                'reviewReply_updateTime' => $reviews[0]['reviewReply']['updateTime'] ?? null,
            ]);
        }
        
        $googleReviewsCount = is_array($reviews) ? count($reviews) : 0;

        $upsertCount = 0;
        $skippedCount = 0;
        $maxUpdateTime = null; // 最大updateTimeを追跡
        $rows = []; // upsert用の配列

        // ② foreach前
        Log::info('SYNC_REVIEWS_BEFORE_LOOP', [
            'shop_id' => $shop->id,
            'reviews_count' => count($reviews),
        ]);

        foreach ($reviews as $review) {
            try {
                // ③ foreach内
                Log::info('SYNC_REVIEWS_LOOP_ITEM', [
                    'shop_id' => $shop->id,
                    'name' => $review['name'] ?? null,
                ]);
                
                // gbp_review_idを取得
                if (!isset($review['name'])) {
                            continue;
                        }
                $reviewId = basename($review['name']);

                // updateTimeを取得（review.updateTime > review.createTime の優先順位）
                // update_timeには常に値（updateTimeまたはcreateTime）を保存する
                // 重要: timezone統一（UTCで比較）
                // Carbon::parse() は曖昧解釈されるため、createFromFormat() で明示的にUTCとして読む
                $updateTimeRaw = data_get($review, 'updateTime');
                $createTimeRaw = $review['createTime'];
                
                // APIのcreateTimeをUTCとして解析（ISO8601形式を想定）
                $createTime = \Carbon\Carbon::parse($createTimeRaw)->utc();
                
                // update_timeには常に値を持つ（updateTimeが存在すればそれ、なければcreateTime）
                $parsedUpdateTime = $updateTimeRaw
                    ? \Carbon\Carbon::parse($updateTimeRaw)->utc()
                    : $createTime;
                
                // update_timeのデバッグログ
                Log::info('REVIEWS_UPDATE_TIME_PARSED', [
                    'shop_id' => $shop->id,
                    'gbp_review_id' => $reviewId,
                    'updateTime_raw' => $updateTimeRaw,
                    'createTime_raw' => $review['createTime'],
                    'parsed_update_time' => $parsedUpdateTime->toIso8601String(),
                    'parsed_update_time_type' => get_class($parsedUpdateTime),
                ]);
                
                // 返信用のupdateTime（reviewReply.updateTime）を取得
                $replyUpdateTimeRaw = data_get($review, 'reviewReply.updateTime');

                // 既存レコードを取得（DBを唯一の真実とする）
                $existingReview = Review::where('shop_id', $shop->id)
                    ->where('gbp_review_id', $reviewId)
                    ->first();

                // 増分同期フィルタ: 既存レコードが存在し、かつ既存のupdate_time >= APIのupdateTime の場合はスキップ
                // 修正: update_time が null の場合は create_time で判定
                $existingTime = null;
                $shouldSkip = false;
                
                if ($existingReview) {
                    // update_time が null の場合は create_time を使用
                    // 重要: timezone統一（UTCで比較）
                    // Carbon::parse() は曖昧解釈されるため、createFromFormat() で明示的にUTCとして読む
                    $existingTime = null;
                    $existingTimeRaw = null;
                    $existingTimeAsUtc = null;
                    $existingTimeAsJst = null;
                    
                    if ($existingReview->update_time) {
                        // DBの update_time は datetime 型で、'Y-m-d H:i:s' 形式の文字列として取得
                        // または Carbon インスタンスとして取得される可能性がある
                        $existingTimeRaw = $existingReview->update_time instanceof \Carbon\Carbon
                            ? $existingReview->update_time->format('Y-m-d H:i:s')
                            : (string)$existingReview->update_time;
                        
                        // DBの update_time は 'Y-m-d H:i:s' 形式でUTCとして保存されている前提
                        $existingTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC');
                        $existingTimeAsUtc = $existingTime->copy();
                        $existingTimeAsJst = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'Asia/Tokyo')->utc();
                    } elseif ($existingReview->create_time) {
                        // DBの create_time は datetime 型で、'Y-m-d H:i:s' 形式の文字列として取得
                        // または Carbon インスタンスとして取得される可能性がある
                        $existingTimeRaw = $existingReview->create_time instanceof \Carbon\Carbon
                            ? $existingReview->create_time->format('Y-m-d H:i:s')
                            : (string)$existingReview->create_time;
                        
                        // DBの create_time は 'Y-m-d H:i:s' 形式でUTCとして保存されている前提
                        $existingTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC');
                        $existingTimeAsUtc = $existingTime->copy();
                        $existingTimeAsJst = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'Asia/Tokyo')->utc();
                    }
                    
                    // 既存の時刻が存在し、かつ APIのupdateTime <= 既存の時刻 の場合はスキップ
                    if ($existingTime && $parsedUpdateTime->lte($existingTime)) {
                        $shouldSkip = true;
                    }
                }
                
                // スキップ判定のログ（updateTime=null問題の検証用）
                Log::info('REVIEW_SYNC_SKIP_CHECK', [
                    'shop_id' => $shop->id,
                    'gbp_review_id' => $reviewId,
                    'existing_review_exists' => $existingReview !== null,
                    'existing_update_time' => $existingReview?->update_time ? $existingReview->update_time->toIso8601String() : null,
                    'existing_create_time' => $existingReview?->create_time ? $existingReview->create_time->toIso8601String() : null,
                    'existing_time_used' => $existingTime ? $existingTime->toIso8601String() : null,
                    'parsed_update_time' => $parsedUpdateTime->toIso8601String(),
                    'parsed_update_time_is_null' => false, // parsedUpdateTimeは常に値を持つ（updateTime or createTime）
                    'should_skip' => $shouldSkip,
                    'delta_key' => 'update_time_or_create_time', // 差分判定キー
                    'timezone' => 'UTC', // timezone統一
                ]);
                
                if ($shouldSkip) {
                    $skippedCount++;
                    continue;
                }
                
                // 注意: ここに到達した場合は、updateTime の差分判定は通過したが、
                // 実際のデータ変更チェックは後続の処理で行う

                // 返信情報
                $replyText = data_get($review, 'reviewReply.comment');
                $repliedAt = $replyUpdateTimeRaw
                    ? \Carbon\Carbon::parse($replyUpdateTimeRaw)
                    : null;

                // 既存レコードと比較して、実際に変更がある場合のみ $rows に追加
                $hasChanges = false;
                
                if ($existingReview) {
                    // 既存レコードと比較（空文字/NULLの正規化）
                    $normalizeString = function($value) {
                        if ($value === null || $value === '') {
                            return null;
                        }
                        return trim($value);
                    };
                    
                    $newAuthorName = $normalizeString($review['reviewer']['displayName'] ?? '不明');
                    $newRating = $this->convertStarRating($review['starRating'] ?? null);
                    $newComment = $normalizeString($review['comment'] ?? null);
                    $newReplyText = $normalizeString($replyText);
                    $newRepliedAt = $repliedAt ? $repliedAt->format('Y-m-d H:i:s') : null;
                    
                    $existingAuthorName = $normalizeString($existingReview->author_name);
                    $existingRating = $existingReview->rating;
                    $existingComment = $normalizeString($existingReview->comment);
                    $existingReplyText = $normalizeString($existingReview->reply_text);
                    $existingRepliedAt = $existingReview->replied_at ? $existingReview->replied_at->format('Y-m-d H:i:s') : null;
                    
                    // 変更があるかチェック
                    $timeComparison = null;
                    if ($existingTime) {
                        if ($parsedUpdateTime->gt($existingTime)) {
                            $timeComparison = 'gt';
                        } elseif ($parsedUpdateTime->eq($existingTime)) {
                            $timeComparison = 'eq';
                        } else {
                            $timeComparison = 'lt';
                        }
                    }
                    
                    // parsedUpdateTime->gt(existingTime) が true になる原因を特定するための詳細ログ
                    if ($parsedUpdateTime->gt($existingTime)) {
                        // DBの create_time / update_time の raw 文字列を取得
                        $dbCreateTimeRaw = $existingReview->create_time 
                            ? ($existingReview->create_time instanceof \Carbon\Carbon 
                                ? $existingReview->create_time->format('Y-m-d H:i:s')
                                : (string)$existingReview->create_time)
                            : null;
                        $dbUpdateTimeRaw = $existingReview->update_time 
                            ? ($existingReview->update_time instanceof \Carbon\Carbon 
                                ? $existingReview->update_time->format('Y-m-d H:i:s')
                                : (string)$existingReview->update_time)
                            : null;
                        
                        Log::info('REVIEW_SYNC_TIMECOMPARISON_GT_DETAIL', [
                            'shop_id' => $shop->id,
                            'gbp_review_id' => $reviewId,
                            'api_createTime_raw' => $createTimeRaw,
                            'api_updateTime_raw' => $updateTimeRaw,
                            'db_create_time_raw' => $dbCreateTimeRaw,
                            'db_update_time_raw' => $dbUpdateTimeRaw,
                            'api_createTime_utc' => $createTime->toIso8601String(),
                            'parsedUpdateTime_utc' => $parsedUpdateTime->toIso8601String(),
                            'existingTime_utc' => $existingTime ? $existingTime->toIso8601String() : null,
                            'existingTime_as_utc' => $existingTimeAsUtc ? $existingTimeAsUtc->toIso8601String() : null,
                            'existingTime_as_jst_then_utc' => $existingTimeAsJst ? $existingTimeAsJst->toIso8601String() : null,
                            'time_comparison' => $timeComparison,
                            'parsedUpdateTime_gt_existingTime' => $parsedUpdateTime->gt($existingTime),
                            'parsedUpdateTime_eq_existingTime' => $parsedUpdateTime->eq($existingTime),
                            'parsedUpdateTime_lt_existingTime' => $parsedUpdateTime->lt($existingTime),
                            'time_diff_seconds' => $existingTime ? $parsedUpdateTime->diffInSeconds($existingTime) : null,
                        ]);
                    }
                    
                    if ($newAuthorName !== $existingAuthorName ||
                        $newRating !== $existingRating ||
                        $newComment !== $existingComment ||
                        $newReplyText !== $existingReplyText ||
                        $newRepliedAt !== $existingRepliedAt ||
                        $parsedUpdateTime->gt($existingTime)) {
                        $hasChanges = true;
                    }
                } else {
                    // 新規レコード
                    $hasChanges = true;
                }
                
                // 変更がある場合のみ $rows に追加
                if ($hasChanges) {
                    $row = [
                        'shop_id' => $shop->id,
                        'gbp_review_id' => $reviewId,
                        'snapshot_id' => $snapshotId,
                        'author_name' => $review['reviewer']['displayName'] ?? '不明',
                        'rating' => $this->convertStarRating($review['starRating'] ?? null),
                        'comment' => $review['comment'] ?? null,
                        'create_time' => $createTime->format('Y-m-d H:i:s'),
                        'update_time' => $parsedUpdateTime->format('Y-m-d H:i:s'),
                        'reply_text' => $replyText,
                        'replied_at' => $repliedAt ? $repliedAt->format('Y-m-d H:i:s') : null,
                    ];
                    
                    $rows[] = $row;
                    } else {
                    // 変更がない場合はスキップ
                    $skippedCount++;
                    continue;
                }

                // 最大updateTimeを更新
                if ($maxUpdateTime === null || $parsedUpdateTime->gt($maxUpdateTime)) {
                    $maxUpdateTime = $parsedUpdateTime;
                }

                $upsertCount++;

            } catch (\Throwable $e) {
                Log::error('SYNC_REVIEWS_EXCEPTION', [
                    'shop_id' => $shop->id,
                    'operator_id' => $operatorId,
                    'review_data' => $review,
                    'message' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        // 1) rows配列生成直後のログ（差分保存検証用）
        Log::info('REVIEWS_ROWS_BEFORE_UPSERT', [
            'shop_id' => $shop->id,
            'fetched_count' => $fetchedCount,
            'rows_to_write_count' => count($rows),
            'skipped_unchanged_count' => $skippedCount,
            'sample' => $rows[0] ?? null,
            'sample_update_time' => $rows[0]['update_time'] ?? 'NO_KEY',
            'sample_create_time' => $rows[0]['create_time'] ?? 'NO_KEY',
        ]);

        // upsert実行（差分保存の検証用）
        $dbStartTime = microtime(true);
        $upsertInsertedCount = 0;
        $upsertUpdatedCount = 0;
        $totalDbWriteCount = 0;
        
        if (!empty($rows)) {
            // upsert前の既存レコードIDを取得（差分判定用）
            $gbpReviewIds = array_column($rows, 'gbp_review_id');
            $existingReviewIds = Review::where('shop_id', $shop->id)
                ->whereIn('gbp_review_id', $gbpReviewIds)
                ->pluck('gbp_review_id')
                ->toArray();
            
            $beforeCount = count($existingReviewIds);
            
            // upsert実行（updated_at は updateColumns から除外 = DBに任せる）
            // 重要: updated_at を updateColumns に含めると、既存レコードが更新される際に updated_at も更新される
            // これが全件UPDATEの原因。updated_at は DB の ON UPDATE CURRENT_TIMESTAMP に任せる
            Review::upsert(
                $rows,
                ['shop_id', 'gbp_review_id'], // ユニークキー: shop_id + gbp_review_id
                [
                    'snapshot_id',
                    'author_name',
                    'rating',
                    'comment',
                    'create_time',
                    'reply_text',
                    'replied_at',
                    'update_time',   // ← これを必ず入れる
                    // 'updated_at' は除外（DBに任せる）
                ]
            );
            
            // 新規追加数 = upsert対象数 - 既存数
            $upsertInsertedCount = count($rows) - $beforeCount;
            // 更新数 = 既存数（upsertは既存があれば更新）
            $upsertUpdatedCount = $beforeCount;
            $totalDbWriteCount = $upsertInsertedCount + $upsertUpdatedCount;
            
            $dbElapsedMs = (microtime(true) - $dbStartTime) * 1000;
            
            Log::info('REVIEWS_UPSERT_EXECUTED', [
                'shop_id' => $shop->id,
                'fetched_count' => $fetchedCount,
                'rows_to_write_count' => count($rows),
                'upsert_inserted_count' => $upsertInsertedCount,
                'upsert_updated_count' => $upsertUpdatedCount,
                'skipped_unchanged_count' => $skippedCount, // スキップされた件数（差分判定で除外された件数）
                'total_db_write_count' => $totalDbWriteCount,
                'db_elapsed_ms' => round($dbElapsedMs, 2),
                'unique_key_columns' => ['shop_id', 'gbp_review_id'],
                'db_operation' => 'upsert',
                'update_columns' => [
                    'snapshot_id',
                    'author_name',
                    'rating',
                    'comment',
                    'create_time',
                    'reply_text',
                    'replied_at',
                    'update_time',
                    // 'updated_at' は除外（DBに任せる）
                ],
            ]);
            
            // 4) update_time が null になる "現物証拠" をDBで取る
            $probe = \DB::table('reviews')
                ->where('shop_id', $shop->id)
                ->whereNotNull('update_time')
                ->count();
            Log::info('SYNC_REVIEWS__DB_PROBE', [
                'shop_id' => $shop->id,
                'not_null_update_time_count' => $probe,
                'total_reviews_count' => \DB::table('reviews')->where('shop_id', $shop->id)->count(),
            ]);
        }

        // 同期終了後のレビュー総数を取得
        $totalReviewsAfterSync = $shop->reviews()->count();

        // 最終同期日時を更新（修正: maxUpdateTime が存在する場合は upsertCount に関係なく更新）
        $previousLastSyncedAt = $shop->last_reviews_synced_at;
        
        // ⑥ last更新直前
        Log::info('SYNC_REVIEWS_BEFORE_LAST_UPDATE', [
            'shop_id' => $shop->id,
            'upsert_count' => $upsertCount,
            'skipped_count' => $skippedCount,
            'max_update_time' => $maxUpdateTime ? $maxUpdateTime->toIso8601String() : null,
            'previous_last_synced_at' => $previousLastSyncedAt ? $previousLastSyncedAt->toIso8601String() : null,
            'will_update' => $maxUpdateTime !== null,
        ]);
        
        // 修正: maxUpdateTime が存在する場合は upsertCount に関係なく更新
        // 同期でレビューを取得できた時点で "最後に見たupdateTime" を前進させる
        if ($maxUpdateTime !== null) {
            $shop->update([
                'last_reviews_synced_at' => $maxUpdateTime,
            ]);
            
            Log::info('REVIEW_LAST_SYNC_UPDATED', [
                'shop_id' => $shop->id,
                'previous_last_synced_at' => $previousLastSyncedAt ? $previousLastSyncedAt->toIso8601String() : null,
                'new_last_synced_at' => $maxUpdateTime->toIso8601String(),
                'upsert_count' => $upsertCount,
                'skipped_count' => $skippedCount,
                'max_update_time' => $maxUpdateTime->toIso8601String(),
                'update_reason' => 'maxUpdateTime exists (upsertCount independent)',
            ]);
        } else {
            Log::info('REVIEW_LAST_SYNC_NOT_UPDATED', [
                'shop_id' => $shop->id,
                'previous_last_synced_at' => $previousLastSyncedAt ? $previousLastSyncedAt->toIso8601String() : null,
                'upsert_count' => $upsertCount,
                'skipped_count' => $skippedCount,
                'max_update_time' => null,
                'update_reason' => 'maxUpdateTime is null (no reviews processed)',
            ]);
        }

        // 安全な増分同期終了ログ（差分同期検証用）
        Log::info('REVIEW_SAFE_INCREMENTAL_SYNC_END', [
            'shop_id' => $shop->id,
            'snapshot_id' => $snapshotId,
            'google_reviews_count' => $googleReviewsCount,
            'fetched_count' => $fetchedCount,
            'upsert_count' => $upsertCount,
            'upsert_inserted_count' => $upsertInsertedCount,
            'upsert_updated_count' => $upsertUpdatedCount,
            'skipped_count' => $skippedCount,
            'total_db_write_count' => $totalDbWriteCount,
            'total_reviews_after_sync' => $totalReviewsAfterSync,
            'max_update_time' => $maxUpdateTime ? $maxUpdateTime->toIso8601String() : null,
            'last_synced_at_updated' => $maxUpdateTime !== null, // 修正: upsertCount に関係なく更新
            'previous_last_synced_at' => $previousLastSyncedAt ? $previousLastSyncedAt->toIso8601String() : null,
            'new_last_synced_at' => $maxUpdateTime ? $maxUpdateTime->toIso8601String() : null,
            'delta_sync_key' => 'update_time_or_create_time', // 差分判定キー
            'is_delta_sync' => $skippedCount > 0 || $totalDbWriteCount < $fetchedCount, // 差分同期の判定
        ]);

        return $upsertCount;
    }

    /**
     * 星評価を数値に変換
     */
    private function convertStarRating($starRating): ?int
    {
        if ($starRating === null) {
            return null;
        }

        if (is_string($starRating)) {
            $ratingMap = [
                'FIVE' => 5,
                'FOUR' => 4,
                'THREE' => 3,
                'TWO' => 2,
                'ONE' => 1,
            ];
            return $ratingMap[strtoupper($starRating)] ?? (int)$starRating;
        }

        return (int)$starRating;
    }

    /**
     * 写真を同期（完全差分同期）
     */
    private function syncPhotos(Shop $shop, string $accessToken, GoogleBusinessProfileService $googleService, int $snapshotId, string $sinceDate): array
    {
        $operatorId = session('operator_id');
        
        if (!$shop->gbp_location_id) {
            Log::warning('PHOTO_SYNC_GBP_LOCATION_ID_NOT_SET', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'operator_id' => $operatorId,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        if (!$shop->gbp_account_id) {
            Log::warning('PHOTO_SYNC_GBP_ACCOUNT_ID_NOT_SET', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'operator_id' => $operatorId,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        Log::info('PHOTO_SYNC_START', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'operator_id' => $operatorId,
            'gbp_account_id' => $shop->gbp_account_id,
            'gbp_location_id' => $shop->gbp_location_id,
        ]);

        // Google Business Profile APIから写真一覧を取得
        $mediaResponse = $googleService->listMedia($accessToken, $shop->gbp_account_id, $shop->gbp_location_id);
        
        if (empty($mediaResponse)) {
            Log::warning('PHOTO_SYNC_EMPTY_RESPONSE', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'operator_id' => $operatorId,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        // レスポンスの構造を確認
        $apiPhotos = $mediaResponse['mediaItems'] ?? [];

        Log::info('PHOTO_SYNC_API_RESPONSE', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'operator_id' => $operatorId,
            'media_items_count' => count($apiPhotos),
        ]);

        // PHOTO形式のみ抽出
        $photoItems = array_filter($apiPhotos, function ($item) {
            return ($item['mediaFormat'] ?? null) === 'PHOTO';
        });

        // sinceDateをUTCに変換
        $sinceUtc = CarbonImmutable::parse($sinceDate, 'Asia/Tokyo')
            ->startOfDay()
            ->timezone('UTC');

        // 最新20件のみチェック対象（APIレスポンスは通常 updateTime DESC で返る）
        $latestPhotos = array_slice($photoItems, 0, 20);

        if (empty($latestPhotos)) {
            Log::info('PHOTO_SYNC_NO_PHOTOS', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'operator_id' => $operatorId,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        // DBに存在するか一括取得
        $mediaNames = collect($latestPhotos)->pluck('name')->filter()->toArray();
        $existingIds = Photo::where('shop_id', $shop->id)
            ->whereIn('gbp_media_name', $mediaNames)
            ->pluck('gbp_media_name')
            ->toArray();

        $insertedCount = 0;
        $updatedCount = 0;

        foreach ($latestPhotos as $photoData) {
            try {
                // updateTime基準で打ち切り判定
                if (isset($photoData['updateTime'])) {
                    $photoTime = CarbonImmutable::parse($photoData['updateTime'], 'UTC');
                    if ($photoTime->lessThan($sinceUtc)) {
                        break;
                    }
                }

                $mediaName = $photoData['name'] ?? null;
                if (!$mediaName) {
                    continue;
                }

                // 既にDBに存在する場合はスキップ（古い写真は触らない）
                if (in_array($mediaName, $existingIds)) {
                    continue;
                }

                // 新規のみ保存
                $apiUpdateTime = isset($photoData['updateTime'])
                    ? CarbonImmutable::parse($photoData['updateTime'], 'UTC')->format('Y-m-d H:i:s')
                    : null;

                Photo::create([
                    'shop_id' => $shop->id,
                    'gbp_media_name' => $mediaName,
                    'google_url' => $photoData['googleUrl'] ?? null,
                    'gbp_update_time' => $apiUpdateTime,
                ]);

                $insertedCount++;

            } catch (\Exception $e) {
                Log::error('PHOTO_SYNC_ITEM_ERROR', [
                    'shop_id' => $shop->id,
                    'operator_id' => $operatorId,
                    'photo_data' => $photoData,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // 早期終了ログ
        if ($insertedCount === 0) {
            Log::info('PHOTO_SYNC_NO_NEW_PHOTOS', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'operator_id' => $operatorId,
                'checked_count' => count($latestPhotos),
            ]);
        }

        Log::info('PHOTO_SYNC_COMPLETE', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'operator_id' => $operatorId,
            'inserted_count' => $insertedCount,
            'updated_count' => $updatedCount,
        ]);

        Log::info('SYNC_WITH_SINCE_FILTER', [
            'shop_id' => $shop->id,
            'since_date' => $sinceDate,
            'type' => 'photo',
        ]);

        return ['inserted' => $insertedCount, 'updated' => $updatedCount];
    }

    public function downloadPdf(Request $request, $shopId)
    {
        // グラフ画像データを取得
        $chartImage = $request->input('chart_image', '');
        
        $shop = Shop::findOrFail($shopId);
        
        // 顧客閲覧範囲による権限チェック
        $user = Auth::user();
        if ($user && $user->is_admin) {
            if ($user->customer_scope === 'own' && $shop->created_by !== $user->id) {
                abort(403, 'この店舗を閲覧する権限がありません。');
            }
        } elseif ($user && !$user->is_admin) {
            if ($shop->created_by !== $user->id) {
                abort(403, 'この店舗を閲覧する権限がありません。');
            }
        }
        
        $googleService = new GoogleBusinessProfileService();
        
        // オペレーターの場合は自分の担当店舗のみアクセス可能（operator_shopsテーブルを使用、なければoperation_person_idでフォールバック）
        $operatorId = session('operator_id');
        if (!$user || !$user->is_admin) {
            // オペレーターの場合
            if ($user && !$user->is_admin && $user->operator_id) {
                $operatorId = $user->operator_id;
            }
            if ($operatorId) {
                $isAssigned = \App\Models\OperatorShop::where('operator_id', $operatorId)
                    ->where('shop_id', $shop->id)
                    ->exists();
                
                // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
                if (!$isAssigned) {
                    $isAssigned = $shop->operation_person_id == $operatorId;
                }
                
                if (!$isAssigned) {
                    abort(403, 'この店舗のレポートにアクセスする権限がありません。');
                }
            }
        }
        
        // 期間の取得（デフォルトは今月）
        // JSTの日付をUTCに変換
        $from = $request->get('from', Carbon::now('Asia/Tokyo')->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', Carbon::now('Asia/Tokyo')->endOfMonth()->format('Y-m-d'));
        
        $fromDate = Carbon::parse($from, 'Asia/Tokyo')->startOfDay();
        $toDate = Carbon::parse($to, 'Asia/Tokyo')->endOfDay();
        
        // キーワードを平均順位の良い順に取得
        $keywords = $shop->meoKeywords()
            ->with(['rankLogs' => function ($query) use ($fromDate, $toDate) {
                $query->whereBetween('checked_at', [$fromDate, $toDate])
                      ->orderBy('checked_at');
            }])
            ->get()
            ->map(function ($keyword) use ($fromDate, $toDate) {
                $ranks = $keyword->rankLogs()
                    ->whereBetween('checked_at', [$fromDate, $toDate])
                    ->whereNotNull('position')
                    ->pluck('position');
                $keyword->average_rank = $ranks->isEmpty() ? null : $ranks->average();
                return $keyword;
            })
            ->sortBy(function ($keyword) {
                return $keyword->average_rank ?? 999;
            })
            ->values();

        // 日付範囲の生成
        $dates = [];
        $currentDate = $fromDate->copy();
        while ($currentDate <= $toDate) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->addDay();
        }

        // キーワードごとの日別順位データ
        $rankData = [];
        foreach ($keywords as $keyword) {
            $keywordRanks = [];
            foreach ($dates as $date) {
                $log = $keyword->rankLogs->first(function ($log) use ($date) {
                    return $log->checked_at->format('Y-m-d') === $date;
                });
                $keywordRanks[$date] = $log ? $log->position : null;
            }
            $rankData[$keyword->id] = [
                'keyword' => $keyword->keyword,
                'ranks' => $keywordRanks,
            ];
        }

        // KPIサマリーの計算（DBから直接取得）
        // JSTの日付範囲をUTCに変換
        $currentPeriodStart = $fromDate->copy()->utc();
        $currentPeriodEnd = $toDate->copy()->utc();
        $prevPeriodStart = $currentPeriodStart->copy()->subMonth();
        $prevPeriodEnd = $currentPeriodEnd->copy()->subMonth();
        
        // 口コミ数：該当期間で投稿された口コミ数（ユニーク：gbp_review_idで重複排除）
        // create_time はUTCで保存されているので、UTCで比較
            $currentReviewIds = DB::table('reviews')
                ->where('shop_id', $shop->id)
                ->whereBetween('create_time', [$currentPeriodStart->format('Y-m-d H:i:s'), $currentPeriodEnd->format('Y-m-d H:i:s')])
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('gbp_review_id')
                ->pluck('id');
            
            $currentReviewCount = count($currentReviewIds);
            
            $prevReviewIds = DB::table('reviews')
                ->where('shop_id', $shop->id)
                ->whereBetween('create_time', [$prevPeriodStart->format('Y-m-d H:i:s'), $prevPeriodEnd->format('Y-m-d H:i:s')])
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('gbp_review_id')
                ->pluck('id');
            
            $prevReviewCount = count($prevReviewIds);
            
            $reviewMoM = $prevReviewCount > 0 
                ? round((($currentReviewCount - $prevReviewCount) / $prevReviewCount) * 100, 1)
                : ($currentReviewCount > 0 ? 100 : 0);

            // 評価点数：該当期間で投稿された口コミの評価の平均値（ユニーク）
            $currentRating = Review::whereIn('id', $currentReviewIds)
                ->avg('rating');
            $prevRating = Review::whereIn('id', $prevReviewIds)
                ->avg('rating');
            $ratingMoM = $prevRating > 0 
                ? round((($currentRating - $prevRating) / $prevRating) * 100, 1)
                : ($currentRating > 0 ? 100 : 0);

            // 返信率：該当期間で投稿された口コミに対して返信した口コミの割合（ユニーク）
            $repliedReviews = Review::whereIn('id', $currentReviewIds)
                ->whereNotNull('reply_text')
                ->count();
            $replyRate = $currentReviewCount > 0 
                ? round(($repliedReviews / $currentReviewCount) * 100, 1)
                : 0;

        // 有効投稿数（Google評価対象）：Service経由で集計
        $currentPostCount = $googleService->countPostsByPeriod($shop, $from, $to);
        
        // 前月同期間の有効投稿数は、前月時点での投稿数を取得（簡易的に現在の値を使用）
        // 注: Step 1では一旦現在の投稿数を使用（Step 2以降で履歴管理を実装）
        $prevPostCount = $currentPostCount;
            
            $postCount = $currentPostCount;
        $postMoM = 0; // Step 1では一旦0とする
            
            // 写真数：該当期間でGBPに投稿された数（ユニーク：gbp_media_idで重複排除）
            $currentPhotoIds = DB::table('photos')
                ->where('shop_id', $shop->id)
                ->whereBetween('create_time', [$currentPeriodStart->format('Y-m-d H:i:s'), $currentPeriodEnd->format('Y-m-d H:i:s')])
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('gbp_media_id')
                ->pluck('id');
            
            $currentPhotoCount = count($currentPhotoIds);
            
            $prevPhotoIds = DB::table('photos')
                ->where('shop_id', $shop->id)
                ->whereBetween('create_time', [$prevPeriodStart->format('Y-m-d H:i:s'), $prevPeriodEnd->format('Y-m-d H:i:s')])
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('gbp_media_id')
                ->pluck('id');
            
            $prevPhotoCount = count($prevPhotoIds);
            
            $photoMoM = $prevPhotoCount > 0 
                ? round((($currentPhotoCount - $prevPhotoCount) / $prevPhotoCount) * 100, 1)
                : ($currentPhotoCount > 0 ? 100 : 0);
        
        // KPI取得元をログに記録
        Log::info('REPORT_KPI_SOURCE', [
            'source' => 'db',
            'shop_id' => $shop->id,
            'current_review_count' => $currentReviewCount,
            'prev_review_count' => $prevReviewCount,
            'current_rating' => $currentRating,
            'prev_rating' => $prevRating,
            'reply_rate' => $replyRate,
            'current_photo_count' => $currentPhotoCount,
            'prev_photo_count' => $prevPhotoCount,
            'current_post_count' => $currentPostCount,
        ]);

        // GBP Insights データを取得（PDF用）
        $insightsService = new GbpInsightsService();
        $insightsData = null;
        $insightsMetrics = [];
        $gbp_impressions_final_clean = [];
        
        if ($shop->gbp_location_id && $shop->gbp_refresh_token) {
            try {
                $insightsData = $insightsService->getInsightsFromDb($shop, $from, $to);
                
                if ($insightsData) {
                    $insightsMetrics = $insightsService->formatMetrics($insightsData);
                    
                    // 日別データを取得（グラフ・テーブル表示用）
                    $dailyInsights = $insightsService->getDailyInsights($shop, $from, $to);
                    $insightsMetrics['daily'] = $dailyInsights['daily'];
                    $insightsMetrics['daily_total'] = $dailyInsights['total'];
                    
                    // PDF用のグラフデータを生成
                    foreach ($dates as $date) {
                        $dateKey = is_string($date) ? $date : $date->format('Y-m-d');
                        $gbp_impressions_final_clean[] = $insightsMetrics['daily'][$dateKey]['impressions'] ?? 0;
                    }
                }
            } catch (\Exception $e) {
                Log::error('GBP Insights取得エラー（PDF）', [
                    'shop_id' => $shop->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 日付を分割して表示（PDFで見やすくするため）
        // 1ページに10日分ずつ表示
        $datesPerPage = 10;
        $dateChunks = array_chunk($dates, $datesPerPage);

        // mPDFでPDF生成（日本語対応・横向き）
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L', // 横向き
            'orientation' => 'L',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 9,
            'margin_footer' => 9,
            'tempDir' => storage_path('app/tmp'),
            'default_font_size' => 10,
            'default_font' => 'sazanami-gothic', // 日本語フォント（ゴシック体）
        ]);
        
        // 日本語フォントを有効化
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        // HTMLを生成
        $html = view('reports.pdf', compact(
            'shop',
            'keywords',
            'dates',
            'dateChunks',
            'rankData',
            'from',
            'to',
            'fromDate',
            'toDate',
            'currentReviewCount',
            'reviewMoM',
            'currentRating',
            'ratingMoM',
            'replyRate',
            'postCount',
            'currentPhotoCount',
            'photoMoM',
            'insightsMetrics',
            'gbp_impressions_final_clean',
            'chartImage'
        ))->render();

        $mpdf->WriteHTML($html);
        
        $filename = 'レポート_' . $shop->name . '_' . $fromDate->format('Ymd') . '_' . $toDate->format('Ymd') . '.pdf';
        
        return response()->streamDownload(function () use ($mpdf) {
            echo $mpdf->Output('', 'S');
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
