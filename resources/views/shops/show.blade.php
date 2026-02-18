<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('店舗詳細') }}
            </h2>
            <div class="space-x-2">
                @if(!session('operator_id'))
                    <button onclick="toggleEditMode()" id="editToggleBtn" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                        編集
                    </button>
                    <form action="{{ route('shops.destroy', $shop) }}" method="POST" class="inline" onsubmit="return confirm('本当に削除しますか？この操作は取り消せません。');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                            削除
                        </button>
                    </form>
                @endif
                @if(session('operator_id'))
                    <a href="{{ route('operator.dashboard') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        ダッシュボードに戻る
                    </a>
                @else
                    <a href="{{ route('shops.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        一覧に戻る
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-8 text-gray-900">
                    <!-- 編集フォーム -->
                    <div id="editForm" class="hidden">
                        <form action="{{ route('shops.update', $shop) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <h2 class="text-lg font-semibold mb-4">基本情報</h2>
                                    
                                    <div class="mb-4">
                                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>店舗名 <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="name" id="name" value="{{ old('name', $shop->name) }}" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('name') border-red-500 @enderror">
                                        @error('name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="plan_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>プラン
                                        </label>
                                        <select name="plan_id" id="plan_id"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('plan_id') border-red-500 @enderror">
                                            <option value="">選択してください</option>
                                            @foreach($plans as $plan)
                                                <option value="{{ $plan->id }}" {{ old('plan_id', $shop->plan_id) == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('plan_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="sales_person_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>担当営業
                                        </label>
                                        <select name="sales_person_id" id="sales_person_id"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('sales_person_id') border-red-500 @enderror">
                                            <option value="">選択してください</option>
                                            @foreach($salesPersons as $salesPerson)
                                                <option value="{{ $salesPerson->id }}" {{ old('sales_person_id', $shop->sales_person_id) == $salesPerson->id ? 'selected' : '' }}>{{ $salesPerson->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('sales_person_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="operation_person_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            オペレーション担当
                                        </label>
                                        <select name="operation_person_id" id="operation_person_id"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('operation_person_id') border-red-500 @enderror">
                                            <option value="">選択してください</option>
                                            @foreach($operationPersons ?? [] as $operationPerson)
                                                <option value="{{ $operationPerson->id }}" {{ old('operation_person_id', $shop->operation_person_id) == $operationPerson->id ? 'selected' : '' }}>{{ $operationPerson->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('operation_person_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="shop_contact_name" class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>店舗担当者名
                                        </label>
                                        <input type="text" name="shop_contact_name" id="shop_contact_name" value="{{ old('shop_contact_name', $shop->shop_contact_name) }}"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('shop_contact_name') border-red-500 @enderror">
                                        @error('shop_contact_name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="shop_contact_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>店舗担当者電話番号
                                        </label>
                                        <input type="text" name="shop_contact_phone" id="shop_contact_phone" value="{{ old('shop_contact_phone', $shop->shop_contact_phone) }}"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('shop_contact_phone') border-red-500 @enderror">
                                        @error('shop_contact_phone')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>金額
                                        </label>
                                        <input type="number" name="price" id="price" value="{{ old('price', $shop->price) }}" step="0.01" min="0"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('price') border-red-500 @enderror"
                                            placeholder="0.00">
                                        @error('price')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="initial_cost" class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>初期費用
                                        </label>
                                        <input type="number" name="initial_cost" id="initial_cost" value="{{ old('initial_cost', $shop->initial_cost) }}" step="0.01" min="0"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('initial_cost') border-red-500 @enderror"
                                            placeholder="0.00">
                                        @error('initial_cost')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>契約形態
                                        </label>
                                        <div class="flex items-center space-x-6">
                                            <label class="flex items-center">
                                                <input type="radio" name="contract_type" value="own" {{ old('contract_type', $shop->contract_type ?? 'own') === 'own' ? 'checked' : '' }}
                                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                <span class="ml-2 text-sm text-gray-700">自社契約</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="radio" name="contract_type" value="referral" {{ old('contract_type', $shop->contract_type) === 'referral' ? 'checked' : '' }}
                                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                <span class="ml-2 text-sm text-gray-700">紹介契約</span>
                                            </label>
                                        </div>
                                        @error('contract_type')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4" id="referral_fee_container" style="display: {{ old('contract_type', $shop->contract_type) === 'referral' ? 'block' : 'none' }};">
                                        <label for="referral_fee" class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>月額紹介フィー
                                        </label>
                                        <input type="number" name="referral_fee" id="referral_fee" value="{{ old('referral_fee', $shop->referral_fee) }}" step="0.01" min="0"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('referral_fee') border-red-500 @enderror"
                                            placeholder="0.00">
                                        @error('referral_fee')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <label for="contract_date" class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>契約日
                                            </label>
                                            <input type="date" name="contract_date" id="contract_date" 
                                                value="{{ old('contract_date', $shop->contract_date ? $shop->contract_date->format('Y-m-d') : '') }}"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('contract_date') border-red-500 @enderror">
                                            @error('contract_date')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="contract_end_date" class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>契約終了日
                                            </label>
                                            <input type="date" name="contract_end_date" id="contract_end_date" 
                                                value="{{ old('contract_end_date', $shop->contract_end_date ? $shop->contract_end_date->format('Y-m-d') : '') }}"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('contract_end_date') border-red-500 @enderror">
                                            @error('contract_end_date')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="blog_option" value="1" 
                                                {{ old('blog_option', $shop->blog_option) ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-gray-700">ブログ投稿お任せオプション</span>
                                        </label>
                                    </div>

                                    <div class="mb-4">
                                        <label for="review_monthly_target" class="block text-sm font-medium text-gray-700 mb-2">
                                            月間口コミノルマ
                                        </label>
                                        <input type="number" name="review_monthly_target" id="review_monthly_target" 
                                               value="{{ old('review_monthly_target', $shop->review_monthly_target) }}" 
                                               min="0" step="1"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('review_monthly_target') border-red-500 @enderror"
                                               placeholder="0">
                                        @error('review_monthly_target')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="photo_monthly_target" class="block text-sm font-medium text-gray-700 mb-2">
                                            月間写真ノルマ
                                        </label>
                                        <input type="number" name="photo_monthly_target" id="photo_monthly_target" 
                                               value="{{ old('photo_monthly_target', $shop->photo_monthly_target) }}" 
                                               min="0" step="1"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('photo_monthly_target') border-red-500 @enderror"
                                               placeholder="0">
                                        @error('photo_monthly_target')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="video_monthly_target" class="block text-sm font-medium text-gray-700 mb-2">
                                            月間動画ノルマ
                                        </label>
                                        <input type="number" name="video_monthly_target" id="video_monthly_target" 
                                               value="{{ old('video_monthly_target', $shop->video_monthly_target) }}" 
                                               min="1" max="4" step="1"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('video_monthly_target') border-red-500 @enderror"
                                               placeholder="1～4">
                                        @error('video_monthly_target')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="google_place_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            Google Place ID（口コミ投稿URL用）
                                        </label>
                                        <input type="text" name="google_place_id" id="google_place_id" value="{{ old('google_place_id', $shop->google_place_id) }}"
                                            placeholder="例: 0x601885e86c1ada87:0xb82066a958690a8a"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('google_place_id') border-red-500 @enderror">
                                        <p class="mt-1 text-xs text-gray-500">Google Mapsの口コミ投稿ページのURL生成に使用します</p>
                                        <p class="mt-1 text-xs">
                                            <a href="https://developers.google.com/maps/documentation/places/web-service/place-id?hl=ja" 
                                               target="_blank" 
                                               rel="noopener noreferrer"
                                               class="text-indigo-600 hover:text-indigo-800 underline">
                                                placeIdを取得
                                            </a>
                                        </p>
                                        @error('google_place_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div>
                                    <h3 class="text-lg font-semibold mb-4">Google Business Profile情報</h3>
                                    
                                    <div class="mb-4">
                                        <label for="gbp_account_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            GBPアカウントID
                                        </label>
                                        <input type="text" name="gbp_account_id" id="gbp_account_id" value="{{ old('gbp_account_id', $shop->gbp_account_id) }}"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('gbp_account_id') border-red-500 @enderror">
                                        @error('gbp_account_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="gbp_location_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            GBPロケーションID
                                        </label>
                                        <input type="text" name="gbp_location_id" id="gbp_location_id" value="{{ old('gbp_location_id', $shop->gbp_location_id) }}"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('gbp_location_id') border-red-500 @enderror">
                                        @error('gbp_location_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="gbp_refresh_token" class="block text-sm font-medium text-gray-700 mb-2">
                                            GBPリフレッシュトークン
                                        </label>
                                        <textarea name="gbp_refresh_token" id="gbp_refresh_token" rows="4"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('gbp_refresh_token') border-red-500 @enderror">{{ old('gbp_refresh_token', $shop->gbp_refresh_token) }}</textarea>
                                        @error('gbp_refresh_token')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="gbp_name" class="block text-sm font-medium text-gray-700 mb-2">
                                            GBP店舗名（正式名称）
                                        </label>
                                        <input type="text" name="gbp_name" id="gbp_name" value="{{ old('gbp_name', $shop->gbp_name) }}"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('gbp_name') border-red-500 @enderror"
                                            placeholder="Google Business Profileの店舗名">
                                        <p class="mt-1 text-sm text-gray-500">Google Business Profileから取得した店舗の正式名称</p>
                                        @error('gbp_name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="ai_reply_keywords" class="block text-sm font-medium text-gray-700 mb-2">
                                            AI返信時に必ず入れるキーワード
                                        </label>
                                        <textarea 
                                            name="ai_reply_keywords" 
                                            id="ai_reply_keywords" 
                                            rows="4"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('ai_reply_keywords') border-red-500 @enderror"
                                            placeholder="例：地域名、強み、サービス名など（カンマ区切り or 改行区切りで複数キーワード可）">{{ old('ai_reply_keywords', $shop->ai_reply_keywords) }}</textarea>
                                        <p class="mt-1 text-sm text-gray-500">口コミ返信文に自然に含めたいキーワードを入力してください（例：地域名、強み、サービス名など）</p>
                                        @error('ai_reply_keywords')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="low_rating_response" class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>★2以下の時の対応
                                        </label>
                                        <textarea 
                                            name="low_rating_response" 
                                            id="low_rating_response" 
                                            rows="4"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('low_rating_response') border-red-500 @enderror"
                                            placeholder="★2以下の口コミが来た時の対応方法を入力してください">{{ old('low_rating_response', $shop->low_rating_response) }}</textarea>
                                        @error('low_rating_response')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-4">
                                        <label for="memo" class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>メモ
                                        </label>
                                        <textarea 
                                            name="memo" 
                                            id="memo" 
                                            rows="4"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('memo') border-red-500 @enderror"
                                            placeholder="店舗に関するメモを入力してください">{{ old('memo', $shop->memo) }}</textarea>
                                        @error('memo')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    @if(!session('operator_id'))
                                        <div class="mt-4">
                                            <a href="{{ route('shops.connect', $shop->id) }}"
                                               class="inline-block bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                                🔗 Google連携
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-6 pt-6 border-t">
                                <h3 class="text-lg font-semibold mb-4">
                                    <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>レポート送信先メールアドレス（最大5件）
                                </h3>
                                @for ($i = 1; $i <= 5; $i++)
                                    <div class="mb-3">
                                        <label for="report_email_{{ $i }}" class="block text-sm font-medium text-gray-700 mb-1">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>メールアドレス {{ $i }}
                                        </label>
                                        <input type="email" name="report_email_{{ $i }}" id="report_email_{{ $i }}"
                                               value="{{ old('report_email_' . $i, $shop->{'report_email_' . $i}) }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('report_email_' . $i) border-red-500 @enderror"
                                               placeholder="example@example.com">
                                        @error('report_email_' . $i)
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endfor
                            </div>

                            <div class="mt-6 pt-6 border-t">
                                <h3 class="text-lg font-semibold mb-4">
                                    <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>MEOキーワード（1～3件推奨。最大10件）
                                </h3>
                                <div id="meo-keywords-container" class="space-y-2">
                                    @php
                                        $existingKeywords = $shop->meoKeywords->pluck('keyword')->toArray();
                                        $oldKeywords = old('meo_keywords', $existingKeywords);
                                        $initialCount = max(3, count(array_filter($oldKeywords)));
                                        $initialCount = min($initialCount, 10);
                                    @endphp
                                    @for($i = 0; $i < $initialCount; $i++)
                                        <div class="meo-keyword-item flex items-center gap-2">
                                            <input type="text" 
                                                name="meo_keywords[]" 
                                                class="meo-keyword-input flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                value="{{ old('meo_keywords.' . $i, $existingKeywords[$i] ?? '') }}"
                                                placeholder="キーワード{{ $i + 1 }}">
                                        </div>
                                    @endfor
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <button type="button" id="add-meo-keyword-btn" class="px-3 py-1 text-sm bg-indigo-500 text-white rounded-md hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                        追加
                                    </button>
                                    <p class="text-sm text-gray-500">空欄は保存されません</p>
                                </div>
                            </div>

                            <div class="mt-6 pt-6 border-t">
                                <h3 class="text-lg font-semibold mb-4">連携設定</h3>
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>連携タイプ
                                    </label>
                                    <div class="flex items-center space-x-6">
                                        <label class="flex items-center">
                                            <input type="radio" name="integration_type" value="blog" 
                                                {{ old('integration_type', $shop->integration_type) === 'blog' ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                onchange="toggleIntegrationForms()">
                                            <span class="ml-2 text-sm text-gray-700">ブログ連携</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="integration_type" value="instagram" 
                                                {{ old('integration_type', $shop->integration_type) === 'instagram' ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                onchange="toggleIntegrationForms()">
                                            <span class="ml-2 text-sm text-gray-700">Instagram連携</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="integration_type" value="" 
                                                {{ old('integration_type', $shop->integration_type) === '' || old('integration_type', $shop->integration_type) === null ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                onchange="toggleIntegrationForms()">
                                            <span class="ml-2 text-sm text-gray-700">未使用</span>
                                        </label>
                                    </div>
                                    @error('integration_type')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div id="blog_settings" class="mb-3" style="display: {{ old('integration_type', $shop->integration_type) === 'blog' ? 'block' : 'none' }};">
                                    <h4 class="text-md font-semibold mb-3">ブログクロール設定</h4>
                                
                                @php
                                    $currentIntegrationType = old('integration_type', $shop->integration_type);
                                    // ブログ設定のフォームでは、integration_typeが'blog'の場合のみold()を使う
                                    $blogListUrl = ($currentIntegrationType === 'blog' && old('blog_list_url') !== null) ? old('blog_list_url') : ($shop->integration_type === 'blog' ? $shop->blog_list_url : '');
                                    $blogLinkSelector = ($currentIntegrationType === 'blog' && old('blog_link_selector') !== null) ? old('blog_link_selector') : ($shop->integration_type === 'blog' ? $shop->blog_link_selector : '');
                                    $blogItemSelector = ($currentIntegrationType === 'blog' && old('blog_item_selector') !== null) ? old('blog_item_selector') : ($shop->integration_type === 'blog' ? $shop->blog_item_selector : '');
                                    $blogDateSelector = ($currentIntegrationType === 'blog' && old('blog_date_selector') !== null) ? old('blog_date_selector') : ($shop->integration_type === 'blog' ? $shop->blog_date_selector : '');
                                    $blogImageSelector = ($currentIntegrationType === 'blog' && old('blog_image_selector') !== null) ? old('blog_image_selector') : ($shop->integration_type === 'blog' ? $shop->blog_image_selector : '');
                                    $blogContentSelector = ($currentIntegrationType === 'blog' && old('blog_content_selector') !== null) ? old('blog_content_selector') : ($shop->integration_type === 'blog' ? $shop->blog_content_selector : '');
                                @endphp
                                
                                <div class="mb-3">
                                    <label for="blog_list_url" class="block text-sm font-medium text-gray-700 mb-1">
                                        <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>記事一覧URL
                                    </label>
                                    <input type="url" name="blog_list_url" id="blog_list_url" 
                                           value="{{ $blogListUrl }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_list_url') border-red-500 @enderror"
                                           placeholder="https://example.com/blog">
                                    @error('blog_list_url')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="blog_item_selector" class="block text-sm font-medium text-gray-700 mb-1">
                                        投稿ブロック（親要素）セレクター
                                    </label>
                                    <input type="text" name="blog_item_selector" id="blog_item_selector" 
                                           value="{{ $blogItemSelector }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_item_selector') border-red-500 @enderror"
                                           placeholder="例）article.post, div.post-item">
                                    <p class="mt-1 text-xs text-gray-500">1記事を囲む親要素のCSSセレクターを指定してください。タイトル・画像・リンク・本文はこの要素内から取得されます。未設定の場合は従来のリンクセレクターを起点にします。</p>
                                    @error('blog_item_selector')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                                    <div>
                                        <label for="blog_link_selector" class="block text-sm font-medium text-gray-700 mb-1">
                                            記事リンクセレクター
                                        </label>
                                        <input type="text" name="blog_link_selector" id="blog_link_selector" 
                                               value="{{ $blogLinkSelector }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_link_selector') border-red-500 @enderror"
                                               placeholder="article a">
                                        @error('blog_link_selector')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                                    <div>
                                        <label for="blog_date_selector" class="block text-sm font-medium text-gray-700 mb-1">
                                            日付セレクター
                                        </label>
                                        <input type="text" name="blog_date_selector" id="blog_date_selector" 
                                               value="{{ $blogDateSelector }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_date_selector') border-red-500 @enderror"
                                               placeholder=".post-date">
                                        @error('blog_date_selector')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="blog_image_selector" class="block text-sm font-medium text-gray-700 mb-1">
                                            画像セレクター
                                        </label>
                                        <input type="text" name="blog_image_selector" id="blog_image_selector" 
                                               value="{{ $blogImageSelector }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_image_selector') border-red-500 @enderror"
                                               placeholder=".post-image img">
                                        @error('blog_image_selector')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="blog_content_selector" class="block text-sm font-medium text-gray-700 mb-1">
                                            本文セレクター
                                        </label>
                                        <input type="text" name="blog_content_selector" id="blog_content_selector" 
                                               value="{{ $blogContentSelector }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_content_selector') border-red-500 @enderror"
                                               placeholder=".post-content">
                                        @error('blog_content_selector')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="blog_crawl_time" class="block text-sm font-medium text-gray-700 mb-1">
                                        クロール実行時刻
                                    </label>
                                    <input type="time" name="blog_crawl_time" id="blog_crawl_time" 
                                           value="{{ old('blog_crawl_time', $shop->blog_crawl_time ? (is_string($shop->blog_crawl_time) ? substr($shop->blog_crawl_time, 0, 5) : (\Carbon\Carbon::parse($shop->blog_crawl_time)->format('H:i'))) : '03:00') }}"
                                           class="w-full md:w-auto px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_crawl_time') border-red-500 @enderror">
                                    <p class="mt-1 text-xs text-gray-500">毎日この時刻に自動クロールが実行されます</p>
                                    @error('blog_crawl_time')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="blog_fallback_image_url" class="block text-sm font-medium text-gray-700 mb-1">
                                        ダミー画像URL（画像が取得できない場合に使用）
                                    </label>
                                    <input type="url" name="blog_fallback_image_url" id="blog_fallback_image_url" 
                                           value="{{ old('blog_fallback_image_url', $shop->blog_fallback_image_url) }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_fallback_image_url') border-red-500 @enderror"
                                           placeholder="https://example.com/images/fallback.jpg">
                                    <p class="mt-1 text-xs text-gray-500">記事画像が取得できない場合に使用する代替画像のURLを指定してください</p>
                                    @error('blog_fallback_image_url')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                </div>

                                <div id="instagram_settings" class="mb-3" style="display: {{ old('integration_type', $shop->integration_type) === 'instagram' ? 'block' : 'none' }};">
                                    <h4 class="text-md font-semibold mb-3">Instagramクロール設定</h4>
                                    
                                    @php
                                        $currentIntegrationType = old('integration_type', $shop->integration_type);
                                        // Instagram設定のフォームでは、integration_typeが'instagram'の場合のみold()を使う
                                        $instagramListUrl = ($currentIntegrationType === 'instagram' && old('blog_list_url') !== null) ? old('blog_list_url') : ($shop->integration_type === 'instagram' ? $shop->blog_list_url : '');
                                        $instagramLinkSelector = ($currentIntegrationType === 'instagram' && old('blog_link_selector') !== null) ? old('blog_link_selector') : ($shop->integration_type === 'instagram' ? $shop->blog_link_selector : '');
                                        $instagramImageSelector = ($currentIntegrationType === 'instagram' && old('blog_image_selector') !== null) ? old('blog_image_selector') : ($shop->integration_type === 'instagram' ? $shop->blog_image_selector : '');
                                        $instagramContentSelector = ($currentIntegrationType === 'instagram' && old('blog_content_selector') !== null) ? old('blog_content_selector') : ($shop->integration_type === 'instagram' ? $shop->blog_content_selector : '');
                                    @endphp
                                    
                                    <div class="mb-3">
                                        <label for="instagram_list_url" class="block text-sm font-medium text-gray-700 mb-1">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>Instagram一覧URL
                                        </label>
                                        <input type="url" name="blog_list_url" id="instagram_list_url" 
                                               value="{{ $instagramListUrl }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_list_url') border-red-500 @enderror"
                                               placeholder="https://www.instagram.com/username/">
                                        @error('blog_list_url')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="instagram_item_selector" class="block text-sm font-medium text-gray-700 mb-1">
                                            投稿ブロック（親要素）セレクター
                                        </label>
                                        <input type="text" name="instagram_item_selector" id="instagram_item_selector" 
                                               value="{{ old('integration_type', $shop->integration_type) === 'instagram' ? old('instagram_item_selector', $shop->instagram_item_selector) : '' }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('instagram_item_selector') border-red-500 @enderror"
                                               placeholder=".instagram-gallery-item">
                                        <p class="mt-1 text-xs text-gray-500">Instagramの1投稿を囲んでいる親要素のCSSセレクターを指定してください。</p>
                                        @error('instagram_item_selector')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                                        <div>
                                            <label for="instagram_link_selector" class="block text-sm font-medium text-gray-700 mb-1">
                                                投稿リンクセレクター
                                            </label>
                                            <input type="text" name="blog_link_selector" id="instagram_link_selector" 
                                                   value="{{ $instagramLinkSelector }}"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_link_selector') border-red-500 @enderror"
                                                   placeholder="a[href*='instagram.com/p/']">
                                            @error('blog_link_selector')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="instagram_image_selector" class="block text-sm font-medium text-gray-700 mb-1">
                                                画像セレクター
                                            </label>
                                            <input type="text" name="blog_image_selector" id="instagram_image_selector" 
                                                   value="{{ $instagramImageSelector }}"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_image_selector') border-red-500 @enderror"
                                                   placeholder="img">
                                            @error('blog_image_selector')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label for="instagram_content_selector" class="block text-sm font-medium text-gray-700 mb-1">
                                                本文セレクター
                                            </label>
                                            <input type="text" name="blog_content_selector" id="instagram_content_selector" 
                                                   value="{{ $instagramContentSelector }}"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_content_selector') border-red-500 @enderror"
                                                   placeholder=".post-caption">
                                            @error('blog_content_selector')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="instagram_crawl_time" class="block text-sm font-medium text-gray-700 mb-1">
                                            クロール実行時刻
                                        </label>
                                        <input type="time" name="instagram_crawl_time" id="instagram_crawl_time" 
                                               value="{{ old('instagram_crawl_time', $shop->instagram_crawl_time ? (is_string($shop->instagram_crawl_time) ? substr($shop->instagram_crawl_time, 0, 5) : (\Carbon\Carbon::parse($shop->instagram_crawl_time)->format('H:i'))) : '03:00') }}"
                                               class="w-full md:w-auto px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('instagram_crawl_time') border-red-500 @enderror">
                                        <p class="mt-1 text-xs text-gray-500">毎日この時刻に自動クロールが実行されます</p>
                                        @error('instagram_crawl_time')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-3 p-4 bg-gray-50 rounded-md border border-gray-200">
                                        <div class="mb-3">
                                            <label class="flex items-center">
                                                <input type="checkbox" name="wp_post_enabled" id="wp_post_enabled" 
                                                       value="1" 
                                                       {{ old('wp_post_enabled', $shop->wp_post_enabled) ? 'checked' : '' }}
                                                       onchange="toggleWpPostSettings()"
                                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                <span class="ml-2 text-sm font-medium text-gray-700">WordPressへ投稿する</span>
                                            </label>
                                        </div>

                                        <div id="wp_post_settings" style="display: {{ old('wp_post_enabled', $shop->wp_post_enabled) ? 'block' : 'none' }};">
                                            <div class="mb-3">
                                                <label for="wp_post_type" class="block text-sm font-medium text-gray-700 mb-1">
                                                    投稿タイプ
                                                </label>
                                                <div class="flex gap-2">
                                                    <input type="text" name="wp_post_type" id="wp_post_type" 
                                                           value="{{ old('wp_post_type', $shop->wp_post_type) }}"
                                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('wp_post_type') border-red-500 @enderror"
                                                           placeholder="post / news / blog / works など（カスタム投稿タイプ名）">
                                                    <button type="button" id="fetch-wp-post-types-btn" 
                                                            onclick="fetchWpPostTypes({{ $shop->id }})"
                                                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md whitespace-nowrap">
                                                        投稿タイプ取得
                                                    </button>
                                                </div>
                                                <p class="mt-1 text-xs text-gray-500">WordPressの投稿タイプ（RESTのエンドポイントに使います）。例: post, news, blog, works など</p>
                                                <div id="wp-post-types-list" class="mt-2 hidden">
                                                    <p class="text-xs font-medium text-gray-700 mb-1">取得された投稿タイプ（クリックで選択）:</p>
                                                    <div id="wp-post-types-items" class="flex flex-wrap gap-2"></div>
                                                </div>
                                                <div id="wp-post-types-error" class="mt-2 hidden">
                                                    <p class="text-sm text-red-600"></p>
                                                </div>
                                                @error('wp_post_type')
                                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="mb-3">
                                                <label for="wp_post_status" class="block text-sm font-medium text-gray-700 mb-1">
                                                    投稿ステータス
                                                </label>
                                                <select name="wp_post_status" id="wp_post_status" 
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('wp_post_status') border-red-500 @enderror">
                                                    <option value="publish" {{ old('wp_post_status', $shop->wp_post_status ?: 'publish') === 'publish' ? 'selected' : '' }}>公開 (publish)</option>
                                                    <option value="draft" {{ old('wp_post_status', $shop->wp_post_status ?: 'publish') === 'draft' ? 'selected' : '' }}>下書き (draft)</option>
                                                    <option value="pending" {{ old('wp_post_status', $shop->wp_post_status ?: 'publish') === 'pending' ? 'selected' : '' }}>レビュー待ち (pending)</option>
                                                </select>
                                                @error('wp_post_status')
                                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="mb-3 pt-3 border-t border-gray-300">
                                                <h5 class="text-sm font-semibold text-gray-700 mb-2">WordPress接続情報</h5>
                                                
                                                <div class="mb-3">
                                                    <label for="wp_base_url" class="block text-sm font-medium text-gray-700 mb-1">
                                                        WordPressサイトURL
                                                    </label>
                                                    <input type="url" name="wp_base_url" id="wp_base_url" 
                                                           value="{{ old('wp_base_url', $shop->wp_base_url) }}"
                                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('wp_base_url') border-red-500 @enderror"
                                                           placeholder="https://example.com">
                                                    <p class="mt-1 text-xs text-gray-500">WordPressサイトのベースURLを入力してください</p>
                                                    @error('wp_base_url')
                                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div class="mb-3">
                                                    <label for="wp_username" class="block text-sm font-medium text-gray-700 mb-1">
                                                        ユーザー名
                                                    </label>
                                                    <input type="text" name="wp_username" id="wp_username" 
                                                           value="{{ old('wp_username', $shop->wp_username) }}"
                                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('wp_username') border-red-500 @enderror"
                                                           placeholder="wordpress-username">
                                                    @error('wp_username')
                                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </div>

                                                <div class="mb-3">
                                                    <label for="wp_app_password" class="block text-sm font-medium text-gray-700 mb-1">
                                                        Application Password
                                                        @if($shop->wp_app_password)
                                                            <span class="ml-2 text-xs text-green-600 font-normal">（保存済み）</span>
                                                        @endif
                                                    </label>
                                                    <input type="password" name="wp_app_password" id="wp_app_password" 
                                                           value=""
                                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('wp_app_password') border-red-500 @enderror"
                                                           placeholder="{{ $shop->wp_app_password ? '新しいパスワードを入力（変更しない場合は空欄）' : 'Application Passwordを入力' }}">
                                                    <p class="mt-1 text-xs text-gray-500">
                                                        @if($shop->wp_app_password)
                                                            既存のパスワードは暗号化されて保存されています。変更する場合のみ入力してください。
                                                        @else
                                                            WordPress Application Passwordを入力してください。
                                                        @endif
                                                    </p>
                                                    @error('wp_app_password')
                                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-4 pt-4 border-t">
                                <button type="button" onclick="toggleEditMode()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                    キャンセル
                                </button>
                                <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    更新
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- 詳細表示 -->
                    <div id="detailView">
                        <div class="space-y-6">
                            <!-- 上段：店舗基本情報 | 契約・担当情報 -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <!-- カード1: 店舗基本情報 -->
                                <div class="bg-white border border-gray-200 rounded-lg p-5 lg:p-6">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-5 pb-3 border-b border-gray-200">店舗基本情報</h3>
                                    <dl class="space-y-4">
                                        <div class="bg-gray-50 rounded-md p-3.5">
                                            <dt class="text-xs font-medium text-gray-500 mb-1.5">ID</dt>
                                            <dd class="text-base font-semibold text-gray-900">{{ $shop->id }}</dd>
                                        </div>
                                        <div class="bg-gray-50 rounded-md p-3.5">
                                            <dt class="text-xs font-medium text-gray-500 mb-1.5 flex items-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>店舗名
                                            </dt>
                                            <dd class="text-base font-semibold text-gray-900 mt-1.5">{{ $shop->name }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1.5 flex items-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>プラン
                                            </dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1.5">
                                                @if($shop->plan_id && $shop->plan && is_object($shop->plan))
                                                    {{ $shop->plan->name }}
                                                @elseif($shop->plan && !is_object($shop->plan))
                                                    {{ $shop->plan }}
                                                @else
                                                    <span class="text-gray-400">未設定</span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1.5 flex items-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>担当営業
                                            </dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1.5">
                                                @if($shop->salesPerson && is_object($shop->salesPerson))
                                                    {{ $shop->salesPerson->name }}
                                                @else
                                                    <span class="text-gray-400">未設定</span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1.5">オペレーション担当</dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1.5">
                                                @if($shop->operationPerson && is_object($shop->operationPerson))
                                                    {{ $shop->operationPerson->name }}
                                                @else
                                                    <span class="text-gray-400">未設定</span>
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                <!-- カード2: 契約・担当情報 -->
                                <div class="bg-white border border-gray-200 rounded-lg p-5 lg:p-6">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-5 pb-3 border-b border-gray-200">契約・担当情報</h3>
                                    <dl class="space-y-4">
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>店舗担当者名
                                            </dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1">{{ $shop->shop_contact_name ?? '未設定' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>店舗担当者電話番号
                                            </dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1">{{ $shop->shop_contact_phone ?? '未設定' }}</dd>
                                        </div>
                                        <div class="bg-gray-50 rounded-md p-3">
                                            <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>金額
                                            </dt>
                                            <dd class="text-base font-semibold text-gray-900 mt-1">
                                                @if($shop->price)
                                                    <span class="text-[#00afcc]">¥{{ number_format($shop->price, 0) }}</span>
                                                @else
                                                    <span class="text-gray-400">未設定</span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="bg-gray-50 rounded-md p-3">
                                            <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>初期費用
                                            </dt>
                                            <dd class="text-base font-semibold text-gray-900 mt-1">
                                                @if($shop->initial_cost)
                                                    <span class="text-[#00afcc]">¥{{ number_format($shop->initial_cost, 0) }}</span>
                                                @else
                                                    <span class="text-gray-400">未設定</span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>契約形態
                                            </dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1">
                                                @if($shop->contract_type === 'referral')
                                                    紹介契約
                                                @else
                                                    自社契約
                                                @endif
                                            </dd>
                                        </div>
                                        @if($shop->contract_type === 'referral' && $shop->referral_fee)
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>月額紹介フィー
                                            </dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1">
                                                ¥{{ number_format($shop->referral_fee, 0) }}
                                            </dd>
                                        </div>
                                        @endif
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>契約日
                                            </dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1">
                                                {{ $shop->contract_date ? $shop->contract_date->format('Y年m月d日') : '未設定' }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>契約終了日
                                            </dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1">
                                                {{ $shop->contract_end_date ? $shop->contract_end_date->format('Y年m月d日') : '未設定' }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <!-- 中段：MEO設定情報 | ブログ連携設定 -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <!-- カード3: MEO設定情報 -->
                                <div class="bg-white border border-gray-200 rounded-lg p-5 lg:p-6">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-5 pb-3 border-b border-gray-200">MEO設定情報</h3>
                                    <dl class="space-y-4">
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>レポート送信先メールアドレス
                                            </dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1">
                                                @php
                                                    $reportEmails = array_filter([
                                                        $shop->report_email_1,
                                                        $shop->report_email_2,
                                                        $shop->report_email_3,
                                                        $shop->report_email_4,
                                                        $shop->report_email_5,
                                                    ]);
                                                @endphp
                                                @if(count($reportEmails) > 0)
                                                    <ul class="space-y-1.5 mt-2">
                                                        @foreach($reportEmails as $email)
                                                            <li class="text-sm text-gray-700">{{ $email }}</li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    <span class="text-gray-400">未設定</span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>MEOキーワード
                                            </dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1">
                                                @if($shop->meoKeywords->count() > 0)
                                                    <div class="flex flex-wrap gap-2 mt-2">
                                                        @foreach($shop->meoKeywords as $keyword)
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-[#00afcc]/10 text-[#00afcc] border border-[#00afcc]/20">
                                                                {{ $keyword->keyword }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-gray-400">未設定</span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="bg-gray-50 rounded-md p-3">
                                            <dt class="text-xs font-medium text-gray-500 mb-1">Google Place ID</dt>
                                            <dd class="text-xs font-mono text-gray-700 mt-1 break-all">
                                                {{ $shop->google_place_id ?? '未設定' }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1">月間口コミノルマ</dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1">
                                                {{ $shop->review_monthly_target ? $shop->review_monthly_target . '件' : '未設定' }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1">月間写真ノルマ</dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1">
                                                {{ $shop->photo_monthly_target ? $shop->photo_monthly_target . '件' : '未設定' }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1">月間動画ノルマ</dt>
                                            <dd class="text-sm font-medium text-gray-900 mt-1">
                                                {{ $shop->video_monthly_target ? $shop->video_monthly_target . '件' : '未設定' }}
                                            </dd>
                                        </div>
                                        @if(session('operator_id'))
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                                有効投稿数（Google評価対象）
                                                <span class="ml-1 text-gray-400 cursor-help" title="Google API は古い投稿や期限切れ投稿を返さないため、ここに表示される数は検索順位に影響する投稿数です">
                                                    <svg class="inline-block w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                                                    </svg>
                                                </span>
                                            </dt>
                                            <dd class="text-sm font-semibold text-[#00afcc] mt-1">
                                                {{ $postCount ?? 0 }}件
                                            </dd>
                                        </div>
                                        @endif
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1">ブログ投稿お任せオプション</dt>
                                            <dd class="mt-1">
                                                @if($shop->blog_option)
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                        有効
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 011.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                        </svg>
                                                        無効
                                                    </span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="pt-2 border-t border-gray-100">
                                            <dt class="text-xs font-medium text-gray-500 mb-1">登録日</dt>
                                            <dd class="text-xs text-gray-600 mt-1">{{ $shop->created_at->format('Y年m月d日 H:i') }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1">更新日</dt>
                                            <dd class="text-xs text-gray-600 mt-1">{{ $shop->updated_at->format('Y年m月d日 H:i') }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <!-- カード4: ブログ連携設定 -->
                                <div class="bg-white border border-gray-200 rounded-lg p-5 lg:p-6">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-5 pb-3 border-b border-gray-200">ブログ連携設定</h3>
                                    <dl class="space-y-4">
                                        @if($shop->integration_type === 'blog')
                                            <div>
                                                <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                                    <span class="inline-flex items-center justify-center w-5 h-5 bg-blue-500 text-white text-xs font-bold rounded mr-1.5">営</span>記事一覧URL
                                                </dt>
                                                <dd class="text-xs font-mono text-gray-700 mt-1 break-all">{{ $shop->blog_list_url ?? '未設定' }}</dd>
                                            </div>
                                            @if($shop->blog_link_selector)
                                                <div>
                                                    <dt class="text-xs font-medium text-gray-500 mb-1">記事リンクセレクター</dt>
                                                    <dd class="text-xs font-mono text-gray-700 mt-1">{{ $shop->blog_link_selector }}</dd>
                                                </div>
                                            @endif
                                            @if($shop->blog_item_selector)
                                                <div>
                                                    <dt class="text-xs font-medium text-gray-500 mb-1">投稿ブロック（親要素）セレクター</dt>
                                                    <dd class="text-xs font-mono text-gray-700 mt-1">{{ $shop->blog_item_selector }}</dd>
                                                </div>
                                            @endif
                                            @if($shop->blog_date_selector)
                                                <div>
                                                    <dt class="text-xs font-medium text-gray-500 mb-1">日付セレクター</dt>
                                                    <dd class="text-xs font-mono text-gray-700 mt-1">{{ $shop->blog_date_selector }}</dd>
                                                </div>
                                            @endif
                                            @if($shop->blog_image_selector)
                                                <div>
                                                    <dt class="text-xs font-medium text-gray-500 mb-1">画像セレクター</dt>
                                                    <dd class="text-xs font-mono text-gray-700 mt-1">{{ $shop->blog_image_selector }}</dd>
                                                </div>
                                            @endif
                                            @if($shop->blog_content_selector)
                                                <div>
                                                    <dt class="text-xs font-medium text-gray-500 mb-1">本文セレクター</dt>
                                                    <dd class="text-xs font-mono text-gray-700 mt-1">{{ $shop->blog_content_selector }}</dd>
                                                </div>
                                            @endif
                                            @if($shop->blog_crawl_time)
                                                <div>
                                                    <dt class="text-xs font-medium text-gray-500 mb-1">クロール実行時刻</dt>
                                                    <dd class="text-sm font-medium text-gray-900 mt-1">{{ is_string($shop->blog_crawl_time) ? substr($shop->blog_crawl_time, 0, 5) : (\Carbon\Carbon::parse($shop->blog_crawl_time)->format('H:i')) }}</dd>
                                                </div>
                                            @endif
                                            <div>
                                                <dt class="text-xs font-medium text-gray-500 mb-1">連携タイプ</dt>
                                                <dd class="mt-1">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                        ブログ連携
                                                    </span>
                                                </dd>
                                            </div>
                                            @if($shop->blog_list_url)
                                                <div class="pt-4 border-t border-gray-100">
                                                    <button type="button" id="blogTestBtn" onclick="runBlogTest(event)" 
                                                        class="w-full px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                                                        ブログクロールテスト
                                                    </button>
                                                </div>
                                            @endif
                                        @elseif($shop->integration_type === 'instagram')
                                            <div>
                                                <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                                    <span class="inline-flex items-center justify-center w-5 h-5 bg-blue-500 text-white text-xs font-bold rounded mr-1.5">営</span>Instagram一覧URL
                                                </dt>
                                                <dd class="text-xs font-mono text-gray-700 mt-1 break-all">{{ $shop->blog_list_url ?? '未設定' }}</dd>
                                            </div>
                                            @if($shop->blog_link_selector)
                                                <div>
                                                    <dt class="text-xs font-medium text-gray-500 mb-1">投稿リンクセレクター</dt>
                                                    <dd class="text-xs font-mono text-gray-700 mt-1">{{ $shop->blog_link_selector }}</dd>
                                                </div>
                                            @endif
                                            @if($shop->instagram_item_selector)
                                                <div>
                                                    <dt class="text-xs font-medium text-gray-500 mb-1">投稿ブロック（親要素）セレクター</dt>
                                                    <dd class="text-xs font-mono text-gray-700 mt-1">{{ $shop->instagram_item_selector }}</dd>
                                                </div>
                                            @endif
                                            @if($shop->blog_image_selector)
                                                <div>
                                                    <dt class="text-xs font-medium text-gray-500 mb-1">画像セレクター</dt>
                                                    <dd class="text-xs font-mono text-gray-700 mt-1">{{ $shop->blog_image_selector }}</dd>
                                                </div>
                                            @endif
                                            @if($shop->blog_content_selector)
                                                <div>
                                                    <dt class="text-xs font-medium text-gray-500 mb-1">本文セレクター</dt>
                                                    <dd class="text-xs font-mono text-gray-700 mt-1">{{ $shop->blog_content_selector }}</dd>
                                                </div>
                                            @endif
                                            @if($shop->instagram_crawl_time)
                                                <div>
                                                    <dt class="text-xs font-medium text-gray-500 mb-1">クロール実行時刻</dt>
                                                    <dd class="text-sm font-medium text-gray-900 mt-1">{{ is_string($shop->instagram_crawl_time) ? substr($shop->instagram_crawl_time, 0, 5) : (\Carbon\Carbon::parse($shop->instagram_crawl_time)->format('H:i')) }}</dd>
                                                </div>
                                            @endif
                                            <div>
                                                <dt class="text-xs font-medium text-gray-500 mb-1">連携タイプ</dt>
                                                <dd class="mt-1">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                        Instagram連携
                                                    </span>
                                                </dd>
                                            </div>
                                            @if($shop->blog_list_url)
                                                <div class="pt-4 border-t border-gray-100">
                                                    <button type="button" id="instagramTestBtn" onclick="runInstagramTest(event)" 
                                                        class="w-full px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-colors">
                                                        Instagramクロールテスト
                                                    </button>
                                                </div>
                                            @endif
                                        @else
                                            <div class="text-sm text-gray-400">未設定</div>
                                        @endif
                                    </dl>
                                </div>
                            </div>

                            <!-- 下段：Google Business Profile情報 | AI返信設定 -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <!-- カード5: Google Business Profile情報 -->
                                <div class="bg-white border border-gray-200 rounded-lg p-5 lg:p-6">
                                    <div class="flex items-center justify-between mb-5 pb-3 border-b border-gray-200">
                                        <h3 class="text-lg font-semibold text-gray-900">Google Business Profile情報</h3>
                                        @if(!session('operator_id'))
                                            <a href="{{ route('shops.connect', $shop->id) }}"
                                               class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md transition-colors">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                                </svg>
                                                Google連携
                                            </a>
                                        @endif
                                    </div>
                                    <dl class="space-y-4">
                                        <div class="bg-gray-50 rounded-md p-3">
                                            <dt class="text-xs font-medium text-gray-500 mb-1">GBPアカウントID</dt>
                                            <dd class="text-xs font-mono text-gray-700 mt-1 break-all">
                                                {{ $shop->gbp_account_id ?? '未設定' }}
                                            </dd>
                                        </div>
                                        <div class="bg-gray-50 rounded-md p-3">
                                            <dt class="text-xs font-medium text-gray-500 mb-1">GBPロケーションID</dt>
                                            <dd class="text-xs font-mono text-gray-700 mt-1 break-all">
                                                {{ $shop->gbp_location_id ?? '未設定' }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1">リフレッシュトークン</dt>
                                            <dd class="mt-1">
                                                @if($shop->gbp_refresh_token)
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                        設定済み
                                                    </span>
                                                @else
                                                    <span class="text-sm text-gray-400">未設定</span>
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="bg-gray-50 rounded-md p-3">
                                            <dt class="text-xs font-medium text-gray-500 mb-1">GBP店舗名（正式名称）</dt>
                                            <dd class="text-sm font-semibold text-gray-900 mt-1">
                                                {{ $shop->gbp_name ?? '未設定' }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                <!-- カード6: AI返信設定 -->
                                <div class="bg-white border border-gray-200 rounded-lg p-5 lg:p-6">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-5 pb-3 border-b border-gray-200">AI返信設定</h3>
                                    <dl class="space-y-4">
                                        <div>
                                            <dt class="text-xs font-medium text-gray-500 mb-1">AI返信時に必ず入れるキーワード</dt>
                                            <dd class="text-sm text-gray-700 mt-1 whitespace-pre-wrap bg-gray-50 rounded-md p-3">
                                                {{ $shop->ai_reply_keywords ?? '未設定' }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <!-- 単独配置：メモ・注意事項 -->
                            <div class="bg-white border border-gray-200 rounded-lg p-5 lg:p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-5 pb-3 border-b border-gray-200">メモ・注意事項</h3>
                                <dl class="space-y-4">
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>★2以下の口コミ対応方針
                                        </dt>
                                        <dd class="text-sm text-gray-700 mt-1 whitespace-pre-wrap bg-gray-50 rounded-md p-3">
                                            {{ $shop->low_rating_response ?? '未設定' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-gray-500 mb-1 flex items-center">
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>メモ
                                        </dt>
                                        <dd class="text-sm text-gray-700 mt-1 whitespace-pre-wrap bg-gray-50 rounded-md p-3">
                                            {{ $shop->memo ?? '未設定' }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Instagram投稿履歴 -->
                            @if($shop->integration_type === 'instagram')
                            <div class="bg-white border border-gray-200 rounded-lg p-5 lg:p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-5 pb-3 border-b border-gray-200">Instagram投稿履歴</h3>
                                @if($instagramPosts && $instagramPosts->count() > 0)
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">投稿日時</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Instagram URL</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">WordPress</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                @foreach($instagramPosts as $post)
                                                    <tr>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                            {{ $post->posted_at ? $post->posted_at->format('Y/m/d H:i') : ($post->create_time ? $post->create_time->format('Y/m/d H:i') : '-') }}
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-900">
                                                            <a href="{{ $post->source_url }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 break-all">
                                                                {{ Str::limit($post->source_url, 50) }}
                                                            </a>
                                                        </td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                            @if($post->wp_post_status === 'success')
                                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                                    成功
                                                                </span>
                                                            @elseif($post->wp_post_status === 'failed')
                                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                                    失敗
                                                                </span>
                                                            @else
                                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                                    {{ $post->wp_post_status === null ? '未実行' : '処理中' }}
                                                                </span>
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                                            @if($shop->wp_post_enabled && !$post->wp_post_id && ($post->wp_post_status === 'failed' || $post->wp_post_status === null))
                                                                <form action="{{ route(session('operator_id') ? 'operator.shops.gbp-posts.retry-wp' : 'shops.gbp-posts.retry-wp', ['shop' => $shop->id, 'gbpPost' => $post->id]) }}" method="POST" class="inline" onsubmit="return confirm('WordPress投稿を実行しますか？');">
                                                                    @csrf
                                                                    <button type="submit" class="px-3 py-1 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md">
                                                                        {{ $post->wp_post_status === 'failed' ? 'WordPress再投稿' : 'WordPress投稿' }}
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-8">
                                        <p class="text-gray-500">投稿履歴がありません。</p>
                                    </div>
                                @endif
                            </div>
                            @endif

                            <!-- 動画プレビューモーダル -->
                            <div id="video-preview-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
                                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                                    <!-- 背景オーバーレイ -->
                                    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onclick="closeVideoPreview()"></div>
                                    
                                    <!-- モーダルパネル -->
                                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                                            <div class="flex items-center justify-between mb-4">
                                                <h3 id="video-preview-title" class="text-lg font-medium text-gray-900"></h3>
                                                <button type="button" onclick="closeVideoPreview()" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="mt-4">
                                                <video id="video-preview-player" controls class="w-full rounded-lg" style="max-height: 70vh;">
                                                    お使いのブラウザは動画再生に対応していません。
                                                </video>
                                            </div>
                                            <div class="mt-4 flex justify-end space-x-3">
                                                <button type="button" onclick="closeVideoPreview()" class="px-4 py-2 bg-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                                    閉じる
                                                </button>
                                                <a id="video-preview-download" href="#" class="js-media-download px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 inline-block text-center">
                                                    ダウンロード（使用）
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 投稿素材ストレージ -->
                            <div class="bg-white border border-gray-200 rounded-lg p-5 lg:p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-5 pb-3 border-b border-gray-200">投稿素材ストレージ</h3>
                                
                                <!-- タブ -->
                                <div class="mb-4 border-b border-gray-200">
                                    <nav class="-mb-px flex space-x-8">
                                        <button onclick="switchMediaTab('image')" id="tab-image" class="media-tab-button border-b-2 border-indigo-500 py-4 px-1 text-sm font-medium text-indigo-600">
                                            画像
                                        </button>
                                        <button onclick="switchMediaTab('video')" id="tab-video" class="media-tab-button border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                            動画
                                        </button>
                                    </nav>
                                </div>

                                <!-- アップロードエリア -->
                                <div class="mb-6">
                                    <div id="upload-area" class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-indigo-400 transition-colors">
                                        <input type="file" id="media-file-input" multiple accept="image/*,video/*" class="hidden">
                                        <div class="space-y-2">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-sm text-gray-600">
                                                <label for="media-file-input" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                    <span>ファイルを選択</span>
                                                </label>
                                                <p class="pl-1">またはドラッグ＆ドロップ</p>
                                            </div>
                                            <p class="text-xs text-gray-500">
                                                画像: JPG, PNG, WebP (最大10MB) / 動画: MP4, WebM, MOV (最大100MB)
                                            </p>
                                        </div>
                                    </div>
                                    <div id="upload-progress" class="hidden mt-4">
                                        <div class="bg-gray-200 rounded-full h-2.5">
                                            <div id="upload-progress-bar" class="bg-indigo-600 h-2.5 rounded-full" style="width: 0%"></div>
                                        </div>
                                        <p id="upload-status" class="text-sm text-gray-600 mt-2"></p>
                                    </div>
                                </div>

                                <!-- 素材一覧 -->
                                <div id="media-assets-list" class="space-y-4">
                                    <div class="text-center text-gray-500 py-8">
                                        読み込み中...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        // 編集モードの切り替え
        function toggleEditMode() {
            const editForm = document.getElementById('editForm');
            const detailView = document.getElementById('detailView');
            const editBtn = document.getElementById('editToggleBtn');
            
            if (editForm.classList.contains('hidden')) {
                editForm.classList.remove('hidden');
                detailView.classList.add('hidden');
                editBtn.textContent = 'キャンセル';
                editBtn.classList.remove('bg-indigo-500', 'hover:bg-indigo-700');
                editBtn.classList.add('bg-gray-500', 'hover:bg-gray-700');
            } else {
                editForm.classList.add('hidden');
                detailView.classList.remove('hidden');
                editBtn.textContent = '編集';
                editBtn.classList.remove('bg-gray-500', 'hover:bg-gray-700');
                editBtn.classList.add('bg-indigo-500', 'hover:bg-indigo-700');
            }
        }

        // セッションからedit_modeが渡された場合は編集モードで開く
        @if(session('edit_mode'))
            document.addEventListener('DOMContentLoaded', function() {
                toggleEditMode();
            });
        @endif

        // 投稿素材ストレージ：タブ切り替え
        let currentMediaType = 'image';
        
        function switchMediaTab(type) {
            currentMediaType = type;
            
            // タブボタンのスタイルを更新
            document.querySelectorAll('.media-tab-button').forEach(btn => {
                btn.classList.remove('border-indigo-500', 'text-indigo-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            
            const activeTab = document.getElementById('tab-' + type);
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-indigo-500', 'text-indigo-600');
            
            // 素材一覧を再読み込み
            loadMediaAssets();
        }

        // 投稿素材ストレージ：素材一覧を読み込み
        function loadMediaAssets() {
            const listContainer = document.getElementById('media-assets-list');
            listContainer.innerHTML = '<div class="text-center text-gray-500 py-8">読み込み中...</div>';
            
            const isOperator = @json(session('operator_id') ? true : false);
            const routeName = isOperator ? 'operator.shops.media-assets.index' : 'shops.media-assets.index';
            const url = `{{ route('shops.media-assets.index', $shop) }}?type=${currentMediaType}`;
            
            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.assets.length === 0) {
                        listContainer.innerHTML = '<div class="text-center text-gray-500 py-8">素材がありません</div>';
                    } else {
                        listContainer.innerHTML = data.assets.map(asset => {
                            // preview_urlを使用（Controller経由）
                            let thumbnailHtml;
                            if (asset.type === 'image' && asset.preview_url) {
                                thumbnailHtml = `<img src="${asset.preview_url}" alt="${asset.original_filename}" class="w-20 h-20 object-cover rounded">`;
                            } else if (asset.type === 'video' && asset.preview_url) {
                                // 動画の場合はクリック可能なサムネイル
                                thumbnailHtml = `
                                    <button type="button" onclick="openVideoPreview('${asset.preview_url}', '${asset.original_filename}', '${asset.download_url}')" class="w-20 h-20 bg-gray-200 rounded flex items-center justify-center hover:bg-gray-300 transition-colors cursor-pointer group relative">
                                        <svg class="w-8 h-8 text-gray-400 group-hover:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <svg class="w-6 h-6 text-white opacity-80" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-6.89a1.5 1.5 0 000-2.538L6.3 2.84z" />
                                            </svg>
                                        </div>
                                    </button>
                                `;
                            } else {
                                thumbnailHtml = '<div class="w-20 h-20 bg-gray-200 rounded flex items-center justify-center"><svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg></div>';
                            }
                            
                            // 使用済みの場合は操作ボタンを非表示
                            if (asset.is_used) {
                                return `
                                    <div class="border border-gray-200 rounded-lg p-4 flex items-center justify-between hover:bg-gray-50 opacity-60">
                                        <div class="flex items-center space-x-4 flex-1">
                                            ${thumbnailHtml}
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-gray-900">${asset.original_filename}</p>
                                                <p class="text-xs text-gray-500 mt-1">${formatFileSize(asset.file_size)} • ${asset.uploaded_at}</p>
                                            </div>
                                        </div>
                                        <span class="ml-4 px-4 py-2 bg-gray-400 text-white text-sm font-medium rounded-md cursor-not-allowed">
                                            使用済み
                                        </span>
                                    </div>
                                `;
                            }
                            
                            return `
                                <div class="border border-gray-200 rounded-lg p-4 flex items-center justify-between hover:bg-gray-50">
                                    <div class="flex items-center space-x-4 flex-1">
                                        ${thumbnailHtml}
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-900">${asset.original_filename}</p>
                                            <p class="text-xs text-gray-500 mt-1">${formatFileSize(asset.file_size)} • ${asset.uploaded_at}</p>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex space-x-2">
                                        <a href="${asset.download_url}" class="js-media-download px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 inline-block text-center">
                                            ダウンロード（使用）
                                        </a>
                                        <form action="${asset.delete_url}" method="POST" class="js-media-delete-form inline" data-asset-id="${asset.id}">
                                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                            <input type="hidden" name="_method" value="DELETE">
                                            <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                削除
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            `;
                        }).join('');
                    }
                } else {
                    listContainer.innerHTML = '<div class="text-center text-red-500 py-8">エラーが発生しました</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                listContainer.innerHTML = '<div class="text-center text-red-500 py-8">読み込みに失敗しました</div>';
            });
        }

        // 投稿素材ストレージ：ファイルサイズをフォーマット
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // 投稿素材ストレージ：ファイルアップロード
        document.getElementById('media-file-input').addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            if (files.length === 0) return;
            
            // 現在のタブタイプに応じたファイルのみをフィルタ
            const filteredFiles = files.filter(file => {
                if (currentMediaType === 'image') {
                    return file.type.startsWith('image/');
                } else {
                    return file.type.startsWith('video/');
                }
            });
            
            if (filteredFiles.length === 0) {
                alert('選択したファイルは現在のタブ（' + (currentMediaType === 'image' ? '画像' : '動画') + '）に一致しません。');
                return;
            }
            
            uploadMediaAssets(filteredFiles);
        });

        // 投稿素材ストレージ：ドラッグ＆ドロップ
        const uploadArea = document.getElementById('upload-area');
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('border-indigo-500', 'bg-indigo-50');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('border-indigo-500', 'bg-indigo-50');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('border-indigo-500', 'bg-indigo-50');
            
            const files = Array.from(e.dataTransfer.files);
            if (files.length === 0) return;
            
            const filteredFiles = files.filter(file => {
                if (currentMediaType === 'image') {
                    return file.type.startsWith('image/');
                } else {
                    return file.type.startsWith('video/');
                }
            });
            
            if (filteredFiles.length === 0) {
                alert('選択したファイルは現在のタブ（' + (currentMediaType === 'image' ? '画像' : '動画') + '）に一致しません。');
                return;
            }
            
            uploadMediaAssets(filteredFiles);
        });

        // 投稿素材ストレージ：アップロード実行
        function uploadMediaAssets(files) {
            const formData = new FormData();
            formData.append('type', currentMediaType);
            files.forEach(file => {
                formData.append('files[]', file);
            });
            
            const progressContainer = document.getElementById('upload-progress');
            const progressBar = document.getElementById('upload-progress-bar');
            const statusText = document.getElementById('upload-status');
            
            progressContainer.classList.remove('hidden');
            progressBar.style.width = '0%';
            statusText.textContent = 'アップロード中...';
            
            const routeName = @json(session('operator_id') ? 'operator.shops.media-assets.store' : 'shops.media-assets.store');
            const url = `{{ route('shops.media-assets.store', $shop) }}`;
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    progressBar.style.width = '100%';
                    statusText.textContent = data.message;
                    setTimeout(() => {
                        progressContainer.classList.add('hidden');
                        loadMediaAssets(); // 一覧を再読み込み
                        document.getElementById('media-file-input').value = ''; // 入力クリア
                    }, 1000);
                } else {
                    statusText.textContent = 'エラー: ' + (data.message || 'アップロードに失敗しました');
                    if (data.errors && data.errors.length > 0) {
                        alert(data.errors.join('\n'));
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusText.textContent = 'アップロードに失敗しました';
            });
        }

        // 投稿素材ストレージ：ダウンロード確認
        document.addEventListener('click', function(e) {
            // ダウンロードリンクのクリック
            if (e.target.classList.contains('js-media-download') || e.target.closest('.js-media-download')) {
                const link = e.target.classList.contains('js-media-download') ? e.target : e.target.closest('.js-media-download');
                
                if (!confirm('ダウンロードするとストレージから削除されますがよろしいですか？')) {
                    e.preventDefault();
                    return false;
                }
                
                // OKの場合、少し待ってから一覧を再読み込み
                setTimeout(() => {
                    loadMediaAssets();
                }, 1000);
            }
            
            // 削除フォームの送信
            if (e.target.closest('.js-media-delete-form') && e.target.type === 'submit') {
                e.preventDefault();
                const form = e.target.closest('.js-media-delete-form');
                
                if (!confirm('ダウンロードせずに削除します。よろしいですか？')) {
                    return false;
                }
                
                // OKの場合、フォームを送信
                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: new URLSearchParams(new FormData(form))
                })
                .then(response => {
                    if (response.redirected) {
                        // リダイレクトの場合はページをリロード
                        window.location.reload();
                    } else if (response.ok) {
                        // 成功時は一覧を再読み込み
                        loadMediaAssets();
                    } else {
                        return response.json().then(data => {
                            throw new Error(data.message || '削除に失敗しました');
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('削除に失敗しました: ' + error.message);
                    loadMediaAssets(); // エラー時も一覧を再読み込み
                });
            }
        });

        // 動画プレビュー：モーダルを開く
        function openVideoPreview(previewUrl, filename, downloadUrl) {
            const modal = document.getElementById('video-preview-modal');
            const videoPlayer = document.getElementById('video-preview-player');
            const title = document.getElementById('video-preview-title');
            const downloadLink = document.getElementById('video-preview-download');
            
            title.textContent = filename;
            videoPlayer.src = previewUrl;
            downloadLink.href = downloadUrl;
            
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // 背景スクロールを無効化
            
            // 動画を読み込む
            videoPlayer.load();
        }

        // 動画プレビュー：モーダルを閉じる
        function closeVideoPreview() {
            const modal = document.getElementById('video-preview-modal');
            const videoPlayer = document.getElementById('video-preview-player');
            
            modal.classList.add('hidden');
            document.body.style.overflow = ''; // 背景スクロールを再有効化
            
            // 動画を停止
            videoPlayer.pause();
            videoPlayer.src = '';
        }

        // ESCキーでモーダルを閉じる
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('video-preview-modal');
                if (!modal.classList.contains('hidden')) {
                    closeVideoPreview();
                }
            }
        });

        // ページ読み込み時に素材一覧を読み込み
        document.addEventListener('DOMContentLoaded', function() {
            loadMediaAssets();
        });

        // ブログクロールテスト実行
        function runBlogTest(event) {
            const btn = event && event.target ? event.target : document.getElementById('blogTestBtn');
            if (!btn) {
                alert('ボタンが見つかりません');
                return;
            }
            
            const originalText = btn.textContent;
            
            // ボタンを無効化
            btn.disabled = true;
            btn.textContent = 'テスト実行中...';
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            
            fetch('{{ route("shops.blog-test", $shop) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('ブログの自動投稿に成功しました\n\n記事URL: ' + data.article_url + '\nGBP投稿ID: ' + data.gbp_post_id);
                } else {
                    alert('失敗：' + (data.message || '不明なエラーが発生しました'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('失敗：通信エラーが発生しました');
            })
            .finally(() => {
                // ボタンを再有効化
                btn.disabled = false;
                btn.textContent = originalText;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            });
        }

        // Instagramクロールテスト実行
        function runInstagramTest(event) {
            const btn = event && event.target ? event.target : document.getElementById('instagramTestBtn');
            if (!btn) {
                alert('ボタンが見つかりません');
                return;
            }
            
            const originalText = btn.textContent;
            
            // ボタンを無効化
            btn.disabled = true;
            btn.textContent = 'テスト実行中...';
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            
            fetch('{{ route("shops.instagram-test", $shop) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Instagramの自動投稿に成功しました\n\n投稿URL: ' + data.article_url + '\nGBP投稿ID: ' + data.gbp_post_id);
                } else {
                    alert('失敗：' + (data.message || '不明なエラーが発生しました'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('失敗：通信エラーが発生しました');
            })
            .finally(() => {
                // ボタンを再有効化
                btn.disabled = false;
                btn.textContent = originalText;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            });
        }
        </script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const contractTypeInputs = document.querySelectorAll('input[name="contract_type"]');
                const referralFeeContainer = document.getElementById('referral_fee_container');
                const referralFeeInput = document.getElementById('referral_fee');

                if (contractTypeInputs.length > 0 && referralFeeContainer && referralFeeInput) {
                    contractTypeInputs.forEach(input => {
                        input.addEventListener('change', function() {
                            if (this.value === 'referral') {
                                referralFeeContainer.style.display = 'block';
                                referralFeeInput.required = true;
                            } else {
                                referralFeeContainer.style.display = 'none';
                                referralFeeInput.required = false;
                                referralFeeInput.value = '';
                            }
                        });
                    });
                }

                // MEOキーワード追加機能
                const addKeywordBtn = document.getElementById('add-meo-keyword-btn');
                const keywordsContainer = document.getElementById('meo-keywords-container');
                const maxKeywords = 10;

                if (addKeywordBtn && keywordsContainer) {
                    addKeywordBtn.addEventListener('click', function() {
                        const currentCount = keywordsContainer.querySelectorAll('.meo-keyword-item').length;
                        
                        if (currentCount >= maxKeywords) {
                            alert('最大' + maxKeywords + '件まで登録できます');
                            return;
                        }

                        const newIndex = currentCount;
                        const newItem = document.createElement('div');
                        newItem.className = 'meo-keyword-item flex items-center gap-2';
                        newItem.innerHTML = `
                            <input type="text" 
                                name="meo_keywords[]" 
                                class="meo-keyword-input flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="キーワード${newIndex + 1}">
                        `;
                        
                        keywordsContainer.appendChild(newItem);
                        
                        // 最大件数に達したら追加ボタンを無効化
                        if (currentCount + 1 >= maxKeywords) {
                            addKeywordBtn.disabled = true;
                            addKeywordBtn.classList.add('opacity-50', 'cursor-not-allowed');
                        }
                    });

                    // 初期状態で最大件数に達している場合は追加ボタンを無効化
                    const initialCount = keywordsContainer.querySelectorAll('.meo-keyword-item').length;
                    if (initialCount >= maxKeywords) {
                        addKeywordBtn.disabled = true;
                        addKeywordBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                }

                // 連携タイプ選択時のフォーム表示切替
                toggleIntegrationForms();
            });

            function toggleIntegrationForms() {
                const integrationType = document.querySelector('input[name="integration_type"]:checked')?.value || '';
                const blogSettings = document.getElementById('blog_settings');
                const instagramSettings = document.getElementById('instagram_settings');

                if (blogSettings && instagramSettings) {
                    if (integrationType === 'blog') {
                        blogSettings.style.display = 'block';
                        instagramSettings.style.display = 'none';
                        // ブログ設定フィールドのdisabled属性を削除
                        blogSettings.querySelectorAll('input, select, textarea').forEach(field => {
                            field.removeAttribute('disabled');
                        });
                        // Instagram設定フィールドをdisabledにする（送信されないように）
                        instagramSettings.querySelectorAll('input, select, textarea').forEach(field => {
                            field.setAttribute('disabled', 'disabled');
                        });
                    } else if (integrationType === 'instagram') {
                        blogSettings.style.display = 'none';
                        instagramSettings.style.display = 'block';
                        // Instagram設定フィールドのdisabled属性を削除
                        instagramSettings.querySelectorAll('input, select, textarea').forEach(field => {
                            field.removeAttribute('disabled');
                        });
                        // ブログ設定フィールドをdisabledにする（送信されないように）
                        blogSettings.querySelectorAll('input, select, textarea').forEach(field => {
                            field.setAttribute('disabled', 'disabled');
                        });
                    } else {
                        blogSettings.style.display = 'none';
                        instagramSettings.style.display = 'none';
                        // 両方の設定フィールドをdisabledにする
                        blogSettings.querySelectorAll('input, select, textarea').forEach(field => {
                            field.setAttribute('disabled', 'disabled');
                        });
                        instagramSettings.querySelectorAll('input, select, textarea').forEach(field => {
                            field.setAttribute('disabled', 'disabled');
                        });
                    }
                }
            }

            // フォーム送信時にdisabled属性を削除して、すべてのフィールドを送信可能にする
            document.querySelector('form[action*="shops.update"]')?.addEventListener('submit', function(e) {
                // すべてのdisabled属性を削除
                this.querySelectorAll('input[disabled], select[disabled], textarea[disabled]').forEach(field => {
                    field.removeAttribute('disabled');
                });
            });

            function toggleWpPostSettings() {
                const wpPostEnabled = document.getElementById('wp_post_enabled');
                const wpPostSettings = document.getElementById('wp_post_settings');
                
                if (wpPostEnabled && wpPostSettings) {
                    if (wpPostEnabled.checked) {
                        wpPostSettings.style.display = 'block';
                        // フィールドのdisabled属性を削除
                        wpPostSettings.querySelectorAll('input, select, textarea').forEach(field => {
                            field.removeAttribute('disabled');
                        });
                    } else {
                        wpPostSettings.style.display = 'none';
                        // フィールドをdisabledにする（送信されないように）
                        wpPostSettings.querySelectorAll('input, select, textarea').forEach(field => {
                            field.setAttribute('disabled', 'disabled');
                        });
                    }
                }
            }

            function fetchWpPostTypes(shopId) {
                const btn = document.getElementById('fetch-wp-post-types-btn');
                const listContainer = document.getElementById('wp-post-types-list');
                const itemsContainer = document.getElementById('wp-post-types-items');
                const errorContainer = document.getElementById('wp-post-types-error');
                const errorMessage = errorContainer.querySelector('p');

                // ボタンを無効化
                btn.disabled = true;
                btn.textContent = '取得中...';

                // エラーとリストを非表示
                listContainer.classList.add('hidden');
                errorContainer.classList.add('hidden');
                itemsContainer.innerHTML = '';

                // ルート名を決定（オペレーターか管理者か）
                const isOperator = {{ session('operator_id') ? 'true' : 'false' }};
                const url = isOperator 
                    ? `/operator/shops/${shopId}/fetch-wp-post-types`
                    : `/shops/${shopId}/fetch-wp-post-types`;

                // CSRFトークンを取得
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                    || document.querySelector('input[name="_token"]')?.value
                    || '';

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.post_types) {
                        // 取得成功
                        itemsContainer.innerHTML = '';
                        Object.entries(data.post_types).forEach(([slug, name]) => {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md border border-gray-300 cursor-pointer';
                            button.textContent = `${name} (${slug})`;
                            button.onclick = function() {
                                document.getElementById('wp_post_type').value = slug;
                            };
                            itemsContainer.appendChild(button);
                        });
                        listContainer.classList.remove('hidden');
                    } else {
                        // 取得失敗
                        errorMessage.textContent = data.message || '投稿タイプの取得に失敗しました。';
                        errorContainer.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    errorMessage.textContent = 'エラーが発生しました。';
                    errorContainer.classList.remove('hidden');
                })
                .finally(() => {
                    // ボタンを再有効化
                    btn.disabled = false;
                    btn.textContent = '投稿タイプ取得';
                });
            }
        </script>
    </x-app-layout>

