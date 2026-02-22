<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                    {{ __('スケジュール') }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">口コミ・写真の投稿状況を日別で確認</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ session('operator_id') ? route('operator.schedule', ['year' => $prevMonth->year, 'month' => $prevMonth->month]) : route('shops.schedule', ['year' => $prevMonth->year, 'month' => $prevMonth->month]) }}" 
                   class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 shadow-sm hover:shadow">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    前月
                </a>
                <div class="px-5 py-2 bg-gradient-to-r from-[#00afcc]/10 to-[#0088a3]/10 border-2 border-[#00afcc] text-gray-900 rounded-lg font-semibold shadow-md">
                    {{ $targetDate->format('Y年m月') }}
                </div>
                <a href="{{ session('operator_id') ? route('operator.schedule', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) : route('shops.schedule', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}" 
                   class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 shadow-sm hover:shadow">
                    次月
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mb-6">
            <!-- 絞り込み -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4">
                    <form method="GET" action="{{ session('operator_id') ? route('operator.schedule') : route('shops.schedule') }}" class="space-y-4">
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="month" value="{{ $month }}">
                        <div class="flex flex-wrap items-center gap-4">
                            <span class="text-sm font-medium text-gray-700">店舗ステータス:</span>
                            <label class="flex items-center">
                                <input type="radio" name="status" value="all" {{ ($status ?? 'active') === 'all' ? 'checked' : '' }} 
                                       onchange="this.form.submit()" class="mr-2">
                                <span class="text-sm text-gray-700">すべて</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="status" value="active" {{ ($status ?? 'active') === 'active' ? 'checked' : '' }} 
                                       onchange="this.form.submit()" class="mr-2">
                                <span class="text-sm text-gray-700">契約中店舗のみ</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="status" value="expired" {{ ($status ?? 'active') === 'expired' ? 'checked' : '' }} 
                                       onchange="this.form.submit()" class="mr-2">
                                <span class="text-sm text-gray-700">契約終了店舗のみ</span>
                            </label>
                        </div>
                        <div class="flex flex-wrap items-center gap-4">
                            <label class="text-sm font-medium text-gray-700">営業担当:</label>
                            <select name="sales_person_id" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">すべて</option>
                                @foreach($salesPersons ?? [] as $sp)
                                    <option value="{{ $sp->id }}" {{ ($salesPersonId ?? '') == $sp->id ? 'selected' : '' }}>{{ $sp->name }}</option>
                                @endforeach
                            </select>
                            
                            <label class="text-sm font-medium text-gray-700 ml-4">オペレーション担当:</label>
                            <select name="operation_person_id" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">すべて</option>
                                @foreach($operationPersons ?? [] as $op)
                                    <option value="{{ $op->id }}" {{ ($operationPersonId ?? '') == $op->id ? 'selected' : '' }}>{{ $op->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-6 bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-800 px-4 py-3 rounded-lg shadow-sm" role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        @if(session('sync_details'))
                            <span class="font-medium cursor-pointer hover:underline" onclick="showSyncDetailsModal()">{{ session('success') }}</span>
                        @else
                            <span class="font-medium">{{ session('success') }}</span>
                        @endif
                    </div>
                </div>
            @endif

            @if(session('sync_batch_id'))
                <div data-sync-batch-id="{{ session('sync_batch_id') }}" style="display: none;"></div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-gradient-to-r from-red-50 to-rose-50 border-l-4 border-red-500 text-red-800 px-4 py-3 rounded-lg shadow-sm" role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        @if(session('sync_details'))
                            <span class="font-medium cursor-pointer hover:underline" onclick="showSyncDetailsModal()">{{ session('error') }}</span>
                        @else
                            <span class="font-medium">{{ session('error') }}</span>
                        @endif
                    </div>
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-6 bg-gradient-to-r from-yellow-50 to-amber-50 border-l-4 border-yellow-500 text-yellow-800 px-4 py-3 rounded-lg shadow-sm" role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        @if(session('sync_details'))
                            <span class="font-medium cursor-pointer hover:underline" onclick="showSyncDetailsModal()">{{ session('warning') }}</span>
                        @else
                            <span class="font-medium">{{ session('warning') }}</span>
                        @endif
                    </div>
                </div>
            @endif

            <!-- 口コミ・写真・投稿同期 -->
            @if((isset($shops) && $shops->count() > 0) || (isset($shopsForSync) && $shopsForSync->count() > 0))
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
                        <form method="POST" action="{{ session('operator_id') ? route('operator.schedule.sync') : route('shops.schedule.sync') }}" class="flex flex-col lg:flex-row lg:items-end gap-4" onsubmit="if(document.querySelector('select[name=shop_id]').value === 'all') { return confirm('全店舗の口コミ・写真・投稿を同期しますか？\n時間がかかる場合があります。'); }">
                            @csrf
                            <input type="hidden" name="year" value="{{ $year }}">
                            <input type="hidden" name="month" value="{{ $month }}">
                            <div class="flex-1">
                                <label for="sync_shop_id" class="block text-sm font-semibold text-gray-700 mb-2">店舗</label>
                                <select name="shop_id" id="sync_shop_id" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-[#00afcc] focus:border-[#00afcc] transition-all">
                                    <option value="all" selected>全店舗</option>
                                    @if(isset($shopsForSync) && $shopsForSync->count() > 0)
                                        @foreach($shopsForSync as $shop)
                                            <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                                        @endforeach
                                    @else
                                        @foreach($shops as $shop)
                                            @if($shop->gbp_location_id)
                                                <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                                            @endif
                                        @endforeach
                                    @endif
                                </select>
                            </div>
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

            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 border border-gray-300" style="min-width: 1200px;">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300 sticky left-0 bg-gray-50" style="position: sticky; left: 0; top: 0; z-index: 50;">No</th>
                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 border border-gray-300 sticky bg-gray-50 whitespace-nowrap" style="writing-mode: horizontal-tb; text-orientation: mixed; position: sticky; left: 56px; top: 0; z-index: 50; min-width: 220px; width: 220px;">店舗名</th>
                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 border border-gray-300 sticky bg-gray-50 whitespace-nowrap" style="writing-mode: horizontal-tb; text-orientation: mixed; position: sticky; left: 276px; top: 0; z-index: 50; min-width: 180px; width: 180px;">店舗担当者</th>
                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 border border-gray-300 sticky bg-gray-50 whitespace-nowrap" style="writing-mode: horizontal-tb; text-orientation: mixed; position: sticky; left: 456px; top: 0; z-index: 50; min-width: 180px; width: 180px;">オペレーション担当</th>
                                <th class="px-6 py-4 text-center text-sm font-bold text-gray-700 border border-gray-300 min-w-[150px] whitespace-nowrap sticky top-0 z-20 bg-gray-50" style="writing-mode: horizontal-tb; text-orientation: mixed;">口ノルマ</th>
                                <th class="px-6 py-4 text-center text-sm font-bold text-gray-700 border border-gray-300 min-w-[150px] whitespace-nowrap sticky top-0 z-20 bg-gray-50" style="writing-mode: horizontal-tb; text-orientation: mixed;">写ノルマ</th>
                                <th class="px-6 py-4 text-center text-sm font-bold text-gray-700 border border-gray-300 min-w-[150px] whitespace-nowrap sticky top-0 z-20 bg-gray-50" style="writing-mode: horizontal-tb; text-orientation: mixed;">動ノルマ</th>
                                <th class="px-6 py-4 text-center text-sm font-bold text-gray-700 border border-gray-300 min-w-[150px] whitespace-nowrap sticky top-0 z-20 bg-gray-50" style="writing-mode: horizontal-tb; text-orientation: mixed;">
                                    有効投稿数
                                    <span class="ml-1 text-gray-400 cursor-help" title="Google API は古い投稿や期限切れ投稿を返さないため、ここに表示される数は検索順位に影響する投稿数です">
                                        <svg class="inline-block w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                                        </svg>
                                    </span>
                                </th>
                                @for($day = 1; $day <= $daysInMonth; $day++)
                                    @php
                                        $date = Carbon\Carbon::create($year, $month, $day);
                                        $isWeekday = \App\Helpers\HolidayHelper::isWeekday($date);
                                        $cellBgColor = $isWeekday ? '#00afcc' : '#727070';
                                        $isToday = $date->isToday();
                                        $borderStyle = $isToday ? 'border: 3px solid #ef4444;' : 'border: 1px solid #d1d5db;';
                                    @endphp
                                    <th class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider min-w-[60px] sticky top-0 z-10" style="background-color: {{ $cellBgColor }}; color: white; {{ $borderStyle }}">
                                        <div>{{ $day }}</div>
                                        @if((Auth::check() || session('operator_id')) && isset($rankFetchedCountByDate[$day]))
                                            <div class="text-xs font-normal mt-1" style="color: rgba(255, 255, 255, 0.9);">
                                                {{ $rankFetchedCountByDate[$day]['fetched'] }} / {{ $rankFetchedCountByDate[$day]['total'] }}
                                            </div>
                                            @if($isToday && isset($rankFetchedCountByDate[$day]) && $rankFetchedCountByDate[$day]['fetched'] < $rankFetchedCountByDate[$day]['total'])
                                                <button type="button" 
                                                    class="batch-fetch-btn mt-2 px-2 py-1 text-xs font-semibold rounded shadow-sm transition-all duration-200 hover:shadow-md"
                                                    style="background-color: #3b82f6; color: #fff;"
                                                    data-date="{{ $date->format('Y-m-d') }}"
                                                    onclick="handleBatchRankFetch('{{ $date->format('Y-m-d') }}')"
                                                    title="表示中の全店舗の順位を一括取得">
                                                    一括取得
                                                </button>
                                            @endif
                                        @endif
                                    </th>
                                @endfor
                                <th class="px-6 py-4 text-center text-sm font-bold text-gray-700 border border-gray-300 bg-blue-50 min-w-[200px] whitespace-nowrap sticky top-0 z-10" style="writing-mode: horizontal-tb; text-orientation: mixed;">合計(口コミ/写真/投稿)</th>
                                <th class="px-6 py-4 text-center text-sm font-bold text-gray-700 border border-gray-300 bg-yellow-50 min-w-[200px] whitespace-nowrap sticky top-0 z-10" style="writing-mode: horizontal-tb; text-orientation: mixed;">差分(口コミ/写真)</th>
                                <th class="px-6 py-4 text-center text-sm font-bold text-gray-700 border border-gray-300 bg-gray-50 min-w-[220px] whitespace-nowrap sticky top-0 z-10" style="writing-mode: horizontal-tb; text-orientation: mixed;">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($scheduleData as $index => $data)
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 sticky left-0 bg-white z-20 text-center" style="position: sticky; left: 0;">
                                        {{ $index + 1 }}
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-900 border border-gray-300 sticky bg-white z-20" style="position: sticky; left: 56px; min-width: 220px; width: 220px;">
                                        <div class="flex flex-col">
                                            <a href="{{ session('operator_id') ? route('operator.shops.show', $data['shop']) : route('shops.show', $data['shop']) }}" class="text-[#00afcc] hover:text-[#0088a3] font-semibold hover:underline">
                                                {{ $data['shop']->name }}
                                            </a>
                                            @if($data['shop']->blog_option)
                                                <span class="inline-flex items-center justify-center h-6 bg-green-500 text-white text-xs font-bold rounded mt-1" style="width: 5.5rem;" title="ブログ投稿お任せ">
                                                    ブログお任せ
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-900 border border-gray-300 sticky bg-white z-20" style="position: sticky; left: 276px; min-width: 180px; width: 180px;">
                                        <div class="flex flex-col">
                                            <div class="font-medium">{{ $data['shop']->shop_contact_name ?? '—' }}</div>
                                            @if($data['shop']->shop_contact_phone)
                                                <div class="text-xs text-gray-500 mt-1">{{ $data['shop']->shop_contact_phone }}</div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-900 border border-gray-300 sticky bg-white z-20" style="position: sticky; left: 456px; min-width: 180px; width: 180px;">
                                        @if($data['shop']->operationPerson && is_object($data['shop']->operationPerson))
                                            {{ $data['shop']->operationPerson->name }}
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                                        {{ $data['review_target'] ?: '-' }}
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                                        {{ $data['photo_target'] ?: '-' }}
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                                        {{ $data['video_target'] ?: '-' }}
                                    </td>
                                    <td class="px-6 py-3 text-sm font-semibold text-gray-900 border border-gray-300 text-center bg-blue-50">
                                        {{ $data['post_total'] ?? 0 }}
                                    </td>
                                    @for($day = 1; $day <= $daysInMonth; $day++)
                                        @php
                                            $date = Carbon\Carbon::create($year, $month, $day);
                                            $dateStr = $date->format('Y-m-d');
                                            $hasContactLog = $data['daily'][$day]['has_contact_log'] ?? false;
                                            $isRankFetched = $data['daily'][$day]['is_rank_fetched'] ?? false;
                                            $today = Carbon\Carbon::today();
                                            $isFuture = $date->isFuture();
                                            $isToday = $date->isToday();
                                            $isPast = $date->isPast() && !$date->isToday();
                                            
                                            // キーワード取得ボタンの状態を決定
                                            $rankButtonClass = '';
                                            $rankButtonDisabled = false;
                                            $rankButtonLabel = '未取得';
                                            
                                            if ($isFuture) {
                                                // 未来：グレー（#727070）、disabled
                                                $rankButtonClass = 'text-white cursor-not-allowed';
                                                $rankButtonStyle = 'background-color: #727070;';
                                                $rankButtonDisabled = true;
                                                $rankButtonLabel = '未取得';
                                            } elseif ($isRankFetched) {
                                                // 済み：青、disabled（当日・過去共通）
                                                $rankButtonClass = 'bg-blue-500 text-white cursor-not-allowed';
                                                $rankButtonDisabled = true;
                                                $rankButtonLabel = '済み';
                                            } elseif ($isToday) {
                                                // 当日・未取得：赤、enabled
                                                $rankButtonClass = 'bg-red-500 hover:bg-red-600 text-white';
                                                $rankButtonStyle = '';
                                                $rankButtonDisabled = false;
                                                $rankButtonLabel = '未取得';
                                            } else {
                                                // 過去・未取得：赤、disabled
                                                $rankButtonClass = 'bg-red-500 text-white cursor-not-allowed';
                                                $rankButtonStyle = '';
                                                $rankButtonDisabled = true;
                                                $rankButtonLabel = '未取得';
                                            }
                                        @endphp
                                        @php
                                            $todayBorderStyle = $isToday ? 'border: 3px solid #ef4444;' : 'border: 1px solid #d1d5db;';
                                        @endphp
                                        <td class="px-3 py-2 text-sm text-gray-900 text-center relative" style="{{ $todayBorderStyle }}">
                                            <div class="flex flex-col items-center gap-1">
                                        @php
                                            $reviewCount = $data['daily'][$day]['review'] ?? 0;
                                            $photoCount = $data['daily'][$day]['photo'] ?? 0;
                                            $postCount = $data['daily'][$day]['post'] ?? 0;
                                            $videoDisplayDays = $data['video_display_days'] ?? [];
                                            $blogDisplayDays = $data['blog_display_days'] ?? [];
                                            $photoDisplayDays = $data['photo_display_days'] ?? [];
                                            // 型を統一して比較（整数に変換）
                                            $dayInt = (int)$day;
                                            $photoDisplayDaysInt = array_map('intval', $photoDisplayDays);
                                            $shouldShowVideoMark = in_array($dayInt, array_map('intval', $videoDisplayDays), true);
                                            $shouldShowBlogMark = in_array($dayInt, array_map('intval', $blogDisplayDays), true);
                                            $shouldShowPhotoMark = in_array($dayInt, $photoDisplayDaysInt, true);
                                        @endphp
                                        @if($reviewCount > 0 || $photoCount > 0 || $postCount > 0)
                                            <span class="font-medium">
                                                {{ $reviewCount }} / {{ $photoCount }} / {{ $postCount }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                        @if($shouldShowPhotoMark)
                                            <span class="inline-flex items-center justify-center w-6 h-6 text-white text-xs font-bold rounded mt-1" style="background-color: #f97316;" title="写真投稿予定日">
                                                写
                                            </span>
                                        @endif
                                        @if($shouldShowVideoMark)
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-purple-500 text-white text-xs font-bold rounded mt-1" title="動画投稿予定日">
                                                動
                                            </span>
                                        @endif
                                        @if($shouldShowBlogMark)
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-green-500 text-white text-xs font-bold rounded mt-1" title="ブログ投稿予定日">
                                                ブ
                                            </span>
                                        @endif
                                                <div class="flex flex-col items-center gap-1">
                                                    <button 
                                                        type="button"
                                                        class="contact-btn w-6 h-6 rounded text-xs font-semibold transition-colors {{ $hasContactLog ? 'bg-blue-500 hover:bg-blue-600 text-white' : 'bg-red-500 hover:bg-red-600 text-white' }}"
                                                        data-shop-id="{{ $data['shop']->id }}"
                                                        data-date="{{ $dateStr }}"
                                                        data-has-log="{{ $hasContactLog ? '1' : '0' }}"
                                                        onclick="openContactModal({{ $data['shop']->id }}, '{{ $dateStr }}', {{ $hasContactLog ? 'true' : 'false' }})"
                                                        title="連絡履歴">
                                                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                        </svg>
                                                    </button>
                                                    <button 
                                                        type="button"
                                                        class="rank-fetch-btn px-2 py-1 rounded text-xs font-semibold transition-colors {{ $rankButtonClass }}"
                                                        @if(isset($rankButtonStyle) && $rankButtonStyle)
                                                        style="{{ $rankButtonStyle }}"
                                                        @endif
                                                        data-shop-id="{{ $data['shop']->id }}"
                                                        data-date="{{ $dateStr }}"
                                                        data-is-fetched="{{ $isRankFetched ? 'true' : 'false' }}"
                                                        {{ $rankButtonDisabled ? 'disabled' : '' }}
                                                        @if(!$rankButtonDisabled)
                                                        onclick="handleRankFetchClick({{ $data['shop']->id }}, '{{ $dateStr }}', {{ $isRankFetched ? 'true' : 'false' }})"
                                                        @endif
                                                        title="{{ $rankButtonLabel }}">
                                                        {{ $rankButtonLabel }}
                                                    </button>
                                                    @if($isRankFetched && $isToday)
                                                    <button 
                                                        type="button"
                                                        class="rank-delete-btn ml-1 px-1 py-1 rounded text-xs font-semibold transition-colors bg-red-500 hover:bg-red-600 text-white"
                                                        data-shop-id="{{ $data['shop']->id }}"
                                                        data-date="{{ $dateStr }}"
                                                        onclick="handleRankDeleteClick({{ $data['shop']->id }}, '{{ $dateStr }}')"
                                                        title="削除">
                                                        ×
                                                    </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    @endfor
                                    <td class="px-6 py-3 text-sm font-semibold text-gray-900 border border-gray-300 text-center bg-blue-50">
                                        {{ $data['review_total'] }} / {{ $data['photo_total'] }} / {{ $data['post_total'] ?? 0 }}
                                    </td>
                                    <td class="px-6 py-3 text-sm font-semibold border border-gray-300 text-center bg-yellow-50">
                                        <div class="flex flex-col gap-1">
                                            <span class="{{ $data['review_diff'] < 0 ? 'text-red-600' : ($data['review_diff'] > 0 ? 'text-green-600' : 'text-gray-900') }}">
                                                @if($data['review_diff'] < 0)
                                                    あと{{ abs($data['review_diff']) }}件
                                                @elseif($data['review_diff'] > 0)
                                                    +{{ $data['review_diff'] }}
                                                @else
                                                    {{ $data['review_diff'] }}
                                                @endif
                                            </span>
                                            <span class="{{ $data['photo_diff'] < 0 ? 'text-red-600' : ($data['photo_diff'] > 0 ? 'text-green-600' : 'text-gray-900') }}">
                                                @if($data['photo_diff'] < 0)
                                                    あと{{ abs($data['photo_diff']) }}件
                                                @elseif($data['photo_diff'] > 0)
                                                    +{{ $data['photo_diff'] }}
                                                @else
                                                    {{ $data['photo_diff'] }}
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-sm border border-gray-300 bg-gray-50">
                                        <div class="flex flex-col gap-2" style="writing-mode: horizontal-tb; text-orientation: mixed;">
                                            @if($data['shop']->gbp_location_id)
                                                @php
                                                    // Google Mapsの口コミ投稿URLを生成
                                                    $reviewUrl = $data['shop']->getGoogleMapsReviewUrl();
                                                @endphp
                                                @if($reviewUrl)
                                                    <a href="{{ $reviewUrl }}" 
                                                       target="_blank"
                                                       class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-r from-[#00afcc] to-[#0088a3] text-white text-sm font-semibold rounded-lg hover:from-[#0088a3] hover:to-[#006b7f] transition-all duration-200 shadow-sm hover:shadow whitespace-nowrap">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                                        </svg>
                                                        口コミ投稿
                                                    </a>
                                                    <a href="{{ $reviewUrl }}" 
                                                       target="_blank"
                                                       class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-r from-[#00afcc] to-[#0088a3] text-white text-sm font-semibold rounded-lg hover:from-[#0088a3] hover:to-[#006b7f] transition-all duration-200 shadow-sm hover:shadow whitespace-nowrap">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                        </svg>
                                                        写真投稿
                                                    </a>
                                                @else
                                                    <span class="text-xs text-gray-400 text-center whitespace-nowrap">Place ID未設定</span>
                                                @endif
                                            @else
                                                <span class="text-sm text-gray-400 text-center whitespace-nowrap">GBP未連携</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- 連絡履歴モーダル -->
                <div id="contactModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-gray-900">連絡履歴登録</h3>
                                <button type="button" onclick="closeContactModal()" class="text-gray-400 hover:text-gray-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                            <form id="contactLogForm" onsubmit="saveContactLog(event)">
                                @csrf
                                <input type="hidden" id="contact_shop_id" name="shop_id">
                                <input type="hidden" id="contact_log_id" name="contact_log_id">
                                <div class="mb-4">
                                    <label for="contact_date" class="block text-sm font-medium text-gray-700 mb-2">日付</label>
                                    <input type="date" id="contact_date" name="contact_date" required readonly
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-600">
                                </div>
                                <div class="mb-4">
                                    <label for="contact_time" class="block text-sm font-medium text-gray-700 mb-2">時間</label>
                                    <input type="time" id="contact_time" name="contact_time" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                <div class="mb-4">
                                    <label for="contact_content" class="block text-sm font-medium text-gray-700 mb-2">話した内容</label>
                                    <textarea id="contact_content" name="content" rows="6" required
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                              placeholder="今月の目標進捗など、話した内容を記入してください"></textarea>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <button type="button" id="deleteContactLogBtn" onclick="deleteContactLog()" 
                                            class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors hidden">
                                        削除
                                    </button>
                                    <div class="flex items-center gap-3 ml-auto">
                                        <button type="button" onclick="closeContactModal()" 
                                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                                            キャンセル
                                        </button>
                                        <button type="submit" 
                                                class="px-4 py-2 bg-[#00afcc] text-white rounded-md hover:bg-[#0088a3] transition-colors">
                                            保存
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                @if(count($scheduleData) === 0)
                    <div class="p-12 text-center">
                        <div class="w-20 h-20 mx-auto mb-4 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center">
                            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">店舗が登録されていません</h3>
                        <p class="text-gray-500 mb-6">店舗を登録してスケジュールを確認しましょう</p>
                        <a href="{{ route('shops.index') }}" class="inline-flex items-center justify-center px-6 py-3 bg-gradient-to-r from-[#00afcc] to-[#0088a3] text-white font-semibold rounded-lg shadow-md hover:from-[#0088a3] hover:to-[#006b7f] transition-all duration-200 hover:shadow-lg">
                            店舗を登録する
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        // 連絡履歴のURLを設定（オペレーターか管理者かで切り替え）
        @if(session('operator_id'))
            const contactLogsBaseUrl = '{{ url("/operator/shops") }}';
            const fetchRankUrl = '{{ route("operator.schedule.fetch-rank") }}';
            const deleteRankUrl = '{{ route("operator.schedule.delete-rank") }}';
        @else
            const contactLogsBaseUrl = '{{ url("/shops") }}';
            const fetchRankUrl = '{{ route("shops.schedule.fetch-rank") }}';
            const deleteRankUrl = '{{ route("shops.schedule.delete-rank") }}';
        @endif
        
        let currentShopId = null;
        let currentDate = null;
        let currentButton = null;
        let hasExistingLog = false;

        function openContactModal(shopId, date, hasLog) {
            currentShopId = shopId;
            currentDate = date;
            hasExistingLog = hasLog;
            
            // モーダルを開く
            document.getElementById('contactModal').classList.remove('hidden');
            
            // フォームに値を設定
            document.getElementById('contact_shop_id').value = shopId;
            document.getElementById('contact_date').value = date;
            
            // 削除ボタンを一旦非表示
            document.getElementById('deleteContactLogBtn').classList.add('hidden');
            
            // 既存の連絡履歴がある場合は取得して表示
            if (hasLog) {
                fetch(`${contactLogsBaseUrl}/${shopId}/contact-logs?date=${date}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.contact_log) {
                            // 既存の連絡履歴を表示
                            document.getElementById('contact_log_id').value = data.contact_log.id || '';
                            // contact_time を H:i 形式に変換（秒を削除）
                            let contactTime = data.contact_log.contact_time || '';
                            if (contactTime && contactTime.length > 5) {
                                contactTime = contactTime.substring(0, 5);
                            }
                            document.getElementById('contact_time').value = contactTime;
                            document.getElementById('contact_content').value = data.contact_log.content || '';
                            
                            // 削除ボタンを表示
                            document.getElementById('deleteContactLogBtn').classList.remove('hidden');
                        } else {
                            // 取得に失敗した場合は新規作成モード
                            resetFormToNew();
                        }
                    })
                    .catch(error => {
                        console.error('連絡履歴の取得に失敗しました:', error);
                        resetFormToNew();
                    });
            } else {
                // 新規作成モード
                resetFormToNew();
            }
            
            // クリックされたボタンを記録
            const buttons = document.querySelectorAll(`button[data-shop-id="${shopId}"][data-date="${date}"]`);
            if (buttons.length > 0) {
                currentButton = buttons[0];
            }
        }

        function resetFormToNew() {
            // 新規作成の場合は現在時刻をデフォルトに
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            document.getElementById('contact_time').value = `${hours}:${minutes}`;
            document.getElementById('contact_content').value = '';
            document.getElementById('contact_log_id').value = '';
            document.getElementById('deleteContactLogBtn').classList.add('hidden');
        }

        function closeContactModal() {
            document.getElementById('contactModal').classList.add('hidden');
            document.getElementById('contactLogForm').reset();
            document.getElementById('deleteContactLogBtn').classList.add('hidden');
            currentShopId = null;
            currentDate = null;
            currentButton = null;
            hasExistingLog = false;
        }

        function deleteContactLog() {
            if (!confirm('この連絡履歴を削除しますか？')) {
                return;
            }

            const shopId = currentShopId;
            const date = currentDate;

            fetch(`${contactLogsBaseUrl}/${shopId}/contact-logs?date=${date}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                                    document.querySelector('input[name="_token"]')?.value,
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // ボタンの色を赤に戻す
                    if (currentButton) {
                        currentButton.classList.remove('bg-blue-500', 'hover:bg-blue-600');
                        currentButton.classList.add('bg-red-500', 'hover:bg-red-600');
                        currentButton.setAttribute('data-has-log', '0');
                    }
                    
                    // 同じ日付の他のボタンも更新
                    const buttons = document.querySelectorAll(`button[data-shop-id="${shopId}"][data-date="${date}"]`);
                    buttons.forEach(btn => {
                        btn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
                        btn.classList.add('bg-red-500', 'hover:bg-red-600');
                        btn.setAttribute('data-has-log', '0');
                    });
                    
                    alert('連絡履歴を削除しました。');
                    closeContactModal();
                } else {
                    alert('連絡履歴の削除に失敗しました: ' + (data.message || 'エラーが発生しました'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('連絡履歴の削除に失敗しました。');
            });
        }

        function saveContactLog(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const shopId = formData.get('shop_id');
            
            // contact_time を H:i 形式に変換（秒を削除）
            const contactTime = formData.get('contact_time');
            if (contactTime && contactTime.length > 5) {
                // HH:mm:ss 形式の場合は HH:mm に変換
                formData.set('contact_time', contactTime.substring(0, 5));
            }
            
            fetch(`${contactLogsBaseUrl}/${shopId}/contact-logs`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                                    document.querySelector('input[name="_token"]')?.value
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // ボタンの色を青に変更
                    if (currentButton) {
                        currentButton.classList.remove('bg-red-500', 'hover:bg-red-600');
                        currentButton.classList.add('bg-blue-500', 'hover:bg-blue-600');
                        currentButton.setAttribute('data-has-log', '1');
                    }
                    
                    // 同じ日付の他のボタンも更新
                    const buttons = document.querySelectorAll(`button[data-shop-id="${shopId}"][data-date="${currentDate}"]`);
                    buttons.forEach(btn => {
                        btn.classList.remove('bg-red-500', 'hover:bg-red-600');
                        btn.classList.add('bg-blue-500', 'hover:bg-blue-600');
                        btn.setAttribute('data-has-log', '1');
                    });
                    
                    alert('連絡履歴を保存しました。');
                    closeContactModal();
                } else {
                    alert('連絡履歴の保存に失敗しました: ' + (data.message || 'エラーが発生しました'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('連絡履歴の保存に失敗しました。');
            });
        }

        // モーダル外をクリックしたら閉じる
        document.getElementById('contactModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeContactModal();
            }
        });

        // 順位取得ボタンのクリック処理
        function handleRankFetchClick(shopId, date, isFetched) {
            console.log('RANK_FETCH_CLICK_STARTED', {
                shopId: shopId,
                date: date,
                isFetched: isFetched,
                fetchRankUrl: fetchRankUrl,
                timestamp: new Date().toISOString()
            });

            if (isFetched) {
                // 取得済の場合は何もしない（disabledなので通常は呼ばれない）
                console.log('RANK_FETCH_ALREADY_FETCHED', { shopId, date });
                return;
            }
            
            // 未取得の場合はAPIを呼び出してジョブを登録
            const formData = new FormData();
            formData.append('shop_id', shopId);
            formData.append('target_date', date);
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             document.querySelector('input[name="_token"]')?.value;
            
            if (!csrfToken) {
                console.error('RANK_FETCH_CSRF_TOKEN_MISSING', {
                    shopId: shopId,
                    date: date,
                    metaToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    inputToken: document.querySelector('input[name="_token"]')?.value
                });
                alert('CSRFトークンが見つかりません。ページを再読み込みしてください。');
                return;
            }

            console.log('RANK_FETCH_REQUEST_SENDING', {
                shopId: shopId,
                date: date,
                url: fetchRankUrl,
                csrfToken: csrfToken ? 'present' : 'missing'
            });
            
            fetch(fetchRankUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            })
            .then(response => {
                console.log('RANK_FETCH_RESPONSE_RECEIVED', {
                    shopId: shopId,
                    date: date,
                    status: response.status,
                    statusText: response.statusText,
                    ok: response.ok,
                    headers: Object.fromEntries(response.headers.entries())
                });

                if (response.status === 409) {
                    // UNIQUE制約違反（既に登録済み）
                    return response.json().then(data => {
                        console.log('RANK_FETCH_CONFLICT', {
                            shopId: shopId,
                            date: date,
                            data: data
                        });
                        alert(data.message || 'この日付・店舗の順位取得は既に登録済みまたは実行中です。');
                        // ボタンを取得済に変更
                        updateRankFetchButton(shopId, date, true);
                    });
                } else if (!response.ok) {
                    return response.json().then(data => {
                        console.error('RANK_FETCH_ERROR_RESPONSE', {
                            shopId: shopId,
                            date: date,
                            status: response.status,
                            data: data
                        });
                        alert(data.message || `順位取得ジョブの登録に失敗しました。 (HTTP ${response.status})`);
                    }).catch(parseError => {
                        console.error('RANK_FETCH_JSON_PARSE_ERROR', {
                            shopId: shopId,
                            date: date,
                            status: response.status,
                            parseError: parseError.message,
                            responseText: 'Unable to parse response'
                        });
                        alert(`順位取得ジョブの登録に失敗しました。 (HTTP ${response.status})`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data && data.success) {
                    console.log('RANK_FETCH_SUCCESS', {
                        shopId: shopId,
                        date: date,
                        data: data
                    });
                    // 成功したらボタンを取得済（青）に変更
                    updateRankFetchButton(shopId, date, true);
                } else if (data) {
                    console.warn('RANK_FETCH_UNEXPECTED_RESPONSE', {
                        shopId: shopId,
                        date: date,
                        data: data
                    });
                    alert(data.message || '予期しない応答が返されました。');
                }
            })
            .catch(error => {
                console.error('RANK_FETCH_NETWORK_ERROR', {
                    shopId: shopId,
                    date: date,
                    error: error.message,
                    errorStack: error.stack,
                    errorName: error.name,
                    fetchRankUrl: fetchRankUrl,
                    timestamp: new Date().toISOString()
                });
                alert('順位取得ジョブの登録中にネットワークエラーが発生しました。コンソールを確認してください。');
            });
        }

        // 一括取得ボタンのクリック処理
        function handleBatchRankFetch(targetDate) {
            console.log('BATCH_RANK_FETCH_STARTED', {
                targetDate: targetDate,
                timestamp: new Date().toISOString()
            });

            // 当日のすべての店舗の順位取得ボタンを取得
            const buttons = document.querySelectorAll(`button.rank-fetch-btn[data-date="${targetDate}"]`);
            const unfetchedButtons = Array.from(buttons).filter(btn => {
                return btn.getAttribute('data-is-fetched') === 'false' && !btn.disabled;
            });

            if (unfetchedButtons.length === 0) {
                alert('取得対象の店舗がありません。');
                return;
            }

            if (!confirm(`表示中の${unfetchedButtons.length}店舗の順位を一括取得しますか？`)) {
                return;
            }

            // 一括取得ボタンを無効化
            const batchBtn = document.querySelector(`button.batch-fetch-btn[data-date="${targetDate}"]`);
            if (batchBtn) {
                batchBtn.disabled = true;
                batchBtn.textContent = '取得中...';
                batchBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }

            // 各ボタンを順次クリック（少し間隔を開けて）
            let completed = 0;
            let failed = 0;
            const total = unfetchedButtons.length;

            unfetchedButtons.forEach((btn, index) => {
                setTimeout(() => {
                    const shopId = btn.getAttribute('data-shop-id');
                    const isFetched = btn.getAttribute('data-is-fetched') === 'true';
                    
                    // ボタンをクリック（非同期処理）
                    handleRankFetchClick(shopId, targetDate, isFetched);
                    
                    // 完了をカウント（実際の完了は非同期なので、少し遅延してカウント）
                    setTimeout(() => {
                        completed++;
                        if (completed + failed >= total) {
                            // すべて完了
                            if (batchBtn) {
                                batchBtn.disabled = false;
                                batchBtn.textContent = '一括取得';
                                batchBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                            }
                            alert(`${total}店舗の順位取得を開始しました。`);
                        }
                    }, 500);
                }, index * 200); // 200ms間隔で実行
            });
        }

        // 順位データ削除のクリック処理
        function handleRankDeleteClick(shopId, date) {
            if (!confirm(`${date}のキーワード別順位データを削除しますか？`)) {
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             document.querySelector('input[name="_token"]')?.value;
            
            if (!csrfToken) {
                alert('CSRFトークンが見つかりません。ページを再読み込みしてください。');
                return;
            }

            // POSTリクエストで_method=DELETEを指定（LaravelのForm Method Spoofingを使用）
            const formData = new FormData();
            formData.append('_token', csrfToken);
            formData.append('_method', 'DELETE');
            formData.append('shop_id', shopId);
            formData.append('target_date', date);

            fetch(deleteRankUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message || 'データを削除しました。');
                    // ボタンの状態を更新（未取得状態に戻す）
                    updateRankFetchButton(shopId, date, false);
                    // 削除ボタンを非表示にする
                    const deleteButtons = document.querySelectorAll(`button.rank-delete-btn[data-shop-id="${shopId}"][data-date="${date}"]`);
                    deleteButtons.forEach(btn => btn.remove());
                } else {
                    alert(data.message || 'データの削除に失敗しました。');
                }
            })
            .catch(error => {
                console.error('RANK_DELETE_ERROR', error);
                alert('データの削除中にエラーが発生しました。');
            });
        }

        // 順位取得ボタンの状態を更新
        function updateRankFetchButton(shopId, date, isFetched) {
            const buttons = document.querySelectorAll(`button.rank-fetch-btn[data-shop-id="${shopId}"][data-date="${date}"]`);
            const targetDate = new Date(date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            targetDate.setHours(0, 0, 0, 0);
            
            const isFuture = targetDate > today;
            const isToday = targetDate.getTime() === today.getTime();
            const isPast = targetDate < today;
            
            buttons.forEach(btn => {
                // すべての状態クラスを削除
                btn.classList.remove('bg-red-500', 'bg-blue-500', 'bg-gray-400', 'hover:bg-red-600', 'cursor-not-allowed');
                // インラインスタイルの背景色をクリア
                btn.style.backgroundColor = '';
                
                if (isFetched) {
                    // 取得済（青）、disabled（当日・過去共通）
                    btn.classList.add('bg-blue-500', 'cursor-not-allowed');
                    btn.disabled = true;
                    btn.textContent = '済み';
                    btn.title = '済み';
                    btn.setAttribute('data-is-fetched', 'true');
                    btn.removeAttribute('onclick');
                } else if (isFuture) {
                    // 未来：グレー（#727070）、disabled
                    btn.style.backgroundColor = '#727070';
                    btn.classList.add('cursor-not-allowed');
                    btn.disabled = true;
                    btn.textContent = '未取得';
                    btn.title = '未取得';
                    btn.setAttribute('data-is-fetched', 'false');
                    btn.removeAttribute('onclick');
                } else if (isToday) {
                    // 当日・未取得：赤、enabled
                    btn.classList.add('bg-red-500', 'hover:bg-red-600');
                    btn.disabled = false;
                    btn.textContent = '未取得';
                    btn.title = '未取得';
                    btn.setAttribute('data-is-fetched', 'false');
                    // onclickは既にBlade側で設定されているので、そのまま
                } else {
                    // 過去・未取得：赤、disabled
                    btn.classList.add('bg-red-500', 'cursor-not-allowed');
                    btn.disabled = true;
                    btn.textContent = '未取得';
                    btn.title = '未取得';
                    btn.setAttribute('data-is-fetched', 'false');
                    btn.removeAttribute('onclick');
                }
            });
        }
    </script>

    @if(session('sync_batch_id'))
        <script>
            console.log('SyncProgressTracker: Script loaded, sync_batch_id = {{ session("sync_batch_id") }}');
            
            document.addEventListener('DOMContentLoaded', function() {
                console.log('SyncProgressTracker: DOMContentLoaded');
                
                const syncBatchIdElement = document.querySelector('[data-sync-batch-id]');
                console.log('SyncProgressTracker: syncBatchIdElement', syncBatchIdElement);
                
                if (syncBatchIdElement) {
                    const batchId = syncBatchIdElement.getAttribute('data-sync-batch-id');
                    console.log('SyncProgressTracker: batchId', batchId);
                    
                    if (batchId && batchId !== '') {
                        console.log('SyncProgressTracker: Starting tracker with batchId', batchId);
                        
                        // 同期バッチの進捗をポーリングで監視
                        class SyncProgressTracker {
                            constructor(batchId) {
                                this.batchId = batchId;
                                this.pollInterval = null;
                                this.pollIntervalMs = 3000; // 3秒
                            }

                            start() {
                                this.createProgressArea();
                                this.checkProgress();
                                this.pollInterval = setInterval(() => {
                                    this.checkProgress();
                                }, this.pollIntervalMs);
                            }

                            createProgressArea() {
                                const existing = document.getElementById('sync-progress-area');
                                if (existing) {
                                    existing.remove();
                                }

                                const progressArea = document.createElement('div');
                                progressArea.id = 'sync-progress-area';
                                progressArea.className = 'mb-6 bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden';
                                progressArea.innerHTML = `
                                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-blue-200 px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                                <svg class="w-6 h-6 text-white animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                </svg>
                                            </div>
                                            <h3 class="text-lg font-bold text-gray-900 flex-1 min-w-0">同期処理中...</h3>
                                        </div>
                                    </div>
                                    <div class="p-6">
                                        <div class="mb-4">
                                            <div class="flex justify-between items-center mb-2">
                                                <span class="text-sm font-medium text-gray-700">進捗</span>
                                                <span id="sync-progress-percentage" class="text-sm font-bold text-gray-900">0%</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                                <div id="sync-progress-bar" class="bg-gradient-to-r from-blue-500 to-indigo-600 h-2.5 rounded-full transition-all duration-300 ease-out" style="width: 0%; min-width: 0.5%;"></div>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div>
                                                <span class="text-gray-600">完了店舗:</span>
                                                <span id="sync-completed-shops" class="ml-2 font-semibold text-gray-900">0</span>
                                                <span class="text-gray-500">/</span>
                                                <span id="sync-total-shops" class="font-semibold text-gray-900">0</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-600">新規追加:</span>
                                                <span id="sync-total-inserted" class="ml-2 font-semibold text-green-600">0</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-600">更新:</span>
                                                <span id="sync-total-updated" class="ml-2 font-semibold text-blue-600">0</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-600">ステータス:</span>
                                                <span id="sync-status" class="ml-2 font-semibold text-gray-900">running</span>
                                            </div>
                                        </div>
                                    </div>
                                `;

                                const form = document.querySelector('form[action*="sync"]');
                                if (form && form.parentElement) {
                                    form.parentElement.insertBefore(progressArea, form);
                                } else {
                                    document.body.insertBefore(progressArea, document.body.firstChild);
                                }
                            }

                            async checkProgress() {
                                try {
                                    const url = `/api/sync-batches/${this.batchId}`;
                                    console.log('SyncProgressTracker: Checking progress', url);
                                    
                                    const response = await fetch(url);
                                    
                                    if (!response.ok) {
                                        console.error('SyncProgressTracker: API response not OK', response.status, response.statusText);
                                        return;
                                    }
                                    
                                    const data = await response.json();
                                    console.log('SyncProgressTracker: Progress data', data);

                                    this.updateProgress(data);

                                    if (data.status === 'finished') {
                                        console.log('SyncProgressTracker: Status is finished, stopping');
                                        this.stop();
                                        this.showCompletionMessage(data);
                                    } else if (data.status === 'failed') {
                                        console.log('SyncProgressTracker: Status is failed, stopping');
                                        this.stop();
                                        this.showErrorMessage(data);
                                    }
                                } catch (error) {
                                    console.error('SyncProgressTracker: 進捗確認エラー:', error);
                                }
                            }

                            updateProgress(data) {
                                console.log('SyncProgressTracker: updateProgress called', data);
                                
                                const progressBar = document.getElementById('sync-progress-bar');
                                const progressPercentage = document.getElementById('sync-progress-percentage');
                                const completedShops = document.getElementById('sync-completed-shops');
                                const totalShops = document.getElementById('sync-total-shops');
                                const totalInserted = document.getElementById('sync-total-inserted');
                                const totalUpdated = document.getElementById('sync-total-updated');
                                const status = document.getElementById('sync-status');

                                // 進捗率を計算（APIから取得できない場合は手動計算）
                                let percentage = data.progress_percentage;
                                if (percentage === undefined || percentage === null) {
                                    if (data.total_shops > 0) {
                                        percentage = (data.completed_shops / data.total_shops) * 100;
                                    } else {
                                        percentage = 0;
                                    }
                                }
                                
                                // 0-100の範囲に制限
                                percentage = Math.max(0, Math.min(100, percentage));
                                
                                console.log('SyncProgressTracker: Calculated percentage', percentage);

                                if (progressBar) {
                                    // 進捗バーの幅を更新（最小0.5%で表示）
                                    const width = percentage > 0 ? Math.max(0.5, percentage) : 0;
                                    progressBar.style.width = `${width}%`;
                                    progressBar.style.minWidth = percentage > 0 ? '0.5%' : '0%';
                                    console.log('SyncProgressTracker: Progress bar width set to', `${width}%`);
                                } else {
                                    console.warn('SyncProgressTracker: progressBar element not found');
                                }

                                if (progressPercentage) {
                                    progressPercentage.textContent = `${Math.round(percentage)}%`;
                                }

                                if (completedShops) {
                                    completedShops.textContent = data.completed_shops || 0;
                                }

                                if (totalShops) {
                                    totalShops.textContent = data.total_shops || 0;
                                }

                                if (totalInserted) {
                                    totalInserted.textContent = data.total_inserted || 0;
                                }

                                if (totalUpdated) {
                                    totalUpdated.textContent = data.total_updated || 0;
                                }

                                if (status) {
                                    status.textContent = data.status || 'running';
                                }
                            }

                            showCompletionMessage(data) {
                                // まず進捗バーを100%に更新
                                this.updateProgress({
                                    ...data,
                                    progress_percentage: 100,
                                    completed_shops: data.total_shops || data.completed_shops || 0,
                                });

                                const progressArea = document.getElementById('sync-progress-area');
                                if (progressArea) {
                                    const header = progressArea.querySelector('.bg-gradient-to-r');
                                    if (header) {
                                        header.className = 'bg-gradient-to-r from-green-50 to-emerald-50 border-b border-green-200 px-6 py-4';
                                        const icon = header.querySelector('div');
                                        if (icon) {
                                            icon.className = 'w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center mr-3';
                                            // アニメーションを停止
                                            const svg = icon.querySelector('svg');
                                            if (svg) {
                                                svg.classList.remove('animate-spin');
                                            }
                                        }
                                        const title = header.querySelector('h3');
                                        if (title) {
                                            title.className = 'text-lg font-bold text-gray-900 flex-1 min-w-0';
                                            title.style.whiteSpace = 'nowrap';
                                            title.style.overflow = 'hidden';
                                            title.style.textOverflow = 'ellipsis';
                                            title.textContent = '同期処理が完了しました';
                                        }
                                    }

                                    const content = progressArea.querySelector('.p-6');
                                    if (content) {
                                        // 既存の完了メッセージを削除
                                        const existingMessage = content.querySelector('.completion-message');
                                        if (existingMessage) {
                                            existingMessage.remove();
                                        }
                                        
                                        const finishedAt = data.finished_at ? new Date(data.finished_at).toLocaleString('ja-JP') : '';
                                        const messageDiv = document.createElement('div');
                                        messageDiv.className = 'completion-message mt-4 p-4 bg-green-50 border border-green-200 rounded-lg';
                                        messageDiv.innerHTML = `
                                            <p class="text-sm text-green-800">
                                                <strong>完了時刻:</strong> <span style="white-space: nowrap;">${finishedAt}</span>
                                            </p>
                                            <p class="text-sm text-green-800 mt-2">
                                                <strong>結果:</strong> <span class="cursor-pointer hover:underline font-medium" onclick="showBatchSyncDetailsModal(${this.batchId})">新規追加 ${data.total_inserted || 0}件、更新 ${data.total_updated || 0}件</span>
                                            </p>
                                        `;
                                        content.appendChild(messageDiv);
                                        
                                        // バッチデータをグローバルに保存（モーダル表示用）
                                        window.batchSyncData = data;
                                    }
                                }
                            }

                            showErrorMessage(data) {
                                const progressArea = document.getElementById('sync-progress-area');
                                if (progressArea) {
                                    const header = progressArea.querySelector('.bg-gradient-to-r');
                                    if (header) {
                                        header.className = 'bg-gradient-to-r from-red-50 to-rose-50 border-b border-red-200 px-6 py-4';
                                        const icon = header.querySelector('div');
                                        if (icon) {
                                            icon.className = 'w-10 h-10 bg-red-500 rounded-lg flex items-center justify-center mr-3';
                                        }
                                        const title = header.querySelector('h3');
                                        if (title) {
                                            title.className = 'text-lg font-bold text-gray-900';
                                            title.textContent = '同期処理が失敗しました';
                                        }
                                    }
                                }
                            }

                            stop() {
                                if (this.pollInterval) {
                                    clearInterval(this.pollInterval);
                                    this.pollInterval = null;
                                }
                            }
                        }
                        
                        const tracker = new SyncProgressTracker(batchId);
                        tracker.start();
                    } else {
                        console.warn('SyncProgressTracker: batchId is empty');
                    }
                } else {
                    console.warn('SyncProgressTracker: syncBatchIdElement not found');
                }
            });
        </script>
    @endif

    <!-- バッチ同期詳細モーダル -->
    <div id="batchSyncDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">同期処理の詳細</h3>
                    <button onclick="closeBatchSyncDetailsModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-700 mb-2">全体サマリー</h4>
                        <div class="space-y-2 text-sm" id="batch-sync-summary">
                            <!-- JavaScriptで動的に生成 -->
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <h4 class="font-semibold text-gray-700 mb-3">店舗別の更新内容</h4>
                        <div class="space-y-3 max-h-96 overflow-y-auto" id="batch-sync-shop-results">
                            <!-- JavaScriptで動的に生成 -->
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button onclick="closeBatchSyncDetailsModal()" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        閉じる
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function showBatchSyncDetailsModal(batchId) {
            try {
                const response = await fetch(`/api/sync-batches/${batchId}`);
                const data = await response.json();
                
                // 全体サマリーを表示
                const summaryDiv = document.getElementById('batch-sync-summary');
                summaryDiv.innerHTML = '';
                
                if (data.total_inserted > 0) {
                    const insertedDiv = document.createElement('div');
                    insertedDiv.className = 'flex justify-between';
                    insertedDiv.innerHTML = `
                        <span class="text-gray-600">新規追加:</span>
                        <span class="font-medium text-green-600">${data.total_inserted}件</span>
                    `;
                    summaryDiv.appendChild(insertedDiv);
                }
                
                if (data.total_updated > 0) {
                    const updatedDiv = document.createElement('div');
                    updatedDiv.className = 'flex justify-between';
                    updatedDiv.innerHTML = `
                        <span class="text-gray-600">更新:</span>
                        <span class="font-medium text-blue-600">${data.total_updated}件</span>
                    `;
                    summaryDiv.appendChild(updatedDiv);
                }
                
                // 店舗別の結果を表示
                const shopResultsDiv = document.getElementById('batch-sync-shop-results');
                shopResultsDiv.innerHTML = '';
                
                if (data.shop_results && data.shop_results.length > 0) {
                    data.shop_results.forEach((shopResult) => {
                        const shopDiv = document.createElement('div');
                        shopDiv.className = 'border-b border-gray-200 pb-3 last:border-b-0 last:pb-0';
                        
                        let shopContent = `<div class="font-semibold text-gray-900 mb-2">${shopResult.shop_name}</div><div class="space-y-1 text-sm ml-4">`;
                        
                        if (shopResult.reviews_changed > 0) {
                            shopContent += `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">口コミの更新:</span>
                                    <span class="font-medium text-gray-900">${shopResult.reviews_changed}件</span>
                                </div>
                            `;
                        }
                        
                        if (shopResult.photos_inserted > 0) {
                            shopContent += `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">写真の新規追加:</span>
                                    <span class="font-medium text-green-600">${shopResult.photos_inserted}件</span>
                                </div>
                            `;
                        }
                        
                        if (shopResult.photos_updated > 0) {
                            shopContent += `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">写真の更新:</span>
                                    <span class="font-medium text-blue-600">${shopResult.photos_updated}件</span>
                                </div>
                            `;
                        }
                        
                        if (shopResult.posts_synced > 0) {
                            shopContent += `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">投稿の同期:</span>
                                    <span class="font-medium text-purple-600">${shopResult.posts_synced}件</span>
                                </div>
                            `;
                        }
                        
                        if (shopResult.reviews_changed == 0 && shopResult.photos_inserted == 0 && shopResult.photos_updated == 0 && shopResult.posts_synced == 0) {
                            shopContent += `<div class="text-gray-400 text-xs">変更なし</div>`;
                        }
                        
                        shopContent += '</div>';
                        shopDiv.innerHTML = shopContent;
                        shopResultsDiv.appendChild(shopDiv);
                    });
                } else {
                    shopResultsDiv.innerHTML = '<div class="text-gray-400 text-sm">店舗別の詳細情報は利用できません</div>';
                }
                
                document.getElementById('batchSyncDetailsModal').classList.remove('hidden');
            } catch (error) {
                console.error('バッチ同期詳細の取得エラー:', error);
                alert('詳細情報の取得に失敗しました');
            }
        }

        function closeBatchSyncDetailsModal() {
            document.getElementById('batchSyncDetailsModal').classList.add('hidden');
        }

        // モーダル外をクリックで閉じる
        document.getElementById('batchSyncDetailsModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeBatchSyncDetailsModal();
            }
        });

        // ESCキーで閉じる
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeBatchSyncDetailsModal();
            }
        });
    </script>

    <!-- 同期詳細モーダル -->
    @if(session('sync_details'))
        <div id="syncDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">同期処理の詳細</h3>
                        <button onclick="closeSyncDetailsModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-gray-700 mb-2">全体サマリー</h4>
                            <div class="space-y-2 text-sm">
                                @php
                                    $details = session('sync_details');
                                @endphp
                                @if($details['reviews_changed'] > 0)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">口コミの更新:</span>
                                        <span class="font-medium text-gray-900">{{ $details['reviews_changed'] }}件</span>
                                    </div>
                                @endif
                                @if($details['photos_inserted'] > 0)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">写真の新規追加:</span>
                                        <span class="font-medium text-green-600">{{ $details['photos_inserted'] }}件</span>
                                    </div>
                                @endif
                                @if($details['photos_updated'] > 0)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">写真の更新:</span>
                                        <span class="font-medium text-blue-600">{{ $details['photos_updated'] }}件</span>
                                    </div>
                                @endif
                                @if($details['posts_synced'] > 0)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">投稿の同期:</span>
                                        <span class="font-medium text-purple-600">{{ $details['posts_synced'] }}件</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @if(!empty($details['shop_results']) && count($details['shop_results']) > 0)
                            <div class="bg-white p-4 rounded-lg border border-gray-200">
                                <h4 class="font-semibold text-gray-700 mb-3">店舗別の更新内容</h4>
                                <div class="space-y-3 max-h-96 overflow-y-auto">
                                    @foreach($details['shop_results'] as $shopResult)
                                        <div class="border-b border-gray-200 pb-3 last:border-b-0 last:pb-0">
                                            <div class="font-semibold text-gray-900 mb-2">{{ $shopResult['shop_name'] }}</div>
                                            <div class="space-y-1 text-sm ml-4">
                                                @if($shopResult['reviews_changed'] > 0)
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-600">口コミの更新:</span>
                                                        <span class="font-medium text-gray-900">{{ $shopResult['reviews_changed'] }}件</span>
                                                    </div>
                                                @endif
                                                @if($shopResult['photos_inserted'] > 0)
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-600">写真の新規追加:</span>
                                                        <span class="font-medium text-green-600">{{ $shopResult['photos_inserted'] }}件</span>
                                                    </div>
                                                @endif
                                                @if($shopResult['photos_updated'] > 0)
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-600">写真の更新:</span>
                                                        <span class="font-medium text-blue-600">{{ $shopResult['photos_updated'] }}件</span>
                                                    </div>
                                                @endif
                                                @if($shopResult['posts_synced'] > 0)
                                                    <div class="flex justify-between">
                                                        <span class="text-gray-600">投稿の同期:</span>
                                                        <span class="font-medium text-purple-600">{{ $shopResult['posts_synced'] }}件</span>
                                                    </div>
                                                @endif
                                                @if($shopResult['reviews_changed'] == 0 && $shopResult['photos_inserted'] == 0 && $shopResult['photos_updated'] == 0 && $shopResult['posts_synced'] == 0)
                                                    <div class="text-gray-400 text-xs">変更なし</div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if(!empty($details['detail_message']))
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <h4 class="font-semibold text-gray-700 mb-2">メッセージ</h4>
                                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $details['detail_message'] }}</p>
                            </div>
                        @endif
                        @if(!empty($details['errors']))
                            <div class="bg-red-50 p-4 rounded-lg">
                                <h4 class="font-semibold text-red-700 mb-2">エラー</h4>
                                <ul class="list-disc list-inside text-sm text-red-700">
                                    @foreach($details['errors'] as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button onclick="closeSyncDetailsModal()" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            閉じる
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function showSyncDetailsModal() {
                document.getElementById('syncDetailsModal').classList.remove('hidden');
            }

            function closeSyncDetailsModal() {
                document.getElementById('syncDetailsModal').classList.add('hidden');
            }

            // モーダル外をクリックで閉じる
            document.getElementById('syncDetailsModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeSyncDetailsModal();
                }
            });

            // ESCキーで閉じる
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeSyncDetailsModal();
                }
            });
        </script>
    @endif

</x-app-layout>

