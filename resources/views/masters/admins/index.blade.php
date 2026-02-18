<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('管理者マスタ') }}
            </h2>
            <a href="{{ route('masters.admins.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                新規登録
            </a>
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
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">名前</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">メールアドレス</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">パスワード</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">権限</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($admins as $admin)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $admin->id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $admin->name }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">{{ $admin->email }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500 font-mono">{{ $admin->password_plain ?? '—' }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-500">
                                                @php
                                                    $permissionLabels = [
                                                        'dashboard' => 'ダッシュボード',
                                                        'shops.index' => '顧客一覧',
                                                        'shops.schedule' => 'スケジュール',
                                                        'reviews.index' => '口コミ管理',
                                                        'reports.index' => '月次レポート',
                                                        'gbp-insights.import' => 'CSVインポート',
                                                        'report-email-settings.index' => 'メール設定',
                                                        'masters.index' => 'マスタ管理',
                                                        'competitor-analysis' => '競合分析',
                                                    ];
                                                    $permissions = $admin->permissions ?? [];
                                                @endphp
                                                @if(empty($permissions))
                                                    <span class="text-gray-400">権限なし</span>
                                                @else
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach($permissions as $permission)
                                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                                {{ $permissionLabels[$permission] ?? $permission }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('masters.admins.edit', $admin) }}" class="text-indigo-600 hover:text-indigo-900 mr-4">編集</a>
                                            @if($admin->id !== auth()->id())
                                                <form action="{{ route('masters.admins.destroy', $admin) }}" method="POST" class="inline" onsubmit="return confirm('本当に削除しますか？');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900">削除</button>
                                                </form>
                                            @else
                                                <span class="text-gray-400">（自分自身）</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            管理者が登録されていません
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <a href="{{ route('masters.index') }}" class="text-gray-600 hover:text-gray-900">← マスタ管理トップに戻る</a>
            </div>
        </div>
    </div>
</x-app-layout>

