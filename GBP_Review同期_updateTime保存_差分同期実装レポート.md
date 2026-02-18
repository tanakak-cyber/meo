# GBP Review 同期：updateTime をDB保存して差分同期を成立させる実装レポート

## 実装完了サマリー

### 目的
- Google Business Profile API の `reviews.list` は「更新日時(updateTime)」がキー
- これをDBに保存していないため、差分同期の停止条件が作れず、毎回フル同期/重複/取りこぼしが起きる
- shop単位で「どこまで見たか」を保存し、次回以降は `updateTime` で早期停止する
- review本体と返信(reply)の更新も `updateTime` で正しくUPDATEする

## 実装内容

### 1. マイグレーション作成

#### A. shops テーブルに追加
**ファイル**: `database/migrations/2026_02_07_000004_add_last_reviews_synced_update_time_to_shops_table.php`

追加カラム：
- `last_reviews_synced_update_time` (timestamp, nullable)
  - 意味：この店舗で「前回同期で確定的に取り込めた最新の review.updateTime（最大値）」を保存する
  - これが次回差分同期の cutoff（停止ライン）になる
- `last_reviews_sync_started_at` (timestamp, nullable) - 監視/デバッグ用
- `last_reviews_sync_finished_at` (timestamp, nullable) - 監視/デバッグ用

#### B. reviews テーブルに追加
**ファイル**: `database/migrations/2026_02_07_000005_add_gbp_update_fields_to_reviews_table.php`

追加カラム：
- `gbp_update_time` (timestamp, nullable) - Google review.updateTime
- `gbp_create_time` (timestamp, nullable) - Google review.createTime
- `gbp_reply_update_time` (timestamp, nullable) - Google reviewReply.updateTime
- `has_reply` (boolean, default false) - 返信の有無を明示的に保存

### 2. Model更新

#### A. Shop Model
**ファイル**: `app/Models/Shop.php`

追加：
- `fillable` に `last_reviews_synced_update_time`, `last_reviews_sync_started_at`, `last_reviews_sync_finished_at` を追加
- `casts` に datetime キャストを追加

#### B. Review Model
**ファイル**: `app/Models/Review.php`

追加：
- `fillable` に `gbp_update_time`, `gbp_create_time`, `gbp_reply_update_time`, `has_reply` を追加
- `casts` に datetime/boolean キャストを追加

### 3. 同期ロジック修正

#### A. GoogleBusinessProfileService::listReviews の変更
**ファイル**: `app/Services/GoogleBusinessProfileService.php`

変更内容：
- シグネチャ変更：`listReviews(..., ?string $pageToken = null, int $pageSize = 100)`
- 単一ページ取得に変更（呼び出し側でループ制御）
- 戻り値に `nextPageToken` を追加

#### B. ReviewSyncService::syncShop の修正
**ファイル**: `app/Services/ReviewSyncService.php`

**STEP0: 事前準備**
```php
$cutoff = $shop->last_reviews_synced_update_time 
    ? CarbonImmutable::instance($shop->last_reviews_synced_update_time)->utc()
    : null;
$maxSeen = $cutoff; // NULLなら maxSeen=NULL
```

**STEP1: ページ取得ループ（cutoff判定による早期停止対応）**
- `listReviews` をページ単位で呼び出し
- 各ページのレビューを順に処理

**STEP2: 各reviewの停止判定（差分同期の核）**
```php
if ($cutoff !== null && $reviewUpdate->lessThanOrEqualTo($cutoff)) {
    $stoppedByCutoff = true;
    break 2; // 内側と外側のループを抜ける
}
```
- `cutoff != NULL` かつ `reviewUpdate <= cutoff` なら同期を打ち切る
- 同一updateTimeが並ぶ可能性があるため `<=` で止める（重複はunique + updateOrCreateで吸収）

**STEP3: DBへ upsert**
- `gbp_update_time`, `gbp_create_time`, `gbp_reply_update_time`, `has_reply` を保存
- `updateColumns` に追加

