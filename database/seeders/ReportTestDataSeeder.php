<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shop;
use App\Models\MeoKeyword;
use App\Models\MeoRankLog;
use App\Models\Review;
use Carbon\Carbon;

class ReportTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // カウンジャー小岩店を作成または取得
        $shop = Shop::firstOrCreate(
            ['name' => 'カウンジャー小岩店'],
            [
                'plan' => '初年度プラン',
                'price' => 50000,
                'contract_date' => Carbon::parse('2024-01-01'),
                'contract_end_date' => Carbon::parse('2027-01-01'), // 契約終了日を2027年に延長
                'blog_option' => true,
                'review_monthly_target' => 20,
                'photo_monthly_target' => 10,
            ]
        );
        
        // 既存の店舗の場合、契約終了日を更新（テストデータが表示されるように）
        if ($shop->wasRecentlyCreated === false) {
            $shop->update([
                'contract_end_date' => Carbon::parse('2027-01-01'), // 契約終了日を2027年に延長
            ]);
        }

        // MEOキーワードを設定（最大10件）
        $keywords = [
            'カウンジャー 小岩',
            'カウンジャー 江戸川区',
            'カウンジャー 小岩駅',
            'カウンジャー 小岩 営業時間',
            'カウンジャー 小岩 メニュー',
            'カウンジャー 小岩 予約',
            'カウンジャー 小岩 口コミ',
            'カウンジャー 小岩 電話',
            'カウンジャー 小岩 アクセス',
            'カウンジャー 小岩 駐車場',
        ];

        // 契約終了日を更新（テストデータが表示されるように）
        $shop->update([
            'contract_end_date' => Carbon::parse('2027-01-01'),
        ]);

        // キーワードを作成（既存のキーワードは削除しない、重複チェック）
        $meoKeywords = [];
        foreach ($keywords as $keywordText) {
            // 既存のキーワードと重複していない場合のみ作成
            $existingKeyword = $shop->meoKeywords()
                ->where('keyword', $keywordText)
                ->first();
            
            if ($existingKeyword) {
                // 既存のキーワードを使用
                $meoKeywords[] = $existingKeyword;
            } else {
                // 新しいキーワードを作成
                $meoKeywords[] = MeoKeyword::create([
                    'shop_id' => $shop->id,
                    'keyword' => $keywordText,
                ]);
            }
        }

        // 1月分の順位ログデータを生成（2026年1月）
        $startDate = Carbon::parse('2026-01-01');
        $endDate = Carbon::parse('2026-01-31');

        foreach ($meoKeywords as $index => $keyword) {
            $currentDate = $startDate->copy();
            
            while ($currentDate <= $endDate) {
                // キーワードごとに異なる順位パターンを生成
                $baseRank = 10 + ($index * 5); // キーワードごとにベース順位を変える
                
                // 日付に応じて順位を変動させる（週ごとにパターン）
                $dayOfWeek = $currentDate->dayOfWeek;
                $weekOfMonth = ceil($currentDate->day / 7);
                
                // 順位の変動（1-100の範囲、時々圏外）
                $rank = null;
                if (rand(1, 10) > 1) { // 90%の確率で順位あり
                    $variation = rand(-5, 5);
                    $rank = max(1, min(100, $baseRank + $variation + ($weekOfMonth * 2)));
                }

                MeoRankLog::updateOrCreate(
                    [
                        'meo_keyword_id' => $keyword->id,
                        'checked_at' => $currentDate->format('Y-m-d'),
                    ],
                    [
                        'rank' => $rank,
                    ]
                );

                $currentDate->addDay();
            }
        }

        // 1月分の口コミデータを生成
        $reviewTemplates = [
            [
                'author_name' => '田中太郎',
                'rating' => 5,
                'comment' => 'とても美味しかったです！また来ます。',
            ],
            [
                'author_name' => '佐藤花子',
                'rating' => 4,
                'comment' => '雰囲気が良くて、料理も美味しいです。',
            ],
            [
                'author_name' => '鈴木一郎',
                'rating' => 5,
                'comment' => 'スタッフの対応が素晴らしかったです。',
            ],
            [
                'author_name' => '山田次郎',
                'rating' => 3,
                'comment' => 'まあまあでした。',
            ],
            [
                'author_name' => '高橋三郎',
                'rating' => 5,
                'comment' => '最高でした！',
            ],
            [
                'author_name' => '伊藤四郎',
                'rating' => 4,
                'comment' => '良いお店です。',
            ],
            [
                'author_name' => '渡辺五郎',
                'rating' => 5,
                'comment' => 'また来たいです！',
            ],
            [
                'author_name' => '中村六郎',
                'rating' => 4,
                'comment' => '満足しました。',
            ],
        ];

        // 1月の各日に口コミを生成
        $currentDate = $startDate->copy();
        $reviewIdCounter = 1;

        while ($currentDate <= $endDate) {
            // 1日に0-3件の口コミをランダムに生成
            $reviewCount = rand(0, 3);
            
            for ($i = 0; $i < $reviewCount; $i++) {
                $template = $reviewTemplates[array_rand($reviewTemplates)];
                $createTime = $currentDate->copy()
                    ->setTime(rand(10, 22), rand(0, 59), rand(0, 59));

                // 返信率を30%に設定
                $hasReply = rand(1, 10) <= 3;
                $replyText = $hasReply ? 'ご来店ありがとうございます。またのお越しをお待ちしております。' : null;
                $repliedAt = $hasReply ? $createTime->copy()->addHours(rand(1, 24)) : null;

                Review::create([
                    'shop_id' => $shop->id,
                    'gbp_review_id' => 'test_review_' . $shop->id . '_' . $reviewIdCounter,
                    'author_name' => $template['author_name'] . ' ' . $currentDate->format('m/d'),
                    'rating' => $template['rating'],
                    'comment' => $template['comment'],
                    'create_time' => $createTime,
                    'reply_text' => $replyText,
                    'replied_at' => $repliedAt,
                ]);
                
                $reviewIdCounter++;
            }

            $currentDate->addDay();
        }

        $this->command->info('カウンジャー小岩店の1月分テストデータを作成しました！');
        $this->command->info('店舗ID: ' . $shop->id);
        $this->command->info('キーワード数: ' . count($meoKeywords));
        $this->command->info('順位ログ数: ' . MeoRankLog::whereIn('meo_keyword_id', collect($meoKeywords)->pluck('id'))->count());
        $this->command->info('口コミ数: ' . Review::where('shop_id', $shop->id)->count());
    }
}

