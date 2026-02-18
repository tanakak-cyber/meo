<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('レポートメール文面設定') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form action="{{ route('report-email-settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-6">
                            <label for="admin_email" class="block text-sm font-medium text-gray-700 mb-2">
                                管理者メールアドレス <span class="text-red-500">*</span>
                            </label>
                            <input type="email" name="admin_email" id="admin_email"
                                   value="{{ old('admin_email', $setting->admin_email ?: auth()->user()->email) }}"
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('admin_email') border-red-500 @enderror"
                                   placeholder="admin@example.com">
                            @error('admin_email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-sm text-gray-500">レポートのコピーが送信されます</p>
                        </div>

                        <div class="mb-6">
                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                                メール件名 <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="subject" id="subject"
                                   value="{{ old('subject', $setting->subject) }}"
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('subject') border-red-500 @enderror"
                                   placeholder="【@{{shop_name}}】月次レポート">
                            @error('subject')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-sm text-gray-500">使用可能な変数: <code class="bg-gray-100 px-1 rounded">@{{shop_name}}</code></p>
                        </div>

                        <div class="mb-6">
                            <label for="body" class="block text-sm font-medium text-gray-700 mb-2">
                                メール本文 <span class="text-red-500">*</span>
                            </label>
                            <textarea name="body" id="body" rows="10"
                                      required
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('body') border-red-500 @enderror"
                                      placeholder="@{{shop_name}}様&#10;&#10;前月の月次レポートをお送りいたします。&#10;&#10;ご確認のほどよろしくお願いいたします。">{{ old('body', $setting->body) }}</textarea>
                            @error('body')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-sm text-gray-500">使用可能な変数: <code class="bg-gray-100 px-1 rounded">@{{shop_name}}</code></p>
                        </div>

                        <div class="flex items-center justify-end space-x-4 pt-4 border-t">
                            <a href="{{ route('dashboard') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                キャンセル
                            </a>
                            <button type="submit" class="px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                保存
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

