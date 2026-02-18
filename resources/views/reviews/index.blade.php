<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                {{ __('口コミ一覧') }}
            </h2>
            <p class="text-sm text-gray-500 mt-1">Google Business Profileの口コミを管理</p>
        </div>
    </x-slot>

    <div class="py-8">
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

            <!-- 絞り込み -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-4">
                    <form method="GET" action="{{ session('operator_id') ? route('operator.reviews.index') : route('reviews.index') }}" class="space-y-4">
                        <input type="hidden" name="shop_id" value="{{ request('shop_id') }}">
                        <input type="hidden" name="start_date" value="{{ $startDate }}">
                        <input type="hidden" name="end_date" value="{{ $endDate }}">
                        <input type="hidden" name="reply_status" value="{{ request('reply_status') }}">
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

            @if(session('error'))
                <div class="mb-6 bg-gradient-to-r from-red-50 to-rose-50 border-l-4 border-red-500 text-red-800 px-4 py-3 rounded-lg shadow-sm" role="alert">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="font-medium">{{ session('error') }}</span>
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

            @if(session('sync_batch_id'))
                <div data-sync-batch-id="{{ session('sync_batch_id') }}" style="display: none;"></div>
            @endif

            <!-- 同期ボタン -->
            @if($shops->count() > 0)
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
                        <form method="POST" action="{{ session('operator_id') ? route('operator.reviews.sync') : route('reviews.sync') }}" class="flex flex-col lg:flex-row lg:items-end gap-4" onsubmit="if(document.querySelector('select[name=shop_id]').value === 'all') { return confirm('全店舗の口コミ・写真・投稿を同期しますか？\n時間がかかる場合があります。'); }">
                            @csrf
                            <div class="flex-1">
                                <label for="sync_shop_id" class="block text-sm font-semibold text-gray-700 mb-2">店舗</label>
                                <select name="shop_id" id="sync_shop_id" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-[#00afcc] focus:border-[#00afcc] transition-all">
                                    <option value="all" selected>全店舗</option>
                                    @foreach($shops as $shop)
                                        <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                                    @endforeach
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

            <!-- 絞り込みフォーム -->
            <div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6 overflow-hidden">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                        </svg>
                        絞り込み
                    </h3>
                </div>
                <div class="p-6">
                    <form method="GET" action="{{ session('operator_id') ? route('operator.reviews.index') : route('reviews.index') }}">
                        <div class="flex flex-col lg:flex-row lg:items-end gap-4">
                            <!-- 店舗絞り込み -->
                            <div class="flex-1">
                                <label for="shop_id" class="block text-sm font-semibold text-gray-700 mb-2">店舗</label>
                                <select name="shop_id" id="shop_id" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-[#00afcc] focus:border-[#00afcc] transition-all">
                                    <option value="">すべて</option>
                                    @foreach($shops as $shop)
                                        <option value="{{ $shop->id }}" {{ request('shop_id') == $shop->id ? 'selected' : '' }}>
                                            {{ $shop->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- 開始日 -->
                            <div class="flex-1">
                                <label for="start_date" class="block text-sm font-semibold text-gray-700 mb-2">開始日</label>
                                <input type="date" name="start_date" id="start_date" value="{{ request('start_date', $startDate ?? '') }}" 
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-[#00afcc] focus:border-[#00afcc] transition-all">
                            </div>

                            <!-- 終了日 -->
                            <div class="flex-1">
                                <label for="end_date" class="block text-sm font-semibold text-gray-700 mb-2">終了日</label>
                                <input type="date" name="end_date" id="end_date" value="{{ request('end_date', $endDate ?? '') }}" 
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-[#00afcc] focus:border-[#00afcc] transition-all">
                            </div>

                            <!-- 返信状態 -->
                            <div class="flex-1">
                                <label for="reply_status" class="block text-sm font-semibold text-gray-700 mb-2">返信状態</label>
                                <select name="reply_status" id="reply_status" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-[#00afcc] focus:border-[#00afcc] transition-all">
                                    <option value="">すべて</option>
                                    <option value="not_replied" {{ request('reply_status') == 'not_replied' ? 'selected' : '' }}>未返信</option>
                                    <option value="replied" {{ request('reply_status') == 'replied' ? 'selected' : '' }}>返信済</option>
                                </select>
                            </div>

                            <!-- ボタン -->
                            <div class="flex flex-col sm:flex-row gap-2 lg:flex-shrink-0">
                                <a href="{{ session('operator_id') ? route('operator.reviews.index') : route('reviews.index') }}" class="px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 font-semibold hover:bg-gray-50 text-center transition-all whitespace-nowrap">
                                    リセット
                                </a>
                                <button type="submit" class="px-4 py-2.5 bg-gradient-to-r from-[#00afcc] to-[#0088a3] text-white font-semibold rounded-lg hover:from-[#0088a3] hover:to-[#006b7f] transition-all duration-200 shadow-md hover:shadow-lg whitespace-nowrap">
                                    絞り込み
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 口コミ一覧 -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="p-6">
                    @if($reviews->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">店舗名</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">オペレーション担当</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">投稿者</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">評価</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">投稿日時</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">本文</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">返信状態</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($reviews as $review)
                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <a href="{{ session('operator_id') ? route('operator.shops.show', $review->shop) : route('shops.show', $review->shop) }}" 
                                                   class="text-sm font-semibold text-[#00afcc] hover:text-[#0088a3] hover:underline transition-colors">
                                                    {{ $review->shop->name }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-700">
                                                    @if($review->shop->operationPerson && is_object($review->shop->operationPerson))
                                                        {{ $review->shop->operationPerson->name }}
                                                    @else
                                                        <span class="text-gray-400">—</span>
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-700">{{ $review->author_name }}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    @php
                                                        // 評価を数値に変換（文字列の場合は数値に変換）
                                                        $ratingValue = is_numeric($review->rating) ? (int)$review->rating : null;
                                                        
                                                        if ($ratingValue === null && is_string($review->rating)) {
                                                            $ratingMap = [
                                                                'FIVE' => 5,
                                                                'FOUR' => 4,
                                                                'THREE' => 3,
                                                                'TWO' => 2,
                                                                'ONE' => 1,
                                                            ];
                                                            $ratingValue = $ratingMap[strtoupper($review->rating)] ?? null;
                                                        }
                                                        
                                                        // 評価に応じて色を決定（★2以下の場合は赤、それ以外は黄色）
                                                        $starColor = ($ratingValue !== null && $ratingValue <= 2) ? 'text-red-500' : 'text-yellow-400';
                                                    @endphp
                                                    @if($ratingValue !== null)
                                                        @for($i = 1; $i <= 5; $i++)
                                                            @if($i <= $ratingValue)
                                                                <span class="{{ $starColor }} text-lg">★</span>
                                                            @else
                                                                <span class="text-gray-300 text-lg">★</span>
                                                            @endif
                                                        @endfor
                                                        <span class="ml-2 text-sm font-semibold text-gray-700">({{ $ratingValue }})</span>
                                                    @else
                                                        <span class="text-gray-400 text-sm">評価なし</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                                {{ $review->create_time->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600 max-w-xs">
                                                <span class="truncate block">{{ Str::limit($review->comment ?? '（本文なし）', 50) }}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($review->isReplied())
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                        </svg>
                                                        返信済
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-600 text-white">
                                                        未返信
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ session('operator_id') ? route('operator.reviews.show', $review) : route('reviews.show', $review) }}" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-r from-[#00afcc] to-[#0088a3] text-white text-sm font-semibold rounded-lg hover:from-[#0088a3] hover:to-[#006b7f] transition-all duration-200 shadow-sm hover:shadow">
                                                    詳細
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($reviews->hasPages())
                            <div class="mt-6 border-t border-gray-200 pt-4">
                                {{ $reviews->links() }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-12">
                            <div class="w-20 h-20 mx-auto mb-4 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center">
                                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">口コミが見つかりませんでした</h3>
                            <p class="text-gray-500">条件を変更して再度検索してください</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

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

