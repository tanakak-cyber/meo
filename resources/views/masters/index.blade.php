<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('マスタ管理') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- プランマスタ -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">プランマスタ</h3>
                            <a href="{{ route('masters.plans.index') }}" class="text-sm text-[#00afcc] hover:text-[#0088a3] font-medium">
                                管理画面へ →
                            </a>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            店舗に設定するプランを管理します。
                        </p>
                        <a href="{{ route('masters.plans.create') }}" class="inline-flex items-center px-4 py-2 bg-[#00afcc] hover:bg-[#0088a3] text-white text-sm font-semibold rounded-lg transition-all duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            新規プラン登録
                        </a>
                    </div>
                </div>

                <!-- 担当営業マスタ -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">担当営業マスタ</h3>
                            <a href="{{ route('masters.sales-persons.index') }}" class="text-sm text-[#00afcc] hover:text-[#0088a3] font-medium">
                                管理画面へ →
                            </a>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            店舗の担当営業を管理します。
                        </p>
                        <a href="{{ route('masters.sales-persons.create') }}" class="inline-flex items-center px-4 py-2 bg-[#00afcc] hover:bg-[#0088a3] text-white text-sm font-semibold rounded-lg transition-all duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            新規担当営業登録
                        </a>
                    </div>
                </div>

                <!-- オペレーション担当マスタ -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">オペレーション担当マスタ</h3>
                            <a href="{{ route('masters.operation-persons.index') }}" class="text-sm text-[#00afcc] hover:text-[#0088a3] font-medium">
                                管理画面へ →
                            </a>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            店舗のオペレーション担当を管理します。
                        </p>
                        <a href="{{ route('masters.operation-persons.create') }}" class="inline-flex items-center px-4 py-2 bg-[#00afcc] hover:bg-[#0088a3] text-white text-sm font-semibold rounded-lg transition-all duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            新規オペレーション担当登録
                        </a>
                    </div>
                </div>

                <!-- 管理者マスタ -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">管理者マスタ</h3>
                            <a href="{{ route('masters.admins.index') }}" class="text-sm text-[#00afcc] hover:text-[#0088a3] font-medium">
                                管理画面へ →
                            </a>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            管理者アカウントと権限を管理します。
                        </p>
                        <a href="{{ route('masters.admins.create') }}" class="inline-flex items-center px-4 py-2 bg-[#00afcc] hover:bg-[#0088a3] text-white text-sm font-semibold rounded-lg transition-all duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            新規管理者登録
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

