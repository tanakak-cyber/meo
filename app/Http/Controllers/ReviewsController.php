<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Shop;
use App\Models\GbpSnapshot;
use App\Models\Photo;
use App\Models\SyncBatch;
use App\Services\GoogleBusinessProfileService;
use App\Services\ReviewSyncService;
use App\Jobs\SyncShopDataJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Bus;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class ReviewsController extends Controller
{
    public function index(Request $request)
    {
        // 店舗ステータスで絞り込み（セッションから取得、なければデフォルトは'active'）
        $status = $request->get('status');
        if ($status) {
            // リクエストで指定された場合はセッションに保存
            session(['shop_status_filter' => $status]);
        } else {
            // セッションから取得、なければデフォルトは'active'（契約中店舗のみ）
            $status = session('shop_status_filter', 'active');
        }
        
        $today = \Carbon\Carbon::today();
        
        $user = Auth::user();
        $shopQuery = Shop::whereNotNull('gbp_location_id');
        
      // 顧客閲覧範囲によるフィルタリング（統一ロジック）
$user = Auth::user();

if ($user) {
    // 「自分の顧客のみ」の場合だけ制限
    if ($user->customer_scope === 'own') {
        $shopQuery->where('created_by', $user->id);
    }
    // customer_scope = 'all' の場合は制限なし（全店舗同期可能）
}
        
        if ($status === 'active') {
            // 契約中店舗のみ
            $shopQuery->where(function ($q) use ($today) {
                $q->whereNull('contract_end_date')
                  ->orWhere('contract_end_date', '>=', $today);
            });
        } elseif ($status === 'expired') {
            // 契約終了店舗のみ
            $shopQuery->whereNotNull('contract_end_date')
                      ->where('contract_end_date', '<', $today);
        }
        // 'all' の場合はフィルタリングなし
        
        // 営業担当で絞り込み
        $salesPersonId = $request->get('sales_person_id');
        if ($salesPersonId) {
            $shopQuery->where('sales_person_id', $salesPersonId);
        }
        
        // オペレーション担当で絞り込み
        $operationPersonId = $request->get('operation_person_id');
        if ($operationPersonId) {
            $shopQuery->where('operation_person_id', $operationPersonId);
        }
        
        // オペレーターIDをセッションから取得
        $user = Auth::user();
        if ($user && $user->is_admin) {
            $operatorId = null;
        } else {
            $operatorId = session('operator_id');
        }
        $user = Auth::user();
        if ($user && !$user->is_admin && $user->operator_id) {
            $operatorId = $user->operator_id;
        }
        
        // オペレーターがログインしている場合は、自分の担当店舗のみ
        if ($operatorId) {
            // operator_shopsテーブルから担当店舗IDを取得
            $assignedShopIds = \App\Models\OperatorShop::where('operator_id', $operatorId)
                ->pluck('shop_id')
                ->toArray();
            
            if (!empty($assignedShopIds)) {
                $shopQuery->whereIn('id', $assignedShopIds);
            } else {
                // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
                $shopQuery->where('operation_person_id', $operatorId);
            }
        }
        
        $shops = $shopQuery->orderBy('name')->get();
        
        // オペレーターの場合は担当店舗のみをフィルタ（operator_shopsテーブルを使用、なければoperation_person_idでフォールバック）
        $user = Auth::user();
        $user = Auth::user();
        if ($user && $user->is_admin) {
            $operatorId = null;
        } else {
            $operatorId = session('operator_id');
        }
        $assignedShopIds = null;
        $useOperationPersonId = false;
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
                
                // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
                if (empty($assignedShopIds)) {
                    $useOperationPersonId = true;
                }
            }
        }
        
        $query = Review::with('shop.operationPerson')
            ->whereHas('shop', function ($q) use ($status, $today, $salesPersonId, $operationPersonId, $assignedShopIds, $useOperationPersonId, $operatorId) {
                $q->whereNotNull('gbp_location_id');
                
                // 店舗ステータスで絞り込み
                if ($status === 'active') {
                    $q->where(function ($subQ) use ($today) {
                        $subQ->whereNull('contract_end_date')
                             ->orWhere('contract_end_date', '>=', $today);
                    });
                } elseif ($status === 'expired') {
                    $q->whereNotNull('contract_end_date')
                      ->where('contract_end_date', '<', $today);
                }
                
                // 営業担当で絞り込み
                if ($salesPersonId) {
                    $q->where('sales_person_id', $salesPersonId);
                }
                
                // オペレーション担当で絞り込み
                if ($operationPersonId) {
                    $q->where('operation_person_id', $operationPersonId);
                }
                
                // オペレーターの場合は担当店舗のみをフィルタ
                if ($assignedShopIds !== null || $useOperationPersonId) {
                    if ($useOperationPersonId) {
                        // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
                        $q->where('operation_person_id', $operatorId);
                    } else {
                        $q->whereIn('id', $assignedShopIds);
                    }
                }
            });

        // 店舗絞り込み
        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }

        // 返信状態のフィルタリング（日付フィルタリングの前に適用）
        if ($request->filled('reply_status')) {
            if ($request->reply_status === 'replied') {
                $query->whereNotNull('reply_text')->whereNotNull('replied_at');
            } elseif ($request->reply_status === 'not_replied') {
                $query->where(function ($q) {
                    $q->whereNull('reply_text')->orWhereNull('replied_at');
                });
            }
        }

        // 開始日・終了日の処理（JSTの日付をUTCの日付範囲に変換）
        $startDateInput = $request->get('start_date');
        $endDateInput = $request->get('end_date');
        
        // 終了日が空の場合は本日の日付を使用
        if (empty($endDateInput)) {
            $endDateInput = Carbon::now('Asia/Tokyo')->format('Y-m-d');
        }
        
        // 開始日が空の場合は過去一番古い口コミの日付を取得
        if (empty($startDateInput)) {
            // 現在のクエリ条件（店舗絞り込み、返信状態）で最も古い口コミの日付を取得
            $oldestReview = (clone $query)->orderBy('create_time', 'asc')->first();
            if ($oldestReview && $oldestReview->create_time) {
                $startDateInput = Carbon::parse($oldestReview->create_time, 'UTC')
                    ->setTimezone('Asia/Tokyo')
                    ->format('Y-m-d');
            } else {
                // 口コミが存在しない場合は、終了日と同じ日付を使用
                $startDateInput = $endDateInput;
            }
        }
        
        // 日付範囲でフィルタリング
        if ($startDateInput && $endDateInput) {
            $start = Carbon::parse($startDateInput, 'Asia/Tokyo')
                ->startOfDay()
                ->utc();
            $end = Carbon::parse($endDateInput, 'Asia/Tokyo')
                ->endOfDay()
                ->utc();
            $query->whereBetween('create_time', [$start, $end]);
        }


        // オペレーターの場合は担当店舗のみをフィルタ（operator_shopsテーブルを使用、なければoperation_person_idでフォールバック）
        // 注意: このフィルタリングは既に whereHas の中で行われているため、ここでは重複チェックのみ
        // ただし、念のため再度フィルタリングを追加（whereHas と whereIn の両方でフィルタリング）
        $user = Auth::user();
        $user = Auth::user();
        if ($user && $user->is_admin) {
            $operatorId = null;
        } else {
            $operatorId = session('operator_id');
        }
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
                
                if (empty($assignedShopIds)) {
                    // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
                    $query->whereHas('shop', function ($q) use ($operatorId) {
                        $q->where('operation_person_id', $operatorId);
                    });
                } else {
                    $query->whereIn('shop_id', $assignedShopIds);
                }
            }
        }
        
        // 重複を除外：同じshop_idとgbp_review_idの組み合わせで、最新のもの（idが最大のもの）のみを表示
        // スナップショットを使わず、DBに保存しているデータを直接参照
        $latestReviewIds = DB::table('reviews')
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('shop_id', 'gbp_review_id')
            ->pluck('id');
        
        // 口コミIDが存在する場合のみフィルタリング
        if ($latestReviewIds->isNotEmpty()) {
            $query->whereIn('id', $latestReviewIds);
        } else {
            // 口コミが存在しない場合は空の結果を返す
            $query->whereRaw('1 = 0');
        }

        // 投稿日時が最近順（降順）で並び替え
        $reviews = $query->orderBy('create_time', 'desc')->paginate(20);

        // ビューに渡す日付（リクエストパラメータがあればそのまま、なければ空文字列）
        // リセットボタンで日付を空にするため、リクエストパラメータがない場合は空文字列を返す
        $startDate = $request->get('start_date', '');
        $endDate = $request->get('end_date', '');

        // 絞り込み用のマスタデータを取得
        $salesPersons = \App\Models\SalesPerson::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        $operationPersons = \App\Models\OperationPerson::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        
        // セッションから同期日付を取得（次回まで保持）
        $syncStartDate = session('sync_start_date');
        $syncEndDate = session('sync_end_date');

        return view('reviews.index', compact('reviews', 'shops', 'startDate', 'endDate', 'status', 'salesPersons', 'operationPersons', 'salesPersonId', 'operationPersonId', 'syncStartDate', 'syncEndDate'));
    }

    public function show(Review $review)
    {
        $user = Auth::user();
        $shop = $review->shop;
        $user = Auth::user();
        if ($user && $user->is_admin) {
            $operatorId = null;
        } else {
            $operatorId = session('operator_id');
        }
        
        // オペレーターでログインしている場合
        if ($operatorId) {
            // operator_shopsテーブルから担当店舗IDを確認
            $isAssigned = \App\Models\OperatorShop::where('operator_id', $operatorId)
                ->where('shop_id', $review->shop_id)
                ->exists();
            
            // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
            if (!$isAssigned) {
                if ($shop->operation_person_id != $operatorId) {
                    abort(403, 'この口コミを閲覧する権限がありません。');
                }
            }
        } else {
            // 顧客閲覧範囲による権限チェック（UI設定と統一）
$user = Auth::user();

if ($user) {
    // 「自分の顧客のみ」のときだけ制限
    if ($user->customer_scope === 'own') {
        if ($shop->created_by !== $user->id) {
            abort(403, 'この口コミを閲覧する権限がありません。');
        }
    }
    // customer_scope = all の場合は全店舗閲覧OK
}
        }
        
        $review->load('shop');
        return view('reviews.show', compact('review'));
    }

    public function sync(Request $request)
    {
        // 経路の可視化ログ
        Log::info('SYNC_REVIEWS_PATH', ['path' => 'ReviewsController']);
        
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
            $user = Auth::user();
        if ($user && $user->is_admin) {
            $operatorId = null;
        } else {
            $operatorId = session('operator_id');
        }
            $routeName = $operatorId ? 'operator.reviews.index' : 'reviews.index';
            return redirect()->route($routeName)
                ->with('error', '開始日が終了日より後になっています。');
        }

        // オペレーション担当で絞り込み
        $operationPersonId = $request->input('operation_person_id');
        
        // オペレーターIDを取得
        $user = Auth::user();
        if ($user && $user->is_admin) {
            $operatorId = null;
        } else {
            $operatorId = session('operator_id');
        }
        
        if ($shopId === 'all') {
            // 全店舗同期の場合、契約中の店舗のみを取得
            $shopQuery = Shop::whereNotNull('gbp_location_id')
                ->whereNotNull('gbp_refresh_token')
                ->where(function ($query) use ($today) {
                    $query->whereNull('contract_end_date')
                          ->orWhere('contract_end_date', '>=', $today);
                });
            
         // 顧客閲覧範囲によるフィルタリング（sync用：UI仕様と完全一致）
$user = Auth::user();

if ($user) {
    // 「自分の顧客のみ」のときだけ制限
    if ($user->customer_scope === 'own') {
        $shopQuery->where('created_by', $user->id);
    }
    // customer_scope = 'all' の場合は一切制限しない（←超重要）
}
            
            // オペレーターがログインしている場合は、自分の担当店舗のみ
            if ($operatorId) {
                $shopQuery->where('operation_person_id', $operatorId);
            } elseif ($operationPersonId) {
                // 管理者がオペレーション担当で絞り込む場合
                $shopQuery->where('operation_person_id', $operationPersonId);
            }
            
            $shops = $shopQuery->get();
        } else {
            // 特定店舗同期の場合、契約中かどうかを確認
            $shop = Shop::whereNotNull('gbp_location_id')
                ->whereNotNull('gbp_refresh_token')
                ->where('id', $shopId)
                ->firstOrFail();
            
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
            
            // オペレーターがログインしている場合は、自分の担当店舗のみアクセス可能
            if ($operatorId && $shop->operation_person_id != $operatorId) {
                $routeName = 'operator.reviews.index';
                return redirect()->route($routeName)
                    ->with('error', 'この店舗を同期する権限がありません。');
            }
            
            if (!$shop->isContractActive()) {
                $routeName = $operatorId ? 'operator.reviews.index' : 'reviews.index';
                return redirect()->route($routeName)
                    ->with('error', '契約が終了している店舗の同期はできません。');
            }
            
            $shops = collect([$shop]);
        }

        if ($shops->isEmpty()) {
            $user = Auth::user();
        if ($user && $user->is_admin) {
            $operatorId = null;
        } else {
            $operatorId = session('operator_id');
        }
            $routeName = $operatorId ? 'operator.reviews.index' : 'reviews.index';
            return redirect()->route($routeName)
                ->with('error', '同期対象の店舗が見つかりませんでした。Google連携が完了している店舗を選択してください。');
        }

        // オペレーターIDを取得（管理者の場合はnullでもOK）
        $user = Auth::user();
        if ($user && $user->is_admin) {
            $operatorId = null;
        } else {
            $operatorId = session('operator_id');
        }

        // 店舗IDの配列を取得
        $shopIds = $shops->pluck('id')->toArray();
        $shopCount = count($shopIds);

        // 1店舗の場合は直列処理、複数店舗の場合は並列処理
        if ($shopCount <= 1) {
            // ✅ 1店舗は直列処理
            $googleService = new GoogleBusinessProfileService();
            $totalReviewsChanged = 0;
            $totalPhotosInserted = 0;
            $totalPhotosUpdated = 0;
            $totalPostsSynced = 0;
            $errors = [];
            $shopResults = []; // 店舗ごとの結果を保存

            foreach ($shops as $shop) {
            try {
                // オペレーターがログインしている場合は、自分の担当店舗のみアクセス可能
                if ($operatorId && $shop->operation_person_id != $operatorId) {
                    $errors[] = "{$shop->name}: この店舗を同期する権限がありません。";
                    continue;
                }

                // アクセストークンをリフレッシュ
                $accessToken = $googleService->getAccessToken($shop);
                
                if (!$accessToken) {
                    $errors[] = "{$shop->name}: アクセストークンの取得に失敗しました。";
                    continue;
                }

                // 口コミを取得・保存
                if (!$shop->gbp_account_id) {
                    $errors[] = "{$shop->name}: GBPアカウントIDが設定されていません。";
                    continue;
                }
                
                // スナップショットを作成（同期実行者の記録のみ、データは共有）
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

                // 口コミを同期（期間指定あり、スナップショットIDを渡す）
                // GBPデータはSingle Source of Truthとして保存（operator_idは関係ない）
                $reviewsChanged = $this->syncReviews($shop, $accessToken, $googleService, $snapshot->id, $sinceDate);
                $totalReviewsChanged += $reviewsChanged;

                // 写真を同期（完全差分同期）
                // GBPデータはSingle Source of Truthとして保存（operator_idは関係ない）
                $photoResult = $this->syncPhotos($shop, $accessToken, $googleService, $snapshot->id, $sinceDate);
                $totalPhotosInserted += $photoResult['inserted'];
                $totalPhotosUpdated += $photoResult['updated'];
                
                // 投稿を同期（Service経由で保存）
                // GBPデータはSingle Source of Truthとして保存（operator_idは関係ない）
                $postResult = $googleService->syncLocalPostsAndSave($shop, $sinceDate);
                $postsCount = ($postResult['inserted'] ?? 0) + ($postResult['updated'] ?? 0);
                $totalPostsSynced += $postsCount;
                
                // スナップショットの数を更新（写真は追加+更新の合計）
                $snapshot->update([
                    'photos_count' => $photoResult['inserted'] + $photoResult['updated'],
                    'reviews_count' => $reviewsChanged,
                    'posts_count' => $postsCount,
                ]);

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
                Log::error('口コミ・写真の同期エラー', [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'operator_id' => $operatorId,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errors[] = "{$shop->name}: " . $e->getMessage();
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

            $routeName = $operatorId ? 'operator.reviews.index' : 'reviews.index';
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
        
        // 口コミのメッセージを構築
        $reviewMessages = [];
        if ($totalReviewsChanged > 0) {
            $reviewMessages[] = "口コミ {$totalReviewsChanged}件を更新しました";
        } else {
            $reviewMessages[] = "口コミの変更はありませんでした";
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
        $allMessages = array_merge($reviewMessages, $photoMessages, $postMessages);
        $detailMessage = implode("、", $allMessages) . "{$periodInfo}。";
        if (!empty($errors)) {
            $detailMessage .= " エラー: " . implode(', ', $errors);
        }
        
        // 詳細情報をセッションに保存
        $syncDetails = [
            'reviews_changed' => $totalReviewsChanged,
            'photos_inserted' => $totalPhotosInserted,
            'photos_updated' => $totalPhotosUpdated,
            'posts_synced' => $totalPostsSynced,
            'total_inserted' => $totalInserted,
            'total_updated' => $totalUpdated,
            'errors' => $errors,
            'period_info' => $periodInfo,
            'detail_message' => $detailMessage,
            'shop_results' => $shopResults ?? [], // 店舗ごとの結果
        ];
        
        $message = $resultMessage;

        // オペレーターの場合はオペレーター用ルートにリダイレクト
        $routeName = $operatorId ? 'operator.reviews.index' : 'reviews.index';
        
        return redirect()->route($routeName)
            ->with($errors ? 'warning' : 'success', $message)
            ->with('sync_details', $syncDetails);
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
     * 写真を同期
     */
    private function syncPhotos(Shop $shop, string $accessToken, GoogleBusinessProfileService $googleService, int $snapshotId, string $sinceDate): array
    {
        if (!$shop->gbp_location_id) {
            Log::warning('PHOTO_SYNC_GBP_LOCATION_ID_NOT_SET', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        if (!$shop->gbp_account_id) {
            Log::warning('PHOTO_SYNC_GBP_ACCOUNT_ID_NOT_SET', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        Log::info('PHOTO_SYNC_START', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'gbp_account_id' => $shop->gbp_account_id,
            'gbp_location_id' => $shop->gbp_location_id,
        ]);

        // Google Business Profile APIから写真一覧を取得
        $mediaResponse = $googleService->listMedia($accessToken, $shop->gbp_account_id, $shop->gbp_location_id);
        
        if (empty($mediaResponse)) {
            Log::warning('PHOTO_SYNC_EMPTY_RESPONSE', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
            ]);
            return ['inserted' => 0, 'updated' => 0];
        }

        // レスポンスの構造を確認
        $apiPhotos = $mediaResponse['mediaItems'] ?? [];

        Log::info('PHOTO_SYNC_API_RESPONSE', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
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
                'checked_count' => count($latestPhotos),
            ]);
        }

        Log::info('PHOTO_SYNC_COMPLETE', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
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

    /**
     * 投稿を同期（件数のみを snapshot に保存、投稿の中身は保存しない）
     * 

    /**
     * AI返信文を生成
     */
    public function generateReply(Review $review)
    {
        try {
            // 口コミが返信済みの場合はエラー
            if ($review->isReplied()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'この口コミは既に返信済みです。'
                ], 400);
            }

            // 店舗を取得
            $shop = $review->shop;
            if (!$shop) {
                return response()->json([
                    'status' => 'error',
                    'message' => '店舗情報が見つかりません。'
                ], 404);
            }

            // OpenAIサービスを使用して返信文を生成
            $openAIService = new \App\Services\OpenAIService();
            $replyText = $openAIService->generateReply([
                'review_text' => $review->comment ?? '',
                'rating' => $review->rating ?? '',
                'shop_name' => $shop->name ?? '',
                'ai_reply_keywords' => $shop->ai_reply_keywords ?? '',
            ]);

            return response()->json([
                'status' => 'ok',
                'reply_text' => $replyText
            ]);

        } catch (\Exception $e) {
            Log::error('AI返信生成エラー', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => '返信文の生成に失敗しました。再度お試しください'
            ], 500);
        }
    }

    public function reply(Request $request, Review $review)
    {
        // 顧客閲覧範囲による権限チェック
        $user = Auth::user();
        $shop = $review->shop;
        $user = Auth::user();
        if ($user && $user->is_admin) {
            $operatorId = null;
        } else {
            $operatorId = session('operator_id');
        }
        
        // オペレーターでログインしている場合
        if ($operatorId) {
            // operator_shopsテーブルから担当店舗IDを確認
            $isAssigned = \App\Models\OperatorShop::where('operator_id', $operatorId)
                ->where('shop_id', $review->shop_id)
                ->exists();
            
            // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
            if (!$isAssigned) {
                if ($shop->operation_person_id != $operatorId) {
                    abort(403, 'この口コミに返信する権限がありません。');
                }
            }
        } else {
            // 管理者/一般ユーザーの場合の権限チェック
            if ($user && $user->is_admin) {
                if ($user->customer_scope === 'own' && $shop->created_by !== $user->id) {
                    abort(403, 'この口コミに返信する権限がありません。');
                }
            } elseif ($user && !$user->is_admin) {
                if ($shop->created_by !== $user->id) {
                    abort(403, 'この口コミに返信する権限がありません。');
                }
            }
        }
        
        // オペレーター用のルート名を決定
        $routeName = $operatorId ? 'operator.reviews.show' : 'reviews.show';
        
        // 契約終了日を確認
        $review->load('shop');
        if (!$review->shop->isContractActive()) {
            return redirect()->route($routeName, $review)
                ->with('error', '契約が終了している店舗の口コミには返信できません。');
        }

        $validated = $request->validate([
            'reply_text' => 'required|string|max:4096',
        ]);

        // Google連携が完了しているか確認
        if (!$review->shop->gbp_location_id || !$review->shop->gbp_refresh_token) {
            return redirect()->route($routeName, $review)
                ->with('error', 'Google連携が完了していないため、返信を送信できません。');
        }

        // reviewIdの確認
        if (!$review->gbp_review_id) {
            Log::error('GBP_REPLY_MISSING_REVIEW_ID', [
                'review_id' => $review->id,
                'shop_id' => $review->shop_id,
            ]);
            return redirect()->route($routeName, $review)
                ->with('error', '口コミIDが設定されていません。口コミを再同期してください。');
        }

        // Google APIに返信を送信
        $googleService = new GoogleBusinessProfileService();
        $refreshResult = $googleService->refreshAccessToken($review->shop->gbp_refresh_token);
        if (!$refreshResult || !$refreshResult['access_token']) {
            Log::error('GBP_REPLY_TOKEN_REFRESH_FAILED', [
                'shop_id' => $review->shop->id,
                'error' => $refreshResult['error'] ?? 'unknown',
                'error_message' => $refreshResult['error_message'] ?? 'リフレッシュトークンが無効です。',
            ]);
            return redirect()->back()->with('error', 'アクセストークンの取得に失敗しました。');
        }
        $accessToken = $refreshResult['access_token'];
        
        Log::info('GBP_REPLY_TOKEN_REFRESH_SUCCESS', [
            'review_id' => $review->id,
            'shop_id' => $review->shop_id,
        ]);

        // reviews.list のレスポンスから review.name を取得
        // DBに保存されているgbp_review_idと、Google APIから取得したreviewIdが一致するreview.nameを使用
        $reviewsResponse = $googleService->listReviews($accessToken, $review->shop->gbp_account_id, $review->shop->gbp_location_id, $review->shop->id);
        $reviews = $reviewsResponse['reviews'] ?? [];
        
        // reviews[i].name をログ出力
        Log::info('GBP_REPLY_REVIEWS_LIST', [
            'review_id' => $review->id,
            'db_gbp_review_id' => $review->gbp_review_id,
            'reviews_count' => count($reviews),
            'review_names' => array_map(function($r) {
                return [
                    'name' => $r['name'] ?? null,
                    'reviewId' => $r['reviewId'] ?? null,
                ];
            }, $reviews),
        ]);
        
        $foundReviewName = null;
        foreach ($reviews as $reviewData) {
            $apiReviewId = $reviewData['reviewId'] ?? null;
            $apiReviewName = $reviewData['name'] ?? null;
            
            if ($apiReviewId && $apiReviewName) {
                // reviewIdの比較（プレフィックスを除去）
                $apiReviewIdClean = str_replace('reviews/', '', $apiReviewId);
                if ($apiReviewIdClean === $review->gbp_review_id) {
                    $foundReviewName = $apiReviewName;
                    Log::info('GBP_REPLY_REVIEW_NAME_FOUND', [
                        'review_id' => $review->id,
                        'db_gbp_review_id' => $review->gbp_review_id,
                        'found_review_name' => $foundReviewName,
                    ]);
                    break;
                }
            }
        }

        if (!$foundReviewName) {
            Log::warning('GBP_REPLY_REVIEW_NAME_NOT_FOUND', [
                'review_id' => $review->id,
                'db_gbp_review_id' => $review->gbp_review_id,
                'shop_id' => $review->shop_id,
                'message' => 'DBに保存されているreviewIdに対応するreview.nameがGoogle APIのレスポンスに見つかりません',
            ]);
            return redirect()->route($routeName, $review)
                ->with('error', '口コミIDが一致しません。口コミを再同期してください。');
        }

        // Google APIに返信を送信
        // review.name をそのまま使用（例: "accounts/100814587656903598763/locations/14533069664155190447/reviews/AbFv..."）
        // 重要: v4のaccountIdという概念は不要。review.nameが唯一の正しい識別子
        $replyResponse = $googleService->replyToReview(
            $accessToken,
            $foundReviewName,
            $validated['reply_text']
        );

        // 成功条件: HTTP 200 でOK
        // レスポンス構造: { "comment": "...", "updateTime": "..." }
        // reviewReply ラッパーは存在しない
        // PHP 8対応: isset(式) を使わず、変数に代入してから判定
        $replyComment = $replyResponse['comment'] ?? null;
        $updateTime = $replyResponse['updateTime'] ?? null;
        
        if (!empty($replyResponse) && $replyComment !== null) {
            // 成功: DBに保存
            $review->update([
                'reply_text' => $validated['reply_text'],
                'replied_at' => now(),
            ]);

            Log::info('GBP_REPLY_SUCCESS_SAVED', [
                'review_id' => $review->id,
                'review_name' => $foundReviewName,
                'reply_comment' => $replyComment,
                'update_time' => $updateTime,
            ]);

            return redirect()->route($routeName, $review)->with('success', '返信を送信しました。');
        } else {
            Log::error('GBP_REPLY_API_FAILED', [
                'review_id' => $review->id,
                'review_name' => $foundReviewName,
                'response' => $replyResponse,
                'has_comment' => $replyComment !== null,
            ]);

            return redirect()->route($routeName, $review)
                ->with('error', 'Google APIへの返信送信に失敗しました。ログを確認してください。');
        }
    }
}

