# 口コミ同期のログ抽出ガイド

## 1. rows_to_write_count / skipped_unchanged_count を確定

### ログキー
- `REVIEWS_ROWS_BEFORE_UPSERT`: rows配列生成直後のログ
- `REVIEWS_UPSERT_EXECUTED`: upsert実行後のログ

### 抽出コマンド

```bash
# REVIEWS_ROWS_BEFORE_UPSERT を抽出（2回分）
grep "REVIEWS_ROWS_BEFORE_UPSERT" storage/logs/laravel.log | tail -2

# REVIEWS_UPSERT_EXECUTED を抽出（2回分）
grep "REVIEWS_UPSERT_EXECUTED" storage/logs/laravel.log | tail -2
```

### 確認すべき値

**1回目の同期**:
- `fetched_count`: APIから取得したレビュー件数
- `rows_to_write_count`: DB書き込み対象の件数
- `skipped_unchanged_count`: 変更なしでスキップした件数

**2回目の同期**:
- `fetched_count`: APIから取得したレビュー件数（1回目と同じ）
- `rows_to_write_count`: DB書き込み対象の件数（0 または少数が期待値）
- `skipped_unchanged_count`: 変更なしでスキップした件数（多数が期待値）

**比較**:
- `fetched_count(1回目)` vs `fetched_count(2回目)`: 同じ値（全件取得）
- `rows_to_write_count(1回目)` vs `rows_to_write_count(2回目)`: 2回目が0または少数
- `skipped_unchanged_count(1回目)` vs `skipped_unchanged_count(2回目)`: 2回目が多数

## 2. hasChanges が全件trueになる原因を特定

### ログキー
- `REVIEW_SYNC_TIMECOMPARISON_GT_DETAIL`: `parsedUpdateTime->gt($existingTime)` が true になったケース

### 抽出コマンド

```bash
# REVIEW_SYNC_TIMECOMPARISON_GT_DETAIL を抽出（1件で良い）
grep "REVIEW_SYNC_TIMECOMPARISON_GT_DETAIL" storage/logs/laravel.log | tail -1
```

### 確認すべき値

**API側**:
- `api_createTime_raw`: API側の createTime（raw文字列）
- `api_updateTime_raw`: API側の updateTime（raw文字列）
- `api_createTime_utc`: API側の createTime（UTC化した値）
- `parsedUpdateTime_utc`: parsedUpdateTime（UTC化した値）

**DB側**:
- `db_create_time_raw`: DB側の create_time（raw文字列）
- `db_update_time_raw`: DB側の update_time（raw文字列）
- `existingTime_utc`: existingTime（UTCとして読んだ値）
- `existingTime_as_utc`: existingTime（UTCとして読んだ値）
- `existingTime_as_jst_then_utc`: existingTime（Asia/Tokyoとして読んでからUTCに変換した値）

**比較結果**:
- `time_comparison`: 比較結果（gt/eq/lt）
- `parsedUpdateTime_gt_existingTime`: parsedUpdateTime > existingTime の結果
- `parsedUpdateTime_eq_existingTime`: parsedUpdateTime == existingTime の結果
- `parsedUpdateTime_lt_existingTime`: parsedUpdateTime < existingTime の結果
- `time_diff_seconds`: 時刻差（秒）

### 原因特定のポイント

1. **timezone の影響を確認**:
   - `existingTime_as_utc` vs `existingTime_as_jst_then_utc` を比較
   - 差が9時間（32400秒）なら、timezone の問題

2. **時刻差を確認**:
   - `time_diff_seconds` が小さい（数秒〜数分）場合、時刻の丸め誤差の可能性
   - `time_diff_seconds` が大きい（数時間以上）場合、timezone の問題の可能性

3. **raw文字列を確認**:
   - `api_createTime_raw` vs `db_create_time_raw` を比較
   - `api_updateTime_raw` vs `db_update_time_raw` を比較
   - 文字列レベルで同じか確認

## 3. 修正方針

### DBの create_time / update_time は UTCとして保存している前提に統一

**修正前**:
```php
$existingTime = \Carbon\Carbon::parse($existingReview->update_time)->utc();
```

**修正後**:
```php
// DBの update_time は datetime 型で、Carbon インスタンスとして取得される
$existingTimeRaw = $existingReview->update_time instanceof \Carbon\Carbon
    ? $existingReview->update_time->format('Y-m-d H:i:s')
    : (string)$existingReview->update_time;

// DBの update_time は 'Y-m-d H:i:s' 形式でUTCとして保存されている前提
$existingTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $existingTimeRaw, 'UTC');
```

### Carbon::parse() で曖昧解釈しない

**修正前**:
```php
$createTime = \Carbon\Carbon::parse($review['createTime'])->utc();
```

**修正後**:
```php
// APIのcreateTimeをUTCとして解析（ISO8601形式を想定）
$createTime = \Carbon\Carbon::parse($createTimeRaw)->utc();
// 注意: API側は ISO8601 形式なので parse() でOK
// DB側は 'Y-m-d H:i:s' 形式なので createFromFormat() を使用
```

## 4. 期待される結果

### 修正前
- `parsedUpdateTime->gt($existingTime)` が true になるケースが多発
- `hasChanges` が全件 true になる
- `rows_to_write_count` が全件になる

### 修正後
- `parsedUpdateTime->gt($existingTime)` が true になるケースが減少
- `hasChanges` が true になるのは実際に変更があったレビューのみ
- `rows_to_write_count` が 0 または少数になる

## 5. 検証手順

1. **同期実行**: Web UI または tinker から同期を実行（2回連続）

2. **ログ抽出**:
   ```bash
   grep "REVIEWS_ROWS_BEFORE_UPSERT" storage/logs/laravel.log | tail -2
   grep "REVIEWS_UPSERT_EXECUTED" storage/logs/laravel.log | tail -2
   grep "REVIEW_SYNC_TIMECOMPARISON_GT_DETAIL" storage/logs/laravel.log | tail -1
   ```

3. **結果確認**:
   - `rows_to_write_count(2回目)` が 0 または少数
   - `skipped_unchanged_count(2回目)` が多数
   - `REVIEW_SYNC_TIMECOMPARISON_GT_DETAIL` が出力されない、または少数









