# GBP Insights API デバッグ情報

## ① 実際に叩いているリクエストのログ出力

### 実装済みデバッグログ

以下のログが出力されます：

#### Metrics API (`fetchMultiDailyMetricsTimeSeries`)
- `GBP_INSIGHTS_METRICS_REQUEST`: リクエストURL、メソッド、パラメータ
- `GBP_INSIGHTS_METRICS_PAYLOAD`: リクエストボディ（JSON形式）
- `GBP_INSIGHTS_METRICS_HTTP_STATS`: HTTP統計情報（実際のURL、転送時間）
- `GBP_INSIGHTS_METRICS_RESPONSE`: レスポンス（ステータス、ヘッダー、ボディ、JSON）

#### Keywords API (`fetchSearchKeywords`)
- `GBP_INSIGHTS_KEYWORDS_REQUEST`: リクエストURL、メソッド、パラメータ
- `GBP_INSIGHTS_KEYWORDS_PAYLOAD`: リクエストボディ（JSON形式）
- `GBP_INSIGHTS_KEYWORDS_HTTP_STATS`: HTTP統計情報（実際のURL、転送時間）
- `GBP_INSIGHTS_KEYWORDS_RESPONSE`: レスポンス（ステータス、ヘッダー、ボディ、JSON）

### ログの確認方法

```bash
# Laravel ログファイルを確認
tail -f storage/logs/laravel.log | grep GBP_INSIGHTS
```

## ② 該当箇所の列挙

### `fetchMultiDailyMetricsTimeSeries`
- **ファイル**: `app/Services/GbpInsightsService.php`
- **行番号**: 123

### `fetchSearchKeywords`
- **ファイル**: `app/Services/GbpInsightsService.php`
- **行番号**: 250

### `businessprofileperformance.googleapis.com`
- **ファイル**: `app/Services/GbpInsightsService.php`
  - 行番号: 123 (Metrics API)
  - 行番号: 250 (Keywords API)

### `mybusiness.googleapis.com`
- **ファイル**: `app/Services/GoogleBusinessProfileService.php`
  - 行番号: 452 (reviews.list)
  - 行番号: 533 (media.list)
  - 行番号: 603 (reviews.reply)
  - 行番号: 696 (location.get)
  - 行番号: 757 (media.create)
  - 行番号: 831 (media.list)
  - 行番号: 919, 1012 (localPosts.list)
- **ファイル**: `app/Http/Controllers/ReportController.php`
  - 行番号: 1342 (localPosts.list)
- **ファイル**: `app/Http/Controllers/ShopController.php`
  - 行番号: 1264 (localPosts.list)

## ③ GbpInsightsService.php 全文

### Performance API を呼んでいる関数

#### `fetchMetrics()` (行121-237)
- **URL組み立て**: 行123
  ```php
  $url = "https://businessprofileperformance.googleapis.com/v1/locations/{$locationId}:fetchMultiDailyMetricsTimeSeries";
  ```
- **HTTPメソッド**: POST (行217)
- **パラメータ**: `$payload` (行125-210)

#### `fetchKeywords()` (行248-287)
- **URL組み立て**: 行250
  ```php
  $url = "https://businessprofileperformance.googleapis.com/v1/locations/{$locationId}:fetchSearchKeywords";
  ```
- **HTTPメソッド**: POST (行268)
- **パラメータ**: `$payload` (行256-261)

## ④ 実際に組み立てられたURLをdump

### 一時的なdumpコード（コメントアウト済み）

`GbpInsightsService.php` の以下の箇所でコメントアウトを外すと、実行時にURLとパラメータが表示されます：

#### Metrics API (行218-224付近)
```php
// デバッグ: URLをdump（一時的 - 有効化する場合はコメントアウトを外す）
dd([
    'method' => 'POST',
    'url' => $url,
    'payload' => $payload,
    'location_id' => $locationId,
    'from_date' => $fromDate->format('Y-m-d'),
    'to_date' => $toDate->format('Y-m-d'),
]);
```

