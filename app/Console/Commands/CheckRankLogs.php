<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\MeoKeyword;
use App\Models\MeoRankLog;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckRankLogs extends Command
{
    protected $signature = 'rank-logs:check {shop_name?}';
    protected $description = '順位ログデータの存在確認';

    public function handle()
    {
        $shopName = $this->argument('shop_name') ?? 'カウンジャー小岩店';
        
        $shop = Shop::where('name', $shopName)->first();
        
        if (!$shop) {
            $this->error("店舗「{$shopName}」が見つかりませんでした。");
            return;
        }

        $this->info("店舗名: {$shop->name}");
        $this->info("店舗ID: {$shop->id}");
        $this->info("");

        $keywords = $shop->meoKeywords;
        $this->info("MEOキーワード数: " . $keywords->count());
        $this->info("");

        foreach ($keywords as $keyword) {
            $this->line("キーワード: {$keyword->keyword} (ID: {$keyword->id})");
            
            $totalLogs = $keyword->rankLogs()->count();
            $this->line("  総ログ数: {$totalLogs}");
            
            if ($totalLogs > 0) {
                $firstLog = $keyword->rankLogs()->orderBy('checked_at')->first();
                $lastLog = $keyword->rankLogs()->orderBy('checked_at', 'desc')->first();
                
                $this->line("  最初のログ: " . $firstLog->checked_at->format('Y-m-d') . " (順位: " . ($firstLog->position ?? '圏外') . ")");
                $this->line("  最後のログ: " . $lastLog->checked_at->format('Y-m-d') . " (順位: " . ($lastLog->position ?? '圏外') . ")");
                
                // 2026年1月のデータを確認
                $jan2026Logs = $keyword->rankLogs()
                    ->whereDate('checked_at', '>=', '2026-01-01')
                    ->whereDate('checked_at', '<=', '2026-01-31')
                    ->count();
                $this->line("  2026年1月のログ数: {$jan2026Logs}");
            }
            $this->line("");
        }

        // 全ログのサマリー
        $allLogs = MeoRankLog::whereHas('meoKeyword', function ($q) use ($shop) {
            $q->where('shop_id', $shop->id);
        })->count();
        
        $this->info("全順位ログ数: {$allLogs}");
    }
}










