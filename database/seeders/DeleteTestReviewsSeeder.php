<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Review;

class DeleteTestReviewsSeeder extends Seeder
{
    /**
     * テストデータの口コミを削除
     * gbp_review_id が 'test_review_' で始まる口コミを削除
     */
    public function run(): void
    {
        $this->command->info('テストデータの口コミを削除します...');
        
        // gbp_review_id が 'test_review_' で始まる口コミを削除
        $deletedCount = Review::where('gbp_review_id', 'like', 'test_review_%')->delete();
        
        $this->command->info("テストデータの口コミ {$deletedCount}件を削除しました。");
        
        // 残っている口コミ数を確認
        $remainingCount = Review::count();
        $this->command->info("残っている口コミ数: {$remainingCount}件");
    }
}






