#### Keywords API (行270-276付近)
```php
// デバッグ: URLをdump（一時的 - 有効化する場合はコメントアウトを外す）
dd([
    'method' => 'POST',
    'url' => $url,
    'payload' => $payload,
]);
```

### レスポンスのdump（行228-230付近、行280-282付近）
```php
// デバッグ: レスポンスをdump（一時的）
dump($response->status(), $response->body());
```

## ⑤ 成功している口コミAPIと比較

### reviews.list API (`GoogleBusinessProfileService.php` 行442-515)

#### URL構造
```
GET https://mybusiness.googleapis.com/v4/accounts/{accountId}/locations/{locationId}/reviews
```

#### 実装
```php
$url = "https://mybusiness.googleapis.com/v4/accounts/{$accountId}/locations/{$locationIdClean}/reviews";
$response = Http::withToken($accessToken)->get($url);
```

#### デバッグログ
- `GBP_REVIEWS_LIST_REQUEST_FULL`: リクエスト詳細
- `GBP_REVIEWS_LIST_HTTP_STATS`: HTTP統計情報
- `GBP_REVIEWS_LIST_RESPONSE_FULL`: レスポンス詳細

### reviews.reply API (`GoogleBusinessProfileService.php` 行597-680)

#### URL構造
```
PUT https://mybusiness.googleapis.com/v4/{reviewName}/reply
```

#### 実装
```php
$url = "https://mybusiness.googleapis.com/v4/{$reviewName}/reply";
$response = Http::withToken($accessToken)->put($url, $requestBody);
```

### 比較ポイント

| 項目 | Performance API | My Business API (reviews) |
|------|----------------|---------------------------|
| ベースURL | `businessprofileperformance.googleapis.com/v1` | `mybusiness.googleapis.com/v4` |
| メソッド | POST | GET (list), PUT (reply) |
| 認証 | Bearer Token | Bearer Token |
| パス構造 | `/locations/{locationId}:fetchMultiDailyMetricsTimeSeries` | `/accounts/{accountId}/locations/{locationId}/reviews` |

## ⑥ HTTPレスポンスをそのまま表示

### 実装済み

以下のログにレスポンスが完全に記録されます：

#### Metrics API
```php
Log::info('GBP_INSIGHTS_METRICS_RESPONSE', [
    'status' => $response->status(),
    'headers' => $response->headers(),
    'body' => $response->body(),  // 生JSON
    'json' => $response->json(),  // パース済みJSON
]);
```

#### Keywords API
```php
Log::info('GBP_INSIGHTS_KEYWORDS_RESPONSE', [
    'status' => $response->status(),
    'headers' => $response->headers(),
    'body' => $response->body(),  // 生JSON
    'json' => $response->json(),  // パース済みJSON
]);
```

### 一時的なdump（コメントアウト済み）

以下のコードのコメントアウトを外すと、実行時にレスポンスが画面に表示されます：

```php
// デバッグ: レスポンスをdump（一時的）
dump($response->status(), $response->body());
```

## 実行方法

1. レポート画面 (`/reports/{id}`) にアクセス
2. Laravel ログを確認: `tail -f storage/logs/laravel.log`
3. または、dumpコードのコメントアウトを外して実行

## ログ出力例

```
[2026-02-02 19:00:00] local.INFO: GBP_INSIGHTS_METRICS_REQUEST {"method":"POST","url":"https://businessprofileperformance.googleapis.com/v1/locations/14533069664155190447:fetchMultiDailyMetricsTimeSeries","location_id":"14533069664155190447","from_date":"2026-02-01","to_date":"2026-02-28"}

[2026-02-02 19:00:00] local.INFO: GBP_INSIGHTS_METRICS_PAYLOAD {"payload":{...},"payload_json":"{...}"}

[2026-02-02 19:00:01] local.INFO: GBP_INSIGHTS_METRICS_RESPONSE {"status":200,"headers":{...},"body":"{...}","json":{...}}
```

