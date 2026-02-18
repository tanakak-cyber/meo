<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Google Business Profile ロケーション選択') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="mb-4 text-gray-600">
                        {{ $shop->name }} に紐づけるGoogle Business Profileロケーションを選択してください。
                    </p>

                    <form action="{{ route('shops.store-gbp-location', $shop) }}" method="POST">
                        @csrf

                        <div class="space-y-4">
                            @foreach($locations as $location)
                                @php
                                    $locationId = $location['name'] ?? null; // "locations/123456789" の形式
                                    $locationIdClean = $locationId ? str_replace('locations/', '', $locationId) : '';
                                    $title = $location['title'] ?? null;
                                    $addressLines = $location['storefrontAddress']['addressLines'] ?? [];
                                    $address = !empty($addressLines) ? implode(' ', $addressLines) : null;
                                    $displayName = $title ?: '店舗名未設定';
                                @endphp

                                @if($locationId)
                                    <label class="flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                                        <input type="radio" 
                                               name="location_id" 
                                               value="{{ $locationId }}" 
                                               class="mt-1 mr-4"
                                               required>
                                        <div class="flex-1">
                                            <div class="font-semibold text-gray-900 mb-2 text-lg">
                                                {{ $displayName }}
                                            </div>
                                            @if($address)
                                                <div class="text-sm text-gray-600 mb-2">
                                                    📍 {{ $address }}
                                                </div>
                                            @endif
                                            <div class="text-xs text-gray-500">
                                                Location ID: {{ $locationIdClean }}
                                            </div>
                                        </div>
                                    </label>
                                @endif
                            @endforeach
                        </div>

                        @if(empty($locations))
                            <p class="text-gray-500">選択可能なロケーションがありません。</p>
                        @else
                            <div class="mt-6 flex items-center justify-end space-x-4">
                                <a href="{{ route('shops.show', $shop) }}" 
                                   class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                    キャンセル
                                </a>
                                <button type="submit" 
                                        class="px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    この店舗を連携する
                                </button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

