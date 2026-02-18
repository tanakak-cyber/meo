<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckShopContract extends Command
{
    protected $signature = 'shop:check-contract {name?}';
    protected $description = '店舗の契約終了日を確認';

    public function handle()
    {
        $name = $this->argument('name');
        
        if ($name) {
            $shop = Shop::where('name', 'like', "%{$name}%")->first();
            if (!$shop) {
                $this->error("店舗「{$name}」が見つかりませんでした。");
                return;
            }
            $shops = collect([$shop]);
        } else {
            $shops = Shop::all();
        }

        $today = Carbon::today();
        
        $this->info("今日の日付: {$today->format('Y-m-d')}");
        $this->info("");

        foreach ($shops as $shop) {
            $isActive = $shop->isContractActive();
            $status = $isActive ? '✓ 契約中' : '✗ 契約終了';
            
            $this->line("店舗名: {$shop->name}");
            $this->line("契約開始日: " . ($shop->contract_date ? $shop->contract_date->format('Y-m-d') : '未設定'));
            $this->line("契約終了日: " . ($shop->contract_end_date ? $shop->contract_end_date->format('Y-m-d') : '未設定'));
            $this->line("ステータス: {$status}");
            $this->line("---");
        }
    }
}






















