<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\GbpInsight;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Google Business Profile Insights サービス
 * 
 * 注意: Performance API による取得ロジックは削除されました。
 * CSVデータを受け入れるための新設計に移行予定です。
 */
class GbpInsightsService
{
    /**
     * DBからInsightsデータを取得（CSVデータ用）
     * 
     * @param Shop $shop
     * @param string $from 開始日 (Y-m-d)
     * @param string $to 終了日 (Y-m-d)
     * @return array|null
     */
    public function getInsightsFromDb(Shop $shop, string $from, string $to): ?array
    {
        Log::info('GBP Insights DB取得開始', [
            'shop_id' => $shop->id,
            'from' => $from,
            'to' => $to,
        ]);

        // シンプルなクエリ: 指定された期間内に含まれるレコードをすべて取得
        $insights = GbpInsight::where('shop_id', $shop->id)
            ->whereBetween('from_date', [$from, $to])
            ->where('period_type', 'daily')
            ->whereNotNull('impressions')
            ->get();
        
        Log::info('GBP Insights DB取得: クエリ結果', [
            'shop_id' => $shop->id,
            'from' => $from,
            'to' => $to,
            'count' => $insights->count(),
            'records' => $insights->map(function($record) {
                return [
                    'id' => $record->id,
                    'from_date' => $record->from_date?->format('Y-m-d'),
                    'to_date' => $record->to_date?->format('Y-m-d'),
                    'impressions' => $record->impressions,
                    'directions' => $record->directions,
                    'website' => $record->website,
                    'phone' => $record->phone,
                ];
            }),
        ]);

        if ($insights->isEmpty()) {
            Log::info('GBP Insights DB取得: データなし', [
                'shop_id' => $shop->id,
                'from' => $from,
                'to' => $to,
            ]);
            return null;
        }

        // 取得したコレクションをそのまま返す（複数レコードを集計できるように）
        return [
            'success' => true,
            'insights' => $insights, // コレクションをそのまま返す
            'metrics' => [
                // 合計値を計算
                'BUSINESS_IMPRESSIONS' => $insights->sum('impressions') ?? 0,
                'DIRECTIONS_REQUESTS' => $insights->sum('directions') ?? 0,
                'WEBSITE_CLICKS' => $insights->sum('website') ?? 0,
                'CALL_CLICKS' => $insights->sum('phone') ?? 0,
            ],
        ];
    }

    /**
     * 対象期間に含まれる年月を列挙
     * 
     * @param Carbon $fromDate
     * @param Carbon $toDate
     * @return array [['year' => int, 'month' => int], ...]
     */
    private function getTargetMonths(Carbon $fromDate, Carbon $toDate): array
    {
        $months = [];
        $current = $fromDate->copy()->startOfMonth();
        $end = $toDate->copy()->startOfMonth();

        while ($current->lte($end)) {
            $months[] = [
                'year' => (int)$current->format('Y'),
                'month' => (int)$current->format('m'),
            ];
            $current->addMonth();
        }

        return $months;
    }

    /**
     * 複数月のInsightsデータを合算
     * 
     * @param array $monthlyInsights
     * @return array
     */
    private function aggregateMonthlyInsights(array $monthlyInsights): array
    {
        $totalImpressions = 0;
        $totalDirections = 0;
        $totalWebsite = 0;
        $totalPhone = 0;

        foreach ($monthlyInsights as $monthData) {
            $totalImpressions += $monthData['impressions'] ?? 0;
            $totalDirections += $monthData['directions'] ?? 0;
            $totalWebsite += $monthData['website'] ?? 0;
            $totalPhone += $monthData['phone'] ?? 0;
        }

        return [
            'BUSINESS_IMPRESSIONS' => $totalImpressions,
            'DIRECTIONS_REQUESTS' => $totalDirections,
            'WEBSITE_CLICKS' => $totalWebsite,
            'CALL_CLICKS' => $totalPhone,
        ];
    }

    /**
     * メトリクスデータを整形して返す（CSVデータ用）
     * 
     * @param array $insightsData getInsightsFromDb()の戻り値
     * @return array
     */
    public function formatMetrics(array $insightsData): array
    {
        $metrics = $insightsData['metrics'] ?? [];
        
        return [
            'BUSINESS_IMPRESSIONS' => $metrics['BUSINESS_IMPRESSIONS'] ?? 0,
            'DIRECTIONS_REQUESTS' => $metrics['DIRECTIONS_REQUESTS'] ?? 0,
            'WEBSITE_CLICKS' => $metrics['WEBSITE_CLICKS'] ?? 0,
            'CALL_CLICKS' => $metrics['CALL_CLICKS'] ?? 0,
        ];
    }

    /**
     * キーワードデータを整形して返す（CSVデータ用）
     * 
     * @param array $insightsData getInsightsFromDb()の戻り値
     * @return array
     */
    public function formatKeywords(array $insightsData): array
    {
        // CSVデータ用の実装は後で追加
        return [];
    }

