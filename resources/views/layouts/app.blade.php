<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>MEOのばすくん</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
        <link rel="shortcut icon" type="image/png" href="{{ asset('images/logo.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- 契約終了日アラート -->
            @auth
                @if(isset($expiringShops) && $expiringShops->count() > 0)
                    <div class="bg-orange-50 border-l-4 border-orange-400 p-4">
                        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3 flex-1">
                                    <p class="text-sm text-red-600">
                                        <span class="font-semibold">注意：</span>
                                        契約終了日まで1か月以内の店舗が
                                        <span class="font-bold">{{ $expiringShops->count() }}件</span>
                                        あります。
                                        @foreach($expiringShops->take(3) as $shop)
                                            <a href="{{ route('shops.show', $shop->id) }}" class="underline hover:text-red-800">
                                                {{ $shop->name }}
                                            </a>
                                            @if(!$loop->last)、@endif
                                        @endforeach
                                        @if($expiringShops->count() > 3)
                                            他{{ $expiringShops->count() - 3 }}件
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endauth

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        <!-- 419エラー時の自動リダイレクト処理（全ページに適用） -->
        <script>
        (function(){
            const _fetch = window.fetch;
            window.fetch = function(){
                return _fetch.apply(this, arguments).then(function(response){
                    if (response.status === 419) {
                        alert("セッションが切れました。再ログインしてください。");
                        window.location.href = "/login";
                        return new Promise(()=>{});
                    }
                    return response;
                });
            };
        })();
        </script>
    </body>
</html>

