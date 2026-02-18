<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('ブログ連携履歴') }} - {{ $shop->name }}
            </h2>
            <a href="{{ route('shops.show', $shop) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                店舗詳細に戻る
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($blogHistory && $blogHistory->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">実行日時</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">記事URL</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ステータス</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">メッセージ</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($blogHistory as $history)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ is_array($history) ? ($history['created_at'] ?? '-') : ($history->created_at ?? '-') }}
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                <a href="{{ is_array($history) ? ($history['post_url'] ?? '#') : ($history->post_url ?? '#') }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 break-all">
                                                    {{ Str::limit(is_array($history) ? ($history['post_url'] ?? '') : ($history->post_url ?? ''), 60) }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @php
                                                    $status = is_array($history) ? ($history['status'] ?? '') : ($history->status ?? '');
                                                @endphp
                                                @if($status === 'success')
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                        成功
                                                    </span>
                                                @elseif($status === 'skipped')
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        スキップ
                                                    </span>
                                                @else
                                                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-600 text-white">
                                                        失敗
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                {{ is_array($history) ? ($history['message'] ?? '-') : ($history->message ?? '-') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button 
                                                    onclick="showHistoryDetail({{ json_encode([
                                                        'url' => is_array($history) ? ($history['post_url'] ?? '') : ($history->post_url ?? ''),
                                                        'status' => is_array($history) ? ($history['status'] ?? '') : ($history->status ?? ''),
                                                        'message' => is_array($history) ? ($history['message'] ?? '') : ($history->message ?? ''),
                                                        'created_at' => is_array($history) ? ($history['created_at'] ?? '') : ($history->created_at ?? ''),
                                                    ]) }})"
                                                    class="text-indigo-600 hover:text-indigo-900"
                                                >
                                                    詳細
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <p class="text-gray-500 mb-4">履歴がありません。</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- 詳細モーダル -->
    <div id="historyModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">履歴詳細</h3>
                    <button onclick="closeHistoryDetail()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">実行日時</dt>
                        <dd class="mt-1 text-sm text-gray-900" id="modal-created-at"></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">記事URL</dt>
                        <dd class="mt-1 text-sm text-gray-900 break-all">
                            <a href="#" id="modal-url" target="_blank" class="text-indigo-600 hover:text-indigo-900"></a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">ステータス</dt>
                        <dd class="mt-1 text-sm" id="modal-status"></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">メッセージ</dt>
                        <dd class="mt-1 text-sm text-gray-900" id="modal-message"></dd>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button onclick="closeHistoryDetail()" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                        閉じる
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showHistoryDetail(data) {
            document.getElementById('modal-created-at').textContent = data.created_at;
            document.getElementById('modal-url').href = data.url;
            document.getElementById('modal-url').textContent = data.url;
            
            let statusHtml = '';
            if (data.status === 'success') {
                statusHtml = '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">成功</span>';
            } else if (data.status === 'skipped') {
                statusHtml = '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">スキップ</span>';
            } else {
                statusHtml = '<span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-600 text-white">失敗</span>';
            }
            document.getElementById('modal-status').innerHTML = statusHtml;
            
            document.getElementById('modal-message').textContent = data.message || '-';
            document.getElementById('historyModal').classList.remove('hidden');
        }

        function closeHistoryDetail() {
            document.getElementById('historyModal').classList.add('hidden');
        }

        // モーダル外をクリックで閉じる
        document.getElementById('historyModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeHistoryDetail();
            }
        });
    </script>
</x-app-layout>

