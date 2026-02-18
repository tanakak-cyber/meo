<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                店舗一覧
            </h2>
            <a href="{{ route('shops.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                新規登録
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <!-- 絞り込み -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-4">
                    <form method="GET" action="{{ route('shops.index') }}" class="space-y-4" id="filterForm">
                        <div class="flex flex-wrap items-center gap-4">
                            <label class="text-sm font-medium text-gray-700">期間:</label>
                            <input type="date" name="period_start" value="{{ request('period_start', now()->startOfMonth()->format('Y-m-d')) }}" 
                                   class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <span class="text-sm text-gray-700">～</span>
                            <input type="date" name="period_end" value="{{ request('period_end', now()->endOfMonth()->format('Y-m-d')) }}" 
                                   class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <button type="submit" class="px-4 py-2 bg-blue-500 hover:bg-blue-700 text-white font-medium rounded-md">
                                適用
                            </button>
                            <a href="{{ route('shops.export', request()->query()) }}" class="px-4 py-2 bg-green-500 hover:bg-green-700 text-white font-medium rounded-md">
                                CSVダウンロード
                            </a>
                        </div>
                        <div class="flex flex-wrap items-center gap-4">
                            <label class="text-sm font-medium text-gray-700">営業担当:</label>
                            <select name="sales_person_id" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">すべて</option>
                                @foreach($salesPersons ?? [] as $sp)
                                    <option value="{{ $sp->id }}" {{ ($salesPersonId ?? '') == $sp->id ? 'selected' : '' }}>{{ $sp->name }}</option>
                                @endforeach
                            </select>
                            
                            <label class="text-sm font-medium text-gray-700 ml-4">オペレーション担当:</label>
                            <select name="operation_person_id" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">すべて</option>
                                @foreach($operationPersons ?? [] as $op)
                                    <option value="{{ $op->id }}" {{ ($operationPersonId ?? '') == $op->id ? 'selected' : '' }}>{{ $op->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">店舗名</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">プラン</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">担当営業</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">オペレーション担当</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">店舗担当者名</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">金額</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">契約形態</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">紹介フィー</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">契約開始日</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">契約終了日</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GBP連携</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($shops as $shop)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ route('shops.show', $shop->id) }}" class="text-sm font-medium text-[#00afcc] hover:text-[#0088a3] hover:underline">
                                                {{ $shop->name }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">
                                                @if($shop->plan_id && $shop->plan && is_object($shop->plan))
                                                    {{ $shop->plan->name }}
                                                @elseif($shop->plan && !is_object($shop->plan))
                                                    {{ $shop->plan }}
                                                @else
                                                    —
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">
                                                @if($shop->salesPerson && is_object($shop->salesPerson))
                                                    {{ $shop->salesPerson->name }}
                                                @else
                                                    —
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">
                                                @if($shop->operationPerson && is_object($shop->operationPerson))
                                                    {{ $shop->operationPerson->name }}
                                                @else
                                                    —
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">{{ $shop->shop_contact_name ?? '—' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">
                                                @if($shop->price)
                                                    ¥{{ number_format($shop->price, 0) }}
                                                @else
                                                    —
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">
                                                @if($shop->contract_type === 'referral')
                                                    紹介契約
                                                @else
                                                    自社契約
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">
                                                @if($shop->contract_type === 'referral' && $shop->referral_fee)
                                                    ¥{{ number_format($shop->referral_fee, 0) }}
                                                @else
                                                    —
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">
                                                @if($shop->contract_date)
                                                    {{ $shop->contract_date->format('Y/m/d') }}
                                                @else
                                                    —
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">
                                                @if($shop->contract_end_date)
                                                    {{ $shop->contract_end_date->format('Y/m/d') }}
                                                @else
                                                    —
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($shop->gbp_location_id)
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                    連携済み
                                                </span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                    未連携
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('shops.show', $shop->id) }}" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-[#00afcc] hover:bg-[#0088a3] focus:outline-none transition ease-in-out duration-150">
                                                詳細を見る
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="px-6 py-4 text-center text-gray-500">
                                            店舗が登録されていません
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($shops->count() > 0)
                            <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-right font-semibold text-gray-900">
                                        合計
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">
                                            @php
                                                $totalPrice = $shops->items() ? collect($shops->items())->sum('price') : 0;
                                            @endphp
                                            @if($totalPrice > 0)
                                                ¥{{ number_format($totalPrice, 0) }}
                                            @else
                                                —
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500">
                                            —
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">
                                            @php
                                                $totalReferralFee = $shops->items() ? collect($shops->items())->where('contract_type', 'referral')->sum('referral_fee') : 0;
                                            @endphp
                                            @if($totalReferralFee > 0)
                                                ¥{{ number_format($totalReferralFee, 0) }}
                                            @else
                                                —
                                            @endif
                                        </div>
                                    </td>
                                    <td colspan="4" class="px-6 py-4"></td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>

                        @if($shops->hasPages())
                            <div class="mt-4">
                                {{ $shops->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
