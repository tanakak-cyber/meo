<nav x-data="{ open: false }" class="bg-meo-teal border-b border-meo-teal-700" style="background-color: #00afcc !important;">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between" style="min-height: 7rem; height: auto;">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    @if(session('operator_id'))
                        <a href="{{ route('operator.dashboard') }}">
                            <x-application-logo class="block h-9 w-auto fill-current text-white" />
                        </a>
                    @else
                        <a href="{{ route('dashboard') }}">
                            <x-application-logo class="block h-9 w-auto fill-current text-white" />
                        </a>
                    @endif
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    @if(session('operator_id'))
                        {{-- オペレーター用ナビゲーション --}}
                        <x-nav-link :href="route('operator.dashboard')" :active="request()->routeIs('operator.dashboard')">
                            ダッシュボード
                        </x-nav-link>
                        <x-nav-link :href="route('operator.schedule', ['year' => now()->year, 'month' => now()->month])" :active="request()->routeIs('operator.schedule') || request()->routeIs('operator.schedule.sync')">
                            スケジュール
                        </x-nav-link>
                        <x-nav-link :href="route('operator.reviews.index')" :active="request()->routeIs('operator.reviews.*')">
                            口コミ管理
                        </x-nav-link>
                        <x-nav-link :href="route('operator.reports.index')" :active="request()->routeIs('operator.reports.*')">
                            月次レポート
                        </x-nav-link>
                    @else
                        {{-- 管理者用ナビゲーション（権限に基づいて表示） --}}
                        @php
                            $permissions = session('admin_permissions', []);
                            // 権限が空の場合は何も表示しない（権限が設定されていない場合はアクセス不可）
                        @endphp
                        @if(!empty($permissions) && in_array('dashboard', $permissions))
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            ダッシュボード
                        </x-nav-link>
                        @endif
                        @if(!empty($permissions) && in_array('shops.index', $permissions))
                        <x-nav-link :href="route('shops.index')" :active="request()->routeIs('shops.index') || request()->routeIs('shops.show') || request()->routeIs('shops.create') || request()->routeIs('shops.edit')">
                            顧客一覧
                        </x-nav-link>
                        @endif
                        @if(!empty($permissions) && in_array('shops.schedule', $permissions))
                        <x-nav-link :href="route('shops.schedule', ['year' => now()->year, 'month' => now()->month])" :active="request()->routeIs('shops.schedule') || request()->routeIs('shops.schedule.sync')">
                            スケジュール
                        </x-nav-link>
                        @endif
                        @if(!empty($permissions) && in_array('reviews.index', $permissions))
                        <x-nav-link :href="route('reviews.index')" :active="request()->routeIs('reviews.*')">
                            口コミ管理
                        </x-nav-link>
                        @endif
                        @if(!empty($permissions) && in_array('reports.index', $permissions))
                        <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                            月次レポート
                        </x-nav-link>
                        @endif
                        @if(!empty($permissions) && in_array('competitor-analysis', $permissions))
                        <x-nav-link :href="route('meo.competitor-analysis')" :active="request()->routeIs('meo.competitor-analysis')">
                            競合分析
                        </x-nav-link>
                        @endif
                        @if(!empty($permissions) && in_array('gbp-insights.import', $permissions))
                        <x-nav-link :href="route('gbp-insights.import')" :active="request()->routeIs('gbp-insights.*')">
                            CSVインポート
                        </x-nav-link>
                        @endif
                        @if(!empty($permissions) && in_array('report-email-settings.index', $permissions))
                        <x-nav-link :href="route('report-email-settings.index')" :active="request()->routeIs('report-email-settings.*')">
                            メール設定
                        </x-nav-link>
                        @endif
                        @if(!empty($permissions) && in_array('masters.index', $permissions))
                        <x-nav-link :href="route('masters.index')" :active="request()->routeIs('masters.*')">
                            マスタ管理
                        </x-nav-link>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white/90 bg-[#00afcc] hover:text-white hover:bg-[#0088a3] focus:outline-none transition ease-in-out duration-150">
                            <div>
                                @if(session('operator_id'))
                                    {{ session('operator_name', 'オペレーター') }}
                                @elseif(Auth::check())
                                    {{ Auth::user()->name }}
                                @else
                                    ゲスト
                                @endif
                            </div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        @if(session('operator_id'))
                            <!-- オペレーター用ログアウト -->
                            <form method="POST" action="{{ route('operator.logout') }}">
                                @csrf
                                <x-dropdown-link :href="'#'"
                                        onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        @elseif(Auth::check())
                            <!-- 管理者用メニュー -->
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="'#'"
                                        onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        @endif
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-white/80 hover:text-white hover:bg-white/20 focus:outline-none focus:bg-white/20 focus:text-white transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            @if(session('operator_id'))
                {{-- オペレーター用ナビゲーション（レスポンシブ） --}}
                <x-responsive-nav-link :href="route('operator.dashboard')" :active="request()->routeIs('operator.dashboard')">
                    ダッシュボード
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('operator.schedule', ['year' => now()->year, 'month' => now()->month])" :active="request()->routeIs('operator.schedule') || request()->routeIs('operator.schedule.sync')">
                    スケジュール
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('operator.reviews.index')" :active="request()->routeIs('operator.reviews.*')">
                    口コミ管理
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('operator.reports.index')" :active="request()->routeIs('operator.reports.*')">
                    月次レポート
                </x-responsive-nav-link>
            @else
                {{-- 管理者用ナビゲーション（レスポンシブ、権限に基づいて表示） --}}
                @php
                    $permissions = session('admin_permissions', []);
                    // 権限が空の場合は何も表示しない（権限が設定されていない場合はアクセス不可）
                @endphp
                @if(!empty($permissions) && in_array('dashboard', $permissions))
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    ダッシュボード
                </x-responsive-nav-link>
                @endif
                @if(!empty($permissions) && in_array('shops.index', $permissions))
                <x-responsive-nav-link :href="route('shops.index')" :active="request()->routeIs('shops.index') || request()->routeIs('shops.show') || request()->routeIs('shops.create') || request()->routeIs('shops.edit')">
                    顧客一覧
                </x-responsive-nav-link>
                @endif
                @if(!empty($permissions) && in_array('shops.schedule', $permissions))
                <x-responsive-nav-link :href="route('shops.schedule', ['year' => now()->year, 'month' => now()->month])" :active="request()->routeIs('shops.schedule') || request()->routeIs('shops.schedule.sync')">
                    スケジュール
                </x-responsive-nav-link>
                @endif
                @if(!empty($permissions) && in_array('reviews.index', $permissions))
                <x-responsive-nav-link :href="route('reviews.index')" :active="request()->routeIs('reviews.*')">
                    口コミ管理
                </x-responsive-nav-link>
                @endif
                @if(!empty($permissions) && in_array('reports.index', $permissions))
                <x-responsive-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                    月次レポート
                </x-responsive-nav-link>
                @endif
                @if(!empty($permissions) && in_array('competitor-analysis', $permissions))
                <x-responsive-nav-link :href="route('meo.competitor-analysis')" :active="request()->routeIs('meo.competitor-analysis')">
                    競合分析
                </x-responsive-nav-link>
                @endif
                @if(!empty($permissions) && in_array('gbp-insights.import', $permissions))
                <x-responsive-nav-link :href="route('gbp-insights.import')" :active="request()->routeIs('gbp-insights.*')">
                    CSVインポート
                </x-responsive-nav-link>
                @endif
                @if(!empty($permissions) && in_array('report-email-settings.index', $permissions))
                <x-responsive-nav-link :href="route('report-email-settings.index')" :active="request()->routeIs('report-email-settings.*')">
                    メール設定
                </x-responsive-nav-link>
                @endif
                @if(!empty($permissions) && in_array('masters.index', $permissions))
                <x-responsive-nav-link :href="route('masters.index')" :active="request()->routeIs('masters.*')">
                    マスタ管理
                </x-responsive-nav-link>
                @endif
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-white/20">
            <div class="px-4">
                @if(session('operator_id'))
                    @php
                        $operator = \App\Models\OperationPerson::find(session('operator_id'));
                    @endphp
                    <div class="font-medium text-base text-white">{{ session('operator_name', 'オペレーター') }}</div>
                    <div class="font-medium text-sm text-white/80">{{ $operator->email ?? '' }}</div>
                @elseif(Auth::check())
                    <div class="font-medium text-base text-white">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-white/80">{{ Auth::user()->email }}</div>
                @endif
            </div>

            <div class="mt-3 space-y-1">
                @if(session('operator_id'))
                    <!-- オペレーター用ログアウト -->
                    <form method="POST" action="{{ route('operator.logout') }}">
                        @csrf
                        <x-responsive-nav-link :href="'#'"
                                onclick="event.preventDefault();
                                            this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                @elseif(Auth::check())
                    <!-- 管理者用メニュー -->
                    <x-responsive-nav-link :href="route('profile.edit')">
                        {{ __('Profile') }}
                    </x-responsive-nav-link>

                    <!-- Authentication -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-responsive-nav-link :href="'#'"
                                onclick="event.preventDefault();
                                            this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                @endif
            </div>
        </div>
    </div>
</nav>
