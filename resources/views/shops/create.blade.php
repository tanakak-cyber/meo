<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                店舗新規登録
            </h2>
            <a href="{{ route('shops.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                一覧に戻る
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-8 text-gray-900">
                    <form action="{{ route('shops.store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <h2 class="text-lg font-semibold mb-4">基本情報</h2>
                                
                                <div class="mb-4">
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                        <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>店舗名 <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
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
                                            <option value="{{ $plan->id }}" {{ old('plan_id') == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
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
                                            <option value="{{ $salesPerson->id }}" {{ old('sales_person_id') == $salesPerson->id ? 'selected' : '' }}>{{ $salesPerson->name }}</option>
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
                                            <option value="{{ $operationPerson->id }}" {{ old('operation_person_id') == $operationPerson->id ? 'selected' : '' }}>{{ $operationPerson->name }}</option>
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
                                    <input type="text" name="shop_contact_name" id="shop_contact_name" value="{{ old('shop_contact_name') }}"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('shop_contact_name') border-red-500 @enderror">
                                    @error('shop_contact_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mb-4">
                                    <label for="shop_contact_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                        <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>店舗担当者電話番号
                                    </label>
                                    <input type="text" name="shop_contact_phone" id="shop_contact_phone" value="{{ old('shop_contact_phone') }}"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('shop_contact_phone') border-red-500 @enderror">
                                    @error('shop_contact_phone')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mb-4">
                                    <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                                        <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>金額
                                    </label>
                                    <input type="number" name="price" id="price" value="{{ old('price') }}" step="0.01" min="0"
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
                                    <input type="number" name="initial_cost" id="initial_cost" value="{{ old('initial_cost') }}" step="0.01" min="0"
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
                                            <input type="radio" name="contract_type" value="own" {{ old('contract_type', 'own') === 'own' ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-gray-700">自社契約</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="contract_type" value="referral" {{ old('contract_type') === 'referral' ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-gray-700">紹介契約</span>
                                        </label>
                                    </div>
                                    @error('contract_type')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mb-4" id="referral_fee_container" style="display: {{ old('contract_type') === 'referral' ? 'block' : 'none' }};">
                                    <label for="referral_fee" class="block text-sm font-medium text-gray-700 mb-2">
                                        <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>月額紹介フィー
                                    </label>
                                    <input type="number" name="referral_fee" id="referral_fee" value="{{ old('referral_fee') }}" step="0.01" min="0"
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
                                            value="{{ old('contract_date') }}"
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
                                            value="{{ old('contract_end_date') }}"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('contract_end_date') border-red-500 @enderror">
                                        @error('contract_end_date')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="wp_post_enabled" value="1" 
                                            {{ old('wp_post_enabled') ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <span class="ml-2 text-sm text-gray-700">ブログ投稿お任せオプション</span>
                                    </label>
                                </div>

                                <div class="mb-4">

                                    <label for="review_monthly_target" class="block text-sm font-medium text-gray-700 mb-2">
                                        月間口コミノルマ
                                    </label>
                                    <input type="number" name="review_monthly_target" id="review_monthly_target" 
                                           value="{{ old('review_monthly_target') }}" 
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
                                           value="{{ old('photo_monthly_target') }}" 
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
                                           value="{{ old('video_monthly_target') }}" 
                                           min="1" max="4" step="1"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('video_monthly_target') border-red-500 @enderror"
                                           placeholder="1～4">
                                    @error('video_monthly_target')
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
                                    <input type="text" name="gbp_account_id" id="gbp_account_id" value="{{ old('gbp_account_id') }}"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('gbp_account_id') border-red-500 @enderror">
                                    @error('gbp_account_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mb-4">
                                    <label for="gbp_location_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        GBPロケーションID
                                    </label>
                                    <input type="text" name="gbp_location_id" id="gbp_location_id" value="{{ old('gbp_location_id') }}"
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
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('gbp_refresh_token') border-red-500 @enderror">{{ old('gbp_refresh_token') }}</textarea>
                                    @error('gbp_refresh_token')
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
                                        placeholder="★2以下の口コミが来た時の対応方法を入力してください">{{ old('low_rating_response') }}</textarea>
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
                                        placeholder="店舗に関するメモを入力してください">{{ old('memo') }}</textarea>
                                    @error('memo')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
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
                                               value="{{ old('report_email_' . $i) }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('report_email_' . $i) border-red-500 @enderror"
                                               placeholder="example@example.com">
                                        @error('report_email_' . $i)
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endfor

                                <h3 class="text-lg font-semibold mb-4 mt-6">
                                    <span class="inline-flex items-center justify-center w-6 h-6 bg-blue-500 text-white text-xs font-bold rounded mr-2">営</span>MEOキーワード（1～3件推奨。最大10件）
                                </h3>
<div class="mb-6 p-4 border border-blue-200 rounded-lg bg-blue-50">
    <h3 class="text-md font-semibold text-blue-800 mb-3">
        順位計測座標（MEO定点観測）
    </h3>

    <p class="text-sm text-gray-600 mb-3">
        未設定の場合はデフォルト座標（東京）で順位を取得します。<br>
        店舗の商圏に合わせて設定してください（例：店舗の住所付近）
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="rank_lat" class="block text-sm font-medium text-gray-700 mb-1">
                緯度（Latitude）
            </label>
            <input type="number" step="0.0000001" name="rank_lat" id="rank_lat"
                value="{{ old('rank_lat') }}"
                placeholder="例: 35.2380"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
        </div>

        <div>
            <label for="rank_lng" class="block text-sm font-medium text-gray-700 mb-1">
                経度（Longitude）
            </label>
            <input type="number" step="0.0000001" name="rank_lng" id="rank_lng"
                value="{{ old('rank_lng') }}"
                placeholder="例: 136.0660"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
        </div>
    </div>

    <p class="text-xs text-gray-500 mt-2">
        ※ Googleマップで右クリック → 座標コピーで取得できます
    </p>
</div>
                                <div id="meo-keywords-container" class="space-y-2">
                                    @php
                                        $oldKeywords = old('meo_keywords', []);
                                        $initialCount = max(3, count($oldKeywords));
                                        $initialCount = min($initialCount, 10);
                                    @endphp
                                    @for($i = 0; $i < $initialCount; $i++)
                                        <div class="meo-keyword-item flex items-center gap-2">
                                            <input type="text" 
                                                name="meo_keywords[]" 
                                                class="meo-keyword-input flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                value="{{ old('meo_keywords.' . $i) }}"
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
                                                {{ old('integration_type') === 'blog' ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                onchange="toggleIntegrationForms()">
                                            <span class="ml-2 text-sm text-gray-700">ブログ連携</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="integration_type" value="instagram" 
                                                {{ old('integration_type') === 'instagram' ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                onchange="toggleIntegrationForms()">
                                            <span class="ml-2 text-sm text-gray-700">Instagram連携</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="integration_type" value="" 
                                                {{ old('integration_type') === '' || old('integration_type') === null ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                onchange="toggleIntegrationForms()">
                                            <span class="ml-2 text-sm text-gray-700">未使用</span>
                                        </label>
                                    </div>
                                    @error('integration_type')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div id="blog_settings" class="mb-3" style="display: {{ old('integration_type') === 'blog' ? 'block' : 'none' }};">
                                    <h4 class="text-md font-semibold mb-3">ブログクロール設定</h4>
                                
                                @php
                                    $currentIntegrationType = old('integration_type');
                                    // ブログ設定のフォームでは、integration_typeが'blog'の場合のみold()を使う
                                    $blogListUrl = ($currentIntegrationType === 'blog') ? old('blog_list_url') : '';
                                    $blogLinkSelector = ($currentIntegrationType === 'blog') ? old('blog_link_selector') : '';
                                    $blogItemSelector = ($currentIntegrationType === 'blog') ? old('blog_item_selector') : '';
                                    $blogDateSelector = ($currentIntegrationType === 'blog') ? old('blog_date_selector') : '';
                                    $blogImageSelector = ($currentIntegrationType === 'blog') ? old('blog_image_selector') : '';
                                    $blogContentSelector = ($currentIntegrationType === 'blog') ? old('blog_content_selector') : '';
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
                                           value="{{ old('blog_crawl_time', '03:00') }}"
                                           class="w-full md:w-auto px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_crawl_time') border-red-500 @enderror">
                                    <p class="mt-1 text-xs text-gray-500">毎日この時刻に自動クロールが実行されます</p>
                                    @error('blog_crawl_time')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="blog_fallback_image_url" class="block text-sm font-medium text-gray-700 mb-1">
                                        フォールバック画像URL
                                    </label>
                                    <input type="url" name="blog_fallback_image_url" id="blog_fallback_image_url" 
                                           value="{{ old('blog_fallback_image_url') }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('blog_fallback_image_url') border-red-500 @enderror"
                                           placeholder="https://example.com/default-image.jpg">
                                    <p class="mt-1 text-xs text-gray-500">記事に画像が見つからない場合に使用される画像URL</p>
                                    @error('blog_fallback_image_url')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                </div>

                                <div id="instagram_settings" class="mb-3" style="display: {{ old('integration_type') === 'instagram' ? 'block' : 'none' }};">
                                    <h4 class="text-md font-semibold mb-3">Instagramクロール設定</h4>
                                    
                                    @php
                                        $currentIntegrationType = old('integration_type');
                                        // Instagram設定のフォームでは、integration_typeが'instagram'の場合のみold()を使う
                                        $instagramListUrl = ($currentIntegrationType === 'instagram') ? old('blog_list_url') : '';
                                        $instagramLinkSelector = ($currentIntegrationType === 'instagram') ? old('blog_link_selector') : '';
                                        $instagramImageSelector = ($currentIntegrationType === 'instagram') ? old('blog_image_selector') : '';
                                        $instagramContentSelector = ($currentIntegrationType === 'instagram') ? old('blog_content_selector') : '';
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
                                        <label for="instagram_item_selector" class="block text-sm font-medium text-gray-700 mb-1">
                                            投稿ブロック（親要素）セレクター
                                        </label>
                                        <input type="text" name="instagram_item_selector" id="instagram_item_selector" 
                                               value="{{ old('integration_type') === 'instagram' ? old('instagram_item_selector') : '' }}"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('instagram_item_selector') border-red-500 @enderror"
                                               placeholder=".instagram-gallery-item">
                                        <p class="mt-1 text-xs text-gray-500">Instagramの1投稿を囲んでいる親要素のCSSセレクターを指定してください。</p>
                                        @error('instagram_item_selector')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label for="instagram_crawl_time" class="block text-sm font-medium text-gray-700 mb-1">
                                            クロール実行時刻
                                        </label>
                                        <input type="time" name="instagram_crawl_time" id="instagram_crawl_time" 
                                               value="{{ old('instagram_crawl_time', '03:00') }}"
                                               class="w-full md:w-auto px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('instagram_crawl_time') border-red-500 @enderror">
                                        <p class="mt-1 text-xs text-gray-500">毎日この時刻に自動クロールが実行されます</p>
                                        @error('instagram_crawl_time')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end space-x-4 pt-4 border-t">
                            <a href="{{ route('shops.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                キャンセル
                            </a>
                            <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                登録
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Contract type script loaded');
            const contractTypeInputs = document.querySelectorAll('input[name="contract_type"]');
            const referralFeeContainer = document.getElementById('referral_fee_container');
            const referralFeeInput = document.getElementById('referral_fee');

            console.log('contractTypeInputs:', contractTypeInputs.length);
            console.log('referralFeeContainer:', referralFeeContainer);
            console.log('referralFeeInput:', referralFeeInput);

            if (contractTypeInputs.length > 0 && referralFeeContainer && referralFeeInput) {
                contractTypeInputs.forEach(input => {
                    input.addEventListener('change', function() {
                        console.log('Contract type changed to:', this.value);
                        if (this.value === 'referral') {
                            referralFeeContainer.style.display = 'block';
                            referralFeeInput.required = true;
                            console.log('Referral fee container shown');
                        } else {
                            referralFeeContainer.style.display = 'none';
                            referralFeeInput.required = false;
                            referralFeeInput.value = '';
                            console.log('Referral fee container hidden');
                        }
                    });
                });
            } else {
                console.error('Required elements not found');
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
            });

            // 連携タイプ選択時のフォーム表示切替
            function toggleIntegrationForms() {
                const integrationType = document.querySelector('input[name="integration_type"]:checked')?.value || '';
                const blogSettings = document.getElementById('blog_settings');
                const instagramSettings = document.getElementById('instagram_settings');

                if (blogSettings && instagramSettings) {
                    if (integrationType === 'blog') {
                        blogSettings.style.display = 'block';
                        instagramSettings.style.display = 'none';
                    } else if (integrationType === 'instagram') {
                        blogSettings.style.display = 'none';
                        instagramSettings.style.display = 'block';
                    } else {
                        blogSettings.style.display = 'none';
                        instagramSettings.style.display = 'none';
                    }
                }
            }

            // ページ読み込み時に初期状態を設定
            document.addEventListener('DOMContentLoaded', function() {
                toggleIntegrationForms();
            });
        </script>
</x-app-layout>
