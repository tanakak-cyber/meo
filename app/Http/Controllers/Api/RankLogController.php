<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeoRankLog;
use App\Models\MeoKeyword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RankLogController extends Controller
{
    public function store(Request $request)
    {
        $shopId = $request->input('shop_id');
        $meoKeywordId = $request->input('meo_keyword_id');
        $rank = $request->input('rank');
        $checkedAt = $request->input('checked_at');
        
        // バリデーション
        if (!$shopId || !$meoKeywordId || !$checkedAt) {
            return response()->json([
                'success' => false,
                'message' => 'shop_id, meo_keyword_id, checked_at が必要です。',
            ], 400);
        }
        
        // shop_idとmeo_keyword_idの整合性チェック
        $keyword = MeoKeyword::findOrFail($meoKeywordId);
        if ($keyword->shop_id != $shopId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid shop_id and meo_keyword_id combination.',
            ], 400);
        }
        
        // 順位データを保存（updateOrCreateで重複を防ぐ）
        MeoRankLog::updateOrCreate(
            [
                'meo_keyword_id' => $meoKeywordId,
                'checked_at' => $checkedAt,
            ],
            [
                'position' => $rank, // null=圏外
            ]
        );
        
        Log::info('RANK_LOG_SAVED', [
            'shop_id' => $shopId,
            'meo_keyword_id' => $meoKeywordId,
            'rank' => $rank,
            'checked_at' => $checkedAt,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '順位データを保存しました。',
        ]);
    }
}

    public function store(Request $request)
    {
        $shopId = $request->input('shop_id');
        $meoKeywordId = $request->input('meo_keyword_id');
        $rank = $request->input('rank');
        $checkedAt = $request->input('checked_at');
        
        // バリデーション
        if (!$shopId || !$meoKeywordId || !$checkedAt) {
            return response()->json([
                'success' => false,
                'message' => 'shop_id, meo_keyword_id, checked_at が必要です。',
            ], 400);
        }
        
        // shop_idとmeo_keyword_idの整合性チェック
        $keyword = MeoKeyword::findOrFail($meoKeywordId);
        if ($keyword->shop_id != $shopId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid shop_id and meo_keyword_id combination.',
            ], 400);
        }
        
        // 順位データを保存（updateOrCreateで重複を防ぐ）
        MeoRankLog::updateOrCreate(
            [
                'meo_keyword_id' => $meoKeywordId,
                'checked_at' => $checkedAt,
            ],
            [
                'position' => $rank, // null=圏外
            ]
        );
        
        Log::info('RANK_LOG_SAVED', [
            'shop_id' => $shopId,
            'meo_keyword_id' => $meoKeywordId,
            'rank' => $rank,
            'checked_at' => $checkedAt,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '順位データを保存しました。',
        ]);
    }
}

    public function store(Request $request)
    {
        $shopId = $request->input('shop_id');
        $meoKeywordId = $request->input('meo_keyword_id');
        $rank = $request->input('rank');
        $checkedAt = $request->input('checked_at');
        
        // バリデーション
        if (!$shopId || !$meoKeywordId || !$checkedAt) {
            return response()->json([
                'success' => false,
                'message' => 'shop_id, meo_keyword_id, checked_at が必要です。',
            ], 400);
        }
        
        // shop_idとmeo_keyword_idの整合性チェック
        $keyword = MeoKeyword::findOrFail($meoKeywordId);
        if ($keyword->shop_id != $shopId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid shop_id and meo_keyword_id combination.',
            ], 400);
        }
        
        // 順位データを保存（updateOrCreateで重複を防ぐ）
        MeoRankLog::updateOrCreate(
            [
                'meo_keyword_id' => $meoKeywordId,
                'checked_at' => $checkedAt,
            ],
            [
                'position' => $rank, // null=圏外
            ]
        );
        
        Log::info('RANK_LOG_SAVED', [
            'shop_id' => $shopId,
            'meo_keyword_id' => $meoKeywordId,
            'rank' => $rank,
            'checked_at' => $checkedAt,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '順位データを保存しました。',
        ]);
    }
}

    public function store(Request $request)
    {
        $shopId = $request->input('shop_id');
        $meoKeywordId = $request->input('meo_keyword_id');
        $rank = $request->input('rank');
        $checkedAt = $request->input('checked_at');
        
        // バリデーション
        if (!$shopId || !$meoKeywordId || !$checkedAt) {
            return response()->json([
                'success' => false,
                'message' => 'shop_id, meo_keyword_id, checked_at が必要です。',
            ], 400);
        }
        
        // shop_idとmeo_keyword_idの整合性チェック
        $keyword = MeoKeyword::findOrFail($meoKeywordId);
        if ($keyword->shop_id != $shopId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid shop_id and meo_keyword_id combination.',
            ], 400);
        }
        
        // 順位データを保存（updateOrCreateで重複を防ぐ）
        MeoRankLog::updateOrCreate(
            [
                'meo_keyword_id' => $meoKeywordId,
                'checked_at' => $checkedAt,
            ],
            [
                'position' => $rank, // null=圏外
            ]
        );
        
        Log::info('RANK_LOG_SAVED', [
            'shop_id' => $shopId,
            'meo_keyword_id' => $meoKeywordId,
            'rank' => $rank,
            'checked_at' => $checkedAt,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '順位データを保存しました。',
        ]);
    }
}
