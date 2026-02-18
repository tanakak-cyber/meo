<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('オペレーション担当編集') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form action="{{ route('masters.operation-persons.update', $operationPerson) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                名前 <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" id="name" value="{{ old('name', $operationPerson->name) }}" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('name') border-red-500 @enderror">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                メールアドレス
                            </label>
                            <input type="email" name="email" id="email" value="{{ old('email', $operationPerson->email) }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('email') border-red-500 @enderror">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                電話番号
                            </label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone', $operationPerson->phone) }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('phone') border-red-500 @enderror">
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                ログインパスワード
                            </label>
                            @if($operationPerson->hasPassword())
                                <div class="mb-2 p-2 bg-green-50 border border-green-200 rounded-md">
                                    <p class="text-sm text-green-700">
                                        <strong>✓ パスワードが設定されています</strong>
                                    </p>
                                </div>
                            @else
                                <div class="mb-2 p-2 bg-yellow-50 border border-yellow-200 rounded-md">
                                    <p class="text-sm text-yellow-700">
                                        <strong>⚠ パスワードが設定されていません</strong>
                                    </p>
                                </div>
                            @endif
                            <input type="text" name="password" id="password"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('password') border-red-500 @enderror"
                                placeholder="新しいパスワードを入力してください（変更する場合のみ）"
                                value="{{ old('password', $operationPerson->password_plain) }}">
                            <p class="mt-1 text-sm text-gray-500">
                                パスワードを変更する場合のみ入力してください。空欄の場合は変更されません。<br>
                                <span class="text-red-500">※ パスワードは平文で表示・保存されます（管理者のみアクセス可能な画面のため）</span>
                            </p>
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="display_order" class="block text-sm font-medium text-gray-700 mb-2">
                                表示順
                            </label>
                            <input type="number" name="display_order" id="display_order" value="{{ old('display_order', $operationPerson->display_order) }}" min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 @error('display_order') border-red-500 @enderror">
                            @error('display_order')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $operationPerson->is_active) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">有効</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end space-x-4">
                            <a href="{{ route('masters.operation-persons.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
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

