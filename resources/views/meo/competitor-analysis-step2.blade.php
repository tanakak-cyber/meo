<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>競合分析レポート - MEOのばすくん</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: #f5f7fb;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans JP', sans-serif;
        }
        .report-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            padding: 24px;
            margin-bottom: 24px;
        }
        .report-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 32px;
            border-radius: 12px 12px 0 0;
            margin-bottom: 0;
        }
        .report-logo {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        .report-title {
            font-size: 28px;
            font-weight: 700;
            margin-top: 16px;
        }
        .report-meta {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 12px;
        }
        .highlight-card {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 5px solid #f59e0b;
            padding: 20px;
            border-radius: 8px;
            margin: 16px 0;
        }
        .checklist-item {
            display: flex;
            align-items: flex-start;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .checklist-item:last-child {
            border-bottom: none;
        }
        .checklist-checkbox {
            width: 24px;
            height: 24px;
            border: 2px solid #d1d5db;
            border-radius: 4px;
            margin-right: 12px;
            margin-top: 2px;
            flex-shrink: 0;
        }
        .comparison-table-modern {
            width: 100%;
        }
        .comparison-table-modern table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }
        .comparison-table-modern thead {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        }
        .comparison-table-modern th {
            padding: 16px 20px;
            text-align: left;
            font-size: 0.875rem;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .comparison-table-modern tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }
        .comparison-table-modern tbody tr:last-child {
            border-bottom: none;
        }
        .comparison-table-modern td {
            padding: 16px 20px;
            font-size: 0.9375rem;
            color: #374151;
            vertical-align: middle;
        }
        .comparison-table-modern td:first-child {
            font-weight: 600;
            color: #111827;
            background: #f9fafb;
        }
        .comparison-table-modern td:nth-child(2) {
            background: linear-gradient(135deg, #eef2ff 0%, #f3e8ff 100%);
            font-weight: 700;
            color: #4f46e5;
            border-left: 4px solid #4f46e5;
        }
        .comparison-table-modern td:nth-child(3),
        .comparison-table-modern td:nth-child(4) {
            background-color: white;
        }
        .value-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            background: #eef2ff;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #312e81;
        }
        .status-yes {
            display: inline-flex;
            align-items: center;
            color: #059669;
            font-weight: 600;
        }
        .status-yes::before {
            content: '✓';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: #d1fae5;
            border-radius: 50%;
            margin-right: 8px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        .status-no {
            display: inline-flex;
            align-items: center;
            color: #dc2626;
            font-weight: 600;
        }
        .status-no::before {
            content: '✕';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: #fee2e2;
            border-radius: 50%;
            margin-right: 8px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        .status-missing {
            display: inline-flex;
            align-items: center;
            color: #6b7280;
            font-weight: 500;
            font-style: italic;
        }
        .status-missing::before {
            content: '—';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: #f3f4f6;
            border-radius: 50%;
            margin-right: 8px;
            font-weight: 700;
            font-size: 0.875rem;
        }
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e5e7eb;
        }
        .report-footer {
            text-align: center;
            padding: 24px;
            color: #6b7280;
            font-size: 12px;
            border-top: 1px solid #e5e7eb;
            margin-top: 48px;
        }
    </style>
</head>
<body>
    <div class="min-h-screen py-12">
        <div class="max-w-5xl mx-auto px-6">
            <!-- レポートヘッダー -->
            <div class="report-card report-header">
                <div class="report-logo">MEOのばすくん</div>
                <div class="report-title">競合分析レポート</div>
                <div class="report-meta">
                    <div>対象キーワード：{{ $keyword ?? '未設定' }}</div>
                    <div>作成日：{{ date('Y年m月d日') }}</div>
                </div>
            </div>

            <!-- ① MEO診断サマリー -->
            @if(isset($analysis['rank_summary']))
            <div class="report-card">
                <div class="section-title">MEO診断サマリー</div>
                <p class="text-gray-700 text-lg leading-relaxed whitespace-pre-wrap">{{ $analysis['rank_summary'] }}</p>
            </div>
            @endif

            <!-- ② 比較サマリー（表） -->
            @if(isset($analysis['comparison_table']) && $analysis['comparison_table'] !== null)
            <div class="report-card">
                <div class="section-title">比較サマリー</div>
                <div class="overflow-x-auto">
                    <div class="comparison-table-modern">
                        {!! \Illuminate\Support\Str::markdown($analysis['comparison_table']) !!}
                    </div>
                </div>
            </div>
            @endif

            <!-- 自社チェックリスト（single の場合） -->
            @if(isset($analysis['self_checklist']))
            <div class="report-card">
                <div class="section-title">自社チェックリスト</div>
                <div class="prose max-w-none">
                    {!! \Illuminate\Support\Str::markdown($analysis['self_checklist']) !!}
                </div>
            </div>
            @endif

            <!-- ③ 今すぐ差がつく項目（強調ブロック） -->
            @if(isset($analysis['differentiation_opportunities']) && is_array($analysis['differentiation_opportunities']) && count($analysis['differentiation_opportunities']) > 0)
            <div class="report-card">
                <div class="highlight-card">
                    <div class="flex items-center mb-4">
                        <span class="text-2xl mr-2">🟡</span>
                        <h3 class="text-xl font-bold text-gray-900">今すぐ差がつく項目</h3>
                    </div>
                    <ul class="space-y-2">
                        @foreach($analysis['differentiation_opportunities'] as $item)
                            <li class="flex items-start">
                                <span class="text-amber-600 mr-2 mt-1">・</span>
                                <span class="text-gray-800 flex-1">{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif

            <!-- ④ なぜ自社が負けているのか -->
            @if(isset($analysis['situation']))
            <div class="report-card">
                <div class="section-title">なぜ自社が負けているのか</div>
                <div class="prose max-w-none">
                    <p class="text-gray-700 leading-relaxed whitespace-pre-wrap text-base">{{ $analysis['situation'] }}</p>
                </div>
            </div>
            @endif

            <!-- ⑤ 初期設定で変えるべきこと -->
            @if(isset($analysis['initial_settings']) && is_array($analysis['initial_settings']) && count($analysis['initial_settings']) > 0)
            <div class="report-card">
                <div class="section-title">初期設定で変えるべきこと</div>
                <ul class="space-y-3">
                    @foreach($analysis['initial_settings'] as $item)
                        <li class="flex items-start">
                            <span class="flex-shrink-0 w-6 h-6 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-semibold mr-3 mt-0.5">
                                {{ $loop->iteration }}
                            </span>
                            <span class="text-gray-700 flex-1 text-base">{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- ⑥ 運用で目指すべきこと -->
            @if(isset($analysis['operations']) && is_array($analysis['operations']) && count($analysis['operations']) > 0)
            <div class="report-card">
                <div class="section-title">運用で目指すべきこと</div>
                <ul class="space-y-3">
                    @foreach($analysis['operations'] as $item)
                        <li class="flex items-start">
                            <span class="flex-shrink-0 w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-sm font-semibold mr-3 mt-0.5">
                                {{ $loop->iteration }}
                            </span>
                            <span class="text-gray-700 flex-1 text-base">{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- ⑦ 実行計画（編集可能なチェックリスト風UI） -->
            @if(isset($analysis['execution_plan_initial']) || isset($analysis['execution_plan_operations']))
            <div class="report-card">
                <div class="section-title">実行計画</div>

                @if(isset($analysis['execution_plan_initial']))
                <div class="mb-8">
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">初期</h4>
                    <textarea 
                        id="execution_plan_initial"
                        rows="10"
                        class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-gray-50 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 resize-none font-sans text-base leading-relaxed"
                        placeholder="実行計画（初期）を入力してください...">{{ $analysis['execution_plan_initial'] ?? '' }}</textarea>
                </div>
                @endif

                @if(isset($analysis['execution_plan_operations']))
                <div>
                    <h4 class="text-lg font-semibold text-gray-900 mb-4">運用</h4>
                    <textarea 
                        id="execution_plan_operations"
                        rows="10"
                        class="w-full px-4 py-3 rounded-lg border-2 border-gray-300 bg-gray-50 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 resize-none font-sans text-base leading-relaxed"
                        placeholder="実行計画（運用）を入力してください...">{{ $analysis['execution_plan_operations'] ?? '' }}</textarea>
                </div>
                @endif
            </div>
            @endif

            <!-- フッター -->
            <div class="report-footer">
                <div class="font-semibold mb-2">MEOのばすくん｜MEO診断レポート</div>
                <div class="text-xs">※本レポートはGoogleビジネスプロフィールの公開情報および入力情報をもとに分析しています</div>
            </div>
        </div>
    </div>

    <script>
        // テーブルの内容を動的に処理してモダンなデザインに変換
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.querySelector('.comparison-table-modern table');
            if (!table) return;

            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, index) => {
                    if (index === 0) return;

                    let text = cell.textContent.trim();
                    
                    // 数値のバッジ化
                    const numberMatch = text.match(/^(\d+)(位|件|本|枚)?/);
                    if (numberMatch) {
                        const number = numberMatch[1];
                        const unit = numberMatch[2] || '';
                        cell.innerHTML = `<span class="value-badge">${number}${unit}</span>`;
                        return;
                    }

                    // Yes/あり/している のアイコン化
                    if (text.includes('ある') || text.includes('Yes') || text.includes('している') || text.includes('一致')) {
                        cell.innerHTML = `<span class="status-yes">${text}</span>`;
                        return;
                    }

                    // なし/No/していない のアイコン化
                    if (text.includes('なし') || text.includes('No') || text.includes('していない') || text.includes('不一致')) {
                        cell.innerHTML = `<span class="status-no">${text}</span>`;
                        return;
                    }

                    // 未設定のスタイル
                    if (text.includes('未設定') || text.includes('__MISSING__') || text === '' || text === '-') {
                        cell.innerHTML = `<span class="status-missing">未設定</span>`;
                        return;
                    }

                    // 数値が含まれている場合はバッジ化
                    const hasNumber = /\d+/.test(text);
                    if (hasNumber) {
                        const numbers = text.match(/\d+/g);
                        if (numbers) {
                            let newText = text;
                            numbers.forEach(num => {
                                newText = newText.replace(num, `<span class="value-badge">${num}</span>`);
                            });
                            cell.innerHTML = newText;
                        }
                    }
                });
            });

        });
    </script>
</body>
</html>
