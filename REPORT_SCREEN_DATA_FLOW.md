# レポート画面データフロー完全分析

## ① Controller の特定

### URL: `/reports/{shopId}?from=YYYY-MM-DD&to=YYYY-MM-DD`

**ファイルパス**: `app/Http/Controllers/ReportController.php`  
**メソッド名**: `show(Request $request, $shopId)`  
**行番号**: 132-375

### 呼ばれているServiceクラス

1. **GoogleBusinessProfileService** (行12でuse宣言)
   - ただし、`show()`メソッド内では直接呼び出されていない
   - `sync()` や `syncAll()` メソッドで使用

2. **GbpInsightsService** 
   - 現在は呼び出されていない（切り戻しで削除された可能性）

### ルーティング

**ファイル**: `routes/web.php`  
**行番号**: 55, 135  
```php
Route::get('/reports/{shop}', [ReportController::class, 'show'])->name('reports.show');
```

---

## ② データの一覧表

| UI項目 | データ元 | テーブル or API | 集計ロジック | コード箇所 |
|--------|----------|----------------|--------------|------------|
| **キーワード順位テーブル** | DB | `meo_keywords` + `meo_rank_logs` | 期間内の日別順位データ | ReportController:169-217行 |
| **キーワード順位グラフ** | DB | `meo_keywords` + `meo_rank_logs` | 期間内の日別順位データ（Chart.js用） | ReportController:204-217行 |
| **口コミ数** | DB | `reviews` | 期間内の口コミ数（gbp_review_idでユニーク化） | ReportController:245-253行 |
| **評価点数** | DB | `reviews` | 期間内の口コミの評価平均値 | ReportController:270-273行 |
| **返信率** | DB | `reviews` | 期間内の口コミに対する返信率 | ReportController:279-284行 |
| **有効投稿数** | DB | `gbp_snapshots` | 最新スナップショットの`posts_count` | ReportController:292行 |
| **写真数** | DB | `photos` | 期間内の写真数（gbp_media_idでユニーク化） | ReportController:310-318行 |
| **前月比（MoM）** | DB | 各テーブル | 前月同期間との比較計算 | ReportController:265-267, 274-276, 303-305, 330-332行 |

### データ取得の詳細

#### キーワード順位
- **テーブル**: `meo_keywords`, `meo_rank_logs`
- **クエリ**: 
  ```php
  $shop->meoKeywords()
      ->with(['rankLogs' => function ($query) use ($fromStr, $toStr) {
          $query->where('checked_at', '>=', $fromStr)
                ->where('checked_at', '<=', $toStr)
                ->orderBy('checked_at');
      }])
  ```
- **集計**: 各キーワードの平均順位を計算（行187）

#### KPIサマリー
- **スナップショット**: `gbp_snapshots` テーブルから最新のスナップショットを取得（行221-224）
- **口コミ**: `reviews` テーブルから期間内の口コミを取得（行245-253）
- **写真**: `photos` テーブルから期間内の写真を取得（行310-318）
- **投稿**: `gbp_snapshots.posts_count` を直接使用（行292）

---

## ③ 表示回数を入れる "入れ口" の特定

### 設計レベルでの説明

#### オプション1: 既存テーブルに追加（非推奨）
- `gbp_snapshots` テーブルに `impressions_count` カラムを追加
- **問題点**: スナップショットは同期実行時に作成されるため、期間指定の表示回数には不向き

#### オプション2: 新しいテーブル `gbp_daily_metrics`（推奨）
- **テーブル構造**:
  ```sql
  CREATE TABLE gbp_daily_metrics (
      id BIGINT PRIMARY KEY,
      shop_id BIGINT,
      location_id VARCHAR(255),
      date DATE,
      impressions INT,
      all_actions INT,
      website_clicks INT,
      call_clicks INT,
      directions INT,
      created_at TIMESTAMP,
      updated_at TIMESTAMP,
      UNIQUE(shop_id, date)
  );
  ```
- **メリット**: 
  - 日別データとして保存可能
  - 期間指定の集計が容易
  - 既存のスナップショット構造と独立

