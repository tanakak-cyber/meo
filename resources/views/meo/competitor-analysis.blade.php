<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('競合分析') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- キーワード入力エリア -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="mb-4">
                        <label for="keyword" class="block text-sm font-medium text-gray-700 mb-2">
                            対象キーワード
                        </label>
                        <input type="text" 
                               id="keyword" 
                               name="keyword" 
                               required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               placeholder="例: 横浜市 屋根修理">
                    </div>
                    <div>
                        <label for="industry_description" class="block text-sm font-medium text-gray-700 mb-2">
                            業界の説明（分析の前提）
                            <span class="text-red-500">*</span>
                        </label>
                        <textarea id="industry_description" 
                                  name="industry_description" 
                                  required
                                  rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                  placeholder="例：ブランド・時計・貴金属を中心とした買取専門店。来店型で、地域密着を強みとしている。"></textarea>
                    </div>
                </div>
            </div>

            <!-- タブ切り替えUI -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <!-- タブヘッダー -->
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px" aria-label="Tabs">
                        <button type="button" 
                                data-tab="competitor1"
                                class="tab-button active flex-1 py-4 px-6 text-center border-b-2 font-medium text-sm border-indigo-500 text-indigo-600 bg-indigo-100">
                            競合①（上位1位）
                        </button>
                        <button type="button" 
                                data-tab="competitor2"
                                class="tab-button flex-1 py-4 px-6 text-center border-b-2 font-medium text-sm border-transparent text-gray-500 bg-white hover:bg-gray-50 hover:text-gray-700 hover:border-gray-300">
                            競合②（上位2位）
                        </button>
                        <button type="button" 
                                data-tab="own"
                                class="tab-button flex-1 py-4 px-6 text-center border-b-2 font-medium text-sm border-transparent text-gray-500 bg-white hover:bg-gray-50 hover:text-gray-700 hover:border-gray-300">
                            自社
                        </button>
                    </nav>
                </div>

                <!-- タブコンテンツ -->
                <div class="p-6">
                    <!-- 競合①（上位1位） -->
                    <div id="tab-content-competitor1" class="tab-content">
                        @include('meo.partials.competitor-analysis-form', ['role' => 'competitor1', 'roleLabel' => '競合①'])
                    </div>

                    <!-- 競合②（上位2位） -->
                    <div id="tab-content-competitor2" class="tab-content hidden">
                        @include('meo.partials.competitor-analysis-form', ['role' => 'competitor2', 'roleLabel' => '競合②'])
                    </div>

                    <!-- 自社 -->
                    <div id="tab-content-own" class="tab-content hidden">
                        @include('meo.partials.competitor-analysis-form', ['role' => 'own', 'roleLabel' => '自社'])
                    </div>
                </div>
            </div>

            <!-- 比較ボタン -->
            <div class="flex justify-center">
                <button type="button" 
                        id="compareBtn"
                        class="px-16 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-md text-lg"
                        style="transition-property: color, background-color, border-color, text-decoration-color, fill, stroke; transition-timing-function: cubic-bezier(.4, 0, .2, 1); transition-duration: .15s; margin-top: 20px; padding: 10px;">
                    比較する
                </button>
            </div>

            <!-- メッセージ表示エリア -->
            <div id="messageArea" class="mt-6 hidden"></div>
        </div>
    </div>

    <style>
        /* ラジオボタンの選択状態を視覚的に表示 */
        label:has(input[type="radio"]:checked) span {
            background-color: #4f46e5;
            color: white;
            border-color: #4f46e5;
        }
        label:has(input[type="radio"]:checked) span:hover {
            background-color: #4338ca;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // タブ切り替え機能（即座に反応するように）
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');

                    // 即座にスタイルを変更（transitionを無効化してから変更）
                    tabButtons.forEach(btn => {
                        btn.style.transition = 'none';
                        btn.classList.remove('active', 'border-indigo-500', 'text-indigo-600', 'bg-indigo-100');
                        btn.classList.add('border-transparent', 'text-gray-500', 'bg-white');
                        // 次のフレームでtransitionを復元
                        requestAnimationFrame(() => {
                            btn.style.transition = '';
                        });
                    });
                    
                    // 選択されたタブを即座にアクティブに
                    this.style.transition = 'none';
                    this.classList.add('active', 'border-indigo-500', 'text-indigo-600', 'bg-indigo-100');
                    this.classList.remove('border-transparent', 'text-gray-500', 'bg-white');
                    requestAnimationFrame(() => {
                        this.style.transition = '';
                    });

                    // コンテンツの切り替え
                    tabContents.forEach(content => {
                        content.classList.add('hidden');
                    });
                    document.getElementById(`tab-content-${targetTab}`).classList.remove('hidden');
                });
            });

            const compareBtn = document.getElementById('compareBtn');
            const keywordInput = document.getElementById('keyword');
            const messageArea = document.getElementById('messageArea');

            compareBtn.addEventListener('click', async function() {
                const keyword = keywordInput.value.trim();
                const industryDescription = document.getElementById('industry_description')?.value.trim() || '';
                
                if (!keyword) {
                    showMessage('キーワードを入力してください。', 'error');
                    return;
                }

                if (!industryDescription) {
                    showMessage('業界の説明を入力してください。', 'error');
                    return;
                }

                // フォームデータを収集
                const shops = [];
                const roles = ['competitor1', 'competitor2', 'own'];
                
                roles.forEach(role => {
                    const shopData = collectShopData(role);
                    shopData.role = role;
                    shops.push(shopData);
                });

                // 空の店舗名を持つshopを除外
                const validShops = shops.filter(shop => shop.shop_name && shop.shop_name.trim() !== '');

                // バリデーション：自社（own）は必須
                const ownShop = validShops.find(shop => shop.role === 'own');
                if (!ownShop || !ownShop.shop_name || ownShop.shop_name.trim() === '') {
                    showMessage('自社の店舗名を入力してください。', 'error');
                    return;
                }

                // 自社が存在しない場合
                if (!shops.some(shop => shop.role === 'own')) {
                    showMessage('分析には最低1店舗（自社）が必要です。', 'error');
                    return;
                }

                // 送信データを構築（空の店舗名を持つshopは除外）
                const data = {
                    keyword: keyword,
                    industry_description: industryDescription,
                    shops: validShops
                };

                // ローディング表示
                compareBtn.disabled = true;
                compareBtn.textContent = '送信中...';

                try {
                    // まずデータを保存
                    console.log('[CompetitorAnalysis] submit start');
                    const saveUrl = '/api/meo/competitor-analysis';
                    console.log('[CompetitorAnalysis] save endpoint:', saveUrl);
                    console.log('[CompetitorAnalysis] save payload:', data);
                    console.log('[CompetitorAnalysis] save payload size:', JSON.stringify(data).length);

                    const saveResponse = await fetch(saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(data)
                    });

                    console.log('[CompetitorAnalysis] save response status:', saveResponse.status);
                    console.log('[CompetitorAnalysis] save response headers:', Object.fromEntries(saveResponse.headers.entries()));
                    
                    const text = await saveResponse.text();
                    console.log('[CompetitorAnalysis] save raw response:', text);
                    
                    let saveResult;
                    try {
                        saveResult = JSON.parse(text);
                        console.log('[CompetitorAnalysis] save response data:', saveResult);
                    } catch (e) {
                        console.error('[CompetitorAnalysis] save response is not JSON:', e);
                        console.error('[CompetitorAnalysis] raw response text:', text);
                        throw new Error('Response is not JSON: ' + text.substring(0, 200));
                    }

                    if (!saveResponse.ok) {
                        console.error('[CompetitorAnalysis] save failed:', saveResult);
                        showMessage(saveResult.message || 'データの保存に失敗しました。', 'error');
                        compareBtn.disabled = false;
                        compareBtn.textContent = '比較する';
                        return;
                    }

                    console.log('[CompetitorAnalysis] save success', saveResult);

                    // OpenAI APIで分析を実行
                    showMessage('分析を実行中です。しばらくお待ちください...', 'success');
                    compareBtn.textContent = '分析中...';

                    const analysisUrl = '/api/meo/competitor-analysis/run';
                    console.log('[CompetitorAnalysis] analysis endpoint:', analysisUrl);
                    console.log('[CompetitorAnalysis] analysis payload:', data);
                    console.log('[CompetitorAnalysis] analysis payload size:', JSON.stringify(data).length);

                    const analysisResponse = await fetch(analysisUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(data)
                    });

                    console.log('[CompetitorAnalysis] analysis response status:', analysisResponse.status);
                    console.log('[CompetitorAnalysis] analysis response headers:', Object.fromEntries(analysisResponse.headers.entries()));
                    
                    const analysisText = await analysisResponse.text();
                    console.log('[CompetitorAnalysis] analysis raw response:', analysisText);
                    
                    let analysisResult;
                    try {
                        analysisResult = JSON.parse(analysisText);
                        console.log('[CompetitorAnalysis] analysis response data:', analysisResult);
                    } catch (e) {
                        console.error('[CompetitorAnalysis] analysis response is not JSON:', e);
                        console.error('[CompetitorAnalysis] raw response text:', analysisText);
                        throw new Error('Response is not JSON: ' + analysisText.substring(0, 200));
                    }

                    if (analysisResponse.ok && analysisResult.status === 'ok') {
                        console.log('[CompetitorAnalysis] analysis success, redirecting to step2');
                        // 分析結果をセッションに保存してSTEP2へ遷移
                        // セッションに保存するためにフォーム送信で遷移
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route("meo.competitor-analysis.step2") }}';
                        
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;
                        form.appendChild(csrfInput);

                        const analysisInput = document.createElement('input');
                        analysisInput.type = 'hidden';
                        analysisInput.name = 'analysis';
                        analysisInput.value = JSON.stringify(analysisResult.analysis);
                        form.appendChild(analysisInput);

                        const keywordInput = document.createElement('input');
                        keywordInput.type = 'hidden';
                        keywordInput.name = 'keyword';
                        keywordInput.value = keyword;
                        form.appendChild(keywordInput);

                        document.body.appendChild(form);
                        form.submit();
                    } else {
                        console.error('[CompetitorAnalysis] analysis failed:', analysisResult);
                        showMessage(analysisResult.message || '分析の実行中にエラーが発生しました。', 'error');
                        compareBtn.disabled = false;
                        compareBtn.textContent = '比較する';
                    }
                } catch (error) {
                    console.error('[CompetitorAnalysis] network error', error);
                    
                    if (error.response) {
                        console.error('[CompetitorAnalysis] response status:', error.response.status);
                        console.error('[CompetitorAnalysis] response data:', error.response.data);
                    }
                    
                    if (error.message) {
                        console.error('[CompetitorAnalysis] error message:', error.message);
                    }
                    
                    console.error('[CompetitorAnalysis] error stack:', error.stack);
                    
                    showMessage('通信エラーが発生しました。', 'error');
                    compareBtn.disabled = false;
                    compareBtn.textContent = '比較する';
                }
            });

            function collectShopData(role) {
                const prefix = `shops[${role}]`;
                const data = {
                    shop_name: document.querySelector(`[name="${prefix}[shop_name]"]`)?.value || '',
                    opening_date: getRadioValue(`${prefix}[opening_date]`),
                    address: getRadioValue(`${prefix}[address]`),
                    phone: getRadioValue(`${prefix}[phone]`),
                    website: getRadioValue(`${prefix}[website]`),
                    sns_links: getRadioValue(`${prefix}[sns_links]`),
                    website_nap_match: getRadioValue(`${prefix}[website_nap_match]`),
                    sns_nap_match: getRadioValue(`${prefix}[sns_nap_match]`),
                    service_area: document.querySelector(`[name="${prefix}[service_area]"]`)?.value || '',
                    business_owner_info: getRadioValue(`${prefix}[business_owner_info]`),
                    main_category: document.querySelector(`[name="${prefix}[main_category]"]`)?.value || '',
                    sub_category: document.querySelector(`[name="${prefix}[sub_category]"]`)?.value || '',
                    business_description: document.querySelector(`[name="${prefix}[business_description]"]`)?.value || '',
                    review_count: parseInt(document.querySelector(`[name="${prefix}[review_count]"]`)?.value || '0'),
                    review_rating: document.querySelector(`[name="${prefix}[review_rating]"]`)?.value || '',
                    monthly_review_count: document.querySelector(`[name="${prefix}[monthly_review_count]"]`)?.value ? parseInt(document.querySelector(`[name="${prefix}[monthly_review_count]"]`)?.value) : null,
                    monthly_post_count: document.querySelector(`[name="${prefix}[monthly_post_count]"]`)?.value ? parseInt(document.querySelector(`[name="${prefix}[monthly_post_count]"]`)?.value) : null,
                    photo_count: parseInt(document.querySelector(`[name="${prefix}[photo_count]"]`)?.value || '0'),
                    photo_atmosphere: getRadioValue(`${prefix}[photo_atmosphere]`),
                    has_video: getRadioValue(`${prefix}[has_video]`),
                    video_count: parseInt(document.querySelector(`[name="${prefix}[video_count]"]`)?.value || '0'),
                    review_story_type: getRadioValue(`${prefix}[review_story_type]`),
                    has_menu: getRadioValue(`${prefix}[has_menu]`),
                    menu_genre: getRadioValue(`${prefix}[menu_genre]`),
                    menu_photo: getRadioValue(`${prefix}[menu_photo]`),
                    menu_description: document.querySelector(`[name="${prefix}[menu_description]"]`)?.value || '',
                    price_display: getRadioValue(`${prefix}[price_display]`),
                    reservation_link: getRadioValue(`${prefix}[reservation_link]`),
                    service_link: getRadioValue(`${prefix}[service_link]`),
                    business_hours: getRadioValue(`${prefix}[business_hours]`),
                    last_order: getRadioValue(`${prefix}[last_order]`),
                    entry_period: getRadioValue(`${prefix}[entry_period]`),
                    qa: getRadioValue(`${prefix}[qa]`),
                    barrier_free: getRadioValue(`${prefix}[barrier_free]`),
                    plan: getRadioValue(`${prefix}[plan]`),
                    pet: getRadioValue(`${prefix}[pet]`),
                    child: getRadioValue(`${prefix}[child]`),
                    customer_segment: getRadioValue(`${prefix}[customer_segment]`),
                    payment_method: getRadioValue(`${prefix}[payment_method]`),
                    feature: getRadioValue(`${prefix}[feature]`),
                    meal: getRadioValue(`${prefix}[meal]`),
                    parking: getRadioValue(`${prefix}[parking]`),
                };
                
                // 自社のみown_rankを追加
                if (role === 'own') {
                    const ownRankInput = document.querySelector(`[name="${prefix}[own_rank]"]`);
                    if (ownRankInput) {
                        data.own_rank = parseInt(ownRankInput.value) || null;
                    }
                }
                
                return data;
            }

            function getRadioValue(name) {
                const radio = document.querySelector(`input[name="${name}"]:checked`);
                return radio ? radio.value : '';
            }

            function showMessage(message, type) {
                messageArea.className = `mt-6 p-4 rounded-md ${
                    type === 'success' ? 'bg-green-100 text-green-700 border border-green-400' : 'bg-red-100 text-red-700 border border-red-400'
                }`;
                messageArea.textContent = message;
                messageArea.classList.remove('hidden');
                
                if (type === 'success') {
                    setTimeout(() => {
                        messageArea.classList.add('hidden');
                    }, 5000);
                }
            }
        });
    </script>
</x-app-layout>

