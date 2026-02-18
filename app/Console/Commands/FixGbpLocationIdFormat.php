<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Command;

class FixGbpLocationIdFormat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gbp:fix-location-id-format';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '既存のgbp_location_idを数値のみから"locations/{id}"形式に変換します';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('gbp_location_idの形式を修正します...');

        $shops = Shop::whereNotNull('gbp_location_id')
            ->where('gbp_location_id', 'not like', 'locations/%')
            ->get();

        if ($shops->isEmpty()) {
            $this->info('修正対象の店舗はありません。');
            return Command::SUCCESS;
        }

        $this->info("修正対象店舗数: {$shops->count()}件");

        $fixedCount = 0;
        foreach ($shops as $shop) {
            $oldLocationId = $shop->gbp_location_id;
            $newLocationId = "locations/{$oldLocationId}";

            $shop->update([
                'gbp_location_id' => $newLocationId,
            ]);

            $this->line("店舗ID {$shop->id} ({$shop->name}): {$oldLocationId} → {$newLocationId}");
            $fixedCount++;
        }

        $this->info("修正完了: {$fixedCount}件の店舗を修正しました。");

        return Command::SUCCESS;
    }
}






