**STEP4: maxSeen更新**
```php
if ($maxSeen === null || $gbpUpdateTime->greaterThan($maxSeen)) {
    $maxSeen = $gbpUpdateTime;
}
```

**STEP5: 次ページへ**
- `nextPageToken` があり、かつ STEP2で停止していないなら続行
- 停止した場合は `nextPageToken` があっても無視して終了

**STEP6: 同期成功時のコミット（最後に必ず保存）**
```php
if ($maxSeen !== null) {
    $shop->update([
        'last_reviews_synced_at' => $maxSeen, // 後方互換用
        'last_reviews_synced_update_time' => $maxSeen, // 差分同期のcutoff
        'last_reviews_sync_finished_at' => $syncFinishedAt,
    ]);
}
```
- 例外が出た場合は `shop.last_reviews_synced_update_time` を更新しない（進捗を進めない）

### 4. 例外・落とし穴への対応

- **途中で例外**の場合：
  - `last_reviews_synced_update_time` を更新しない（これがズレると取りこぼし確定）
  - `last_reviews_sync_finished_at` のみ記録
- **updateTime のパース**：
  - 必ずUTC/RFC3339対応で統一（CarbonImmutable使用）
- **同一updateTimeが大量にある場合**：
  - `<= cutoff` で止めるとギリギリの同時刻レビューを取りこぼす懸念がある
  - 現状はtimestampのみで運用（必要に応じて二次キー追加可能）

### 5. ログ改善

追加ログ項目：
- `cutoff` - 差分同期の停止ライン
- `max_seen` - 今回同期中に見た最大updateTime
- `stopped_by_cutoff` - cutoff判定で早期停止したか
- `delta基準時刻` - `last_reviews_synced_update_time`
- `update_candidates_count` - updateTime条件でUPDATE対象になった数
- `reply_diff_update_count` - 返信差分でUPDATE対象になった数
- `skipped_reasons` - 理由別（no_update_time / no_reply_key など）

## 検証手順

### 1. マイグレーション実行
```bash
php artisan migrate
```

### 2. 初回同期（cutoff NULL）
- 同期を実行
- `shop.last_reviews_synced_update_time` が保存されることを確認
- `review.gbp_update_time` が保存されることを確認

### 3. 2回目同期（cutoffあり）
- 同期を実行
- ログで `stopped_by_cutoff=true` が確認できること
- `cutoff以下に来た時点で早期停止` すること
- `fetched_count` が初回より少ないこと

### 4. 返信が後から増えたケース
- 既存レビューに返信を追加
- 同期を実行
- `reviews` が UPDATE され、`has_reply`, `reply_text`, `gbp_reply_update_time` が更新されること

### 5. 途中例外の確認
- 同期中に例外を発生させる（テスト用）
- `shop.last_reviews_synced_update_time` が更新されないことを確認

## 期待される動作

### 初回同期（cutoff = NULL）
- 全レビューを取得
- `maxSeen` を算出して `shop.last_reviews_synced_update_time` に保存
- 全レビューをDBに保存

### 2回目以降の同期（cutoffあり）
- `cutoff` より新しいレビューのみ取得
- `cutoff <= reviewUpdate` になった時点で早期停止
- `maxSeen` を更新して `shop.last_reviews_synced_update_time` に保存

### 返信が追加された場合
- `updateTime` が同じでも返信差分でUPDATEされる
- `has_reply`, `gbp_reply_update_time` が更新される

## 完了条件

✅ マイグレーション作成（shops, reviews）  
✅ Model更新（Shop, Review）  
✅ ReviewSyncService修正：cutoff判定による早期停止  
✅ gbp_update_time, gbp_create_time, gbp_reply_update_time, has_replyの保存  
✅ maxSeen更新とshopへの保存  
✅ 例外時の進捗保存回避  

## 次のステップ

1. マイグレーション実行：`php artisan migrate`
2. 初回同期を実行して `gbp_update_time` を埋める
3. 2回目同期を実行して `stopped_by_cutoff=true` を確認
4. ログで `cutoff` と `max_seen` が正しく動作していることを確認








