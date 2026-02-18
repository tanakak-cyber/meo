<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                月次レポート - {{ $shop->name }}
            </h2>
            <div class="flex gap-2">
                <form id="pdfDownloadForm" method="POST" action="{{ session('operator_id') ? route('operator.reports.pdf', ['shop' => $shop->id, 'from' => $from, 'to' => $to]) : route('reports.pdf', ['shop' => $shop->id, 'from' => $from, 'to' => $to]) }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="chart_image" id="chart_image_input" value="">
                    <button type="submit" class="bg-[#00afcc] hover:bg-[#0088a3] text-white font-bold py-2 px-4 rounded inline-flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        PDFダウンロード
                    </button>
                </form>
                <a href="{{ session('operator_id') ? route('operator.reports.index') : route('reports.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    一覧に戻る
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- 期間指定フォーム -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-4">
                    <form method="GET" action="{{ session('operator_id') ? route('operator.reports.show', $shop->id) : route('reports.show', $shop->id) }}" class="flex flex-row gap-3 items-center">
                        <div class="flex items-center gap-2">
                            <label for="from" class="text-sm font-medium text-gray-700 whitespace-nowrap">開始日</label>
                            <input type="date" name="from" id="from" value="{{ $from }}" required
                                class="px-3 py-1.5 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        </div>
                        <div class="flex items-center gap-2">
                            <label for="to" class="text-sm font-medium text-gray-700 whitespace-nowrap">終了日</label>
                            <input type="date" name="to" id="to" value="{{ $to }}" required
                                class="px-3 py-1.5 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        </div>
                        <div>
                            <button type="submit" class="px-4 py-1.5 bg-[#00afcc] text-white rounded-md hover:bg-[#0088a3] focus:outline-none focus:ring-2 focus:ring-[#00afcc] focus:ring-offset-2 text-sm whitespace-nowrap">
                                期間を変更
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 口コミ・写真・投稿同期 -->
            @if($shop->gbp_location_id)
                <div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6 overflow-hidden">
                    <div class="bg-gradient-to-r from-[#00afcc] to-[#0088a3] px-6 py-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-white">口コミ・写真・投稿同期</h3>
                        </div>
                    </div>
                    <div class="p-6">
                        <form method="POST" action="{{ session('operator_id') ? route('operator.reports.sync', $shop->id) : route('reports.sync', $shop->id) }}" class="flex flex-col lg:flex-row lg:items-end gap-4" onsubmit="return confirm('口コミ・写真・投稿を同期しますか？');">
                            @csrf
                            <input type="hidden" name="from" value="{{ $from }}">
                            <input type="hidden" name="to" value="{{ $to }}">
                            @if(!session('operator_id'))
                            <div class="flex-1">
                                <label for="sync_operation_person_id" class="block text-sm font-semibold text-gray-700 mb-2">オペレーション担当（任意）</label>
                                <select name="operation_person_id" id="sync_operation_person_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-[#00afcc] focus:border-[#00afcc] transition-all">
                                    <option value="">すべて</option>
                                    @foreach($operationPersons ?? [] as $op)
                                        <option value="{{ $op->id }}" {{ old('operation_person_id') == $op->id ? 'selected' : '' }}>{{ $op->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="flex-1">
                                <label for="sync_since_date" class="block text-sm font-semibold text-gray-700 mb-2">〇月〇日以降のみ同期</label>
                                <input type="date" name="since_date" id="sync_since_date" value="{{ old('since_date', now()->subMonths(2)->format('Y-m-d')) }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-[#00afcc] focus:border-[#00afcc] transition-all">
                            </div>
                            <div class="flex-shrink-0">
                                <button type="submit" class="w-full lg:w-auto px-6 py-2.5 bg-gradient-to-r from-[#00afcc] to-[#0088a3] text-white font-semibold rounded-lg hover:from-[#0088a3] hover:to-[#006b7f] transition-all duration-200 shadow-md hover:shadow-lg whitespace-nowrap">
                                    <span class="flex items-center justify-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        口コミ・写真・投稿を同期
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <!-- ① キーワード順位 折れ線グラフ -->
            @if($keywords->count() > 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">キーワード順位推移</h3>
                        <div class="relative" style="height: 400px;">
                            <canvas id="rankChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- ② キーワード順位テーブル -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">キーワード順位テーブル</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">キーワード</th>
                                        @foreach($dates as $date)
                                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                {{ \Carbon\Carbon::parse($date)->format('m/d') }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($keywords as $keyword)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $keyword->keyword }}
                                            </td>
                                            @foreach($dates as $date)
                                                <td class="px-3 py-4 whitespace-nowrap text-sm text-center text-gray-500">
                                                    @php
                                                        $log = $keyword->rankLogs->first(function ($log) use ($date) {
                                                            return $log->checked_at->format('Y-m-d') === $date;
                                                        });
                                                        $rank = $log ? $log->position : null;
                                                    @endphp
                                                    @if($rank !== null)
                                                        {{ $rank }}位
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                    @if(!empty($insightsMetrics) && isset($insightsMetrics['daily']) && !empty($insightsMetrics['daily']))
                                    <!-- GBP Insights 日別データ行 -->
                                    <tr class="bg-blue-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">表示回数</td>
                                        @foreach($dates as $date)
                                            <td class="px-3 py-4 whitespace-nowrap text-sm text-center text-gray-900 font-medium">
                                                {{ number_format($insightsMetrics['daily'][$date]['impressions'] ?? 0) }}
                                            </td>
                                        @endforeach
                                    </tr>
                                    <tr class="bg-blue-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">ウェブサイト</td>
                                        @foreach($dates as $date)
                                            <td class="px-3 py-4 whitespace-nowrap text-sm text-center text-gray-900 font-medium">
                                                {{ number_format($insightsMetrics['daily'][$date]['website'] ?? 0) }}
                                            </td>
                                        @endforeach
                                    </tr>
                                    <tr class="bg-blue-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">電話</td>
                                        @foreach($dates as $date)
                                            <td class="px-3 py-4 whitespace-nowrap text-sm text-center text-gray-900 font-medium">
                                                {{ number_format($insightsMetrics['daily'][$date]['phone'] ?? 0) }}
                                            </td>
                                        @endforeach
                                    </tr>
                                    <tr class="bg-blue-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">ルート</td>
                                        @foreach($dates as $date)
                                            <td class="px-3 py-4 whitespace-nowrap text-sm text-center text-gray-900 font-medium">
                                                {{ number_format($insightsMetrics['daily'][$date]['directions'] ?? 0) }}
                                            </td>
                                        @endforeach
                                    </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 text-center">
                        <p class="text-gray-500">MEOキーワードが設定されていません</p>
                        <a href="{{ session('operator_id') ? route('operator.shops.show', $shop->id) : route('shops.show', $shop->id) }}" class="text-[#00afcc] hover:text-[#0088a3] mt-2 inline-block">
                            店舗詳細でキーワードを設定する
                        </a>
                    </div>
                </div>
            @endif

            <!-- ③ KPIサマリー -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">KPIサマリー</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 {{ !empty($insightsMetrics) ? 'lg:grid-cols-9' : 'lg:grid-cols-5' }} gap-4">
                        @if(!empty($insightsMetrics))
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500">表示回数</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">
                                {{ number_format($insightsMetrics['BUSINESS_IMPRESSIONS'] ?? 0) }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500">Web</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">
                                {{ number_format($insightsMetrics['WEBSITE_CLICKS'] ?? 0) }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500">電話</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">
                                {{ number_format($insightsMetrics['CALL_CLICKS'] ?? 0) }}
                            </dd>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500">ルート</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">
                                {{ number_format($insightsMetrics['DIRECTIONS_REQUESTS'] ?? 0) }}
                            </dd>
                        </div>
                        @endif
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500">口コミ数</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $currentReviewCount }}</dd>
                            @if($reviewMoM != 0)
                                <dd class="mt-1 text-sm {{ $reviewMoM > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $reviewMoM > 0 ? '+' : '' }}{{ $reviewMoM }}% (前月比)
                                </dd>
                            @endif
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500">評価点数</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">
                                {{ $currentRating ? number_format($currentRating, 1) : '—' }}
                            </dd>
                            @if($ratingMoM != 0)
                                <dd class="mt-1 text-sm {{ $ratingMoM > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $ratingMoM > 0 ? '+' : '' }}{{ $ratingMoM }}% (前月比)
                                </dd>
                            @endif
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500">返信率</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $replyRate }}%</dd>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500">
                                有効投稿数（Google評価対象）
                                <span class="ml-1 text-gray-400 cursor-help" title="Google API は古い投稿や期限切れ投稿を返さないため、ここに表示される数は検索順位に影響する投稿数です">
                                    <svg class="inline-block w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                                    </svg>
                                </span>
                            </dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $postCount }}</dd>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500">写真数</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $currentPhotoCount }}</dd>
                            @if($photoMoM != 0)
                                <dd class="mt-1 text-sm {{ $photoMoM > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $photoMoM > 0 ? '+' : '' }}{{ $photoMoM }}% (前月比)
                                </dd>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        @if($keywords->count() > 0)
        const ctx = document.getElementById('rankChart');
        const dates = @json($dates);
        const rankData = @json($rankData);

        // グラフ用のデータセットを生成
        const datasets = [];
        const colors = [
            'rgb(0, 175, 204)', // #00afcc
            'rgb(255, 99, 132)',
            'rgb(54, 162, 235)',
            'rgb(255, 206, 86)',
            'rgb(75, 192, 192)',
            'rgb(153, 102, 255)',
            'rgb(255, 159, 64)',
            'rgb(199, 199, 199)',
            'rgb(83, 102, 255)',
            'rgb(255, 99, 255)',
        ];

        Object.keys(rankData).forEach((keywordId, index) => {
            const data = rankData[keywordId];
            const ranks = dates.map(date => {
                const rank = data.ranks[date];
                return rank !== null ? rank : null;
            });

            datasets.push({
                label: data.keyword,
                data: ranks,
                borderColor: colors[index % colors.length],
                backgroundColor: colors[index % colors.length] + '20',
                tension: 0.4,
                spanGaps: true,
                order: 1, // 折れ線グラフを前面に配置
            });
        });

        // 表示回数データを追加（棒グラフとして）
        @if(!empty($insightsMetrics) && isset($insightsMetrics['daily']) && !empty($gbp_impressions_final_clean))
        // 棒グラフを先に追加して、折れ線の後ろに配置されるようにする
        datasets.unshift({
            label: '表示回数',
            type: 'bar', // 棒グラフにする
            data: @json($gbp_impressions_final_clean),
            yAxisID: 'y_impressions', // 表示回数専用の軸を指定
            backgroundColor: 'rgba(54, 162, 235, 0.3)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1,
            order: 0, // 棒グラフを折れ線の後ろに配置（orderが小さいほど後ろ）
        });
        @endif

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates.map(date => {
                    return new Date(date).toLocaleDateString('ja-JP', { month: 'short', day: 'numeric' });
                }),
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false,
                    }
                },
                scales: {
                    y: { // 左軸：順位用
                        reverse: true, // 順位なので上を1位にする
                        display: true,
                        position: 'left',
                        min: 1,
                        max: 100,
                        title: {
                            display: true,
                            text: '順位（1が最上位）'
                        }
                    },
                    @if(!empty($insightsMetrics) && isset($insightsMetrics['daily']) && !empty($gbp_impressions_final_clean))
                    y_impressions: { // 右軸：表示回数用
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: { drawOnChartArea: false }, // グリッド線が重ならないように
                        title: {
                            display: true,
                            text: '表示回数'
                        }
                    },
                    @endif
                    x: {
                        title: {
                            display: true,
                            text: '日付'
                        }
                    }
                }
            }
        });
        @endif

        // PDFダウンロード時にグラフを画像化
        document.getElementById('pdfDownloadForm').addEventListener('submit', function(e) {
            @if($keywords->count() > 0)
            const canvas = document.getElementById('rankChart');
            if (canvas) {
                try {
                    const imageData = canvas.toDataURL('image/png');
                    document.getElementById('chart_image_input').value = imageData;
                } catch (error) {
                    console.error('グラフ画像の変換に失敗しました:', error);
                    // エラーが発生してもフォーム送信は続行
                }
            }
            @endif
        });
    </script>
</x-app-layout>

