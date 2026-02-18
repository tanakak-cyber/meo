<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RankLogController;
use App\Http\Controllers\Api\RankFetchController;
use App\Http\Controllers\CompetitorAnalysisController;
use App\Http\Controllers\Api\CompetitorAnalysisApiController;
use App\Http\Controllers\SyncBatchController;

// API認証は後でSanctumを設定するが、今は簡易的に実装
Route::post('/rank-log', [RankLogController::class, 'store']);
Route::post('/rank-fetch/finish', [RankFetchController::class, 'finish']);

// 競合分析API
Route::post('/meo/competitor-analysis', [CompetitorAnalysisController::class, 'store']);
Route::post('/meo/competitor-analysis/run', [CompetitorAnalysisController::class, 'run']);

// AI返信生成API
Route::post('/reviews/{review}/generate-reply', [App\Http\Controllers\ReviewsController::class, 'generateReply'])->name('api.reviews.generate-reply');

// 同期バッチ進捗API
Route::get('/sync-batches/{id}', [SyncBatchController::class, 'show'])->name('api.sync-batches.show');



