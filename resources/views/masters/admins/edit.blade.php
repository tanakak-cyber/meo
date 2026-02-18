<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('管理者編集') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form action="{{ route('masters.admins.update', $admin) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                名前 <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" id="name" value="{{ old('name', $admin->name) }}" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('name') border-red-500 @enderror">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                メールアドレス <span class="text-red-500">*</span>
                            </label>
                            <input type="email" name="email" id="email" value="{{ old('email', $admin->email) }}" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('email') border-red-500 @enderror">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                ログインパスワード
                            </label>
                            <input type="text" name="password" id="password"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('password') border-red-500 @enderror"
                                placeholder="新しいパスワードを入力してください（変更する場合のみ）"
                                value="{{ old('password', $admin->password_plain) }}">
                            <p class="mt-1 text-sm text-gray-500">
                                パスワードを変更する場合のみ入力してください。空欄の場合は変更されません。<br>
                                <span class="text-red-500">※ パスワードは平文で表示・保存されます（管理者のみアクセス可能な画面のため）</span>
                            </p>
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                権限設定
                            </label>
                            <p class="mb-2 text-sm text-gray-500">表示する機能を選択してください</p>
                            <div class="space-y-2">
                                @php
                                    $currentPermissions = old('permissions', $admin->permissions ?? []);
                                @endphp
                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="dashboard" {{ in_array('dashboard', $currentPermissions) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-gray-700">ダッシュボード</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="shops.index" id="permission_shops_index" {{ in_array('shops.index', $currentPermissions) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-gray-700">顧客一覧</span>
                                </label>
                                <div id="customer_scope_section" class="ml-8 mb-2 p-3 bg-gray-50 rounded-md" style="display: {{ in_array('shops.index', $currentPermissions) ? 'block' : 'none' }};">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">顧客閲覧範囲</label>
                                    <div class="space-y-2">
                                        <label class="flex items-center">
                                            <input type="radio" name="customer_scope" value="own" {{ old('customer_scope', $admin->customer_scope ?? 'own') === 'own' ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-gray-700">自分の顧客のみ</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="customer_scope" value="all" {{ old('customer_scope', $admin->customer_scope ?? 'own') === 'all' ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-gray-700">全顧客</span>
                                        </label>
                                    </div>
                                </div>
                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="shops.schedule" {{ in_array('shops.schedule', $currentPermissions) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-gray-700">スケジュール</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="reviews.index" {{ in_array('reviews.index', $currentPermissions) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-gray-700">口コミ管理</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="reports.index" {{ in_array('reports.index', $currentPermissions) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-gray-700">月次レポート</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="gbp-insights.import" {{ in_array('gbp-insights.import', $currentPermissions) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-gray-700">CSVインポート</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="report-email-settings.index" {{ in_array('report-email-settings.index', $currentPermissions) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-gray-700">メール設定</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="masters.index" {{ in_array('masters.index', $currentPermissions) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-gray-700">マスタ管理</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" value="competitor-analysis" {{ in_array('competitor-analysis', $currentPermissions) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-gray-700">競合分析</span>
                                </label>
                            </div>
                            @error('permissions')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <script>
                            // 顧客一覧のチェックボックスの状態に応じて、顧客閲覧範囲の表示を切り替え
                            document.addEventListener('DOMContentLoaded', function() {
                                const shopsIndexCheckbox = document.getElementById('permission_shops_index');
                                const customerScopeSection = document.getElementById('customer_scope_section');
                                
                                if (shopsIndexCheckbox && customerScopeSection) {
                                    shopsIndexCheckbox.addEventListener('change', function() {
                                        customerScopeSection.style.display = this.checked ? 'block' : 'none';
                                    });
                                }
                            });
                        </script>

                        <div class="flex items-center justify-end space-x-4">
                            <a href="{{ route('masters.admins.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                キャンセル
                            </a>
                            <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                更新
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

