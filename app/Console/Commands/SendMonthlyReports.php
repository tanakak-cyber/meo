<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\ReportEmailSetting;
use App\Http\Controllers\ReportController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Mpdf\Mpdf;

class SendMonthlyReports extends Command
{
    protected $signature = 'reports:send-monthly';
    protected $description = '月初1日に前月分のレポートPDFをメール送信';

    public function handle()
    {
        $this->info('月次レポート送信処理を開始します...');

        // メール設定の確認
        $mailer = config('mail.default');
        $queueConnection = config('queue.default');
        $this->info("現在のメール設定: MAIL_MAILER={$mailer}, QUEUE_CONNECTION={$queueConnection}");
        
        if ($mailer === 'log') {
            $this->warn("⚠️ 警告: MAIL_MAILERが'log'に設定されています。メールは実際には送信されず、ログファイルに記録されます。");
        } elseif ($mailer === 'array') {
            $this->warn("⚠️ 警告: MAIL_MAILERが'array'に設定されています。メールは実際には送信されず、メモリに保存されます。");
        }
        
        if ($queueConnection !== 'sync') {
            $this->warn("⚠️ 警告: QUEUE_CONNECTIONが'sync'以外（{$queueConnection}）に設定されています。");
            $this->warn("   メールを送信するには 'php artisan queue:work' を実行する必要があります。");
        }

        // 前月の期間を取得
        // 例: 3月3日に実行 → 2月1日〜2月28日
        // 例: 4月3日に実行 → 3月1日〜3月31日
        $lastMonth = Carbon::now('Asia/Tokyo')->subMonth();
        $from = $lastMonth->copy()->startOfMonth()->format('Y-m-d');
        $to = $lastMonth->copy()->endOfMonth()->format('Y-m-d');

        $this->info("対象期間: {$from} ～ {$to}");
        Log::info("月次レポート送信: 対象期間を計算", [
            'current_date' => Carbon::now('Asia/Tokyo')->format('Y-m-d H:i:s'),
            'last_month' => $lastMonth->format('Y-m'),
            'from' => $from,
            'to' => $to,
        ]);

        // メール文面設定を取得
        $emailSetting = ReportEmailSetting::getSettings();

        // メールアドレスが設定されている契約中の店舗を取得
        $today = Carbon::today();
        $shops = Shop::where(function ($query) {
            $query->whereNotNull('report_email_1')
                  ->orWhereNotNull('report_email_2')
                  ->orWhereNotNull('report_email_3')
                  ->orWhereNotNull('report_email_4')
                  ->orWhereNotNull('report_email_5');
        })
        ->where(function ($query) use ($today) {
            // 契約終了日が設定されていない、または今日以降の店舗のみ
            $query->whereNull('contract_end_date')
                  ->orWhere('contract_end_date', '>=', $today);
        })
        ->get();

        $successCount = 0;
        $failCount = 0;

        foreach ($shops as $shop) {
            try {
                // 契約終了日を再確認（念のため）
                if (!$shop->isContractActive()) {
                    $this->info("スキップ: {$shop->name} (契約終了)");
                    continue;
                }

                // 送信先メールアドレスを取得
                $recipients = array_filter([
                    $shop->report_email_1,
                    $shop->report_email_2,
                    $shop->report_email_3,
                    $shop->report_email_4,
                    $shop->report_email_5,
                ]);

                if (empty($recipients)) {
                    continue;
                }

                // PDFを生成
                $pdfData = $this->generatePdf($shop, $from, $to);

                // メール件名と本文の変数を置換
                $subject = $emailSetting->replaceVariables($emailSetting->subject, [
                    'shop_name' => $shop->name,
                ]);
                $body = $emailSetting->replaceVariables($emailSetting->body, [
                    'shop_name' => $shop->name,
                ]);

                // 顧客へのメール送信
                $recipientSuccessCount = 0;
                $recipientFailCount = 0;
                
                foreach ($recipients as $recipient) {
                    try {
                        Log::info("Attempting to send email to: {$recipient} (店舗: {$shop->name})");
                        
                        Mail::raw($body, function ($message) use ($recipient, $subject, $shop, $from, $to, $pdfData) {
                            $message->to($recipient)
                                    ->subject($subject)
                                    ->attachData($pdfData, 'レポート_' . $shop->name . '_' . $from . '_' . $to . '.pdf', [
                                        'mime' => 'application/pdf',
                                    ]);
                        });
                        
                        Log::info("Email sent successfully to: {$recipient} (店舗: {$shop->name})");
                        $recipientSuccessCount++;
                        $this->info("  ✓ {$recipient} へ送信しました");
                    } catch (\Exception $e) {
                        $recipientFailCount++;
                        Log::error("Email send failed to: {$recipient} (店舗: {$shop->name})", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $this->error("  ✗ {$recipient} への送信に失敗: " . $e->getMessage());
                    }
                }

                // 管理者へのメール送信
                if ($emailSetting->admin_email) {
                    try {
                        Log::info("Attempting to send email to admin: {$emailSetting->admin_email} (店舗: {$shop->name})");
                        
                        Mail::raw($body . "\n\n【送信先】\n" . implode("\n", $recipients), function ($message) use ($emailSetting, $subject, $shop, $from, $to, $pdfData) {
                            $message->to($emailSetting->admin_email)
                                    ->subject('[管理者コピー] ' . $subject)
                                    ->attachData($pdfData, 'レポート_' . $shop->name . '_' . $from . '_' . $to . '.pdf', [
                                        'mime' => 'application/pdf',
                                    ]);
                        });
                        
                        Log::info("Email sent successfully to admin: {$emailSetting->admin_email} (店舗: {$shop->name})");
                        $this->info("  ✓ 管理者 ({$emailSetting->admin_email}) へ送信しました");
                    } catch (\Exception $e) {
                        Log::error("Email send failed to admin: {$emailSetting->admin_email} (店舗: {$shop->name})", [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $this->error("  ✗ 管理者 ({$emailSetting->admin_email}) への送信に失敗: " . $e->getMessage());
                    }
                }

                // 成功/失敗の判定
                if ($recipientFailCount === 0 && $recipientSuccessCount > 0) {
                    $successCount++;
                    $this->info("✓ {$shop->name} のレポートを送信しました ({$recipientSuccessCount}件成功)");
                } else {
                    $failCount++;
                    $this->error("✗ {$shop->name} のレポート送信に失敗しました (成功: {$recipientSuccessCount}件, 失敗: {$recipientFailCount}件)");
                }
            } catch (\Exception $e) {
                $failCount++;
                Log::error("レポート送信エラー (店舗ID: {$shop->id}, 店舗名: {$shop->name})", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error("✗ {$shop->name} のレポート送信に失敗: " . $e->getMessage());
            }
        }

        $this->info("処理完了: 成功 {$successCount}件, 失敗 {$failCount}件");
        
        if ($successCount === 0 && $failCount === 0) {
            $this->warn("送信対象の店舗がありませんでした。");
        }
        
        // メール設定の再確認
        if ($mailer === 'log') {
            $this->warn("⚠️ メールはログファイルに記録されています。実際のメール送信には MAIL_MAILER=smtp を設定してください。");
            $this->info("   ログファイルの場所: storage/logs/laravel.log");
        }
    }

    private function generatePdf($shop, $from, $to)
    {
        // ReportControllerのロジックを再利用
        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);

        // キーワードを取得
        $keywords = $shop->meoKeywords()
            ->with(['rankLogs' => function ($query) use ($fromDate, $toDate) {
                $query->whereBetween('checked_at', [$fromDate, $toDate])
                      ->orderBy('checked_at');
            }])
            ->get()
            ->map(function ($keyword) use ($fromDate, $toDate) {
                $ranks = $keyword->rankLogs()
                    ->whereBetween('checked_at', [$fromDate, $toDate])
                    ->whereNotNull('position')
                    ->pluck('position');
                $keyword->average_rank = $ranks->isEmpty() ? null : $ranks->average();
                return $keyword;
            })
            ->sortBy(function ($keyword) {
                return $keyword->average_rank ?? 999;
            })
            ->values();

        // 日付範囲の生成
        $dates = [];
        $currentDate = $fromDate->copy();
        while ($currentDate <= $toDate) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->addDay();
        }

        // キーワードごとの日別順位データ
        $rankData = [];
        foreach ($keywords as $keyword) {
            $keywordRanks = [];
            foreach ($dates as $date) {
                $log = $keyword->rankLogs->first(function ($log) use ($date) {
                    return $log->checked_at->format('Y-m-d') === $date;
                });
                $keywordRanks[$date] = $log ? $log->position : null;
            }
            $rankData[$keyword->id] = [
                'keyword' => $keyword->keyword,
                'ranks' => $keywordRanks,
            ];
        }

        // KPIサマリーの計算
        $currentPeriodStart = $fromDate->copy();
        $currentPeriodEnd = $toDate->copy();
        $prevPeriodStart = $currentPeriodStart->copy()->subMonth();
        $prevPeriodEnd = $currentPeriodEnd->copy()->subMonth();

        $currentReviewCount = $shop->reviews()
            ->whereBetween('create_time', [$currentPeriodStart, $currentPeriodEnd])
            ->count();
        $prevReviewCount = $shop->reviews()
            ->whereBetween('create_time', [$prevPeriodStart, $prevPeriodEnd])
            ->count();
        $reviewMoM = $prevReviewCount > 0 
            ? round((($currentReviewCount - $prevReviewCount) / $prevReviewCount) * 100, 1)
            : ($currentReviewCount > 0 ? 100 : 0);

        $currentRating = $shop->reviews()
            ->whereBetween('create_time', [$currentPeriodStart, $currentPeriodEnd])
            ->avg('rating');
        $prevRating = $shop->reviews()
            ->whereBetween('create_time', [$prevPeriodStart, $prevPeriodEnd])
            ->avg('rating');
        $ratingMoM = $prevRating > 0 
            ? round((($currentRating - $prevRating) / $prevRating) * 100, 1)
            : ($currentRating > 0 ? 100 : 0);

        $totalReviews = $shop->reviews()
            ->whereBetween('create_time', [$currentPeriodStart, $currentPeriodEnd])
            ->count();
        $repliedReviews = $shop->reviews()
            ->whereBetween('create_time', [$currentPeriodStart, $currentPeriodEnd])
            ->whereNotNull('reply_text')
            ->count();
        $replyRate = $totalReviews > 0 
            ? round(($repliedReviews / $totalReviews) * 100, 1)
            : 0;

        $postCount = 0;
        $currentPhotoCount = 0;
        $prevPhotoCount = 0;
        $photoMoM = 0;

        // 1ページに10日分ずつ表示
        $datesPerPage = 10;
        $dateChunks = array_chunk($dates, $datesPerPage);

        // mPDFでPDF生成
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'orientation' => 'L',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 9,
            'margin_footer' => 9,
            'tempDir' => storage_path('app/tmp'),
            'default_font_size' => 10,
            'default_font' => 'sazanami-gothic',
        ]);

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        $html = view('reports.pdf', compact(
            'shop',
            'keywords',
            'dates',
            'dateChunks',
            'rankData',
            'from',
            'to',
            'fromDate',
            'toDate',
            'currentReviewCount',
            'reviewMoM',
            'currentRating',
            'ratingMoM',
            'replyRate',
            'postCount',
            'currentPhotoCount',
            'photoMoM'
        ))->render();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}

