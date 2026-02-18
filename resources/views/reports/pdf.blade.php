<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEOのばすくん</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: sazanami-gothic, sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #00afcc;
        }
        .header h1 {
            font-size: 18pt;
            color: #00afcc;
            margin-bottom: 5px;
        }
        .header .shop-name {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .header .period {
            font-size: 11pt;
            color: #666;
        }
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 14pt;
            font-weight: bold;
            color: #00afcc;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }
        .kpi-summary {
            width: 100%;
            margin-bottom: 20px;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 8px;
        }
        .kpi-summary td {
            width: 20%;
            padding: 8px 4px;
            text-align: center;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            vertical-align: top;
        }
        .kpi-item {
            /* 互換性のため残す */
        }
        .kpi-label {
            font-size: 9pt;
            color: #666;
            margin-bottom: 5px;
        }
        .kpi-value {
            font-size: 14pt;
            font-weight: bold;
            color: #333;
        }
        .kpi-mom {
            font-size: 9pt;
            margin-top: 3px;
        }
        .kpi-mom.positive {
            color: #10b981;
        }
        .kpi-mom.negative {
            color: #ef4444;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 8pt;
        }
        table th {
            background-color: #00afcc;
            color: white;
            padding: 8px 4px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #0088a3;
        }
        table td {
            padding: 6px 4px;
            text-align: center;
            border: 1px solid #ddd;
        }
        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        table .keyword-cell {
            text-align: left;
            font-weight: bold;
            padding-left: 8px;
            background-color: #f0f0f0;
        }
        .page-break {
            page-break-before: always;
        }
        .no-data {
            color: #999;
            font-style: italic;
        }
        .chart-container {
            margin: 20px 0;
            page-break-inside: avoid;
        }
        .chart-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .chart-table td {
            vertical-align: bottom;
            padding: 2px;
            text-align: center;
            border: none;
        }
        .chart-bar-cell {
            height: 150px;
            position: relative;
        }
        .chart-bar {
            background-color: #36a2eb;
            border: 1px solid #1e7cd6;
            min-height: 2px;
            width: 100%;
            position: absolute;
            bottom: 0;
        }
        .chart-bar-value {
            position: absolute;
            top: -18px;
            left: 0;
            right: 0;
            font-size: 7pt;
            font-weight: bold;
            color: #333;
        }
        .chart-bar-label {
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            font-size: 7pt;
            color: #333;
        }
        .chart-legend {
            margin-top: 30px;
            font-size: 9pt;
            text-align: center;
        }
        @page {
            margin: 15mm;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>月次レポート</h1>
        <div class="shop-name">{{ htmlspecialchars($shop->name, ENT_QUOTES, 'UTF-8') }}</div>
        <div class="period">{{ $fromDate->format('Y年m月d日') }} ～ {{ $toDate->format('Y年m月d日') }}</div>
    </div>

    <!-- KPIサマリー -->
    <div class="section">
        <div class="section-title">KPIサマリー</div>
        <table class="kpi-summary">
            <tr>
                @if(!empty($insightsMetrics))
                <td class="kpi-item">
                    <div class="kpi-label">表示回数</div>
                    <div class="kpi-value">{{ number_format($insightsMetrics['BUSINESS_IMPRESSIONS'] ?? 0) }}</div>
                </td>
                <td class="kpi-item">
                    <div class="kpi-label">Web</div>
                    <div class="kpi-value">{{ number_format($insightsMetrics['WEBSITE_CLICKS'] ?? 0) }}</div>
                </td>
                <td class="kpi-item">
                    <div class="kpi-label">電話</div>
                    <div class="kpi-value">{{ number_format($insightsMetrics['CALL_CLICKS'] ?? 0) }}</div>
                </td>
                <td class="kpi-item">
                    <div class="kpi-label">ルート</div>
                    <div class="kpi-value">{{ number_format($insightsMetrics['DIRECTIONS_REQUESTS'] ?? 0) }}</div>
                </td>
                @endif
                <td class="kpi-item">
                    <div class="kpi-label">口コミ数</div>
                    <div class="kpi-value">{{ $currentReviewCount }}</div>
                    @if($reviewMoM != 0)
                        <div class="kpi-mom {{ $reviewMoM > 0 ? 'positive' : 'negative' }}">
                            {{ $reviewMoM > 0 ? '+' : '' }}{{ $reviewMoM }}% (前月比)
                        </div>
                    @endif
                </td>
                <td class="kpi-item">
                    <div class="kpi-label">評価点数</div>
                    <div class="kpi-value">{{ $currentRating ? number_format($currentRating, 1) : '—' }}</div>
                    @if($ratingMoM != 0)
                        <div class="kpi-mom {{ $ratingMoM > 0 ? 'positive' : 'negative' }}">
                            {{ $ratingMoM > 0 ? '+' : '' }}{{ $ratingMoM }}% (前月比)
                        </div>
                    @endif
                </td>
                <td class="kpi-item">
                    <div class="kpi-label">返信率</div>
                    <div class="kpi-value">{{ $replyRate }}%</div>
                </td>
                <td class="kpi-item">
                    <div class="kpi-label">有効投稿数（Google評価対象）</div>
                    <div class="kpi-value">{{ $postCount }}</div>
                    <div class="kpi-note" style="font-size: 10px; color: #666; margin-top: 4px;">
                        ※ Google API は古い投稿や期限切れ投稿を返さないため、この数値は検索順位に影響する投稿数です
                    </div>
                </td>
                <td class="kpi-item">
                    <div class="kpi-label">写真数</div>
                    <div class="kpi-value">{{ $currentPhotoCount }}</div>
                    @if($photoMoM != 0)
                        <div class="kpi-mom {{ $photoMoM > 0 ? 'positive' : 'negative' }}">
                            {{ $photoMoM > 0 ? '+' : '' }}{{ $photoMoM }}% (前月比)
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- グラフ表示（2軸グラフ：順位 + 表示回数） -->
    @if(!empty($chartImage))
    <div class="section">
        <div class="section-title">キーワード順位推移・表示回数</div>
        <div style="text-align: center; margin: 20px 0; page-break-inside: avoid;">
            <img src="{{ $chartImage }}" style="max-width: 100%; height: auto; border: 1px solid #ddd;" alt="キーワード順位推移グラフ">
        </div>
    </div>
    @else
    <!-- グラフ画像が取得できなかった場合のフォールバック -->
    @if(!empty($insightsMetrics) && isset($insightsMetrics['daily']) && !empty($gbp_impressions_final_clean))
    <div class="section">
        <div class="section-title">Googleビジネスプロフィール 表示回数（日別推移）</div>
        <div class="chart-container">
            @php
                $maxValue = max($gbp_impressions_final_clean) > 0 ? max($gbp_impressions_final_clean) : 1;
            @endphp
            <table class="chart-table">
                <tr>
                    @foreach($dates as $index => $date)
                        @php
                            $value = $gbp_impressions_final_clean[$index] ?? 0;
                            $height = $maxValue > 0 ? ($value / $maxValue * 100) : 0;
                        @endphp
                        <td class="chart-bar-cell">
                            <div class="chart-bar" style="height: {{ $height }}%;">
                                @if($value > 0)
                                    <div class="chart-bar-value">{{ $value }}</div>
                                @endif
                            </div>
                            <div class="chart-bar-label">{{ \Carbon\Carbon::parse($date)->format('m/d') }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
            <div class="chart-legend">
                <strong>表示回数</strong>（期間合計: {{ number_format($insightsMetrics['BUSINESS_IMPRESSIONS'] ?? 0) }}回）
            </div>
        </div>
    </div>
    @endif
    @endif

    <!-- キーワード順位テーブル -->
    @if($keywords->count() > 0)
        @foreach($dateChunks as $chunkIndex => $dateChunk)
            @if($chunkIndex > 0)
                <div class="page-break"></div>
            @endif
            <div class="section">
                <div class="section-title">
                    キーワード順位テーブル
                    @if(count($dateChunks) > 1)
                        ({{ $chunkIndex + 1 }}/{{ count($dateChunks) }})
                    @endif
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 150px;">キーワード</th>
                            @foreach($dateChunk as $date)
                                <th>{{ \Carbon\Carbon::parse($date)->format('m/d') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($keywords as $keyword)
                            <tr>
                                <td class="keyword-cell">{{ htmlspecialchars($keyword->keyword, ENT_QUOTES, 'UTF-8') }}</td>
                                @foreach($dateChunk as $date)
                                    <td>
                                        @php
                                            $log = $keyword->rankLogs->first(function ($log) use ($date) {
                                                return $log->checked_at->format('Y-m-d') === $date;
                                            });
                                            $rank = $log ? $log->position : null;
                                        @endphp
                                        @if($rank !== null)
                                            {{ $rank }}位
                                        @else
                                            <span class="no-data">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        @if(!empty($insightsMetrics) && isset($insightsMetrics['daily']))
                        <!-- GBP Insights 日別データ行 -->
                        <tr style="background-color: #e0f2fe;">
                            <td class="keyword-cell" style="font-weight: bold;">表示回数</td>
                            @foreach($dateChunk as $date)
                                <td style="font-weight: bold;">
                                    {{ number_format($insightsMetrics['daily'][$date]['impressions'] ?? 0) }}
                                </td>
                            @endforeach
                        </tr>
                        <tr style="background-color: #e0f2fe;">
                            <td class="keyword-cell" style="font-weight: bold;">ウェブサイト</td>
                            @foreach($dateChunk as $date)
                                <td style="font-weight: bold;">
                                    {{ number_format($insightsMetrics['daily'][$date]['website'] ?? 0) }}
                                </td>
                            @endforeach
                        </tr>
                        <tr style="background-color: #e0f2fe;">
                            <td class="keyword-cell" style="font-weight: bold;">電話</td>
                            @foreach($dateChunk as $date)
                                <td style="font-weight: bold;">
                                    {{ number_format($insightsMetrics['daily'][$date]['phone'] ?? 0) }}
                                </td>
                            @endforeach
                        </tr>
                        <tr style="background-color: #e0f2fe;">
                            <td class="keyword-cell" style="font-weight: bold;">ルート</td>
                            @foreach($dateChunk as $date)
                                <td style="font-weight: bold;">
                                    {{ number_format($insightsMetrics['daily'][$date]['directions'] ?? 0) }}
                                </td>
                            @endforeach
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endforeach
    @else
        <div class="section">
            <div class="section-title">キーワード順位テーブル</div>
            <p class="no-data">MEOキーワードが設定されていません</p>
        </div>
    @endif
</body>
</html>