    /**
     * 日別データを取得（グラフ・テーブル表示用）
     * 取得した複数レコードを日付ごとに集計して返す
     * 
     * @param Shop $shop
     * @param string $from 開始日 (Y-m-d)
     * @param string $to 終了日 (Y-m-d)
     * @return array ['daily' => ['date' => ['impressions' => int, 'website' => int, 'phone' => int, 'directions' => int]], ...], 'total' => [...]]
     */
    public function getDailyInsights(Shop $shop, string $from, string $to): array
    {
        // タイムゾーンを考慮して日付をパース（時刻を切り捨て）
        $fromDate = Carbon::parse($from)
            ->setTimezone(config('app.timezone', 'Asia/Tokyo'))
            ->startOfDay();
        $toDate = Carbon::parse($to)
            ->setTimezone(config('app.timezone', 'Asia/Tokyo'))
            ->startOfDay();
        
        // 期間内のすべての日付を生成（Y-m-d 形式で統一）
        $dates = [];
        $current = $fromDate->copy();
        while ($current->lte($toDate)) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }
        
        // シンプルなクエリ: 指定された期間内に含まれるレコードをすべて取得
        $insights = GbpInsight::where('shop_id', $shop->id)
            ->whereBetween('from_date', [$from, $to])
            ->where('period_type', 'daily')
            ->whereNotNull('impressions')
            ->get();
        
        Log::info('GBP日別データ取得: クエリ結果', [
            'shop_id' => $shop->id,
            'from' => $from,
            'to' => $to,
            'count' => $insights->count(),
        ]);
        
        $dailyData = [];
        $total = [
            'impressions' => 0,
            'website' => 0,
            'phone' => 0,
            'directions' => 0,
        ];
        
        // 各レコードを処理
        foreach ($insights as $insight) {
            // タイムゾーンを考慮して日付部分のみを取得（時刻を切り捨て）
            $recordFromDate = Carbon::parse($insight->from_date)
                ->setTimezone(config('app.timezone', 'Asia/Tokyo'))
                ->startOfDay();
            $recordToDate = Carbon::parse($insight->to_date)
                ->setTimezone(config('app.timezone', 'Asia/Tokyo'))
                ->startOfDay();
            $recordPeriodDays = $recordFromDate->diffInDays($recordToDate) + 1;
            
            // レコードの期間内の各日に値を割り当て
            $current = $recordFromDate->copy();
            while ($current->lte($recordToDate)) {
                // 日付キーを Y-m-d 形式で確実に取得
                $dateKey = $current->format('Y-m-d');
                
                // 表示期間内の日付のみ処理
                if (in_array($dateKey, $dates)) {
                    // レコードの合計値を日数で割って各日に割り当て
                    $dailyImpressions = $recordPeriodDays > 0 ? (int)($insight->impressions / $recordPeriodDays) : 0;
                    $dailyWebsite = $recordPeriodDays > 0 ? (int)($insight->website / $recordPeriodDays) : 0;
                    $dailyPhone = $recordPeriodDays > 0 ? (int)($insight->phone / $recordPeriodDays) : 0;
                    $dailyDirections = $recordPeriodDays > 0 ? (int)($insight->directions / $recordPeriodDays) : 0;
                    
                    // 既に値がある場合は加算（複数レコードが同じ日をカバーする場合）
                    if (!isset($dailyData[$dateKey])) {
                        $dailyData[$dateKey] = [
                            'impressions' => 0,
                            'website' => 0,
                            'phone' => 0,
                            'directions' => 0,
                        ];
                    }
                    
                    $dailyData[$dateKey]['impressions'] += $dailyImpressions;
                    $dailyData[$dateKey]['website'] += $dailyWebsite;
                    $dailyData[$dateKey]['phone'] += $dailyPhone;
                    $dailyData[$dateKey]['directions'] += $dailyDirections;
                }
                
                $current->addDay();
            }
        }
        
        // 合計値を計算
        foreach ($dailyData as $date => $values) {
            $total['impressions'] += $values['impressions'];
            $total['website'] += $values['website'];
            $total['phone'] += $values['phone'];
            $total['directions'] += $values['directions'];
        }
        
        // データがない日付には0を設定（すべての日付を確実に含める）
        $normalizedDailyData = [];
        foreach ($dates as $date) {
            // 日付キーを確実に Y-m-d 形式に正規化（Carbon を使用してタイムゾーンを考慮）
            $normalizedKey = Carbon::parse($date)
                ->setTimezone(config('app.timezone', 'Asia/Tokyo'))
                ->startOfDay()
                ->format('Y-m-d');
            
            $normalizedDailyData[$normalizedKey] = $dailyData[$normalizedKey] ?? [
                'impressions' => 0,
                'website' => 0,
                'phone' => 0,
                'directions' => 0,
            ];
        }
        
        Log::info('GBP日別データ取得: 正規化後のデータ', [
            'shop_id' => $shop->id,
            'dates_count' => count($dates),
            'daily_data_keys' => array_keys($normalizedDailyData),
            'sample_data' => array_slice($normalizedDailyData, 0, 3, true),
        ]);
        
        return [
            'daily' => $normalizedDailyData,
            'total' => $total,
        ];
    }
}
