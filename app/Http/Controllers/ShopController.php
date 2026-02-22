<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\MeoKeyword;
use App\Models\Photo;
use App\Models\Review;
use App\Models\ContactLog;
use App\Models\GbpSnapshot;
use App\Models\GbpPost;
use App\Models\RankFetchJob;
use App\Models\ShopMediaAsset;
use App\Models\SyncBatch;
use App\Services\GoogleBusinessProfileService;
use App\Services\WordPressService;
use App\Jobs\SyncShopDataJob;
use App\Jobs\PostToWordPressJob;
use App\Helpers\HolidayHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Bus;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class ShopController extends Controller
{
    private GoogleBusinessProfileService $googleService;

    public function __construct(GoogleBusinessProfileService $googleService)
    {
        $this->googleService = $googleService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Shop::query();
        


// 顧客閲覧範囲＋オペレーター対応（完全統一版）
$user = Auth::user();
$operatorId = session('operator_id');

// オペレーター経由は最優先（Auth::user()がnullでも存在）
if ($operatorId) {
    // operator_shopsから担当店舗のみ
    $assignedShopIds = \App\Models\OperatorShop::where('operator_id', $operatorId)
        ->pluck('shop_id')
        ->toArray();

    if (!empty($assignedShopIds)) {
        $query->whereIn('id', $assignedShopIds);
    } else {
        // 担当店舗なしは0件
        $query->whereRaw('1 = 0');
    }

} elseif ($user) {
    // customer_scope 正規化（超重要）
    $customerScope = strtolower(trim($user->customer_scope ?? 'all'));

    // own の場合のみ created_by 制限
    if ($customerScope === 'own') {
        $query->where('created_by', $user->id);
    }
    // all は全顧客表示（is_admin関係なし）
}





        
        // 期間で絞り込み（デフォルトは今月）
        $periodStart = $request->get('period_start', now()->startOfMonth()->format('Y-m-d'));
        $periodEnd = $request->get('period_end', now()->endOfMonth()->format('Y-m-d'));
        
        $periodStartDate = Carbon::parse($periodStart)->startOfDay();
        $periodEndDate = Carbon::parse($periodEnd)->endOfDay();
        
        // 指定期間と契約期間が重なる店舗を表示
        // 契約開始日 <= 期間終了日 かつ (契約終了日 >= 期間開始日 または 契約終了日がnull)
        $query->where(function ($q) use ($periodStartDate, $periodEndDate) {
            $q->where(function ($subQ) use ($periodStartDate, $periodEndDate) {
                // 契約開始日が期間内または期間より前
                $subQ->where('contract_date', '<=', $periodEndDate);
            })
            ->where(function ($subQ) use ($periodStartDate, $periodEndDate) {
                // 契約終了日が期間内または期間より後、または契約終了日がnull（無期限）
                $subQ->whereNull('contract_end_date')
                     ->orWhere('contract_end_date', '>=', $periodStartDate);
            });
        });
        
        // 営業担当で絞り込み
        $salesPersonId = $request->get('sales_person_id');
        if ($salesPersonId) {
            $query->where('sales_person_id', $salesPersonId);
        }
        
        // オペレーション担当で絞り込み
        $operationPersonId = $request->get('operation_person_id');
        if ($operationPersonId) {
            $query->where('operation_person_id', $operationPersonId);
        }
        
        $shops = $query->with('plan', 'salesPerson', 'operationPerson')->orderBy('name')->paginate(1000);
        
        // 絞り込み用のマスタデータを取得
        $salesPersons = \App\Models\SalesPerson::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        $operationPersons = \App\Models\OperationPerson::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        
        return view('shops.index', compact('shops', 'salesPersons', 'operationPersons', 'salesPersonId', 'operationPersonId', 'periodStart', 'periodEnd'));
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        $query = Shop::query();
        
        // 顧客閲覧範囲によるフィルタリング
// 顧客閲覧範囲によるフィルタリング（修正版）
if ($user) {
    // customer_scopeがnullの場合は'all'として扱う
    // 大文字小文字・空白対策
    $customerScope = strtolower(trim($user->customer_scope ?? 'all'));

    // デバッグログ（本番でも有用）
    \Log::info('SHOP_INDEX_CUSTOMER_SCOPE', [
        'user_id' => $user->id,
        'is_admin' => $user->is_admin,
        'customer_scope_raw' => $user->customer_scope,
        'customer_scope_processed' => $customerScope,
        'will_filter_by_created_by' => $customerScope === 'own',
    ]);

    if ($customerScope === 'own') {
        $query->where('created_by', $user->id);
    }
    // 'all' はフィルタなし
}

        
        // 期間で絞り込み（デフォルトは今月）
        $periodStart = $request->get('period_start', now()->startOfMonth()->format('Y-m-d'));
        $periodEnd = $request->get('period_end', now()->endOfMonth()->format('Y-m-d'));
        
        $periodStartDate = Carbon::parse($periodStart)->startOfDay();
        $periodEndDate = Carbon::parse($periodEnd)->endOfDay();
        
        // 指定期間と契約期間が重なる店舗を表示
        $query->where(function ($q) use ($periodStartDate, $periodEndDate) {
            $q->where(function ($subQ) use ($periodStartDate, $periodEndDate) {
                $subQ->where('contract_date', '<=', $periodEndDate);
            })
            ->where(function ($subQ) use ($periodStartDate, $periodEndDate) {
                $subQ->whereNull('contract_end_date')
                     ->orWhere('contract_end_date', '>=', $periodStartDate);
            });
        });
        
        // 営業担当で絞り込み
        $salesPersonId = $request->get('sales_person_id');
        if ($salesPersonId) {
            $query->where('sales_person_id', $salesPersonId);
        }
        
        // オペレーション担当で絞り込み
        $operationPersonId = $request->get('operation_person_id');
        if ($operationPersonId) {
            $query->where('operation_person_id', $operationPersonId);
        }
        
        $shops = $query->with('plan', 'salesPerson', 'operationPerson')->orderBy('name')->get();
        
        // CSVファイル名を生成
        $filename = 'shops_' . now()->format('YmdHis') . '.csv';
        
        // CSVヘッダー
        $headers = [
            '店舗名',
            'プラン',
            '担当営業',
            'オペレーション担当',
            '店舗担当者名',
            '金額',
            '契約形態',
            '紹介フィー',
            '契約開始日',
            '契約終了日',
            'GBP連携',
        ];
        
        // CSVデータを生成
        $csvData = [];
        $csvData[] = $headers;
        
        foreach ($shops as $shop) {
            $csvData[] = [
                $shop->name ?? '',
                $shop->plan ? $shop->plan->name : '',
                $shop->salesPerson ? $shop->salesPerson->name : '',
                $shop->operationPerson ? $shop->operationPerson->name : '',
                $shop->shop_contact_name ?? '',
                $shop->price ? number_format($shop->price, 0) : '',
                $shop->contract_type === 'referral' ? '紹介契約' : '自社契約',
                ($shop->contract_type === 'referral' && $shop->referral_fee) ? number_format($shop->referral_fee, 0) : '',
                $shop->contract_date ? $shop->contract_date->format('Y/m/d') : '',
                $shop->contract_end_date ? $shop->contract_end_date->format('Y/m/d') : '',
                $shop->gbp_location_id ? '連携済み' : '未連携',
            ];
        }
        
        // CSVファイルを生成
        $output = fopen('php://temp', 'r+');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        // BOM付きUTF-8で出力（Excelで文字化けしないように）
        $bom = "\xEF\xBB\xBF";
        $csvContent = $bom . $csvContent;
        
        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function create()
    {
        // プランと担当営業、オペレーション担当のマスタを取得
        $plans = \App\Models\Plan::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        $salesPersons = \App\Models\SalesPerson::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        $operationPersons = \App\Models\OperationPerson::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        
        return view('shops.create', compact('plans', 'salesPersons', 'operationPersons'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:shops,name',
            'plan_id' => 'nullable|exists:plans,id',
'blog_option' => 'nullable|boolean',
            'sales_person_id' => 'nullable|exists:sales_persons,id',
            'operation_person_id' => 'nullable|exists:operation_persons,id',
            'shop_contact_name' => 'nullable|string|max:255',
            'shop_contact_phone' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'initial_cost' => 'nullable|numeric|min:0',
            'contract_type' => 'nullable|string|in:own,referral',
            'referral_fee' => 'nullable|numeric|min:0|required_if:contract_type,referral',
            'contract_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after_or_equal:contract_date',
            'integration_type' => 'nullable|in:blog,instagram',
            'blog_list_url' => 'nullable|url|max:255',
            'blog_link_selector' => 'nullable|string|max:255',
            'blog_item_selector' => 'nullable|string|max:255',
            'blog_date_selector' => 'nullable|string|max:255',
            'blog_image_selector' => 'nullable|string|max:255',
            'blog_content_selector' => 'nullable|string|max:255',
            'blog_crawl_time' => 'nullable|date_format:H:i',
            'blog_fallback_image_url' => 'nullable|url|max:1024',
            'instagram_crawl_time' => 'nullable|date_format:H:i|required_if:integration_type,instagram',
            'instagram_item_selector' => 'nullable|string|max:255',
            'review_monthly_target' => 'nullable|integer|min:0',
            'photo_monthly_target' => 'nullable|integer|min:0',
            'gbp_account_id' => 'nullable|string',
            'gbp_location_id' => 'nullable|string',
            'gbp_refresh_token' => 'nullable|string',
            'ai_reply_keywords' => 'nullable|string',
            'low_rating_response' => 'nullable|string',
            'memo' => 'nullable|string',
            'google_place_id' => 'nullable|string|max:255',
            'report_email_1' => 'nullable|email|max:255',
            'report_email_2' => 'nullable|email|max:255',
            'report_email_3' => 'nullable|email|max:255',
            'report_email_4' => 'nullable|email|max:255',
            'report_email_5' => 'nullable|email|max:255',
'rank_lat' => 'nullable|numeric|between:-90,90',
'rank_lng' => 'nullable|numeric|between:-180,180',
        ]);
$validated['wp_post_enabled'] = $request->boolean('wp_post_enabled');
$validated['blog_option'] = (bool) $request->input('blog_option', 0);
$validated['wp_post_enabled'] = (bool) $request->input('wp_post_enabled', 0);
        // review_monthly_target, photo_monthly_target, video_monthly_target が未設定の場合は null を明示的に設定
        if (!isset($validated['review_monthly_target'])) {
            $validated['review_monthly_target'] = null;
        }
        if (!isset($validated['photo_monthly_target'])) {
            $validated['photo_monthly_target'] = null;
        }
        if (!isset($validated['video_monthly_target'])) {
            $validated['video_monthly_target'] = null;
        }

        $shop = Shop::create(array_merge($validated, [
            'created_by' => Auth::id(),
        ]));

        // MEOキーワードの保存
        if ($request->has('meo_keywords')) {
            foreach ($request->meo_keywords as $keyword) {
                if (!empty(trim($keyword))) {
                    MeoKeyword::create([
                        'shop_id' => $shop->id,
                        'keyword' => trim($keyword),
                    ]);
                }
            }
        }

        return redirect()->route('shops.index')->with('success', '店舗を登録しました。');
    }

public function show(Shop $shop)
{
    $user = Auth::user(); // ← ★これを最初に追加（必須）
    // ===== オペレーター優先判定（最重要）=====
    $operatorId = session('operator_id');

    // オペレーターでログインしている場合
    if ($operatorId) {
        // 担当店舗チェック（operator_shops優先）
        $isAssigned = \App\Models\OperatorShop::where('operator_id', $operatorId)
            ->where('shop_id', $shop->id)
            ->exists();

        // フォールバック：operation_person_id
        if (!$isAssigned) {
            $isAssigned = ($shop->operation_person_id == $operatorId);
        }

        if (!$isAssigned) {
            abort(403, 'この店舗を閲覧する権限がありません。（担当外店舗）');
        }

        // オペレーターはここで許可（Auth::user()不要）
    } else {
        // ===== 管理者・通常ユーザー判定 =====
        $user = Auth::user();

        if (!$user) {
            abort(403, '認証が必要です。');
        }





// ===== 管理者・通常ユーザー判定（統一版）=====
if (!$user) {
    abort(403, '認証が必要です。');
}

$customerScope = strtolower(trim($user->customer_scope ?? 'all'));
$isAdmin = (bool) ($user->is_admin ?? false);

// システムアドミン → 全店舗OK
if ($isAdmin) {
    // OK
}

// 管理者（全店舗）→ OK
elseif ($customerScope === 'all') {
    // OK
}

// 管理者（自分のみ）
elseif ($customerScope === 'own') {
    if ($shop->created_by !== $user->id) {
        abort(403, 'この店舗を閲覧する権限がありません。');
    }
}

// その他（念のため）
else {
    if ($shop->created_by !== $user->id) {
        abort(403, 'この店舗を閲覧する権限がありません。');
    }
}














}
//デバッグログ（後で消してOK）
\Log::info('SHOP_SHOW_SCOPE_CHECK', [
    'user_id' => $user?->id,
    'shop_id' => $shop->id,
    'is_admin' => $isAdmin,
    'customer_scope' => $customerScope,
    'shop_created_by' => $shop->created_by,
]);

        
        // オペレーターの場合は自分の担当店舗のみアクセス可能（operator_shopsテーブルを使用、なければoperation_person_idでフォールバック）
        $operatorId = session('operator_id');
        
        if (!$user || !$user->is_admin) {
            // オペレーターの場合
            if ($user && !$user->is_admin && $user->operator_id) {
                $operatorId = $user->operator_id;
            }
            if ($operatorId) {
                // operator_shopsテーブルで担当店舗かチェック
                $isAssigned = \App\Models\OperatorShop::where('operator_id', $operatorId)
                    ->where('shop_id', $shop->id)
                    ->exists();
                
                // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
                if (!$isAssigned) {
                    if ($shop->operation_person_id != $operatorId) {
                        abort(403, 'この店舗を閲覧する権限がありません。');
                    }
                }
            }
        }
        
        // ブログクロール設定を取得（後で実装）
        $setting = null;
        
        // プランと担当営業、オペレーション担当のリレーションをロード
        $shop->load('plan', 'salesPerson', 'operationPerson');
        
        // プランと担当営業、オペレーション担当のマスタを取得
        $plans = \App\Models\Plan::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        $salesPersons = \App\Models\SalesPerson::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        $operationPersons = \App\Models\OperationPerson::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        
        // 有効投稿数（Google評価対象）を取得
        // 注意: これは snapshot の posts_count を使用する（Google API が返す有効投稿数）
        // 履歴の総投稿数とは異なる概念である
        $currentUserId = \App\Helpers\AuthHelper::getCurrentUserId();
        $latestSnapshot = GbpSnapshot::where('shop_id', $shop->id)
            ->where('user_id', $currentUserId)
            ->orderBy('synced_at', 'desc')
            ->first();
        $postCount = $latestSnapshot ? ($latestSnapshot->posts_count ?? 0) : 0;

        // Instagram投稿履歴を取得
        $instagramPosts = GbpPost::where('shop_id', $shop->id)
            ->where('source_type', 'instagram')
            ->orderBy('posted_at', 'desc')
            ->orderBy('create_time', 'desc')
            ->limit(50)
            ->get();

        return view('shops.show', compact('shop', 'setting', 'plans', 'salesPersons', 'operationPersons', 'postCount', 'instagramPosts'));
    }

    public function edit(Shop $shop)
    {
        return redirect()->route('shops.show', $shop)->with('edit_mode', true);
    }

    public function update(Request $request, Shop $shop)
    {
        // 顧客閲覧範囲による権限チェック
        $user = Auth::user();
        
// ===== 編集権限チェック（customer_scope統一版）=====
$user = Auth::user();

if (!$user) {
    abort(403, '認証が必要です。');
}

// customer_scopeを正規化（nullは'all'扱い）
$customerScope = strtolower(trim($user->customer_scope ?? 'all'));
$isAdmin = (bool) ($user->is_admin ?? false);

// 実質管理者判定（あなたのシステム仕様）
$shouldTreatAsAdmin = $isAdmin || ($customerScope === 'all');

// own の場合のみ自分の顧客に制限
if (!$shouldTreatAsAdmin) {
    if ($customerScope === 'own') {
        if ($shop->created_by !== $user->id) {
            abort(403, 'この店舗を編集する権限がありません。');
        }
    } else {
        // 念のための安全ガード
        if ($shop->created_by !== $user->id) {
            abort(403, 'この店舗を編集する権限がありません。');
        }
    }
}

// デバッグログ（あとで削除OK）
\Log::info('SHOP_UPDATE_SCOPE_CHECK', [
    'user_id' => $user->id,
    'shop_id' => $shop->id,
    'is_admin' => $isAdmin,
    'customer_scope' => $customerScope,
    'should_treat_as_admin' => $shouldTreatAsAdmin,
    'shop_created_by' => $shop->created_by,
]);

        
        // メソッドが呼ばれたことを確認するためのログ（tryブロックの外）
        Log::info('SHOP_UPDATE_METHOD_CALLED', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'request_method' => $request->method(),
            'request_uri' => $request->getRequestUri(),
            'request_all' => $request->all(),
        ]);

        try {
            // リクエストデータのログ
            Log::info('SHOP_UPDATE_REQUEST_START', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'request_method' => $request->method(),
                'request_all' => $request->all(),
                'blog_list_url' => $request->input('blog_list_url'),
                'blog_link_selector' => $request->input('blog_link_selector'),
                'blog_date_selector' => $request->input('blog_date_selector'),
                'blog_image_selector' => $request->input('blog_image_selector'),
                'blog_content_selector' => $request->input('blog_content_selector'),
                'blog_crawl_time' => $request->input('blog_crawl_time'),
            ]);

            $validated = $request->validate([
            'name' => 'required|string|max:255|unique:shops,name,' . $shop->id,
            'plan_id' => 'nullable|exists:plans,id',
            'sales_person_id' => 'nullable|exists:sales_persons,id',
            'operation_person_id' => 'nullable|exists:operation_persons,id',
            'shop_contact_name' => 'nullable|string|max:255',
            'shop_contact_phone' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'initial_cost' => 'nullable|numeric|min:0',
            'contract_type' => 'nullable|string|in:own,referral',
            'referral_fee' => 'nullable|numeric|min:0|required_if:contract_type,referral',
            'contract_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after_or_equal:contract_date',
            'integration_type' => 'nullable|in:blog,instagram',
'blog_option' => 'nullable|boolean',
            'blog_list_url' => 'nullable|url|max:255',
            'blog_link_selector' => 'nullable|string|max:255',
            'blog_item_selector' => 'nullable|string|max:255',
            'blog_date_selector' => 'nullable|string|max:255',
            'blog_image_selector' => 'nullable|string|max:255',
            'blog_content_selector' => 'nullable|string|max:255',
            'blog_crawl_time' => 'nullable|date_format:H:i',
            'blog_fallback_image_url' => 'nullable|url|max:1024',
            'instagram_crawl_time' => 'nullable|date_format:H:i|required_if:integration_type,instagram',
            'instagram_item_selector' => 'nullable|string|max:255',
            'wp_post_enabled' => 'nullable|boolean',
            'wp_post_type' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9_-]+$/i'],
            'wp_post_status' => 'nullable|string|in:publish,draft,pending',
            'wp_base_url' => 'nullable|url|max:255',
            'wp_username' => 'nullable|string|max:255',
            'wp_app_password' => 'nullable|string|max:255',
            'review_monthly_target' => 'nullable|integer|min:0',
            'photo_monthly_target' => 'nullable|integer|min:0',
            'video_monthly_target' => 'nullable|integer|min:1|max:4',
            'gbp_account_id' => 'nullable|string',
            'gbp_location_id' => 'nullable|string',
            'gbp_refresh_token' => 'nullable|string',
            'gbp_name' => 'nullable|string|max:255',
            'ai_reply_keywords' => 'nullable|string',
            'low_rating_response' => 'nullable|string',
            'memo' => 'nullable|string',
            'google_place_id' => 'nullable|string|max:255',
            'report_email_1' => 'nullable|email|max:255',
            'report_email_2' => 'nullable|email|max:255',
            'report_email_3' => 'nullable|email|max:255',
            'report_email_4' => 'nullable|email|max:255',
            'report_email_5' => 'nullable|email|max:255',
'rank_lat' => 'nullable|numeric|between:-90,90',
'rank_lng' => 'nullable|numeric|between:-180,180',
        ]);
// チェックボックスは未送信時に消えるので強制確定
$validated['blog_option'] = (bool) $request->input('blog_option', 0);
$validated['wp_post_enabled'] = (bool) $request->input('wp_post_enabled', 0);

        Log::info('SHOP_UPDATE_VALIDATION_PASSED', [
            'shop_id' => $shop->id,
            'validated_data' => $validated,
            'blog_list_url' => $validated['blog_list_url'] ?? null,
            'blog_link_selector' => $validated['blog_link_selector'] ?? null,
            'blog_date_selector' => $validated['blog_date_selector'] ?? null,
            'blog_image_selector' => $validated['blog_image_selector'] ?? null,
            'blog_content_selector' => $validated['blog_content_selector'] ?? null,
            'blog_crawl_time' => $validated['blog_crawl_time'] ?? null,
            'integration_type' => $validated['integration_type'] ?? null,
        ]);

        // review_monthly_target, photo_monthly_target, video_monthly_target が未設定の場合は null を明示的に設定
        if (!isset($validated['review_monthly_target'])) {
            $validated['review_monthly_target'] = null;
        }
        if (!isset($validated['photo_monthly_target'])) {
            $validated['photo_monthly_target'] = null;
        }
        if (!isset($validated['video_monthly_target'])) {
            $validated['video_monthly_target'] = null;
        }

        // blog_fallback_image_url が空の場合は null を設定
        if (isset($validated['blog_fallback_image_url']) && empty(trim($validated['blog_fallback_image_url']))) {
            $validated['blog_fallback_image_url'] = null;
        }

        // wp_post_enabled が false の場合、wp_post_type と wp_post_status を null に設定
        if (!$validated['wp_post_enabled']) {
            $validated['wp_post_type'] = null;
            $validated['wp_post_status'] = null;
        } else {
            // wp_post_enabled=true の場合、wp_post_type が空なら 'post' にフォールバック
            if (isset($validated['wp_post_type'])) {
                $validated['wp_post_type'] = trim($validated['wp_post_type']);
                if (empty($validated['wp_post_type'])) {
                    $validated['wp_post_type'] = 'post';
                }
            } else {
                $validated['wp_post_type'] = 'post';
            }
        }

        // wp_app_password の処理
        // 重要: パスワードフィールドは空文字列が送信される可能性がある
        // 空文字列や null の場合は既存の値を保持するため、$validated から削除する
        // バリデーションルール: nullable|string|max:255
        // nullable により空文字列は null に変換される可能性があるが、
        // $validated に null が含まれると update() で null が保存されてしまうため、
        // null の場合も unset する必要がある
        
        // 重要: array_key_exists() を使用して、キーが存在するかチェック
        // isset() は null の場合 false を返すが、array_key_exists() は true を返す
        $wpAppPasswordInValidated = array_key_exists('wp_app_password', $validated);
        $wpAppPasswordFromValidated = $validated['wp_app_password'] ?? null;
        $wpAppPasswordFromRequest = $request->input('wp_app_password');
        
        if ($wpAppPasswordInValidated) {
            // $validated にキーが存在する場合
            $wpAppPassword = $wpAppPasswordFromValidated;
            
            // null または空文字列の場合は既存の値を保持（更新しない）
            if ($wpAppPassword === null || (is_string($wpAppPassword) && empty(trim($wpAppPassword)))) {
                // $validated から削除することで、update() で既存値が保持される
                unset($validated['wp_app_password']);
                Log::info('WP_APP_PASSWORD_EMPTY_PRESERVE', [
                    'shop_id' => $shop->id,
                    'request_value' => $wpAppPasswordFromRequest,
                    'validated_value' => $wpAppPasswordFromValidated,
                    'is_null' => $wpAppPassword === null,
                    'is_empty' => is_string($wpAppPassword) && empty(trim($wpAppPassword)),
                    'message' => '空文字列または null が送信されたため、既存の値を保持します',
                ]);
            } else {
                // 値が入力されている場合は、そのまま保存（encrypted castにより自動的に暗号化される）
                Log::info('WP_APP_PASSWORD_NEW_VALUE', [
                    'shop_id' => $shop->id,
                    'value_length' => strlen($wpAppPassword),
                    'message' => '新しいパスワードが入力されました',
                ]);
            }
        } elseif ($request->has('wp_app_password') && ($wpAppPasswordFromRequest === null || (is_string($wpAppPasswordFromRequest) && empty(trim($wpAppPasswordFromRequest))))) {
            // $validated に含まれていないが、リクエストに空文字列または null が含まれている場合
            // 念のため、$validated に含まれていないことを確認
            Log::info('WP_APP_PASSWORD_EMPTY_REQUEST', [
                'shop_id' => $shop->id,
                'request_value' => $wpAppPasswordFromRequest,
                'message' => 'リクエストに空文字列または null が含まれていますが、$validated には含まれていません。既存値が保持されます。',
            ]);
        }

        // wp_base_url が空の場合は null を設定
        if (isset($validated['wp_base_url']) && empty(trim($validated['wp_base_url']))) {
            $validated['wp_base_url'] = null;
        }

        // wp_username が空の場合は null を設定
        if (isset($validated['wp_username']) && empty(trim($validated['wp_username']))) {
            $validated['wp_username'] = null;
        }

        // integration_typeの処理
        $currentIntegrationType = $shop->integration_type;
        $newIntegrationType = $validated['integration_type'] ?? null;
        
        // integration_typeがnullまたは空文字列の場合、すべての連携設定をクリア（変更の有無に関わらず）
        if ($newIntegrationType === null || $newIntegrationType === '') {
            // 未設定：すべての連携設定をクリア（明示的にnullを設定）
            $validated['blog_list_url'] = null;
            $validated['blog_link_selector'] = null;
            $validated['blog_item_selector'] = null;
            $validated['blog_date_selector'] = null;
            $validated['blog_image_selector'] = null;
            $validated['blog_content_selector'] = null;
            $validated['blog_crawl_time'] = null;
            $validated['blog_fallback_image_url'] = null;
            $validated['instagram_crawl_time'] = null;
            $validated['instagram_item_selector'] = null;
        } elseif ($currentIntegrationType !== $newIntegrationType) {
            // integration_typeが変更された場合のみ処理
            if ($newIntegrationType === 'blog') {
                // ブログ連携に変更：Instagram専用フィールドをクリア
                $validated['instagram_crawl_time'] = null;
                $validated['instagram_item_selector'] = null;
            } elseif ($newIntegrationType === 'instagram') {
                // Instagram連携に変更：ブログ専用フィールドをすべてクリア
                // 注意：blog_list_url, blog_link_selector, blog_image_selector, blog_content_selector は
                // Instagram設定でも使用するため、クリアしない
                $validated['blog_item_selector'] = null;
                $validated['blog_date_selector'] = null;
                $validated['blog_crawl_time'] = null;
                $validated['blog_fallback_image_url'] = null;
            }
        }
        
        // リクエストに含まれていないフィールドは、$validatedから削除して既存の値を保持
        // ただし、integration_typeが未設定（nullまたは空文字列）の場合は、すべての連携設定をクリアするため、unsetしない
        $requestKeys = array_keys($request->all());
        $isUnsetMode = ($newIntegrationType !== null && $newIntegrationType !== '');
        
        if ($isUnsetMode) {
            // ブログ専用フィールド：リクエストに含まれていない場合は削除（既存値を保持）
            // ただし、integration_typeが'instagram'の場合は、これらのフィールドは既にクリア済み
            if ($newIntegrationType === 'blog') {
                $blogOnlyFields = ['blog_item_selector', 'blog_date_selector', 'blog_crawl_time', 'blog_fallback_image_url'];
                foreach ($blogOnlyFields as $field) {
                    if (!in_array($field, $requestKeys) && !isset($validated[$field])) {
                        unset($validated[$field]);
                    }
                }
            }
            
            // 共有フィールド（blog_list_url, blog_link_selector, blog_image_selector, blog_content_selector）：
            // integration_typeに応じて処理
            $sharedFields = ['blog_list_url', 'blog_link_selector', 'blog_image_selector', 'blog_content_selector'];
            foreach ($sharedFields as $field) {
                // integration_typeが'instagram'の場合、これらのフィールドはInstagram設定として使用される
                // リクエストに含まれていない場合は削除（既存値を保持）
                if (!in_array($field, $requestKeys) && !isset($validated[$field])) {
                    unset($validated[$field]);
                }
            }
            
            // Instagram専用フィールド：リクエストに含まれていない場合は削除（既存値を保持）
            // ただし、integration_typeが'blog'の場合は、これらのフィールドは既にクリア済み
            if ($newIntegrationType === 'instagram') {
                $instagramOnlyFields = ['instagram_crawl_time', 'instagram_item_selector'];
                foreach ($instagramOnlyFields as $field) {
                    if (!in_array($field, $requestKeys) && !isset($validated[$field])) {
                        unset($validated[$field]);
                    }
                }
            }
        }
        // integration_typeが未設定の場合は、上記で明示的にnullを設定済みなので、unset処理は不要

        Log::info('SHOP_UPDATE_BEFORE_SAVE', [
            'shop_id' => $shop->id,
            'validated_data' => $validated,
            'wp_app_password_in_validated' => isset($validated['wp_app_password']),
            'wp_app_password_value_length' => isset($validated['wp_app_password']) ? strlen($validated['wp_app_password']) : 0,
            'request_keys' => array_keys($request->all()),
            'wp_app_password_in_request' => $request->has('wp_app_password'),
            'blog_fields' => [
                'blog_list_url' => $validated['blog_list_url'] ?? null,
                'blog_link_selector' => $validated['blog_link_selector'] ?? null,
                'blog_date_selector' => $validated['blog_date_selector'] ?? null,
                'blog_image_selector' => $validated['blog_image_selector'] ?? null,
                'blog_content_selector' => $validated['blog_content_selector'] ?? null,
                'blog_crawl_time' => $validated['blog_crawl_time'] ?? null,
                'integration_type' => $validated['integration_type'] ?? null,
            ],
        ]);

        // wp_app_password の保存前ログ
        if (isset($validated['wp_app_password'])) {
            Log::info('WP_APP_PASSWORD_UPDATE', [
                'shop_id' => $shop->id,
                'has_value' => !empty($validated['wp_app_password']),
                'value_length' => strlen($validated['wp_app_password']),
            ]);
        } else {
            Log::info('WP_APP_PASSWORD_PRESERVED', [
                'shop_id' => $shop->id,
                'message' => 'wp_app_password は $validated に含まれていないため、既存値が保持されます',
            ]);
        }

        // 最終確認: $validated に wp_app_password が含まれているか確認
        // array_key_exists() を使用して、null の場合も検出できるようにする
        Log::info('SHOP_UPDATE_VALIDATED_FINAL', [
            'shop_id' => $shop->id,
            'wp_app_password_in_validated' => array_key_exists('wp_app_password', $validated),
            'wp_app_password_value' => $validated['wp_app_password'] ?? 'NOT_SET',
            'wp_app_password_is_null' => array_key_exists('wp_app_password', $validated) && $validated['wp_app_password'] === null,
            'validated_keys' => array_keys($validated),
        ]);

        $shop->update($validated);

        // 更新後のデータを再取得して確認
        $shop->refresh();
        
        // wp_app_password の保存後ログ（encrypted cast を考慮）
        $decryptedPassword = $shop->wp_app_password; // encrypted castにより自動的に復号化される
        $rawPassword = $shop->getRawOriginal('wp_app_password'); // DBに保存されている生の値（暗号化済み）
        
        Log::info('WP_APP_PASSWORD_AFTER_UPDATE', [
            'shop_id' => $shop->id,
            'wp_app_password_set' => !empty($decryptedPassword),
            'wp_app_password_length' => $decryptedPassword ? strlen($decryptedPassword) : 0,
            'raw_app_password_set' => !empty($rawPassword),
            'raw_app_password_length' => $rawPassword ? strlen($rawPassword) : 0,
            'wp_username' => $shop->wp_username,
            'wp_base_url' => $shop->wp_base_url,
        ]);

        Log::info('SHOP_UPDATE_AFTER_SAVE', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'saved_blog_list_url' => $shop->blog_list_url,
            'saved_blog_link_selector' => $shop->blog_link_selector,
            'saved_blog_date_selector' => $shop->blog_date_selector,
            'saved_blog_image_selector' => $shop->blog_image_selector,
            'saved_blog_content_selector' => $shop->blog_content_selector,
            'saved_blog_crawl_time' => $shop->blog_crawl_time,
            'saved_integration_type' => $shop->integration_type,
        ]);

        // ブログクロール設定の保存ログを出力
        Log::info('BLOG_CRAWL_SETTING_SAVED', [
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'blog_list_url' => $shop->blog_list_url,
            'blog_link_selector' => $shop->blog_link_selector,
            'blog_date_selector' => $shop->blog_date_selector,
            'blog_image_selector' => $shop->blog_image_selector,
            'blog_content_selector' => $shop->blog_content_selector,
            'blog_crawl_time' => $shop->blog_crawl_time,
            'integration_type' => $shop->integration_type,
        ]);

        // MEOキーワードの更新（リクエストに含まれない既存キーワードは削除）
        $requestKeywords = [];
        if ($request->has('meo_keywords')) {
            foreach ($request->meo_keywords as $keyword) {
                $trimmedKeyword = trim($keyword);
                if (!empty($trimmedKeyword)) {
                    $requestKeywords[] = $trimmedKeyword;
                    
                    // 既存のキーワードと重複していない場合のみ追加
                    $exists = $shop->meoKeywords()
                        ->where('keyword', $trimmedKeyword)
                        ->exists();
                    
                    if (!$exists) {
                        MeoKeyword::create([
                            'shop_id' => $shop->id,
                            'keyword' => $trimmedKeyword,
                        ]);
                    }
                }
            }
        }
        
        // リクエストに含まれない既存キーワードを削除
        // 過去のログは外部キー制約（onDelete('set null')）により保持される
        $shop->meoKeywords()
            ->whereNotIn('keyword', $requestKeywords)
            ->delete();

            return redirect()->route('shops.show', $shop)->with('success', '店舗情報を更新しました。');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // バリデーションエラーのログ
            Log::error('SHOP_UPDATE_VALIDATION_ERROR', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);
            
            // バリデーションエラーは自動的にリダイレクトされる
            throw $e;
        } catch (\Exception $e) {
            // その他の例外のログ
            Log::error('SHOP_UPDATE_EXCEPTION', [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);
            
            return redirect()->route('shops.show', $shop)
                ->with('error', '店舗情報の更新中にエラーが発生しました: ' . $e->getMessage());
        }
    }

    public function destroy(Shop $shop)
    {
        // 顧客閲覧範囲による権限チェック
        $user = Auth::user();
        
        if ($user && $user->is_admin) {
            if ($user->customer_scope === 'own' && $shop->created_by !== $user->id) {
                abort(403, 'この店舗を削除する権限がありません。');
            }
        } elseif ($user && !$user->is_admin) {
            if ($shop->created_by !== $user->id) {
                abort(403, 'この店舗を削除する権限がありません。');
            }
        }
        
        $shop->delete();
        return redirect()->route('shops.index')->with('success', '店舗を削除しました。');
    }

    public function schedule(Request $request)
    {
        // 年月をリクエストパラメータから取得、なければセッションから、それもなければ現在の年月
        $year = $request->get('year');
        $month = $request->get('month');
        
        if ($year && $month) {
            // リクエストで指定された場合はセッションに保存（ユーザーごとに保持）
            session([
                'schedule_year' => $year,
                'schedule_month' => $month,
            ]);
        } else {
            // セッションから取得、なければデフォルトは現在の年月
            $year = session('schedule_year', now()->year);
            $month = session('schedule_month', now()->month);
        }
        
        $targetDate = Carbon::create($year, $month, 1);
        $prevMonth = $targetDate->copy()->subMonth();
        $nextMonth = $targetDate->copy()->addMonth();
        $daysInMonth = $targetDate->daysInMonth;

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
        
        $user = Auth::user();
        $shopQuery = Shop::query();
        
        // 顧客閲覧範囲によるフィルタリン
// 顧客閲覧範囲によるフィルタリング（schedule: メインクエリ）
if ($user) {
    $customerScope = strtolower(trim($user->customer_scope ?? 'all'));

    if ($customerScope === 'own') {
        $shopQuery->where('created_by', $user->id);
    }
    // 'all' は全顧客表示
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
        
        // オペレーターの場合は担当店舗のみをフィルタ（operator_shopsテーブルを使用、なければoperation_person_idでフォールバック）
        // 管理者は全店舗を表示
        $user = Auth::user();
        $operatorId = session('operator_id');
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
                    $shopQuery->whereIn('id', $assignedShopIds);
                } else {
                    // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
                    $shopQuery->where('operation_person_id', $operatorId);
                }
            }
        }
        
        $shops = $shopQuery->with('salesPerson', 'operationPerson')->get();
        // 同期対象は契約中の店舗のみ（ステータスフィルターに関係なく）
        $shopsForSyncQuery = Shop::whereNotNull('gbp_location_id')
            ->where(function ($q) use ($today) {
                // 契約終了日が設定されていない、または今日以降の店舗のみ
                $q->whereNull('contract_end_date')
                  ->orWhere('contract_end_date', '>=', $today);
            });
        
        // オペレーターの場合は担当店舗のみをフィルタ（operator_shopsテーブルを使用、なければoperation_person_idでフォールバック）
        if ($operatorId) {
            // operator_shopsテーブルから担当店舗IDを取得
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
        // 顧客閲覧範囲フィルタリング（同期対象にも適用）
if ($user) {
    $customerScope = strtolower(trim($user->customer_scope ?? 'all'));

    if ($customerScope === 'own') {
        $shopsForSyncQuery->where('created_by', $user->id);
    }
}
        $shopsForSync = $shopsForSyncQuery->get();

        // 連絡履歴を一括取得（パフォーマンス向上のため）
        $contactLogs = ContactLog::whereIn('shop_id', $shops->pluck('id'))
            ->whereYear('contact_date', $year)
            ->whereMonth('contact_date', $month)
            ->get()
            ->groupBy(function ($log) {
                return $log->shop_id . '_' . Carbon::parse($log->contact_date)->day;
            });

        // 順位取得状況を一括取得（N+1防止）
        // meo_rank_logs と meo_keywords をJOINして、shop_id と DATE(checked_at) で判定
        // JSTの日付範囲をUTCに変換（CarbonImmutableを使用）
        $startDateJst = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Asia/Tokyo');
        $endDateJst = $startDateJst->endOfMonth();
        $startDateUtc = $startDateJst->utc();
        $endDateUtc = $endDateJst->utc();
        
        $rankLogs = \App\Models\MeoRankLog::join('meo_keywords', 'meo_rank_logs.meo_keyword_id', '=', 'meo_keywords.id')
            ->whereIn('meo_keywords.shop_id', $shops->pluck('id'))
            ->whereBetween('meo_rank_logs.checked_at', [$startDateUtc->format('Y-m-d'), $endDateUtc->format('Y-m-d')])
            ->select('meo_keywords.shop_id', \DB::raw('DATE(meo_rank_logs.checked_at) as checked_date'))
            ->distinct()
            ->get();
        
        // [shop_id][date] のMapを作成
        $rankFetchedMap = [];
        foreach ($rankLogs as $log) {
            $shopId = $log->shop_id;
            $date = Carbon::parse($log->checked_date)->format('Y-m-d');
            if (!isset($rankFetchedMap[$shopId])) {
                $rankFetchedMap[$shopId] = [];
            }
            $rankFetchedMap[$shopId][$date] = true;
        }
        
        // 管理者向け集計：各日付の取得済店舗数を計算
        $rankFetchedCountByDate = [];
        
          for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateJst = CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'Asia/Tokyo');
                $date = $dateJst->format('Y-m-d');
                $fetchedCount = 0;
                foreach ($shops as $shop) {
                    if (isset($rankFetchedMap[$shop->id][$date])) {
                        $fetchedCount++;
                    }
                }
                $rankFetchedCountByDate[$day] = [
                    'fetched' => $fetchedCount,
                    'total' => $shops->count(),
                ];
            }
        

        $scheduleData = [];
        foreach ($shops as $shop) {
            $daily = [];
            // JSTの日付範囲をUTCに変換（CarbonImmutableを使用）
            $startDateJst = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Asia/Tokyo');
            $endDateJst = $startDateJst->endOfMonth();
            $startDateUtc = $startDateJst->utc();
            $endDateUtc = $endDateJst->utc();
            
            // スナップショットを使わず、DBに保存されているデータを直接参照
            // 重複を除外：同じshop_idとgbp_review_idの組み合わせで、最新のもの（idが最大のもの）のみを取得
            $latestReviewIds = DB::table('reviews')
                ->where('shop_id', $shop->id)
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('shop_id', 'gbp_review_id')
                ->pluck('id');
            
            // 指定された年月の口コミを取得
            $reviewsQuery = Review::where('shop_id', $shop->id)
                ->whereBetween('create_time', [
                    $startDateUtc->format('Y-m-d H:i:s'),
                    $endDateUtc->format('Y-m-d H:i:s')
                ]);
            if ($latestReviewIds->isNotEmpty()) {
                $reviewsQuery->whereIn('id', $latestReviewIds);
            } else {
                // 口コミが存在しない場合は空の結果
                $reviewsQuery->whereRaw('1 = 0');
            }
            $reviews = $reviewsQuery->get();
            
            // 写真を取得（重複を除外：同じshop_idとgbp_media_idの組み合わせで、最新のもののみ）
            $latestPhotoIds = DB::table('photos')
                ->where('shop_id', $shop->id)
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('shop_id', 'gbp_media_id')
                ->pluck('id');
            
            $photosQuery = Photo::where('shop_id', $shop->id)
                ->whereNotNull('create_time');
            if ($latestPhotoIds->isNotEmpty()) {
                $photosQuery->whereIn('id', $latestPhotoIds);
            } else {
                // 写真が存在しない場合は空の結果
                $photosQuery->whereRaw('1 = 0');
            }
            $photos = $photosQuery->get();
            
            // 投稿を取得（Service経由で日別集計）
            $dailyPostCounts = $this->googleService->getDailyPostCounts($shop, $year, $month);
            
            for ($day = 1; $day <= $daysInMonth; $day++) {
                // JSTの日付をUTCに変換（CarbonImmutableを使用）
                $dateJst = CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'Asia/Tokyo');
                $dayStartJst = $dateJst->startOfDay();
                $dayEndJst = $dateJst->endOfDay();
                $dayStartUtc = $dayStartJst->utc();
                $dayEndUtc = $dayEndJst->utc();
                
                // その日の口コミ数をカウント（UTCで比較）
                $reviewCount = $reviews->filter(function ($review) use ($dayStartUtc, $dayEndUtc) {
                    // create_time はUTCで保存されているので、UTCとして扱う
                    if ($review->create_time instanceof Carbon) {
                        $reviewDate = CarbonImmutable::parse($review->create_time->format('Y-m-d H:i:s'), 'UTC');
                    } else {
                        $reviewDate = CarbonImmutable::parse($review->create_time, 'UTC');
                    }
                    return $reviewDate->greaterThanOrEqualTo($dayStartUtc) && $reviewDate->lessThanOrEqualTo($dayEndUtc);
                })->count();
                
                // 写真数を日別に集計（UTCで比較）
                $photoCount = $photos->filter(function ($photo) use ($dayStartUtc, $dayEndUtc) {
                    // create_time はUTCで保存されているので、UTCとして扱う
                    if ($photo->create_time instanceof Carbon) {
                        $photoDate = CarbonImmutable::parse($photo->create_time->format('Y-m-d H:i:s'), 'UTC');
                    } else {
                        $photoDate = CarbonImmutable::parse($photo->create_time, 'UTC');
                    }
                    return $photoDate->greaterThanOrEqualTo($dayStartUtc) && $photoDate->lessThanOrEqualTo($dayEndUtc);
                })->count();
                
                // 有効投稿数を日別に集計（Service経由で取得）
                $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $postCount = $dailyPostCounts->get($dateKey, 0);
                
                // 連絡履歴の有無を確認
                $logKey = $shop->id . '_' . $day;
                $hasContactLog = isset($contactLogs[$logKey]) && $contactLogs[$logKey]->isNotEmpty();
                
                // 順位取得状況を確認（$dateJst を使用）
                $dateStr = $dateJst->format('Y-m-d');
                $isRankFetched = isset($rankFetchedMap[$shop->id][$dateStr]);
                
                $daily[$day] = [
                    'review' => $reviewCount,
                    'photo' => $photoCount,
                    'post' => $postCount,
                    'has_contact_log' => $hasContactLog,
                    'is_rank_fetched' => $isRankFetched,
                ];
            }

            $reviewTarget = $shop->review_monthly_target ?? 0;
            $photoTarget = $shop->photo_monthly_target ? (int)$shop->photo_monthly_target : null;
            $videoTarget = $shop->video_monthly_target ?? null;
            $reviewTotal = array_sum(array_column($daily, 'review'));
            $photoTotal = array_sum(array_column($daily, 'photo'));
            $postTotal = array_sum(array_column($daily, 'post'));

            // ブログ投稿お任せオプションがある場合、毎月4日ランダムで土日祝を除外した日付を決定
            $blogDisplayDays = [];
            if ($shop->blog_option) {
                // 店舗IDと年月をシードとして使用して、毎月同じ4日を選ぶ
                mt_srand($shop->id * 10000 + $year * 100 + $month);
                
                // 月の全平日を取得（土日祝を除外）
                $weekdays = [];
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = Carbon::create($year, $month, $day);
                    if (HolidayHelper::isWeekday($date)) {
                        $weekdays[] = $day;
                    }
                }
                
                // 平日からランダムに4日を選択
                if (count($weekdays) >= 4) {
                    $selectedKeys = array_rand($weekdays, 4);
                    if (is_array($selectedKeys)) {
                        foreach ($selectedKeys as $key) {
                            $blogDisplayDays[] = $weekdays[$key];
                        }
                    } else {
                        $blogDisplayDays[] = $weekdays[$selectedKeys];
                    }
                    sort($blogDisplayDays);
                } else {
                    // 平日が4日未満の場合は全て選択
                    $blogDisplayDays = $weekdays;
                }
                
                // シードをリセット
                mt_srand();
            }

            // 動画ノルマに応じた表示日を決定（土日祝を除外し、ブログ投稿日も可能な限り除外してランダムに選択）
            $videoDisplayDays = [];
            if ($videoTarget) {
                // 店舗IDと年月をシードとして使用して、毎月同じ日を選ぶ
                // ブログ投稿日と異なるシードを使用（+1して区別）
                mt_srand($shop->id * 10000 + $year * 100 + $month + 1);
                
                // 月の全平日を取得（土日祝を除外）
                $weekdays = [];
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = Carbon::create($year, $month, $day);
                    if (HolidayHelper::isWeekday($date)) {
                        $weekdays[] = $day;
                    }
                }
                
                // ブログ投稿日を除外した平日リストを作成
                $availableWeekdays = array_diff($weekdays, $blogDisplayDays);
                
                // 除外後の平日数が動画ノルマ数以上の場合のみ、ブログ投稿日を除外
                // そうでない場合は、仕方なく重なることを許可
                $targetWeekdays = count($availableWeekdays) >= $videoTarget ? $availableWeekdays : $weekdays;
                $targetWeekdays = array_values($targetWeekdays); // インデックスを再振り当て
                
                // 平日からランダムに動画ノルマ数だけ選択
                $targetCount = min($videoTarget, count($targetWeekdays));
                if ($targetCount > 0) {
                    if (count($targetWeekdays) >= $targetCount) {
                        $selectedKeys = array_rand($targetWeekdays, $targetCount);
                        if (is_array($selectedKeys)) {
                            foreach ($selectedKeys as $key) {
                                $videoDisplayDays[] = $targetWeekdays[$key];
                            }
                        } else {
                            $videoDisplayDays[] = $targetWeekdays[$selectedKeys];
                        }
                        sort($videoDisplayDays);
                    } else {
                        // 平日がノルマ数未満の場合は全て選択
                        $videoDisplayDays = $targetWeekdays;
                    }
                }
                
                // シードをリセット
                mt_srand();
            }

            // 写真ノルマに応じた表示日を決定（土日祝を除外し、ブログ・動画投稿日も可能な限り除外してランダムに選択）
            $photoDisplayDays = [];
            if (!is_null($photoTarget) && $photoTarget > 0) {
                // 店舗IDと年月をシードとして使用して、毎月同じ日を選ぶ
                // ブログ・動画投稿日と異なるシードを使用（+2して区別）
                mt_srand($shop->id * 10000 + $year * 100 + $month + 2);
                
                // 月の全平日を取得（土日祝を除外）
                $weekdays = [];
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = Carbon::create($year, $month, $day);
                    if (HolidayHelper::isWeekday($date)) {
                        $weekdays[] = $day;
                    }
                }
                
                // ブログ・動画投稿日を除外した平日リストを作成
                $excludedDays = array_unique(array_merge($blogDisplayDays, $videoDisplayDays));
                $availableWeekdays = array_diff($weekdays, $excludedDays);
                
                // 除外後の平日数が写真ノルマ数以上の場合のみ、ブログ・動画投稿日を除外
                // そうでない場合は、仕方なく重なることを許可
                $targetWeekdays = count($availableWeekdays) >= $photoTarget ? $availableWeekdays : $weekdays;
                $targetWeekdays = array_values($targetWeekdays); // インデックスを再振り当て
                
                // 平日からランダムに写真ノルマ数だけ選択
                $targetCount = min($photoTarget, count($targetWeekdays));
                if ($targetCount > 0) {
                    if (count($targetWeekdays) >= $targetCount) {
                        $selectedKeys = array_rand($targetWeekdays, $targetCount);
                        if (is_array($selectedKeys)) {
                            foreach ($selectedKeys as $key) {
                                $photoDisplayDays[] = $targetWeekdays[$key];
                            }
                        } else {
                            $photoDisplayDays[] = $targetWeekdays[$selectedKeys];
                        }
                        sort($photoDisplayDays);
                    } else {
                        // 平日がノルマ数未満の場合は全て選択
                        $photoDisplayDays = $targetWeekdays;
                    }
                }
                
                // シードをリセット
                mt_srand();
                
                // デバッグログ（ショップID1のみ）
                if ($shop->id == 1) {
                    Log::info('PHOTO_DISPLAY_DAYS_CALCULATION', [
                        'shop_id' => $shop->id,
                        'photo_target' => $photoTarget,
                        'photo_monthly_target' => $shop->photo_monthly_target,
                        'weekdays_count' => count($weekdays),
                        'excluded_days' => $excludedDays,
                        'available_weekdays_count' => count($availableWeekdays),
                        'target_weekdays_count' => count($targetWeekdays),
                        'target_count' => $targetCount,
                        'photo_display_days' => $photoDisplayDays,
                    ]);
                }
            } else {
                // デバッグログ（ショップID1のみ）
                if ($shop->id == 1) {
                    Log::info('PHOTO_DISPLAY_DAYS_SKIPPED', [
                        'shop_id' => $shop->id,
                        'photo_target' => $photoTarget,
                        'photo_monthly_target' => $shop->photo_monthly_target,
                        'is_null' => is_null($photoTarget),
                        'is_greater_than_zero' => $photoTarget > 0,
                    ]);
                }
            }

            $scheduleData[] = [
                'shop' => $shop,
                'review_target' => $reviewTarget,
                'photo_target' => $photoTarget,
                'video_target' => $videoTarget,
                'video_display_days' => $videoDisplayDays,
                'blog_display_days' => $blogDisplayDays,
                'photo_display_days' => $photoDisplayDays,
                'daily' => $daily,
                'review_total' => $reviewTotal,
                'photo_total' => $photoTotal,
                'post_total' => $postTotal,
                'review_diff' => $reviewTotal - $reviewTarget,
                'photo_diff' => $photoTotal - $photoTarget,
            ];
        }

        // 絞り込み用のマスタデータを取得
        $salesPersons = \App\Models\SalesPerson::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        $operationPersons = \App\Models\OperationPerson::where('is_active', true)->orderBy('display_order')->orderBy('id')->get();
        
        // セッションから同期日付を取得（次回まで保持）
        $syncStartDate = session('sync_start_date');
        $syncEndDate = session('sync_end_date');
        
        return view('shops.schedule', compact(
            'targetDate',
            'prevMonth',
            'nextMonth',
            'daysInMonth',
            'scheduleData',
            'year',
            'month',
            'status',
            'shopsForSync',
            'salesPersons',
            'operationPersons',
            'salesPersonId',
            'operationPersonId',
            'syncStartDate',
            'syncEndDate',
            'rankFetchedCountByDate',
            'user'
        ));
    }

    public function scheduleSync(Request $request)
    {
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
            return redirect()->route('shops.schedule', [
                'year' => $request->get('year', now()->year),
                'month' => $request->get('month', now()->month),
            ])->with('error', '開始日が終了日より後になっています。');
        }

        // オペレーション担当で絞り込み
        $operationPersonId = $request->input('operation_person_id');
        