#### オプション3: 既存の `gbp_insights` テーブルを活用（最適）
- **現在の構造**: `gbp_insights` テーブルは既に存在（マイグレーション: `2026_02_02_190000_create_gbp_insights_table.php`）
- **カラム**: `metrics_response` (JSON), `keywords_response` (JSON)
- **メリット**: 
  - 既にテーブルが存在
  - JSON形式で柔軟にデータを保存可能
  - 期間指定（from_date, to_date）に対応

### 推奨実装

**既存の `gbp_insights` テーブルを使用**:
1. `GbpInsightsService::fetchInsights()` でAPIから取得
2. `gbp_insights` テーブルに保存（既に実装済み）
3. `ReportController::show()` で `GbpInsightsService::getInsightsFromDb()` を呼び出し
4. ビューで表示回数を表示

---

## ④ KPIサマリーの構成

### 現在のKPIカード

#### 1. 口コミ数
- **テーブル**: `reviews`
- **クエリ**: 
  ```php
  DB::table('reviews')
      ->where('shop_id', $shop->id)
      ->where('snapshot_id', $latestSnapshot->id)
      ->whereBetween('create_time', [$currentPeriodStart, $currentPeriodEnd])
      ->select(DB::raw('MAX(id) as id'))
      ->groupBy('gbp_review_id')
      ->pluck('id');
  ```
- **集計**: `count($currentReviewIds)` (行253)
- **前月比**: 前月同期間の口コミ数と比較（行265-267）

#### 2. 評価点数
- **テーブル**: `reviews`
- **クエリ**: 
  ```php
  Review::whereIn('id', $currentReviewIds)->avg('rating');
  ```
- **集計**: 平均値（行270-271）
- **前月比**: 前月同期間の評価平均と比較（行274-276）

#### 3. 返信率
- **テーブル**: `reviews`
- **クエリ**: 
  ```php
  Review::whereIn('id', $currentReviewIds)
      ->whereNotNull('reply_text')
      ->count();
  ```
- **集計**: `(返信した口コミ数 / 総口コミ数) * 100` (行282-284)
- **前月比**: なし

#### 4. 有効投稿数
- **テーブル**: `gbp_snapshots`
- **クエリ**: 
  ```php
  $latestSnapshot->posts_count
  ```
- **集計**: スナップショットの `posts_count` を直接使用（行292）
- **前月比**: 前月の最新スナップショットの `posts_count` と比較（行303-305）

#### 5. 写真数
- **テーブル**: `photos`
- **クエリ**: 
  ```php
  DB::table('photos')
      ->where('shop_id', $shop->id)
      ->where('snapshot_id', $latestSnapshot->id)
      ->whereBetween('create_time', [$currentPeriodStart, $currentPeriodEnd])
      ->select(DB::raw('MAX(id) as id'))
      ->groupBy('gbp_media_id')
      ->pluck('id');
  ```
- **集計**: `count($currentPhotoIds)` (行318)
- **前月比**: 前月同期間の写真数と比較（行330-332）

---

## ⑤ レポート期間の扱い

### バリデーション

**場所**: `ReportController::show()` メソッド（行158-162）
```php
$from = $request->get('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
$to = $request->get('to', Carbon::now()->endOfMonth()->format('Y-m-d'));

$fromDate = Carbon::parse($from);
$toDate = Carbon::parse($to);
```

**バリデーション**: 
- 明示的なバリデーションはない
- デフォルト値: 今月の初日～末日
- `Carbon::parse()` で日付形式をチェック（無効な場合は例外）

### クエリでの使用

#### キーワード順位
- **テーブル**: `meo_rank_logs`
- **WHERE句**: 
  ```php
  ->where('checked_at', '>=', $fromStr)
  ->where('checked_at', '<=', $toStr)
  ```
- **行番号**: 172-174

#### 口コミ数
- **テーブル**: `reviews`
- **WHERE句**: 
  ```php
  ->whereBetween('create_time', [$currentPeriodStart->format('Y-m-d H:i:s'), $currentPeriodEnd->format('Y-m-d H:i:s')])
  ```
- **行番号**: 248

#### 写真数
- **テーブル**: `photos`
- **WHERE句**: 
  ```php
  ->whereBetween('create_time', [$currentPeriodStart->format('Y-m-d H:i:s'), $currentPeriodEnd->format('Y-m-d H:i:s')])
  ```
