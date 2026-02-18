<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\GbpInsight;
use App\Services\GbpInsightsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GbpInsightsImportController extends Controller
{
    /**
     * CSVインポート画面を表示
     */
    public function index()
    {
        return view('gbp-insights.import');
    }

    /**
     * CSVファイルをアップロードしてインポート（複数ファイル対応）
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_files.*' => 'required|file|mimes:csv,txt|max:10240', // 10MBまで、複数ファイル対応
        ]);

        $files = $request->file('csv_files');
        if (empty($files)) {
            return redirect()->route('gbp-insights.import')
                ->with('error', 'CSVファイルが選択されていません。');
        }

        $totalSuccessCount = 0;
        $totalSkipCount = 0;
        $allImportErrors = [];

        // 各ファイルを処理
        foreach ($files as $file) {
            $filename = $file->getClientOriginalName();

            // ファイル名から日付を抽出
            $dateRange = $this->extractDateRangeFromFilename($filename);
            if (!$dateRange) {
                $allImportErrors[] = "ファイル「{$filename}」: ファイル名から日付を抽出できませんでした";
                Log::error('GBP CSV日付抽出失敗', [
                    'filename' => $filename,
                ]);
                continue;
            }

            $fromDate = $dateRange['from'];
            $toDate = $dateRange['to'];
            
            Log::info('GBP CSV日付抽出成功', [
                'filename' => $filename,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]);

            // CSVファイルを読み込み
            $handle = fopen($file->getRealPath(), 'r');
            if (!$handle) {
                $allImportErrors[] = "ファイル「{$filename}」: CSVファイルの読み込みに失敗しました";
                continue;
            }

            // 1行目（ヘッダー）と2行目（説明文）をスキップ
            fgetcsv($handle); // 1行目
            fgetcsv($handle); // 2行目

            $successCount = 0;
            $skipCount = 0;
            $importErrors = [];

            // 3行目からデータを読み込み
            $lineNumber = 3;
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                // 空行をスキップ
                if (empty(array_filter($row))) {
                    continue;
                }

                try {
                    // CSVの2列目「ビジネス名」を取得（trim して空白を除去）
                    $businessName = isset($row[1]) ? trim($row[1]) : null;

                    if (!$businessName) {
                        $skipCount++;
                        $importErrors[] = "ファイル「{$filename}」行{$lineNumber}: ビジネス名が空です";
                        continue;
                    }

                    // shops.gbp_name と完全一致で照合（DB側も trim して比較）
                    $shop = Shop::whereRaw('TRIM(gbp_name) = ?', [trim($businessName)])->first();

                    if (!$shop) {
                        $skipCount++;
                        $importErrors[] = "ファイル「{$filename}」行{$lineNumber}: ビジネス名「{$businessName}」に一致する店舗が見つかりませんでした";
                        Log::warning('GBP CSVインポート: 店舗名不一致', [
                            'filename' => $filename,
                            'business_name' => $businessName,
                            'line_number' => $lineNumber,
                        ]);
                        continue;
                    }

                    // データのマッピング
                    // 表示回数（合計）: 5, 6, 7, 8列目の合算（インデックスは0始まりなので4,5,6,7）
                    $impressions = 0;
                    for ($i = 4; $i <= 7; $i++) {
                        $impressions += isset($row[$i]) ? (int)($row[$i] ?? 0) : 0;
                    }

                    // 通話: 9列目（インデックス8）
                    $phone = isset($row[8]) ? (int)($row[8] ?? 0) : 0;

                    // ルート: 12列目（インデックス11）
                    $directions = isset($row[11]) ? (int)($row[11] ?? 0) : 0;

                    // ウェブサイトクリック: 13列目（インデックス12）
                    $website = isset($row[12]) ? (int)($row[12] ?? 0) : 0;

                    // 期間の1日目と末日を計算
                    $fromDateObj = Carbon::parse($fromDate);
                    $toDateObj = Carbon::parse($toDate);
                    
                    $fromDateFormatted = $fromDateObj->format('Y-m-d');
                    $toDateFormatted = $toDateObj->format('Y-m-d');

                    // 既存データを確認
                    $existingInsight = GbpInsight::where('shop_id', $shop->id)
                        ->where('from_date', $fromDateFormatted)
                        ->where('to_date', $toDateFormatted)
                        ->where('period_type', 'daily')
                        ->first();
                    
                    Log::info('GBP CSV更新前チェック', [
                        'filename' => $filename,
                        'shop_id' => $shop->id,
                        'shop_name' => $shop->name,
                        'from_date' => $fromDateFormatted,
                        'to_date' => $toDateFormatted,
                        'period_type' => 'daily',
                        'existing_id' => $existingInsight ? $existingInsight->id : null,
                        'existing_impressions' => $existingInsight ? $existingInsight->impressions : null,
                        'new_impressions' => $impressions,
                    ]);

                    // 店舗IDと期間（開始日〜終了日）をキーとして、期間全体の値を1つのレコードとして保存
                    // 同じ期間のデータが再度インポートされた場合は、新しい数値で上書き更新
                    // キー: shop_id, from_date, to_date, period_type（ユニーク制約に合わせる）
                    $insight = GbpInsight::updateOrCreate(
                        [
                            'shop_id' => $shop->id,
                            'from_date' => $fromDateFormatted,
                            'to_date' => $toDateFormatted,
                            'period_type' => 'daily',
                        ],
                        [
                            'location_id' => $shop->gbp_location_id ?? '',
                            'impressions' => $impressions,
                            'directions' => $directions,
                            'website' => $website,
                            'phone' => $phone,
                            // CSVインポート時はAPIレスポンスが存在しないため、null を設定
                            'metrics_response' => null,
                            'keywords_response' => null,
                        ]
                    );
                    
                    Log::info('GBP CSV更新後', [
                        'filename' => $filename,
                        'shop_id' => $shop->id,
                        'insight_id' => $insight->id,
                        'wasRecentlyCreated' => $insight->wasRecentlyCreated,
                        'impressions' => $insight->impressions,
                        'directions' => $insight->directions,
                        'website' => $insight->website,
                        'phone' => $insight->phone,
                    ]);

                    $successCount++;

                    Log::info('GBP CSVインポート成功', [
                        'filename' => $filename,
                        'shop_id' => $shop->id,
                        'shop_name' => $shop->name,
                        'business_name' => $businessName,
                        'from_date' => $fromDate,
                        'to_date' => $toDate,
                        'impressions' => $impressions,
                        'directions' => $directions,
                        'website' => $website,
                        'phone' => $phone,
                    ]);

                } catch (\Exception $e) {
                    $skipCount++;
                    $importErrors[] = "ファイル「{$filename}」行{$lineNumber}: エラー - {$e->getMessage()}";
                    Log::error('GBP CSVインポートエラー', [
                        'filename' => $filename,
                        'line_number' => $lineNumber,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            fclose($handle);
            $totalSuccessCount += $successCount;
            $totalSkipCount += $skipCount;
            $allImportErrors = array_merge($allImportErrors, $importErrors);
        }

        // 結果を返す
        $message = "{$totalSuccessCount}件成功 / {$totalSkipCount}件スキップ（店舗名不一致）";
        if (!empty($allImportErrors) && count($allImportErrors) <= 10) {
            $message .= "\n\nスキップ詳細:\n" . implode("\n", array_slice($allImportErrors, 0, 10));
        } elseif (!empty($allImportErrors)) {
            $message .= "\n\n（スキップ詳細は最初の10件のみ表示）";
        }

        return redirect()->route('gbp-insights.import')
            ->with('success', $message)
            ->with('success_count', $totalSuccessCount)
            ->with('skip_count', $totalSkipCount)
            ->with('import_errors', $allImportErrors);
    }

    /**
     * ファイル名から日付範囲を抽出
     * 例: "GMB insights...2026-1-1 - 2026-1-31..." → ['from' => '2026-01-01', 'to' => '2026-01-31']
     * 例: "GMB insights...2026-2-10 - 2026-2-10..." → ['from' => '2026-02-10', 'to' => '2026-02-10']
     */
    private function extractDateRangeFromFilename(string $filename): ?array
    {
        Log::info('GBP CSV日付抽出開始', [
            'filename' => $filename,
        ]);
        
        // パターン1: "2026-1-1 - 2026-1-31" 形式（より厳密なマッチング）
        // 日付の前後に空白またはハイフンがあることを確認
        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})\s+-\s+(\d{4})-(\d{1,2})-(\d{1,2})/', $filename, $matches)) {
            $fromYear = (int)$matches[1];
            $fromMonth = (int)$matches[2];
            $fromDay = (int)$matches[3];
            $toYear = (int)$matches[4];
            $toMonth = (int)$matches[5];
            $toDay = (int)$matches[6];

            try {
                $fromDate = Carbon::create($fromYear, $fromMonth, $fromDay)->format('Y-m-d');
                $toDate = Carbon::create($toYear, $toMonth, $toDay)->format('Y-m-d');
                
                Log::info('GBP CSV日付抽出成功（パターン1）', [
                    'filename' => $filename,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'matches' => $matches,
                ]);
                
                return ['from' => $fromDate, 'to' => $toDate];
            } catch (\Exception $e) {
                Log::error('GBP CSV日付抽出エラー（パターン1）', [
                    'filename' => $filename,
                    'error' => $e->getMessage(),
                    'matches' => $matches,
                ]);
                return null;
            }
        }

        // パターン2: "2026/1/1 - 2026/1/31" 形式
        if (preg_match('/(\d{4})\/(\d{1,2})\/(\d{1,2})\s+-\s+(\d{4})\/(\d{1,2})\/(\d{1,2})/', $filename, $matches)) {
            $fromYear = (int)$matches[1];
            $fromMonth = (int)$matches[2];
            $fromDay = (int)$matches[3];
            $toYear = (int)$matches[4];
            $toMonth = (int)$matches[5];
            $toDay = (int)$matches[6];

            try {
                $fromDate = Carbon::create($fromYear, $fromMonth, $fromDay)->format('Y-m-d');
                $toDate = Carbon::create($toYear, $toMonth, $toDay)->format('Y-m-d');
                
                Log::info('GBP CSV日付抽出成功（パターン2）', [
                    'filename' => $filename,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'matches' => $matches,
                ]);
                
                return ['from' => $fromDate, 'to' => $toDate];
            } catch (\Exception $e) {
                Log::error('GBP CSV日付抽出エラー（パターン2）', [
                    'filename' => $filename,
                    'error' => $e->getMessage(),
                    'matches' => $matches,
                ]);
                return null;
            }
        }

        Log::error('GBP CSV日付抽出失敗: パターンに一致しません', [
            'filename' => $filename,
        ]);

        return null;
    }

}