// ★最優先：オペレーター専用ログイン対応（完全版）
$user = Auth::user();
$sessionOperatorId = session('operator_id');

// デバッグログ（本番解析用）
\Log::info('SCHEDULE_SYNC_AUTH_STATE', [
    'auth_user_id' => $user->id ?? null,
    'session_operator_id' => $sessionOperatorId,
]);

// ▼オペレーター最優先（Auth::user() が null でも正常動作）
if ($sessionOperatorId) {
    $operatorId = $sessionOperatorId;

// ▼管理画面ログインユーザー
} elseif ($user) {

    // customer_scope 正規化（安全）
    $customerScope = strtolower(trim($user->customer_scope ?? 'all'));
    $isAdmin = (bool) ($user->is_admin ?? false);

    // customer_scope = all は全店舗許可
    if ($isAdmin || $customerScope === 'all') {
        $operatorId = null; // 全店舗同期可能
    } else {
        // 自分の担当のみ
        if (!$user->operator_id) {
            return redirect()->route('shops.schedule', [
                'year' => $request->get('year', now()->year),
                'month' => $request->get('month', now()->month),
            ])->with('error', 'operator_id が未設定です。');
        }
        $operatorId = $user->operator_id;
    }

// ▼完全未ログイン（異常）
} else {
    return redirect()->route('login');
}












        
        if ($shopId === 'all') {
            // 全店舗同期の場合、契約中の店舗のみを取得
            $shopQuery = Shop::whereNotNull('gbp_location_id')
                ->where(function ($query) use ($today) {
                    $query->whereNull('contract_end_date')
                          ->orWhere('contract_end_date', '>=', $today);
                });
            
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
                ->where('id', $shopId)
                ->firstOrFail();
            
            // オペレーターの場合は自分の担当店舗のみアクセス可能（operator_shopsテーブルを使用）
            if ($operatorId !== null) {
                $isAssigned = \App\Models\OperatorShop::where('operator_id', $operatorId)
                    ->where('shop_id', $shop->id)
                    ->exists();
                
                if (!$isAssigned) {
                    $routeName = $operatorId ? 'operator.schedule' : 'shops.schedule';
                    return redirect()->route($routeName, [
                        'year' => $request->get('year', now()->year),
                        'month' => $request->get('month', now()->month),
                    ])->with('error', 'この店舗を同期する権限がありません。');
                }
            }
            
            if (!$shop->isContractActive()) {
                $routeName = $operatorId ? 'operator.schedule' : 'shops.schedule';
                return redirect()->route($routeName, [
                    'year' => $request->get('year', now()->year),
                    'month' => $request->get('month', now()->month),
                ])->with('error', '契約が終了している店舗の同期はできません。');
            }
            
            $shops = collect([$shop]);
        }

        // 店舗IDの配列を取得
        $shopIds = $shops->pluck('id')->toArray();
        $shopCount = count($shopIds);

        // 1店舗の場合は直列処理、複数店舗の場合は並列処理
        if ($shopCount <= 1) {
            // ✅ 1店舗は直列処理
            $totalPhotosInserted = 0;
            $totalPhotosUpdated = 0;
            $totalReviewsSynced = 0;
            $totalPostsSynced = 0;
            $errors = [];

            foreach ($shops as $shop) {
                try {
                    // アクセストークンを取得
                    $accessToken = $this->googleService->getAccessToken($shop);
                    
                    if (!$accessToken) {
                        // より詳細なエラーメッセージを生成
                        $errorMsg = "{$shop->name}: アクセストークンの取得に失敗しました。";
                        if (empty($shop->gbp_refresh_token)) {
                            $errorMsg = "{$shop->name}: リフレッシュトークンが設定されていません。OAuth認証を再度実行してください。";
                        } else {
                            $errorMsg = "{$shop->name}: リフレッシュトークンからのアクセストークン取得に失敗しました。OAuth認証を再度実行してください。";
                        }
                        $errors[] = $errorMsg;
                        continue;
                    }

                    // 口コミを同期（期間指定あり）
                    if (!$shop->gbp_account_id || !$shop->gbp_location_id) {
                        $errors[] = "{$shop->name}: GBPアカウントIDまたはロケーションIDが設定されていません。";
                        continue;
                    }
                    
                    // オペレーターの場合は自分の担当店舗のみアクセス可能（operator_shopsテーブルを使用）
                    if ($operatorId !== null) {
                        $isAssigned = \App\Models\OperatorShop::where('operator_id', $operatorId)
                            ->where('shop_id', $shop->id)
                            ->exists();
                        
                        if (!$isAssigned) {
                            $errors[] = "{$shop->name}: この店舗を同期する権限がありません。";
                            continue;
                        }
                    }

                    // スナップショットを作成（ユーザー別に分離）
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
                    $reviewsSynced = $this->syncReviews($shop, $accessToken, $snapshot->id, $sinceDate);
                    $totalReviewsSynced += $reviewsSynced;

                    // 写真を同期（完全差分同期）
                    // GBPデータはSingle Source of Truthとして保存（operator_idは関係ない）
                    $photoResult = $this->syncPhotos($shop, $accessToken, $snapshot->id, $sinceDate);
                    $totalPhotosInserted += $photoResult['inserted'];
                    $totalPhotosUpdated += $photoResult['updated'];
                    
                    // 投稿を同期（Service経由で保存）
                    // GBPデータはSingle Source of Truthとして保存（operator_idは関係ない）
                    $postResult = $this->googleService->syncLocalPostsAndSave($shop, $sinceDate);
                    $postsCount = $postResult['inserted'] + $postResult['updated'];
                    $totalPostsSynced += $postsCount;
                    
                    // スナップショットの数を更新（写真は追加+更新の合計）
                    $snapshot->update([
                        'photos_count' => $photoResult['inserted'] + $photoResult['updated'],
                        'reviews_count' => $reviewsSynced,
                        'posts_count' => $postsCount,
                    ]);

                    Log::info('口コミ・写真・投稿同期完了', [
                        'shop_id' => $shop->id,
                        'shop_name' => $shop->name,
                        'reviews_synced' => $reviewsSynced,
                        'photos_inserted' => $photoResult['inserted'],
                        'photos_updated' => $photoResult['updated'],
                        'posts_synced' => $postsCount,
                    ]);

                } catch (\Exception $e) {
                    Log::error('写真同期エラー', [
                        'shop_id' => $shop->id,
                        'shop_name' => $shop->name,
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

            $routeName = $operatorId ? 'operator.schedule' : 'shops.schedule';
            return redirect()->route($routeName, [
                'year' => $request->get('year', now()->year),
                'month' => $request->get('month', now()->month),
            ])->with('success', "{$shopCount}店舗の同期処理を開始しました。バックグラウンドで実行されます。")
              ->with('sync_batch_id', $syncBatch->id);
        }
        
            // 口コミのメッセージを構築
            $reviewMessages = [];
            if ($totalReviewsSynced > 0) {
                $reviewMessages[] = "口コミ {$totalReviewsSynced}件を同期しました";
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
            
            $message = implode("、", array_merge($reviewMessages, $photoMessages, $postMessages)) . "。";
            if (!empty($errors)) {
                $message .= " エラー: " . implode(', ', $errors);
            }
            
            // オペレーターの場合はオペレーター用ルートにリダイレクト
            $routeName = $operatorId ? 'operator.schedule' : 'shops.schedule';
            
            return redirect()->route($routeName, [
                'year' => $request->get('year', now()->year),
                'month' => $request->get('month', now()->month),
            ])->with($errors ? 'warning' : 'success', $message);
    }

    /**
     * 口コミを同期（ReviewSyncServiceを使用）
     */
    private function syncReviews(Shop $shop, string $accessToken, int $snapshotId, string $sinceDate): int
    {
        // ReviewSyncServiceを使用して差分同期を実行
        $reviewSyncService = new \App\Services\ReviewSyncService();
        $result = $reviewSyncService->syncShop($shop, $accessToken, $this->googleService, $snapshotId, $sinceDate);
        
        return $result['inserted_count'] + $result['updated_count'];
        
        if (empty($reviewsResponse)) {
            Log::warning('口コミの取得に失敗: レスポンスが空', [
                'shop_id' => $shop->id,
            ]);
            return 0;
        }

        // レスポンスの構造を確認（reviewsキーがあるか、または直接配列か）
        $reviews = [];
        if (isset($reviewsResponse['reviews'])) {
            $reviews = $reviewsResponse['reviews'];
        } elseif (is_array($reviewsResponse) && isset($reviewsResponse[0])) {
            // 直接配列の場合
            $reviews = $reviewsResponse;
        }

        // reviews[i].name をログ出力
        $reviewNames = [];
        foreach ($reviews as $index => $reviewData) {
            $reviewName = $reviewData['name'] ?? null;
            $reviewNames[] = [
                'index' => $index,
                'name' => $reviewName,
                'reviewId' => $reviewData['reviewId'] ?? null,
            ];
        }
        
        Log::info('口コミAPIレスポンス', [
            'shop_id' => $shop->id,
            'reviews_count' => count($reviews),
            'review_names' => $reviewNames,
        ]);

        $syncedCount = 0;

        foreach ($reviews as $reviewData) {
            try {
                // 期間指定がある場合は、createTimeでフィルタリング
                if ($startDate || $endDate) {
                    $createTime = isset($reviewData['createTime']) ? Carbon::parse($reviewData['createTime']) : null;
                    
                    if ($createTime) {
                        if ($startDate && $createTime->lt($startDate)) {
                            continue;
                        }
                        if ($endDate && $createTime->gt($endDate)) {
                            continue;
                        }
                    } else {
                        // createTimeがない場合はスキップ（期間指定時）
                        continue;
                    }
                }
                
                // gbp_review_idを取得（"accounts/{accountId}/locations/{locationId}/reviews/{reviewId}" の形式から最後の要素のみ）
                $reviewName = $reviewData['name'] ?? null;
                if (!$reviewName) {
                    continue;
                }

                // name の最後の要素（reviewId）のみを取得
                $gbpReviewIdClean = basename($reviewName);

                // レビュアー情報
                $reviewer = $reviewData['reviewer'] ?? [];
                $authorName = $reviewer['displayName'] ?? '匿名';

                // 評価（Google APIは "FIVE", "FOUR", "THREE", "TWO", "ONE" の形式で返す可能性がある）
                $starRating = $reviewData['starRating'] ?? null;
                $rating = null;
                
                if ($starRating !== null) {
                    // 文字列の場合は数値に変換
                    if (is_string($starRating)) {
                        $ratingMap = [
                            'FIVE' => 5,
                            'FOUR' => 4,
                            'THREE' => 3,
                            'TWO' => 2,
                            'ONE' => 1,
                        ];
                        $rating = $ratingMap[strtoupper($starRating)] ?? (int)$starRating;
                    } else {
                        $rating = (int)$starRating;
                    }
                }

                // コメント
                $comment = $reviewData['comment'] ?? null;

                // 作成日時
                $createTime = isset($reviewData['createTime']) 
                    ? Carbon::parse($reviewData['createTime']) 
                    : null;

                // 返信情報
                $reply = $reviewData['reviewReply'] ?? null;
                $replyText = $reply['comment'] ?? null;
                $repliedAt = isset($reply['updateTime']) 
                    ? Carbon::parse($reply['updateTime']) 
                    : null;

                // shop単位でユニークにする（shop_id + gbp_review_id）
                // 同じgbp_review_idは常に1件のみ、返信が変わればupdate、snapshot_idは最後に同期されたsnapshotとして上書き
                Review::updateOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'gbp_review_id' => $gbpReviewIdClean,
                    ],
                    [
                        'snapshot_id' => $snapshotId,
                        'author_name' => $authorName,
                        'rating' => $rating,
                        'comment' => $comment,
                        'create_time' => $createTime,
                        'reply_text' => $replyText,
                        'replied_at' => $repliedAt,
                    ]
                );

                $syncedCount++;

            } catch (\Exception $e) {
                Log::error('口コミの保存エラー', [
                    'shop_id' => $shop->id,
                    'review_data' => $reviewData,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $syncedCount;
    }

    /**
     * 写真を同期
     */
    private function syncPhotos(Shop $shop, string $accessToken, int $snapshotId, string $sinceDate): array
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
        $mediaResponse = $this->googleService->listMedia($accessToken, $shop->gbp_account_id, $shop->gbp_location_id);
        
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
     * Google連携を開始（OAuth認証URLにリダイレクト）
     */
    public function connect(Shop $shop)
    {
        // 契約終了日を確認
        if (!$shop->isContractActive()) {
            return redirect()->route('shops.show', $shop)
                ->with('error', '契約が終了している店舗のGoogle連携はできません。');
        }

        // 既存のApp-onlyトークンがある場合は削除
        if ($shop->gbp_refresh_token || $shop->gbp_access_token) {
            $isAppOnly = false;
            
            // アクセストークンがApp-onlyかどうかを確認
            if ($shop->gbp_access_token) {
                try {
                    $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($shop->gbp_access_token);
                    $tokenInfoResponse = Http::get($tokenInfoUrl);
                    
                    if ($tokenInfoResponse->successful()) {
                        $tokenInfoData = $tokenInfoResponse->json();
                        $email = $tokenInfoData['email'] ?? null;
                        
                        if (!$email) {
                            $isAppOnly = true;
                        }
                        // emailが含まれていればUser OAuthなので有効（どのアカウントでもOK）
                    }
                } catch (\Exception $e) {
                    // エラーが発生した場合は再認証を推奨
                    $isAppOnly = true;
                }
            }
            
            // リフレッシュトークンからアクセストークンを取得して検証
            if (!$isAppOnly && $shop->gbp_refresh_token) {
                try {
                    $refreshResult = $this->googleService->refreshAccessToken($shop->gbp_refresh_token);
                    
                    if ($refreshResult && $refreshResult['access_token']) {
                        $newAccessToken = $refreshResult['access_token'];
                        $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($newAccessToken);
                        $tokenInfoResponse = Http::get($tokenInfoUrl);
                        
                        if ($tokenInfoResponse->successful()) {
                            $tokenInfoData = $tokenInfoResponse->json();
                            $email = $tokenInfoData['email'] ?? null;
                            
                            if (!$email) {
                                $isAppOnly = true;
                            }
                            // emailが含まれていればUser OAuthなので有効（どのアカウントでもOK）
                        }
                    }
                } catch (\Exception $e) {
                    // エラーが発生した場合は再認証を推奨
                    $isAppOnly = true;
                }
            }
            
            // App-onlyトークンの場合は削除
            if ($isAppOnly) {
                $shop->update([
                    'gbp_refresh_token' => null,
                    'gbp_access_token' => null,
                ]);
                
                Log::info('GBP_APP_ONLY_TOKEN_CLEARED_BEFORE_RECONNECT', [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                ]);
            }
        }

        try {
            $authUrl = $this->googleService->getAuthUrl($shop->id);
            
            Log::info('Google OAuth認証URLを生成', [
                'shop_id' => $shop->id,
                'auth_url' => $authUrl,
            ]);
            
            return redirect($authUrl);
        } catch (\Exception $e) {
            Log::error('Google連携エラー', [
                'shop_id' => $shop->id,
                'message' => $e->getMessage(),
            ]);
            
            return redirect()->route('shops.show', $shop)
                ->with('error', 'Google連携の開始に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * Google OAuth コールバック処理
     */
    public function googleCallback(Request $request)
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $error = $request->get('error');

        if ($error) {
            Log::error('Google OAuth認証エラー', [
                'error' => $error,
                'state' => $state,
            ]);
            
            return redirect()->route('shops.index')
                ->with('error', 'Google認証がキャンセルされました: ' . $error);
        }

        if (!$code || !$state) {
            Log::error('Google OAuthコールバックパラメータ不足', [
                'code' => $code ? 'あり' : 'なし',
                'state' => $state ? 'あり' : 'なし',
            ]);
            
            return redirect()->route('shops.index')
                ->with('error', '認証パラメータが不足しています。');
        }

        try {
            // stateからshop_idを取得
            $stateData = json_decode(base64_decode($state), true);
            $shopId = $stateData['shop_id'] ?? null;

            if (!$shopId) {
                Log::error('Google OAuth stateからshop_idが取得できない', [
                    'state' => $state,
                ]);
                
                return redirect()->route('shops.index')
                    ->with('error', '店舗情報が取得できませんでした。');
            }

            $shop = Shop::findOrFail($shopId);

            // アクセストークンとリフレッシュトークンを取得
            $tokens = $this->googleService->getTokensFromCode($code);

            if (empty($tokens) || !isset($tokens['access_token']) || !isset($tokens['refresh_token'])) {
                Log::error('Google OAuthトークン取得失敗', [
                    'shop_id' => $shopId,
                    'tokens' => $tokens,
                ]);
                
                return redirect()->route('shops.show', $shop)
                    ->with('error', 'アクセストークンの取得に失敗しました。');
            }

            // アクセストークンを取得
            $accessToken = $tokens['access_token'];

            // User OAuth確認: tokeninfoでemailを確認（必須）
            $isValidUserOAuth = false;
            $userEmail = null;
            
            try {
                $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($accessToken);
                $tokenInfoResponse = Http::get($tokenInfoUrl);
                
                if ($tokenInfoResponse->successful()) {
                    $tokenInfoData = $tokenInfoResponse->json();
                    $userEmail = $tokenInfoData['email'] ?? null;
                    
                    Log::info('Google OAuth tokeninfo確認', [
                        'shop_id' => $shopId,
                        'email' => $userEmail,
                        'tokeninfo_data' => $tokenInfoData,
                    ]);

                    if ($userEmail) {
                        // User OAuth（email付き）であれば有効（どのアカウントでもOK）
                        $isValidUserOAuth = true;
                        Log::info('Google OAuth User OAuth確認成功', [
                            'shop_id' => $shopId,
                            'email' => $userEmail,
                            'email_verified' => true,
                        ]);
                    } else {
                        Log::error('Google OAuth tokeninfoにemailが含まれていません（App-only OAuth）', [
                            'shop_id' => $shopId,
                            'tokeninfo_data' => $tokenInfoData,
                        ]);
                    }
                } else {
                    Log::error('Google OAuth tokeninfo取得失敗', [
                        'shop_id' => $shopId,
                        'status' => $tokenInfoResponse->status(),
                        'body' => $tokenInfoResponse->body(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Google OAuth tokeninfo確認中にエラー', [
                    'shop_id' => $shopId,
                    'error' => $e->getMessage(),
                ]);
            }

            // User OAuth（email付き）でない場合はエラー
            if (!$isValidUserOAuth) {
                $errorMessage = 'emailが含まれていないトークン（App-only OAuth）は使用できません。authorization_codeフローで認証してください。';
                
                Log::error('Google OAuth User OAuth検証失敗', [
                    'shop_id' => $shopId,
                    'user_email' => $userEmail,
                    'message' => $errorMessage,
                ]);
                
                // App-onlyトークンが保存されている場合は削除
                if ($shop->gbp_refresh_token || $shop->gbp_access_token) {
                    $shop->update([
                        'gbp_refresh_token' => null,
                        'gbp_access_token' => null,
                    ]);
                    Log::info('App-onlyトークンを削除', [
                        'shop_id' => $shopId,
                    ]);
                }
                
                return redirect()->route('shops.show', $shop)
                    ->with('error', $errorMessage);
            }

            // リフレッシュトークンとアクセストークンを保存（User OAuth確認済み）
            $shop->update([
                'gbp_refresh_token' => $tokens['refresh_token'],
                'gbp_access_token' => $accessToken, // User OAuthのaccess_tokenを保存
            ]);

            Log::info('Google OAuth User OAuthトークン保存成功', [
                'shop_id' => $shopId,
                'email' => $userEmail,
                'has_access_token' => !empty($accessToken),
                'has_refresh_token' => !empty($tokens['refresh_token']),
            ]);

            // Google Business Profile APIを呼び出してアカウントとロケーションを取得
            
            // 1. accounts.list (v1) を実行（Business Profile API v1用）
            $accountsResponse = $this->googleService->listAccounts($accessToken);
            
            Log::info('Google Business Profile API accounts.list (v1) レスポンス', [
                'shop_id' => $shopId,
                'response' => $accountsResponse,
            ]);

            if (empty($accountsResponse) || !isset($accountsResponse['accounts'])) {
                return redirect()->route('shops.show', $shop)
                    ->with('error', 'Google Business Profileアカウントの取得に失敗しました。');
            }

            $accounts = $accountsResponse['accounts'] ?? [];
            
            if (empty($accounts)) {
                return redirect()->route('shops.show', $shop)
                    ->with('error', 'Google Business Profileアカウントが見つかりませんでした。');
            }

            // 最初のアカウントを使用（複数アカウントがある場合は最初のものを使用）
            $account = $accounts[0];
            $accountId = $account['name'] ?? null; // "accounts/123456789" の形式

            if (!$accountId) {
                return redirect()->route('shops.show', $shop)
                    ->with('error', 'アカウントIDが取得できませんでした。');
            }

            // v1用のアカウントIDを保存（"accounts/"プレフィックスを除去）
            $accountIdClean = str_replace('accounts/', '', $accountId);
            $shop->update([
                'gbp_account_id' => $accountIdClean,
            ]);

            Log::info('Google Business ProfileアカウントID (v1) を保存', [
                'shop_id' => $shopId,
                'account_id' => $accountIdClean,
            ]);

            // 注意: v4のaccountIdという概念は不要
            // reviews.list のレスポンスに含まれる review.name が唯一の正しい識別子
            // そのため、v4用のaccountIdを取得・保存する処理は不要

            // 2. locations.list を実行（accountIdCleanを使用：プレフィックスなし）
            $locationsResponse = $this->googleService->listLocations($accessToken, $accountIdClean);

            Log::info('Google Business Profile API locations.list レスポンス', [
                'shop_id' => $shopId,
                'account_id' => $accountIdClean,
                'response' => $locationsResponse,
            ]);

            if (empty($locationsResponse) || !isset($locationsResponse['locations'])) {
                return redirect()->route('shops.show', $shop)
                    ->with('error', 'Google Business Profileロケーションの取得に失敗しました。');
            }

            $locations = $locationsResponse['locations'] ?? [];

            if (empty($locations)) {
                return redirect()->route('shops.show', $shop)
                    ->with('error', 'Google Business Profileロケーションが見つかりませんでした。');
            }

            // ロケーションをセッションに保存（DBには保存しない）
            session([
                'google_locations' => $locations,
                'google_account_id' => $accountIdClean,
                'google_shop_id' => $shop->id,
            ]);

            Log::info('Google Business Profileロケーションをセッションに保存', [
                'shop_id' => $shopId,
                'account_id' => $accountIdClean,
                'locations_count' => count($locations),
            ]);

            // ロケーション選択画面にリダイレクト
            return redirect()->route('shops.select-gbp-location', $shop);

        } catch (\Exception $e) {
            Log::error('Google連携コールバック処理エラー', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('shops.index')
                ->with('error', 'Google連携処理中にエラーが発生しました: ' . $e->getMessage());
        }
    }

    public function blogHistory(Shop $shop)
    {
        // ブログ履歴データを取得（後で実装：実際のデータベースから取得）
        $blogHistory = collect(); // 空のコレクション
        
        return view('shops.blog-history', compact('shop', 'blogHistory'));
    }


    public function storeBlogCrawlSettings(Request $request, Shop $shop)
    {
        // 契約終了日を確認
        if (!$shop->isContractActive()) {
            return redirect()->route('shops.show', $shop)
                ->with('error', '契約が終了している店舗のブログクロール設定はできません。');
        }

        // ブログクロール設定の保存（後で実装）
        return redirect()->route('shops.show', $shop)->with('success', 'ブログクロール設定を保存しました。');
    }

    public function updateBlogCrawlSettings(Request $request, Shop $shop, $setting)
    {
        // 契約終了日を確認
        if (!$shop->isContractActive()) {
            return redirect()->route('shops.show', $shop)
                ->with('error', '契約が終了している店舗のブログクロール設定はできません。');
        }

        // ブログクロール設定の更新（後で実装）
        return redirect()->route('shops.show', $shop)->with('success', 'ブログクロール設定を更新しました。');
    }

    public function destroyBlogCrawlSettings(Request $request, Shop $shop, $setting)
    {
        // 契約終了日を確認
        if (!$shop->isContractActive()) {
            return redirect()->route('shops.show', $shop)
                ->with('error', '契約が終了している店舗のブログクロール設定はできません。');
        }

        // ブログクロール設定の削除（後で実装）
        return redirect()->route('shops.show', $shop)->with('success', 'ブログクロール設定を削除しました。');
    }

    /**
     * Google Business Profile ロケーション選択画面を表示
     */
    public function selectGbpLocation(Shop $shop)
    {
        // セッションからロケーション情報を取得
        $locations = session('google_locations', []);
        $accountId = session('google_account_id');
        $sessionShopId = session('google_shop_id');

        // セッションにデータがない、または別の店舗の場合はエラー
        if (empty($locations) || $sessionShopId != $shop->id) {
            return redirect()->route('shops.show', $shop)
                ->with('error', 'Google連携セッションが無効です。再度Google連携を行ってください。');
        }

        return view('shops.select-gbp-location', compact('shop', 'locations', 'accountId'));
    }

    /**
     * 選択したGoogle Business Profile ロケーションを保存
     */
    public function storeGbpLocation(Request $request, Shop $shop)
    {
        $request->validate([
            'location_id' => 'required|string',
        ]);

        // セッションからロケーション情報を取得
        $locations = session('google_locations', []);
        $sessionShopId = session('google_shop_id');

        // セッションにデータがない、または別の店舗の場合はエラー
        if (empty($locations) || $sessionShopId != $shop->id) {
            return redirect()->route('shops.show', $shop)
                ->with('error', 'Google連携セッションが無効です。再度Google連携を行ってください。');
        }

        $locationId = $request->input('location_id');
        
        // location_idは "locations/123456789" の形式で送られてくる

        // 選択されたロケーションがセッション内に存在するか確認
        $selectedLocation = null;
        foreach ($locations as $location) {
            $locationName = $location['name'] ?? null;
            if ($locationName === $locationId) {
                $selectedLocation = $location;
                break;
            }
        }

        if (!$selectedLocation) {
            return redirect()->route('shops.select-gbp-location', $shop)
                ->with('error', '選択されたロケーションが見つかりませんでした。');
        }

        // Place IDを抽出
        // Google Business Profile API v1 の locations.list レスポンスには Place ID が直接含まれていない可能性が高い
        // そのため、住所から Google Places API を使用して Place ID を取得する
        $placeId = null;
        
        // ログ出力: レスポンス構造を確認
        Log::info('Google Business Profileロケーション選択時のレスポンス構造', [
            'shop_id' => $shop->id,
            'location_keys' => array_keys($selectedLocation ?? []),
            'location_data' => $selectedLocation,
        ]);

        // 住所から Place ID を取得を試みる
        if (isset($selectedLocation['storefrontAddress']['addressLines']) && !empty($selectedLocation['storefrontAddress']['addressLines'])) {
            $addressLines = $selectedLocation['storefrontAddress']['addressLines'];
            $address = implode(' ', $addressLines);
            
            // Google Places API を使用して Place ID を取得
            // 注意: Google Places API の API キーが必要です
            // 現時点では、手動入力に依存するため、ここでは取得を試みない
            // 将来的に Google Places API を実装する場合は、ここで取得処理を追加
        }

        // shops.gbp_location_id に location['name'] をそのまま保存（例: "locations/14533069664155190447"）
        // gbp_name に title (店舗の正式名称) を保存
        // google_place_id は既存の値を保持（取得できない場合は既存の値を保持）
        $updateData = [
            'gbp_location_id' => $selectedLocation['name'],
        ];
        
        // 店舗名（title）を保存
        if (isset($selectedLocation['title'])) {
            $updateData['gbp_name'] = $selectedLocation['title'];
            Log::info('GBP店舗名を保存', [
                'shop_id' => $shop->id,
                'gbp_name' => $selectedLocation['title'],
                'location_name' => $selectedLocation['name'],
            ]);
        }
        
        // Place ID が取得できた場合のみ更新
        // 取得できない場合は既存の値を保持（$updateData に含めない）
        if ($placeId) {
            $updateData['google_place_id'] = $placeId;
            Log::info('Google Place IDを自動取得して保存', [
                'shop_id' => $shop->id,
                'place_id' => $placeId,
            ]);
        } else {
            // Place ID が取得できない場合は、既存の値を保持するため、$updateData に含めない
            // これにより、既存の google_place_id が保持される
            Log::info('Google Place IDは既存の値を保持（手動入力が必要）', [
                'shop_id' => $shop->id,
                'existing_place_id' => $shop->google_place_id,
                'address' => isset($selectedLocation['storefrontAddress']['addressLines']) ? implode(' ', $selectedLocation['storefrontAddress']['addressLines']) : null,
            ]);
        }
        
        $shop->update($updateData);

        // セッションをクリア
        session()->forget(['google_locations', 'google_account_id', 'google_shop_id']);

        Log::info('Google Business Profileロケーションを選択して保存', [
            'shop_id' => $shop->id,
            'location_id' => $selectedLocation['name'],
        ]);

        return redirect()->route('shops.show', $shop)
            ->with('success', 'Google Business Profileロケーションを連携しました。');
    }

    /**
     * 連絡履歴を保存
     */
    public function storeContactLog(Request $request, Shop $shop)
    {
        // オペレーターの場合は自分の担当店舗のみアクセス可能（operator_shopsテーブルを使用）
        // 管理者は全店舗にアクセス可能
        $user = Auth::user();
        
        // デバッグ用ログ：ユーザー情報を詳細に記録
        Log::info('CONTACT_LOG_STORE_START', [
            'shop_id' => $shop->id,
            'user_id' => $user ? $user->id : null,
            'user_class' => $user ? get_class($user) : null,
            'user_is_admin_raw' => $user ? $user->is_admin : null,
            'user_is_admin_type' => $user ? gettype($user->is_admin) : null,
            'user_is_admin_bool' => $user ? (bool)$user->is_admin : null,
            'session_operator_id' => session('operator_id'),
            'user_operator_id' => $user ? $user->operator_id : null,
            'auth_guard' => Auth::getDefaultDriver(),
            'all_auth_guards' => array_keys(config('auth.guards')),
        ]);
        
        // 管理者相当（is_admin=true または customer_scope=all）
$isAdmin = $user && (
    (bool)$user->is_admin === true
    || ($user->customer_scope ?? null) === 'all'
);
        
        Log::info('CONTACT_LOG_STORE_ADMIN_CHECK', [
            'is_admin' => $isAdmin,
            'user_exists' => $user !== null,
            'user_is_admin_value' => $user ? $user->is_admin : null,
            'user_is_admin_bool_cast' => $user ? (bool)$user->is_admin : null,
        ]);
        
        // 管理者の場合は operator_id = null で許可
        if ($isAdmin) {
            Log::info('CONTACT_LOG_STORE_ADMIN_ALLOWED', [
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'operator_id' => null,
            ]);
            // 管理者の場合は権限チェックをスキップして処理を続行
        } else {
            // オペレーターの場合のみ権限チェック
            $operatorId = session('operator_id');
            if ($user && $user->operator_id) {
                $operatorId = $user->operator_id;
            }
            
            Log::info('CONTACT_LOG_STORE_OPERATOR_CHECK', [
                'operator_id' => $operatorId,
                'shop_id' => $shop->id,
            ]);
            
            if (!$operatorId) {
                // オペレーターIDが設定されていない場合はエラー
                Log::error('CONTACT_LOG_STORE_NO_OPERATOR_ID', [
                    'shop_id' => $shop->id,
                    'user_id' => $user ? $user->id : null,
                    'user_is_admin' => $user ? $user->is_admin : null,
                    'user_is_admin_bool' => $user ? (bool)$user->is_admin : null,
                    'session_operator_id' => session('operator_id'),
                    'user_operator_id' => $user ? $user->operator_id : null,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'オペレーターIDが設定されていません。',
                ], 403);
            }
            
            $isAssigned = \App\Models\OperatorShop::where('operator_id', $operatorId)
                ->where('shop_id', $shop->id)
                ->exists();
            
            // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
            if (!$isAssigned) {
                $isAssigned = $shop->operation_person_id == $operatorId;
            }
            
            Log::info('CONTACT_LOG_STORE_ASSIGNMENT_CHECK', [
                'operator_id' => $operatorId,
                'shop_id' => $shop->id,
                'is_assigned' => $isAssigned,
                'shop_operation_person_id' => $shop->operation_person_id,
            ]);
            
            if (!$isAssigned) {
                Log::error('CONTACT_LOG_STORE_NO_ACCESS', [
                    'operator_id' => $operatorId,
                    'shop_id' => $shop->id,
                    'shop_operation_person_id' => $shop->operation_person_id,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'この店舗の連絡履歴にアクセスする権限がありません。',
                ], 403);
            }
        }

        try {
            $validated = $request->validate([
                'contact_date' => 'required|date',
                'contact_time' => 'required|date_format:H:i',
                'content' => 'required|string|max:5000',
            ]);

            // 同じ店舗・同じ日付の連絡履歴が既にある場合は更新、なければ新規作成
            // contact_date は文字列として比較する（Carbon インスタンスに変換しない）
            // ただし、データベースの date 型と比較するため、フォーマットを統一
            $contactDate = Carbon::parse($validated['contact_date'])->format('Y-m-d');
            
            // 既存レコードを検索（updateOrCreate の検索条件が正しく動作しない場合があるため、手動で検索）
            $contactLog = ContactLog::where('shop_id', $shop->id)
                ->whereDate('contact_date', $contactDate)
                ->first();
            
            if ($contactLog) {
                // 既存レコードを更新
                $contactLog->update([
                    'contact_time' => $validated['contact_time'],
                    'content' => $validated['content'],
                ]);
            } else {
                // 新規作成
                $contactLog = ContactLog::create([
                    'shop_id' => $shop->id,
                    'contact_date' => $contactDate,
                    'contact_time' => $validated['contact_time'],
                    'content' => $validated['content'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => '連絡履歴を保存しました。',
                'contact_log' => $contactLog,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('CONTACT_LOG_STORE_VALIDATION_ERROR', [
                'shop_id' => $shop->id,
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー: ' . implode(', ', array_map(function ($errors) {
                    return implode(', ', $errors);
                }, $e->errors())),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('CONTACT_LOG_STORE_ERROR', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '連絡履歴の保存中にエラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 連絡履歴を取得
     */
    public function getContactLog(Request $request, Shop $shop)
    {
        // オペレーターの場合は自分の担当店舗のみアクセス可能（operator_shopsテーブルを使用）
        // 管理者は全店舗にアクセス可能
        $user = Auth::user();
        
        Log::info('CONTACT_LOG_GET_START', [
            'shop_id' => $shop->id,
            'user_id' => $user ? $user->id : null,
            'user_is_admin' => $user ? (bool)$user->is_admin : null,
            'request_date' => $request->get('date'),
        ]);
        
        $isAdmin = $user && (
    (bool)$user->is_admin === true
    || ($user->customer_scope ?? null) === 'all'
);
        
        Log::info('CONTACT_LOG_GET_ADMIN_CHECK', [
            'is_admin' => $isAdmin,
            'user_exists' => $user !== null,
        ]);
        
        if (!$isAdmin) {
            // オペレーターの場合のみ権限チェック
            $operatorId = session('operator_id');
            if ($user && $user->operator_id) {
                $operatorId = $user->operator_id;
            }
            
            if (!$operatorId) {
                Log::error('CONTACT_LOG_GET_NO_OPERATOR_ID', [
                    'shop_id' => $shop->id,
                    'user_id' => $user ? $user->id : null,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'オペレーターIDが設定されていません。',
                ], 403);
            }
            
            $isAssigned = \App\Models\OperatorShop::where('operator_id', $operatorId)
                ->where('shop_id', $shop->id)
                ->exists();
            
            // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
            if (!$isAssigned) {
                $isAssigned = $shop->operation_person_id == $operatorId;
            }
            
            if (!$isAssigned) {
                Log::error('CONTACT_LOG_GET_NO_ACCESS', [
                    'operator_id' => $operatorId,
                    'shop_id' => $shop->id,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'この店舗の連絡履歴にアクセスする権限がありません。',
                ], 403);
            }
        }
        
        $date = $request->get('date');
        
        if (!$date) {
            Log::error('CONTACT_LOG_GET_NO_DATE', [
                'shop_id' => $shop->id,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '日付が指定されていません。',
            ], 400);
        }
        
        Log::info('CONTACT_LOG_GET_QUERY', [
            'shop_id' => $shop->id,
            'date' => $date,
        ]);
        
        $contactLog = ContactLog::where('shop_id', $shop->id)
            ->whereDate('contact_date', $date)
            ->first();
        
        Log::info('CONTACT_LOG_GET_RESULT', [
            'shop_id' => $shop->id,
            'date' => $date,
            'found' => $contactLog !== null,
            'contact_log_id' => $contactLog ? $contactLog->id : null,
            'contact_log_date' => $contactLog ? $contactLog->contact_date : null,
        ]);
        
        if ($contactLog) {
            return response()->json([
                'success' => true,
                'contact_log' => [
                    'id' => $contactLog->id,
                    'contact_date' => $contactLog->contact_date->format('Y-m-d'),
                    'contact_time' => $contactLog->contact_time, // time型はそのまま返す
                    'content' => $contactLog->content,
                ],
            ]);
        }
        
        Log::error('CONTACT_LOG_GET_NOT_FOUND', [
            'shop_id' => $shop->id,
            'date' => $date,
            'all_contact_logs_for_shop' => ContactLog::where('shop_id', $shop->id)->get(['id', 'contact_date'])->toArray(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => '連絡履歴が見つかりませんでした。',
        ], 404);
    }

    /**
     * 連絡履歴を削除
     */
    public function destroyContactLog(Request $request, Shop $shop)
    {
        // オペレーターの場合は自分の担当店舗のみアクセス可能（operator_shopsテーブルを使用）
        // 管理者は全店舗にアクセス可能
        $user = Auth::user();
        
        // 管理者判定（customer_scope=all も管理者扱い）
$isAdmin = $user && (
    (bool)$user->is_admin === true
    || ($user->customer_scope ?? null) === 'all'
);
        
        if (!$isAdmin) {
            // オペレーターの場合のみ権限チェック
            $operatorId = session('operator_id');
            if ($user && $user->operator_id) {
                $operatorId = $user->operator_id;
            }
            
            if (!$operatorId) {
                // オペレーターIDが設定されていない場合はエラー
                return response()->json([
                    'success' => false,
                    'message' => 'オペレーターIDが設定されていません。',
                ], 403);
            }
            
            $isAssigned = \App\Models\OperatorShop::where('operator_id', $operatorId)
                ->where('shop_id', $shop->id)
                ->exists();
            
            // operator_shopsテーブルにデータがない場合は、operation_person_idでフォールバック
            if (!$isAssigned) {
                $isAssigned = $shop->operation_person_id == $operatorId;
            }
            
            if (!$isAssigned) {
                return response()->json([
                    'success' => false,
                    'message' => 'この店舗の連絡履歴にアクセスする権限がありません。',
                ], 403);
            }
        }
        
        $date = $request->get('date');
        
        if (!$date) {
            return response()->json([
                'success' => false,
                'message' => '日付が指定されていません。',
            ], 400);
        }

        $contactLog = ContactLog::where('shop_id', $shop->id)
            ->whereDate('contact_date', $date)
            ->first();

        if ($contactLog) {
            $contactLog->delete();
            return response()->json([
                'success' => true,
                'message' => '連絡履歴を削除しました。',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => '連絡履歴が見つかりませんでした。',
        ], 404);
    }

    /**
     * 順位取得ジョブを登録
     */
    public function fetchRank(Request $request)
    {
        Log::info('RANK_FETCH_REQUEST_STARTED', [
            'request_data' => $request->all(),
            'user_id' => Auth::id(),
            'user_is_admin' => Auth::user()?->is_admin ?? false,
            'operator_id' => session('operator_id'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            $validated = $request->validate([
                'shop_id'     => 'required|exists:shops,id',
                'target_date' => 'required|date',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('RANK_FETCH_VALIDATION_ERROR', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー: ' . implode(', ', array_map(function ($errors) {
                    return implode(', ', $errors);
                }, $e->errors())),
                'errors' => $e->errors(),
            ], 422);
        }

        $shopId     = $validated['shop_id'];
        $targetDate = $validated['target_date'];

        $user       = Auth::user();
        $operatorId = session('operator_id');

        if ($user && $user->is_admin) {
            $requestedByType = 'admin';
            $requestedById   = $user->id;
        } else {
            if ($user && !$user->is_admin && $user->operator_id) {
                $operatorId = $user->operator_id;
            }

            if (!$operatorId) {
                Log::warning('RANK_FETCH_OPERATOR_ID_MISSING', [
                    'shop_id' => $shopId,
                    'target_date' => $targetDate,
                    'user_id' => Auth::id(),
                    'user_operator_id' => $user->operator_id ?? null,
                    'session_operator_id' => session('operator_id'),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'オペレーターIDが設定されていません。',
                ], 403);
            }

            $requestedByType = 'operator';
            $requestedById   = $operatorId;
        }

        Log::info('RANK_FETCH_AUTHENTICATION_SUCCESS', [
            'shop_id' => $shopId,
            'target_date' => $targetDate,
            'requested_by_type' => $requestedByType,
            'requested_by_id' => $requestedById,
        ]);

        DB::beginTransaction();

        try {
            // 該当店舗の全キーワードを取得
            $keywords = MeoKeyword::where('shop_id', $shopId)->get();

            Log::info('RANK_FETCH_KEYWORDS_RETRIEVED', [
                'shop_id' => $shopId,
                'keyword_count' => $keywords->count(),
                'keyword_ids' => $keywords->pluck('id')->toArray(),
            ]);

            if ($keywords->isEmpty()) {
                DB::rollBack();
                
                Log::warning('RANK_FETCH_NO_KEYWORDS', [
                    'shop_id' => $shopId,
                    'target_date' => $targetDate,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'キーワードが登録されていません。',
                ], 400);
            }

            $createdJobs = [];
            $updatedJobs = [];

            // 各キーワードに対してジョブを作成
            foreach ($keywords as $keyword) {
                $job = RankFetchJob::updateOrCreate(
                    [
                        'shop_id'        => $shopId,
                        'meo_keyword_id' => $keyword->id,
                        'target_date'    => $targetDate,
                    ],
                    [
                        'status'             => 'queued',
                        'started_at'         => null,
                        'finished_at'        => null,
                        'error_message'      => null,
                        'requested_by_type'  => $requestedByType,
                        'requested_by_id'    => $requestedById,
                    ]
                );

                if ($job->wasRecentlyCreated) {
                    $createdJobs[] = $job->id;
                } else {
                    $updatedJobs[] = $job->id;
                }
            }

            DB::commit();

            Log::info('RANK_FETCH_JOBS_CREATED', [
                'shop_id' => $shopId,
                'target_date' => $targetDate,
                'created_jobs' => $createdJobs,
                'updated_jobs' => $updatedJobs,
                'total_jobs' => count($createdJobs) + count($updatedJobs),
            ]);

            return response()->json([
                'success' => true,
                'message' => '順位取得ジョブを登録しました。',
                'created_jobs' => count($createdJobs),
                'updated_jobs' => count($updatedJobs),
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            Log::error('RANK_FETCH_DATABASE_ERROR', [
                'shop_id'     => $shopId,
                'target_date' => $targetDate,
                'error'       => $e->getMessage(),
                'error_code'  => $e->getCode(),
                'sql_state'   => $e->errorInfo[0] ?? null,
                'sql_code'    => $e->errorInfo[1] ?? null,
                'trace'       => $e->getTraceAsString(),
            ]);

            $message = '順位取得ジョブの登録に失敗しました。';
            if ($e->getCode() == 23000) { // UNIQUE制約違反
                $message = 'この日付・店舗の順位取得は既に登録済みまたは実行中です。';
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'error_code' => $e->getCode(),
            ], 500);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('RANK_FETCH_UNEXPECTED_ERROR', [
                'shop_id'     => $shopId,
                'target_date' => $targetDate,
                'error'       => $e->getMessage(),
                'error_class' => get_class($e),
                'error_code'  => $e->getCode(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '順位取得ジョブの登録に失敗しました。',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 指定日付のキーワード別順位データを削除
     */
    public function deleteRankData(Request $request)
    {
        Log::info('RANK_DELETE_REQUEST_STARTED', [
            'request_data' => $request->all(),
            'user_id' => Auth::id(),
            'user_is_admin' => Auth::user()?->is_admin ?? false,
            'operator_id' => session('operator_id'),
        ]);

        try {
            $validated = $request->validate([
                'shop_id'     => 'required|exists:shops,id',
                'target_date' => 'required|date',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('RANK_DELETE_VALIDATION_ERROR', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'バリデーションエラー: ' . implode(', ', array_map(function ($errors) {
                    return implode(', ', $errors);
                }, $e->errors())),
            ], 422);
        }

        $shopId     = $validated['shop_id'];
        $targetDate = $validated['target_date'];

        $user       = Auth::user();
        $operatorId = session('operator_id');

        // 権限チェック
        if ($user && $user->is_admin) {
            // 管理者は全店舗のデータを削除可能
        } else {
            if ($user && !$user->is_admin && $user->operator_id) {
                $operatorId = $user->operator_id;
            }

            if (!$operatorId) {
                return response()->json([
                    'success' => false,
                    'message' => 'オペレーターIDが設定されていません。',
                ], 403);
            }

            // オペレーターは自分の担当店舗のみ削除可能
            $shop = Shop::find($shopId);
            if (!$shop || $shop->operation_person_id != $operatorId) {
                return response()->json([
                    'success' => false,
                    'message' => 'この店舗のデータを削除する権限がありません。',
                ], 403);
            }
        }

        DB::beginTransaction();

        try {
            // 対象店舗のキーワードを取得
            $keywords = MeoKeyword::where('shop_id', $shopId)->pluck('id');

            if ($keywords->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'キーワードが登録されていません。',
                ], 400);
            }

            // 日付範囲を計算（JSTの1日分をUTCに変換）
            $targetDateJst = Carbon::parse($targetDate, 'Asia/Tokyo')->startOfDay();
            $targetDateEndJst = Carbon::parse($targetDate, 'Asia/Tokyo')->endOfDay();
            $targetDateStartUtc = $targetDateJst->utc();
            $targetDateEndUtc = $targetDateEndJst->utc();

            // MeoRankLogを削除
            $deletedLogs = \App\Models\MeoRankLog::whereIn('meo_keyword_id', $keywords)
                ->whereBetween('checked_at', [
                    $targetDateStartUtc->format('Y-m-d H:i:s'),
                    $targetDateEndUtc->format('Y-m-d H:i:s')
                ])
                ->delete();

            // RankFetchJobを削除
            $deletedJobs = RankFetchJob::where('shop_id', $shopId)
                ->where('target_date', $targetDate)
                ->delete();

            DB::commit();

            Log::info('RANK_DELETE_SUCCESS', [
                'shop_id' => $shopId,
                'target_date' => $targetDate,
                'deleted_logs' => $deletedLogs,
                'deleted_jobs' => $deletedJobs,
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$targetDate}のキーワード別順位データを削除しました。",
                'deleted_logs' => $deletedLogs,
                'deleted_jobs' => $deletedJobs,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('RANK_DELETE_ERROR', [
                'shop_id'     => $shopId,
                'target_date' => $targetDate,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'データの削除に失敗しました。',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * 投稿素材ストレージ：素材一覧を取得
     */
    public function getMediaAssets(Request $request, Shop $shop)
    {
        $type = $request->get('type', 'image'); // image or video
        
        $assets = ShopMediaAsset::where('shop_id', $shop->id)
            ->where('type', $type)
            ->whereNull('deleted_at')
            ->orderBy('uploaded_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'assets' => $assets->map(function ($asset) {
                // 既に使用済み（削除済み）の場合は操作不可
                $isUsed = $asset->trashed() || $asset->used_at !== null;
                
                return [
                    'id' => $asset->id,
                    'type' => $asset->type,
                    'original_filename' => $asset->original_filename,
                    'file_size' => $asset->file_size,
                    'uploaded_at' => $asset->uploaded_at->format('Y-m-d H:i:s'),
                    'preview_url' => route('media-assets.preview', $asset->id),
                    'download_url' => route('media-assets.download', $asset->id),
                    'delete_url' => route('media-assets.destroy', $asset->id),
                    'is_used' => $isUsed,
                ];
            }),
        ]);
    }

    /**
     * 投稿素材ストレージ：素材をアップロード
     */
    public function uploadMediaAsset(Request $request, Shop $shop)
    {
        $validated = $request->validate([
            'type' => 'required|in:image,video',
            'files.*' => 'required|file',
        ]);

        // ファイルタイプに応じたバリデーション
        $type = $validated['type'];
        $maxSize = $type === 'image' ? 10240 : 102400; // 画像10MB、動画100MB
        $allowedMimes = $type === 'image' 
            ? ['image/jpeg', 'image/jpg', 'image/png', 'image/webp']
            : ['video/mp4', 'video/webm', 'video/quicktime'];

        $uploadedFiles = [];
        $errors = [];

        foreach ($request->file('files') as $file) {
            // サイズチェック
            if ($file->getSize() > $maxSize * 1024) {
                $errors[] = "{$file->getClientOriginalName()}: ファイルサイズが大きすぎます（最大" . ($type === 'image' ? '10MB' : '100MB') . "）";
                continue;
            }

            // MIMEタイプチェック
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                $errors[] = "{$file->getClientOriginalName()}: 許可されていないファイル形式です";
                continue;
            }

            try {
                // ストレージパスを生成（publicディスク使用）
                $directory = "media_assets/{$shop->id}";
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                
                // publicディスクに保存
                $filePath = Storage::disk('public')->putFileAs(
                    $directory,
                    $file,
                    $filename
                );

                // 公開URLを生成
                $publicUrl = Storage::disk('public')->url($filePath);

                // データベースに保存
                $asset = ShopMediaAsset::create([
                    'shop_id' => $shop->id,
                    'type' => $type,
                    'file_path' => $filePath,
                    'public_url' => $publicUrl,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_at' => now(),
                ]);

                $uploadedFiles[] = [
                    'id' => $asset->id,
                    'original_filename' => $asset->original_filename,
                ];

                Log::info('MEDIA_ASSET_UPLOADED', [
                    'shop_id' => $shop->id,
                    'asset_id' => $asset->id,
                    'type' => $type,
                    'filename' => $asset->original_filename,
                ]);

                Log::info('MEDIA_ASSET_STORED', [
                    'shop_id' => $shop->id,
                    'asset_id' => $asset->id,
                    'file_path' => $filePath,
                    'public_url' => $publicUrl,
                ]);

            } catch (\Exception $e) {
                Log::error('MEDIA_ASSET_UPLOAD_ERROR', [
                    'shop_id' => $shop->id,
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "{$file->getClientOriginalName()}: アップロードに失敗しました";
            }
        }

        if (count($uploadedFiles) > 0) {
            return response()->json([
                'success' => true,
                'message' => count($uploadedFiles) . '件のファイルをアップロードしました',
                'uploaded_files' => $uploadedFiles,
                'errors' => $errors,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'アップロードに失敗しました',
                'errors' => $errors,
            ], 422);
        }
    }

    /**
     * 投稿素材ストレージ：素材をダウンロード（使用）
     * 旧エンドポイント（後方互換性のため残すが、新規実装では使用しない）
     * @deprecated 新しいエンドポイント /media-assets/{id}/download を使用してください
     */
    public function downloadMediaAsset(Shop $shop, ShopMediaAsset $mediaAsset)
    {
        // 新しいエンドポイントにリダイレクト
        return redirect()->route('media-assets.download', $mediaAsset);
    }


    /**
     * 投稿素材ストレージ：プレビュー表示（サムネイル）
     * GET /media-assets/{id}/preview
     */
    public function previewMediaAsset(ShopMediaAsset $mediaAsset)
    {
        $filePath = $mediaAsset->file_path;
        
        // Storageファサードでファイル存在確認
        if (!Storage::disk('public')->exists($filePath)) {
            Log::error('MEDIA_ASSET_FILE_NOT_FOUND', [
                'asset_id' => $mediaAsset->id,
                'shop_id' => $mediaAsset->shop_id,
                'file_path' => $filePath,
                'context' => 'preview',
            ]);
            abort(404);
        }

        Log::info('MEDIA_ASSET_PREVIEW_RENDERED', [
            'asset_id' => $mediaAsset->id,
            'shop_id' => $mediaAsset->shop_id,
            'file_path' => $filePath,
            'type' => $mediaAsset->type,
        ]);

        // storage/app/public のフルパスを取得
        $fullPath = storage_path('app/public/' . $filePath);
        
        return response()->file($fullPath, [
            'Content-Type' => $mediaAsset->mime_type,
        ]);
    }

    /**
     * 投稿素材ストレージ：ダウンロード（使用）
     * GET /media-assets/{id}/download
     */
    public function downloadMediaAssetById(ShopMediaAsset $mediaAsset)
    {
        // 既に削除されている場合はエラー
        if ($mediaAsset->trashed()) {
            Log::error('MEDIA_ASSET_FILE_NOT_FOUND', [
                'asset_id' => $mediaAsset->id,
                'shop_id' => $mediaAsset->shop_id,
                'file_path' => $mediaAsset->file_path,
                'context' => 'download_already_deleted',
            ]);
            abort(404, 'この素材は既に使用済みです。');
        }

        $filePath = $mediaAsset->file_path;
        
        // Storageファサードでファイル存在確認
        if (!Storage::disk('public')->exists($filePath)) {
            Log::error('MEDIA_ASSET_FILE_NOT_FOUND', [
                'asset_id' => $mediaAsset->id,
                'shop_id' => $mediaAsset->shop_id,
                'file_path' => $filePath,
                'context' => 'download',
            ]);
            abort(404, 'ファイルが見つかりません。');
        }

        Log::info('MEDIA_ASSET_DOWNLOAD_STARTED', [
            'asset_id' => $mediaAsset->id,
            'shop_id' => $mediaAsset->shop_id,
            'file_path' => $filePath,
            'original_filename' => $mediaAsset->original_filename,
        ]);

        try {
            // storage/app/public のフルパスを取得
            $fullPath = storage_path('app/public/' . $filePath);
            
            if (!file_exists($fullPath)) {
                Log::error('MEDIA_ASSET_FILE_NOT_FOUND', [
                    'asset_id' => $mediaAsset->id,
                    'shop_id' => $mediaAsset->shop_id,
                    'file_path' => $filePath,
                ]);
                abort(404, 'ファイルが見つかりません。');
            }

            Log::info('MEDIA_ASSET_DOWNLOAD_STARTED', [
                'asset_id' => $mediaAsset->id,
                'shop_id' => $mediaAsset->shop_id,
                'file_path' => $filePath,
                'original_filename' => $mediaAsset->original_filename,
            ]);

            // ダウンロード前に論理削除（used_atを記録してsoft delete）
            $mediaAsset->update([
                'used_at' => now(),
            ]);
            $mediaAsset->delete();

            Log::info('MEDIA_ASSET_DOWNLOADED_AND_DELETED', [
                'asset_id' => $mediaAsset->id,
                'shop_id' => $mediaAsset->shop_id,
                'file_path' => $filePath,
                'original_filename' => $mediaAsset->original_filename,
                'type' => $mediaAsset->type,
                'note' => '論理削除完了。物理削除は後処理で実行されます。',
            ]);

            // ダウンロードレスポンスを生成
            // deleteFileAfterSend(true) でファイルを自動削除
            return response()
                ->download($fullPath, $mediaAsset->original_filename)
                ->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            Log::error('MEDIA_ASSET_DOWNLOAD_ERROR', [
                'asset_id' => $mediaAsset->id,
                'shop_id' => $mediaAsset->shop_id,
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);
            
            abort(500, 'ダウンロードに失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * 投稿素材ストレージ：削除のみ（ダウンロードしない）
     * DELETE /media-assets/{id}
     */
    public function destroyMediaAsset(ShopMediaAsset $mediaAsset)
    {
        // 既に削除されている場合はエラー
        if ($mediaAsset->trashed()) {
            Log::warning('MEDIA_ASSET_DELETE_FAILED', [
                'asset_id' => $mediaAsset->id,
                'shop_id' => $mediaAsset->shop_id,
                'reason' => 'already_deleted',
            ]);
            abort(409, 'この素材は既に削除されています。');
        }

        // used_atが設定されている場合（ダウンロード済み）もエラー
        if ($mediaAsset->used_at) {
            Log::warning('MEDIA_ASSET_DELETE_FAILED', [
                'asset_id' => $mediaAsset->id,
                'shop_id' => $mediaAsset->shop_id,
                'reason' => 'already_used',
            ]);
            abort(409, 'この素材は既に使用済みです。');
        }

        $filePath = $mediaAsset->file_path;

        Log::info('MEDIA_ASSET_DELETE_STARTED', [
            'asset_id' => $mediaAsset->id,
            'shop_id' => $mediaAsset->shop_id,
            'file_path' => $filePath,
            'original_filename' => $mediaAsset->original_filename,
        ]);

        try {
            // ファイル存在確認
            $fullPath = storage_path('app/public/' . $filePath);
            
            if (!file_exists($fullPath)) {
                Log::error('MEDIA_ASSET_FILE_NOT_FOUND', [
                    'asset_id' => $mediaAsset->id,
                    'shop_id' => $mediaAsset->shop_id,
                    'file_path' => $filePath,
                    'context' => 'delete',
                ]);
                
                // ファイルが存在しない場合でもDBは論理削除する（整合性のため）
                $mediaAsset->delete();
                
                return redirect()->back()->with('warning', 'ファイルが見つかりませんでしたが、データベースからは削除しました。');
            }

            // ファイルを削除
            Storage::disk('public')->delete($filePath);

            // DBレコードを論理削除
            $mediaAsset->delete();

            Log::info('MEDIA_ASSET_DELETED', [
                'asset_id' => $mediaAsset->id,
                'shop_id' => $mediaAsset->shop_id,
                'file_path' => $filePath,
                'original_filename' => $mediaAsset->original_filename,
                'type' => $mediaAsset->type,
            ]);

            return redirect()->back()->with('success', '素材を削除しました。');

        } catch (\Exception $e) {
            Log::error('MEDIA_ASSET_DELETE_FAILED', [
                'asset_id' => $mediaAsset->id,
                'shop_id' => $mediaAsset->shop_id,
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', '削除に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * WordPress投稿を再実行
     * 
     * @param Shop $shop
     * @param GbpPost $gbpPost
     * @return \Illuminate\Http\RedirectResponse
     */
    public function retryWordPressPost(Shop $shop, GbpPost $gbpPost)
    {
        // shop_id一致チェック
        if ($gbpPost->shop_id !== $shop->id) {
            Log::error('WP_RETRY_SHOP_MISMATCH', [
                'shop_id' => $shop->id,
                'gbp_post_id' => $gbpPost->id,
                'gbp_post_shop_id' => $gbpPost->shop_id,
            ]);
            return redirect()->back()->with('error', '店舗IDが一致しません。');
        }

        // wp_post_enabledがtrueか確認
        if (!$shop->wp_post_enabled) {
            return redirect()->back()->with('error', 'WordPress投稿が有効になっていません。');
        }

        // wp_post_idが既に存在する場合はリセットして再実行
        if ($gbpPost->wp_post_id) {
            // 既存のwp_post_idをリセットして再実行
            $gbpPost->update([
                'wp_post_id' => null,
                'wp_post_status' => null,
                'wp_posted_at' => null,
            ]);
        } else {
            // wp_post_statusをnullに更新（再実行中）
            $gbpPost->update([
                'wp_post_status' => null,
            ]);
        }

        // PostToWordPressJobをdispatch
        PostToWordPressJob::dispatch($shop->id, $gbpPost->id);

        Log::info('WP_RETRY_DISPATCHED', [
            'shop_id' => $shop->id,
            'gbp_post_id' => $gbpPost->id,
        ]);

        return redirect()->back()->with('success', 'WordPress再投稿をキューに追加しました。');
    }

    /**
     * WordPress投稿タイプ一覧を取得
     * 
     * @param Shop $shop
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchWpPostTypes(Shop $shop)
    {
        try {
            // wp_base_url未設定時はエラー
            if (!$shop->wp_base_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'WordPressサイトURLが設定されていません。',
                ], 400);
            }

            // wp_username / wp_app_password未設定時はエラー
            // 注意: encrypted cast により、$shop->wp_app_password は自動的に復号化される
            // しかし、DBに保存されていない場合は null になる
            $username = $shop->wp_username;
            $appPassword = $shop->wp_app_password; // encrypted castにより自動的に復号化される
            
            // DBから直接取得して確認（encrypted cast をバイパス）
            $rawAppPassword = $shop->getRawOriginal('wp_app_password');
            
            Log::info('WP_FETCH_POST_TYPES_CHECK', [
                'shop_id' => $shop->id,
                'has_username' => !empty($username),
                'has_app_password' => !empty($appPassword),
                'has_raw_app_password' => !empty($rawAppPassword),
                'wp_username' => $username,
                'wp_app_password_length' => $appPassword ? strlen($appPassword) : 0,
                'raw_app_password_length' => $rawAppPassword ? strlen($rawAppPassword) : 0,
            ]);
            
            if (!$username || !$appPassword) {
                return response()->json([
                    'success' => false,
                    'message' => 'WordPress認証情報が設定されていません。',
                ], 400);
            }

            $wordPressService = new WordPressService();
            $postTypes = $wordPressService->getPostTypes($shop);

            if (empty($postTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => '投稿タイプの取得に失敗しました。WordPress接続情報を確認してください。',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'post_types' => $postTypes,
            ]);
        } catch (\Exception $e) {
            Log::error('WP_FETCH_POST_TYPES_EXCEPTION', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'エラーが発生しました: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * WordPress認証デバッグエンドポイントをテスト
     * Authorizationヘッダーが正しく届いているか確認する
     * 
     * @param Shop $shop
     * @return \Illuminate\Http\JsonResponse
     */
    public function testWpAuthDebug(Shop $shop)
    {
        try {
            // wp_base_url未設定時はエラー
            if (!$shop->wp_base_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'WordPressサイトURLが設定されていません。',
                ], 400);
            }

            // wp_username / wp_app_password未設定時はエラー
            $username = $shop->wp_username;
            $appPassword = $shop->wp_app_password;
            
            if (!$username || !$appPassword) {
                return response()->json([
                    'success' => false,
                    'message' => 'WordPress認証情報が設定されていません。',
                ], 400);
            }

            $wordPressService = new \App\Services\WordPressService();
            $result = $wordPressService->testAuthDebug($shop);

            if ($result === null) {
                return response()->json([
                    'success' => false,
                    'message' => '認証デバッグエンドポイントへのアクセスに失敗しました。',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('WP_AUTH_DEBUG_EXCEPTION', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => '認証デバッグテスト中にエラーが発生しました。',
            ], 500);
        }
    }
}

