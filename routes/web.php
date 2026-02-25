<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewsController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\GbpInsightsImportController;
use App\Http\Controllers\CompetitorAnalysisController;

// フェーズ1：web.php読み込み確認用テストルート
Route::get('/__web_test__', function () {
    return 'WEB FILE LOADED';
});

// フェーズ5：強制ルートテスト
Route::get('/__route_force_test__', function () {
    return 'FORCE ROUTE OK';
});

Route::get('/', function () {
    return view('welcome');
});

// 投稿素材ストレージ：プレビュー・ダウンロード・削除（認証不要、asset_idのみでアクセス）
Route::get('/media-assets/{mediaAsset}/preview', [App\Http\Controllers\ShopController::class, 'previewMediaAsset'])->name('media-assets.preview');
Route::get('/media-assets/{mediaAsset}/download', [App\Http\Controllers\ShopController::class, 'downloadMediaAssetById'])->name('media-assets.download');
Route::delete('/media-assets/{mediaAsset}', [App\Http\Controllers\ShopController::class, 'destroyMediaAsset'])->name('media-assets.destroy');

// オペレーター用ログイン
Route::middleware('guest')->group(function () {
    Route::get('/operator/login', [App\Http\Controllers\OperatorAuthController::class, 'showLoginForm'])->name('operator.login');
    Route::post('/operator/login', [App\Http\Controllers\OperatorAuthController::class, 'login']);
});

// オペレーター用ルート（operator.authミドルウェア）
Route::middleware('operator.auth')->prefix('operator')->name('operator.')->group(function () {
    Route::get('/dashboard', function () {
        $operatorId = session('operator_id');
        $operator = \App\Models\OperationPerson::find($operatorId);
        $shops = \App\Models\Shop::where('operation_person_id', $operatorId)->get();
        return view('operator.dashboard', compact('operator', 'shops'));
    })->name('dashboard');
    
    Route::post('/logout', [App\Http\Controllers\OperatorAuthController::class, 'logout'])->name('logout');
    
    // スケジュール画面（オペレーター用）
    Route::get('/schedule', [App\Http\Controllers\ShopController::class, 'schedule'])->name('schedule');
    Route::post('/schedule/sync', [App\Http\Controllers\ShopController::class, 'scheduleSync'])->name('schedule.sync');
    Route::post('/schedule/fetch-rank', [App\Http\Controllers\ShopController::class, 'fetchRank'])->name('schedule.fetch-rank');
    Route::delete('/schedule/delete-rank', [App\Http\Controllers\ShopController::class, 'deleteRankData'])->name('schedule.delete-rank');
    
    // 口コミ管理（オペレーター用）
    Route::get('/reviews', [App\Http\Controllers\ReviewsController::class, 'index'])->name('reviews.index');
    Route::get('/reviews/{review}', [App\Http\Controllers\ReviewsController::class, 'show'])->name('reviews.show');
    Route::post('/reviews/sync', [App\Http\Controllers\ReviewsController::class, 'syncBatch'])->name('reviews.sync');
    Route::post('/reviews/{review}/reply', [App\Http\Controllers\ReviewsController::class, 'reply'])->name('reviews.reply');
    
    // 店舗詳細（オペレーター用、自分の担当店舗のみ）
    Route::get('/shops/{shop}', [App\Http\Controllers\ShopController::class, 'show'])->name('shops.show');
    
    // 連絡履歴（オペレーター用）
    Route::post('/shops/{shop}/contact-logs', [App\Http\Controllers\ShopController::class, 'storeContactLog'])->name('shops.contact-logs.store');
    Route::get('/shops/{shop}/contact-logs', [App\Http\Controllers\ShopController::class, 'getContactLog'])->name('shops.contact-logs.get');
    Route::delete('/shops/{shop}/contact-logs', [App\Http\Controllers\ShopController::class, 'destroyContactLog'])->name('shops.contact-logs.destroy');
    
    // 投稿素材ストレージ（オペレーター用）
    Route::get('/shops/{shop}/media-assets', [App\Http\Controllers\ShopController::class, 'getMediaAssets'])->name('operator.shops.media-assets.index');
    Route::post('/shops/{shop}/media-assets', [App\Http\Controllers\ShopController::class, 'uploadMediaAsset'])->name('operator.shops.media-assets.store');
    
    // ブログクロールテスト（オペレーター用）
        Route::post('/shops/{shop}/blog-test', [\App\Http\Controllers\BlogTestController::class, 'run'])->name('shops.blog-test');
    Route::post('/shops/{shop}/instagram-test', [\App\Http\Controllers\InstagramTestController::class, 'run'])->name('shops.instagram-test');
    Route::post('/shops/{shop}/gbp-posts/{gbpPost}/retry-wp', [ShopController::class, 'retryWordPressPost'])->name('shops.gbp-posts.retry-wp');
    Route::post('/shops/{shop}/fetch-wp-post-types', [App\Http\Controllers\ShopController::class, 'fetchWpPostTypes'])->name('operator.shops.fetch-wp-post-types');
    Route::post('/shops/{shop}/test-wp-auth-debug', [App\Http\Controllers\ShopController::class, 'testWpAuthDebug'])->name('operator.shops.test-wp-auth-debug');
    
    // 月次レポート（オペレーター用）
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{shop}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('/reports/sync-all', [ReportController::class, 'syncAll'])->name('reports.sync-all');
    Route::post('/reports/{shop}/sync', [ReportController::class, 'sync'])->name('reports.sync');
    Route::match(['get', 'post'], '/reports/{shop}/pdf', [ReportController::class, 'downloadPdf'])->name('reports.pdf');
});