- **行番号**: 313

#### 評価点数・返信率
- **テーブル**: `reviews`
- **WHERE句**: 期間フィルタ済みの `$currentReviewIds` を使用（行270, 279）

### 影響するテーブル

1. **meo_rank_logs**: `checked_at` でフィルタ
2. **reviews**: `create_time` でフィルタ
3. **photos**: `create_time` でフィルタ
4. **gbp_snapshots**: 期間フィルタなし（最新スナップショットのみ使用）

---

## ⑥ 要約: Google Performance API の値を足すときの最短ルート

### 現在の状態

1. **`GbpInsightsService`**: 既に実装済み
   - `fetchInsights()`: APIから取得してDBに保存
   - `getInsightsFromDb()`: DBから取得
   - `formatMetrics()`: メトリクスを整形
   - `formatKeywords()`: キーワードを整形

2. **`gbp_insights` テーブル**: 既に存在
   - `metrics_response` (JSON): KPIメトリクス
   - `keywords_response` (JSON): キーワード表示回数

3. **`ReportController`**: `GbpInsightsService` の呼び出しが削除されている

### 最短ルート（実装手順）

#### Step 1: ReportController に GbpInsightsService を追加

```php
// ReportController.php の use 宣言に追加
use App\Services\GbpInsightsService;

// show() メソッド内（行349付近）に追加
$insightsService = new GbpInsightsService();
$insightsData = null;
$insightsMetrics = [];
$insightsKeywords = [];

if ($shop->gbp_location_id && $shop->gbp_refresh_token) {
    try {
        // まずDBから取得を試みる
        $insightsData = $insightsService->getInsightsFromDb($shop, $from, $to);
        
        // DBにない場合はAPIから取得
        if (!$insightsData) {
            $insightsData = $insightsService->fetchInsights($shop, $from, $to);
        }
        
        if ($insightsData) {
            $insightsMetrics = $insightsService->formatMetrics($insightsData);
            $insightsKeywords = $insightsService->formatKeywords($insightsData);
        }
    } catch (\Exception $e) {
        Log::error('GBP Insights取得エラー', [
            'shop_id' => $shop->id,
            'error' => $e->getMessage(),
        ]);
    }
}
```

#### Step 2: ビューに変数を渡す

```php
// ReportController::show() の return view() に追加
return view('reports.show', compact(
    // ... 既存の変数 ...
    'insightsMetrics',
    'insightsKeywords',
));
```

#### Step 3: ビューで表示

```blade
<!-- resources/views/reports/show.blade.php -->
@if(!empty($insightsMetrics))
    <div class="bg-gray-50 p-4 rounded-lg">
        <dt class="text-sm font-medium text-gray-500">表示回数</dt>
        <dd class="mt-1 text-2xl font-semibold text-gray-900">
            {{ $insightsMetrics['BUSINESS_IMPRESSIONS'] ?? 0 }}
        </dd>
    </div>
    <!-- その他のメトリクスも同様に表示 -->
@endif
```

### データフロー

```
/reports/{shopId}?from=2026-02-01&to=2026-02-03
    ↓
ReportController::show()
    ↓
GbpInsightsService::getInsightsFromDb()  // まずDBから取得
    ↓ (DBにない場合)
GbpInsightsService::fetchInsights()
    ↓
GoogleBusinessProfileService::refreshAccessToken()  // refresh_token → access_token
    ↓
API呼び出し:
  - POST businessprofileperformance.googleapis.com/v1/locations/{locationId}:fetchMultiDailyMetricsTimeSeries
  - POST businessprofileperformance.googleapis.com/v1/locations/{locationId}:fetchSearchKeywords
    ↓
gbp_insights テーブルに保存
    ↓
GbpInsightsService::formatMetrics() / formatKeywords()
    ↓
ビューで表示
```

### 注意点

1. **期間指定**: `from` と `to` は `gbp_insights` テーブルの `from_date` と `to_date` と一致する必要がある
2. **キャッシュ**: DBに保存されている場合はAPIを呼ばずにDBから取得（高速化）
3. **エラーハンドリング**: API呼び出し失敗時は既存のKPI表示に影響しないようにする

