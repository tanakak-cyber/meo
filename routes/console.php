<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 毎月7日の午前9時に前月分のレポートを自動送信
Schedule::command('reports:send-monthly')
    ->monthlyOn(7, '09:00')
    ->timezone('Asia/Tokyo');

// ブログ自動クロール・投稿（毎分実行）
Schedule::command('blog:crawl')
    ->everyMinute()
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping();

// Instagram自動クロール・投稿（毎分実行）
Schedule::command('instagram:crawl')
    ->everyMinute()
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping();