// 管理者用ルート（admin.onlyミドルウェアでオペレーターを除外）
Route::middleware(['auth', 'admin.only'])->group(function () {
    Route::get('/dashboard', function () {
        $shopCount = \App\Models\Shop::count();
        
        // スナップショットを使わず、DBに保存されているデータを直接参照
        // 重複を除外：同じshop_idとgbp_review_idの組み合わせで、最新のもの（idが最大のもの）のみを取得
        $latestReviewIds = \Illuminate\Support\Facades\DB::table('reviews')
            ->select(\Illuminate\Support\Facades\DB::raw('MAX(id) as id'))
            ->groupBy('shop_id', 'gbp_review_id')
            ->pluck('id');
        
        // ユニークな口コミ総数
        $reviewCount = $latestReviewIds->count();
        
        // ユニークな未返信口コミ数
        $unrepliedReviewCount = \App\Models\Review::whereIn('id', $latestReviewIds)
            ->where(function ($q) {
                $q->whereNull('reply_text')->orWhereNull('replied_at');
            })
            ->count();
        
        return view('dashboard', compact('shopCount', 'reviewCount', 'unrepliedReviewCount'));
    })->middleware('admin.permission:dashboard')->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 口コミ管理 - 管理者のみ
    Route::middleware('admin.permission:reviews.index')->group(function () {
        Route::get('/reviews', [ReviewsController::class, 'index'])->name('reviews.index');
        Route::get('/reviews/{review}', [ReviewsController::class, 'show'])->name('reviews.show');
        Route::post('/reviews/sync', [ReviewsController::class, 'syncBatch'])->name('reviews.sync');
        Route::post('/reviews/{review}/reply', [ReviewsController::class, 'reply'])->name('reviews.reply');
    });

    // 店舗管理（顧客管理）
    // scheduleルートはresourceルートより前に定義する必要がある（/shops/{shop}にマッチしないように）
    Route::middleware('admin.permission:shops.schedule')->group(function () {
        Route::get('/shops/schedule', [ShopController::class, 'schedule'])->name('shops.schedule');
        Route::post('/shops/schedule/sync', [ShopController::class, 'scheduleSync'])->name('shops.schedule.sync');
        Route::post('/shops/schedule/fetch-rank', [ShopController::class, 'fetchRank'])->name('shops.schedule.fetch-rank');
        Route::delete('/shops/schedule/delete-rank', [ShopController::class, 'deleteRankData'])->name('shops.schedule.delete-rank');
    });
    Route::middleware('admin.permission:shops.index')->group(function () {
        Route::resource('shops', ShopController::class);
        Route::get('/shops/export/csv', [ShopController::class, 'export'])->name('shops.export');
        Route::get('/shops/{shop}/connect', [ShopController::class, 'connect'])->name('shops.connect');
        Route::get('/shops/google/callback', [ShopController::class, 'googleCallback'])->name('shops.google.callback');
        Route::get('/shops/{shop}/select-gbp-location', [ShopController::class, 'selectGbpLocation'])->name('shops.select-gbp-location');
        Route::post('/shops/{shop}/select-gbp-location', [ShopController::class, 'storeGbpLocation'])->name('shops.store-gbp-location');
        Route::get('/shops/{shop}/blog-history', [ShopController::class, 'blogHistory'])->name('shops.blog-history');
        Route::post('/shops/{shop}/blog-crawl-settings', [ShopController::class, 'storeBlogCrawlSettings'])->name('shops.blog-crawl-settings.store');
        Route::put('/shops/{shop}/blog-crawl-settings/{setting}', [ShopController::class, 'updateBlogCrawlSettings'])->name('shops.blog-crawl-settings.update');
        Route::delete('/shops/{shop}/blog-crawl-settings/{setting}', [ShopController::class, 'destroyBlogCrawlSettings'])->name('shops.blog-crawl-settings.destroy');
        Route::post('/shops/{shop}/contact-logs', [ShopController::class, 'storeContactLog'])->name('shops.contact-logs.store');
        Route::get('/shops/{shop}/contact-logs', [ShopController::class, 'getContactLog'])->name('shops.contact-logs.get');
        Route::delete('/shops/{shop}/contact-logs', [ShopController::class, 'destroyContactLog'])->name('shops.contact-logs.destroy');
        
        // 投稿素材ストレージ
        Route::get('/shops/{shop}/media-assets', [ShopController::class, 'getMediaAssets'])->name('shops.media-assets.index');
        Route::post('/shops/{shop}/media-assets', [ShopController::class, 'uploadMediaAsset'])->name('shops.media-assets.store');
    });

    // ブログクロールテスト（管理者用、Route Model Binding を正しく動作させるため個別定義）
    Route::post('/shops/{shop}/blog-test', [\App\Http\Controllers\BlogTestController::class, 'run'])
        ->name('shops.blog-test')
        ->middleware(['auth', 'admin']);
    Route::post('/shops/{shop}/instagram-test', [\App\Http\Controllers\InstagramTestController::class, 'run'])
        ->name('shops.instagram-test')
        ->middleware(['auth', 'admin']);
    Route::post('/shops/{shop}/gbp-posts/{gbpPost}/retry-wp', [ShopController::class, 'retryWordPressPost'])
        ->name('shops.gbp-posts.retry-wp')
        ->middleware(['auth', 'admin']);
    Route::post('/shops/{shop}/fetch-wp-post-types', [ShopController::class, 'fetchWpPostTypes'])
        ->name('shops.fetch-wp-post-types')
        ->middleware(['auth', 'admin']);
    
    Route::post('/shops/{shop}/test-wp-auth-debug', [ShopController::class, 'testWpAuthDebug'])
        ->name('shops.test-wp-auth-debug')
        ->middleware(['auth', 'admin']);

    // 月次レポート
    Route::middleware('admin.permission:reports.index')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{shop}', [ReportController::class, 'show'])->name('reports.show');
        Route::post('/reports/sync-all', [ReportController::class, 'syncAll'])->name('reports.sync-all');
        Route::post('/reports/{shop}/sync', [ReportController::class, 'sync'])->name('reports.sync');
        Route::match(['get', 'post'], '/reports/{shop}/pdf', [ReportController::class, 'downloadPdf'])->name('reports.pdf');
    });

    // GBP Insights CSVインポート
    Route::middleware('admin.permission:gbp-insights.import')->group(function () {
        Route::get('/gbp-insights/import', [GbpInsightsImportController::class, 'index'])->name('gbp-insights.import');
        Route::post('/gbp-insights/import', [GbpInsightsImportController::class, 'import'])->name('gbp-insights.import.store');
    });

    // レポートメール文面設定
    Route::middleware('admin.permission:report-email-settings.index')->group(function () {
        Route::get('/report-email-settings', [\App\Http\Controllers\ReportEmailSettingController::class, 'index'])->name('report-email-settings.index');
        Route::put('/report-email-settings', [\App\Http\Controllers\ReportEmailSettingController::class, 'update'])->name('report-email-settings.update');
    });

    // 競合分析（権限で制御）
    Route::middleware('admin.permission:competitor-analysis')->group(function () {
        Route::get('/meo/competitor-analysis', [CompetitorAnalysisController::class, 'index'])->name('meo.competitor-analysis');
        Route::post('/meo/competitor-analysis/step2', [CompetitorAnalysisController::class, 'step2'])->name('meo.competitor-analysis.step2');
    });

    // マスタ管理
    Route::middleware('admin.permission:masters.index')->group(function () {
        Route::get('/masters', [MasterController::class, 'index'])->name('masters.index');
        
        // プランマスタ
        Route::get('/masters/plans', [MasterController::class, 'plans'])->name('masters.plans.index');
        Route::get('/masters/plans/create', [MasterController::class, 'createPlan'])->name('masters.plans.create');
        Route::post('/masters/plans', [MasterController::class, 'storePlan'])->name('masters.plans.store');
        Route::get('/masters/plans/{plan}/edit', [MasterController::class, 'editPlan'])->name('masters.plans.edit');
        Route::put('/masters/plans/{plan}', [MasterController::class, 'updatePlan'])->name('masters.plans.update');
        Route::delete('/masters/plans/{plan}', [MasterController::class, 'destroyPlan'])->name('masters.plans.destroy');
        
        // 担当営業マスタ
        Route::get('/masters/sales-persons', [MasterController::class, 'salesPersons'])->name('masters.sales-persons.index');
        Route::get('/masters/sales-persons/create', [MasterController::class, 'createSalesPerson'])->name('masters.sales-persons.create');
        Route::post('/masters/sales-persons', [MasterController::class, 'storeSalesPerson'])->name('masters.sales-persons.store');
        Route::get('/masters/sales-persons/{salesPerson}/edit', [MasterController::class, 'editSalesPerson'])->name('masters.sales-persons.edit');
        Route::put('/masters/sales-persons/{salesPerson}', [MasterController::class, 'updateSalesPerson'])->name('masters.sales-persons.update');
        Route::delete('/masters/sales-persons/{salesPerson}', [MasterController::class, 'destroySalesPerson'])->name('masters.sales-persons.destroy');
        
        // オペレーション担当マスタ
        Route::get('/masters/operation-persons', [MasterController::class, 'operationPersons'])->name('masters.operation-persons.index');
        Route::get('/masters/operation-persons/create', [MasterController::class, 'createOperationPerson'])->name('masters.operation-persons.create');
        Route::post('/masters/operation-persons', [MasterController::class, 'storeOperationPerson'])->name('masters.operation-persons.store');
        Route::get('/masters/operation-persons/{operationPerson}/edit', [MasterController::class, 'editOperationPerson'])->name('masters.operation-persons.edit');
        Route::put('/masters/operation-persons/{operationPerson}', [MasterController::class, 'updateOperationPerson'])->name('masters.operation-persons.update');
        Route::delete('/masters/operation-persons/{operationPerson}', [MasterController::class, 'destroyOperationPerson'])->name('masters.operation-persons.destroy');
        
        // 管理者マスタ
        Route::get('/masters/admins', [MasterController::class, 'admins'])->name('masters.admins.index');
        Route::get('/masters/admins/create', [MasterController::class, 'createAdmin'])->name('masters.admins.create');
        Route::post('/masters/admins', [MasterController::class, 'storeAdmin'])->name('masters.admins.store');
        Route::get('/masters/admins/{admin}/edit', [MasterController::class, 'editAdmin'])->name('masters.admins.edit');
        Route::put('/masters/admins/{admin}', [MasterController::class, 'updateAdmin'])->name('masters.admins.update');
        Route::delete('/masters/admins/{admin}', [MasterController::class, 'destroyAdmin'])->name('masters.admins.destroy');
    });
});

// テストルート
Route::get('/route-test', function () {
    return 'ROUTE OK';
});
