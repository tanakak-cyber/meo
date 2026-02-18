<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shop;
use Carbon\Carbon;

class FixContractEndDateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // カウンジャー小岩店の契約終了日を更新
        $shop = Shop::where('name', 'カウンジャー小岩店')->first();
        
        if ($shop) {
            $shop->update([
                'contract_end_date' => Carbon::parse('2027-01-01'), // 契約終了日を2027年に延長
            ]);
            $this->command->info('カウンジャー小岩店の契約終了日を2027-01-01に更新しました。');
        } else {
            $this->command->warn('カウンジャー小岩店が見つかりませんでした。');
        }
    }
}






















