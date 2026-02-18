@php
    $prefix = "shops[{$role}]";
@endphp

<!-- A. 基本情報 -->
<div class="mb-8 bg-gray-50 rounded-lg p-5 border border-gray-200">
    <h4 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b-2 border-indigo-500">A. 基本情報</h4>
    <div class="space-y-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                店舗名 
                @if($role === 'own')
                    <span class="text-red-500">*</span>
                @endif
            </label>
            <input type="text" name="{{ $prefix }}[shop_name]" 
                   @if($role === 'own') required @endif
                   class="w-full px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 sm:text-sm">
        </div>
        
        @if($role === 'own')
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                自社順位（このキーワードでのGoogleマップ順位）
                <span class="text-red-500">*</span>
            </label>
            <input type="number" name="{{ $prefix }}[own_rank]" 
                   required
                   min="1"
                   placeholder="3"
                   class="w-full px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 sm:text-sm">
        </div>
        @endif
        
        @include('meo.partials.form-radio', ['name' => $prefix.'[opening_date]', 'label' => '開業日', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[address]', 'label' => '住所の記載', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[phone]', 'label' => '電話番号の記載', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[website]', 'label' => 'WEBサイトの記載', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[sns_links]', 'label' => 'SNSリンクの記載', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[website_nap_match]', 'label' => 'WEBサイトNAP一致', 'options' => ['している', 'していない']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[sns_nap_match]', 'label' => 'SNS NAP一致', 'options' => ['している', 'していない']])
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">サービス提供地域</label>
            <input type="text" name="{{ $prefix }}[service_area]"
                   class="w-full px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 sm:text-sm">
        </div>
        
        @include('meo.partials.form-radio', ['name' => $prefix.'[business_owner_info]', 'label' => 'ビジネス所有者情報の記載', 'options' => ['ある', 'なし']])
    </div>
</div>

<!-- B. カテゴリ・意味的関連性 -->
<div class="mb-8 bg-gray-50 rounded-lg p-5 border border-gray-200">
    <h4 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b-2 border-indigo-500">B. カテゴリ・意味的関連性</h4>
    <div class="space-y-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">メインカテゴリ</label>
            <input type="text" name="{{ $prefix }}[main_category]"
                   class="w-full px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 sm:text-sm">
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">サブカテゴリ</label>
            <input type="text" name="{{ $prefix }}[sub_category]"
                   class="w-full px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 sm:text-sm">
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">ビジネス説明文</label>
            <textarea name="{{ $prefix }}[business_description]" rows="3"
                      class="w-full px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 resize-none sm:text-sm"></textarea>
        </div>
    </div>
</div>

<!-- C. エンゲージメント -->
<div class="mb-8 bg-gray-50 rounded-lg p-5 border border-gray-200">
    <h4 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b-2 border-indigo-500">C. エンゲージメント</h4>
    <div class="space-y-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">口コミ総数</label>
            <input type="number" name="{{ $prefix }}[review_count]" min="0"
                   class="w-full px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 sm:text-sm">
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">口コミの総合評価</label>
            <input type="text" name="{{ $prefix }}[review_rating]" placeholder="例: 4.5"
                   class="w-full px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 sm:text-sm">
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">月間の口コミ数</label>
            <input type="number" name="{{ $prefix }}[monthly_review_count]" min="0"
                   class="w-full px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 sm:text-sm">
            <p class="mt-1 text-xs text-gray-500">例：週1回→4、月1回→1、毎日→30</p>
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">月間の投稿数</label>
            <input type="number" name="{{ $prefix }}[monthly_post_count]" min="0"
                   class="w-full px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 sm:text-sm">
            <p class="mt-1 text-xs text-gray-500">例：週1回→4、月1回→1、毎日→30</p>
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">写真枚数</label>
            <input type="number" name="{{ $prefix }}[photo_count]" min="0"
                   class="w-full px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 sm:text-sm">
        </div>
        
        @include('meo.partials.form-radio', ['name' => $prefix.'[photo_atmosphere]', 'label' => '写真で雰囲気がわかるか', 'options' => ['わかる', 'わからない']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[has_video]', 'label' => '動画の有無', 'options' => ['ある', 'なし']])
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">動画数</label>
            <input type="number" name="{{ $prefix }}[video_count]" min="0"
                   class="w-full px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 sm:text-sm">
        </div>
        
        @include('meo.partials.form-radio', ['name' => $prefix.'[review_story_type]', 'label' => '口コミがストーリー型か', 'options' => ['はい', 'いいえ']])
    </div>
</div>

<!-- D. Google機能の活用度 -->
<div class="mb-8 bg-gray-50 rounded-lg p-5 border border-gray-200">
    <h4 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b-2 border-indigo-500">D. Google機能の活用度</h4>
    <div class="space-y-4">
        @include('meo.partials.form-radio', ['name' => $prefix.'[has_menu]', 'label' => 'メニューの有無', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[menu_genre]', 'label' => 'メニューのジャンル分け', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[menu_photo]', 'label' => 'メニュー写真', 'options' => ['ある', 'なし']])
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">メニュー説明文</label>
            <textarea name="{{ $prefix }}[menu_description]" rows="2"
                      class="w-full px-4 py-2.5 rounded-lg border-2 border-gray-300 bg-white text-gray-900 placeholder-gray-400 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-200 resize-none sm:text-sm"></textarea>
        </div>
        
        @include('meo.partials.form-radio', ['name' => $prefix.'[price_display]', 'label' => '価格表記', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[reservation_link]', 'label' => '予約リンク', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[service_link]', 'label' => 'サービスリンク', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[business_hours]', 'label' => '営業時間の記載', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[last_order]', 'label' => 'ラストオーダー記載', 'options' => ['ある', 'ない']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[entry_period]', 'label' => '入店可能期間', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[qa]', 'label' => 'Q&A', 'options' => ['ある', 'なし']])
    </div>
</div>

<!-- E. 属性情報 -->
<div class="mb-8 bg-gray-50 rounded-lg p-5 border border-gray-200">
    <h4 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b-2 border-indigo-500">E. 属性情報</h4>
    <div class="space-y-4">
        @include('meo.partials.form-radio', ['name' => $prefix.'[barrier_free]', 'label' => 'バリアフリー', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[plan]', 'label' => 'プラン', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[pet]', 'label' => 'ペット', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[child]', 'label' => '子供', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[customer_segment]', 'label' => '客層', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[payment_method]', 'label' => '決済方法', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[feature]', 'label' => '特徴', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[meal]', 'label' => '食事', 'options' => ['ある', 'なし']])
        @include('meo.partials.form-radio', ['name' => $prefix.'[parking]', 'label' => '駐車場', 'options' => ['ある', 'なし']])
    </div>
</div>

