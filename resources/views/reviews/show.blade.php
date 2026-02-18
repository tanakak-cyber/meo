<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('口コミ詳細') }}
            </h2>
            <a href="{{ session('operator_id') ? route('operator.reviews.index') : route('reviews.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                一覧に戻る
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <!-- 口コミ情報 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold mb-4">口コミ情報</h3>
                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">店舗名</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $review->shop->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">投稿者</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $review->author_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">評価</dt>
                                <dd class="mt-1 text-sm text-gray-900">
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
                                            
                                            // 評価に応じて色を決定（すべて黄色）
                                            $starColor = 'text-yellow-400';
                                        @endphp
                                        @if($ratingValue !== null)
                                            @for($i = 1; $i <= 5; $i++)
                                                @if($i <= $ratingValue)
                                                    <span class="{{ $starColor }} text-lg">★</span>
                                                @else
                                                    <span class="text-gray-300 text-lg">★</span>
                                                @endif
                                            @endfor
                                            <span class="ml-2">({{ $ratingValue }})</span>
                                        @else
                                            <span class="text-gray-400 text-sm">評価なし</span>
                                        @endif
                                    </div>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">投稿日時</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $review->create_time->timezone('Asia/Tokyo')->format('Y年m月d日 H:i') }}</dd>
                            </div>
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">本文</dt>
                                <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ $review->comment ?? '（本文なし）' }}</dd>
                            </div>
                        </dl>
                    </div>

                    @if($review->isReplied())
                        <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded">
                            <h4 class="text-sm font-semibold text-green-800 mb-2">返信済み</h4>
                            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $review->reply_text }}</p>
                            <p class="text-xs text-gray-500 mt-2">返信日時: {{ $review->replied_at->timezone('Asia/Tokyo')->format('Y年m月d日 H:i') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- 返信フォーム -->
            @if(!$review->isReplied())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">返信を送信</h3>
                        <form method="POST" action="{{ session('operator_id') ? route('operator.reviews.reply', $review) : route('reviews.reply', $review) }}">
                            @csrf
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <label for="reply_text" class="block text-sm font-medium text-gray-700">
                                        返信文
                                    </label>
                                    <button 
                                        type="button" 
                                        id="generate-reply-btn"
                                        class="px-4 py-2 bg-blue-500 text-white text-sm rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <span id="generate-reply-text">AI返信自動生成</span>
                                        <span id="generate-reply-spinner" class="hidden">
                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            生成中...
                                        </span>
                                    </button>
                                </div>
                                <textarea 
                                    name="reply_text" 
                                    id="reply_text" 
                                    rows="6" 
                                    required
                                    maxlength="4096"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="返信文を入力してください（最大4096文字）"
                                ></textarea>
                                <p class="mt-1 text-sm text-gray-500">最大4096文字まで入力できます。</p>
                                @error('reply_text')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex items-center justify-end">
                                <button 
                                    type="submit" 
                                    class="px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    onclick="return confirm('返信を送信しますか？')"
                                >
                                    返信を送信
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const generateBtn = document.getElementById('generate-reply-btn');
                    const replyTextarea = document.getElementById('reply_text');
                    const generateText = document.getElementById('generate-reply-text');
                    const generateSpinner = document.getElementById('generate-reply-spinner');

                    if (generateBtn) {
                        generateBtn.addEventListener('click', async function() {
                            // ボタンを無効化
                            generateBtn.disabled = true;
                            generateText.classList.add('hidden');
                            generateSpinner.classList.remove('hidden');

                            try {
                                const response = await fetch('/api/reviews/{{ $review->id }}/generate-reply', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                        'Accept': 'application/json'
                                    }
                                });

                                const data = await response.json();

                                if (response.ok && data.status === 'ok' && data.reply_text) {
                                    // 返信文をtextareaにセット
                                    replyTextarea.value = data.reply_text;
                                } else {
                                    alert(data.message || '返信文の生成に失敗しました。再度お試しください');
                                }
                            } catch (error) {
                                console.error('Error:', error);
                                alert('返信文の生成に失敗しました。再度お試しください');
                            } finally {
                                // ボタンを再有効化
                                generateBtn.disabled = false;
                                generateText.classList.remove('hidden');
                                generateSpinner.classList.add('hidden');
                            }
                        });
                    }
                });
            </script>
        </div>
    </div>
</x-app-layout>

