<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Shop;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // タイムゾーンを日本時間に設定
        $timezone = config('app.timezone', 'Asia/Tokyo');
        date_default_timezone_set($timezone);
        Carbon::setLocale('ja');

        // 契約終了日が1か月以内の店舗をすべてのビューで共有
        View::composer('*', function ($view) {
            if (auth()->check()) {
                $oneMonthLater = Carbon::now()->addMonth();
                $expiringShops = Shop::whereNotNull('contract_end_date')
                    ->where('contract_end_date', '<=', $oneMonthLater)
                    ->where('contract_end_date', '>=', Carbon::now())
                    ->orderBy('contract_end_date', 'asc')
                    ->get();
                
                $view->with('expiringShops', $expiringShops);
            }
        });
    }
}
