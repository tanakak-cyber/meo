# reviews テーブル構造レポート

## 【1】カラム一覧（型・nullable・default含む）

| カラム名 | 型 | nullable | default | 備考 |
|---------|-----|----------|---------|------|
| `id` | `bigint unsigned` | ❌ NO | `AUTO_INCREMENT` | PRIMARY KEY |
| `snapshot_id` | `bigint unsigned` | ✅ YES | `NULL` | Foreign Key to `gbp_snapshots.id` |
| `gbp_review_id` | `string` | ✅ YES | `NULL` | Google Business Profile のレビューID（"reviews/" プレフィックスなし） |
| `shop_id` | `bigint unsigned` | ❌ NO | - | Foreign Key to `shops.id` |
| `author_name` | `string` | ❌ NO | - | レビュアー名 |
| `rating` | `integer` | ❌ NO | - | 評価（1-5） |
| `comment` | `text` | ✅ YES | `NULL` | レビューコメント |
| `create_time` | `datetime` | ❌ NO | - | レビュー作成日時 |
| `reply_text` | `text` | ✅ YES | `NULL` | 返信テキスト |
| `replied_at` | `datetime` | ✅ YES | `NULL` | 返信日時 |
| `created_at` | `timestamp` | ✅ YES | `NULL` | Laravel のタイムスタンプ |
| `updated_at` | `timestamp` | ✅ YES | `NULL` | Laravel のタイムスタンプ |

### カラム定義の詳細

#### 初期テーブル作成（`2024_01_01_000001_create_reviews_table.php`）

```12:22:database/migrations/2024_01_01_000001_create_reviews_table.php
Schema::create('reviews', function (Blueprint $table) {
    $table->id();
    $table->foreignId('shop_id')->constrained()->onDelete('cascade');
    $table->string('author_name');
    $table->integer('rating');
    $table->text('comment')->nullable();
    $table->datetime('create_time');
    $table->text('reply_text')->nullable();
    $table->datetime('replied_at')->nullable();
    $table->timestamps();
});
```

#### snapshot_id と gbp_review_id の追加（`2026_01_31_000004_add_snapshot_id_to_reviews_table.php`）

```30:39:database/migrations/2026_01_31_000004_add_snapshot_id_to_reviews_table.php
// snapshot_id 追加
if (!Schema::hasColumn('reviews', 'snapshot_id')) {
    $table->unsignedBigInteger('snapshot_id')->nullable()->after('id');
}

// 新しいユニークインデックスを追加
if (!Schema::hasColumn('reviews', 'gbp_review_id')) {
    $table->string('gbp_review_id')->nullable()->after('id');
}
$table->unique(['snapshot_id', 'gbp_review_id']);
```

---

## 【2】index一覧

### 自動生成されるインデックス

1. **PRIMARY KEY**: `id`
   - カラム: `id`
   - 型: PRIMARY KEY

2. **shop_id インデックス**（`foreignId('shop_id')` により自動生成）
   - カラム: `shop_id`
   - 型: INDEX（Foreign Key のため自動生成）

3. **snapshot_id インデックス**（`unique(['snapshot_id', 'gbp_review_id'])` により自動生成）
   - カラム: `snapshot_id`（複合インデックスの一部）
   - 型: INDEX（UNIQUE 制約の一部）

4. **gbp_review_id インデックス**（`unique(['snapshot_id', 'gbp_review_id'])` により自動生成）
   - カラム: `gbp_review_id`（複合インデックスの一部）
   - 型: INDEX（UNIQUE 制約の一部）

### 明示的に定義されたインデックス

- **なし**（すべて自動生成）

---

## 【3】unique制約一覧

### 現在のUNIQUE制約

1. **`reviews_snapshot_id_gbp_review_id_unique`**
   - カラム: `['snapshot_id', 'gbp_review_id']`
   - 定義箇所: `2026_01_31_000004_add_snapshot_id_to_reviews_table.php` 39行目
   - 意味: 同じ `snapshot_id` 内で同じ `gbp_review_id` は1件のみ

```39:39:database/migrations/2026_01_31_000004_add_snapshot_id_to_reviews_table.php
$table->unique(['snapshot_id', 'gbp_review_id']);
```

### 削除されたUNIQUE制約

- **`reviews_gbp_review_id_unique`**（削除済み）
  - カラム: `['gbp_review_id']`
  - 削除箇所: `2026_01_31_000004_add_snapshot_id_to_reviews_table.php` 16行目（MySQL）または 23行目（その他）
  - 理由: `snapshot_id` と組み合わせた複合UNIQUE制約に変更

---

## 【4】foreign key一覧

### 現在の外部キー制約

1. **`reviews_shop_id_foreign`**
   - カラム: `shop_id`
   - 参照先: `shops.id`
   - 削除時: `CASCADE`
   - 定義箇所: `2024_01_01_000001_create_reviews_table.php` 14行目

```14:14:database/migrations/2024_01_01_000001_create_reviews_table.php
$table->foreignId('shop_id')->constrained()->onDelete('cascade');
```

2. **`reviews_snapshot_id_foreign`**（推測）
   - カラム: `snapshot_id`
   - 参照先: `gbp_snapshots.id`
   - 削除時: 未指定（デフォルトは `RESTRICT` または `NO ACTION`）
   - 定義箇所: 明示的な定義なし（`unsignedBigInteger` のみ）

**注意**: `snapshot_id` は `unsignedBigInteger` として定義されていますが、`foreignId()` や `constrained()` が使用されていないため、**外部キー制約は存在しない可能性があります**。

### 削除された外部キー制約

- **`reviews_operator_id_foreign`**（削除済み）
  - カラム: `operator_id`
  - 削除箇所: `2026_02_01_000010_ensure_no_operator_id_in_reviews_photos_posts.php` 22-42行目
  - 理由: `operator_id` カラム自体が削除された

---

## 【5】詳細情報

### snapshot_id の定義

```32:32:database/migrations/2026_01_31_000004_add_snapshot_id_to_reviews_table.php
$table->unsignedBigInteger('snapshot_id')->nullable()->after('id');
```

- **型**: `unsignedBigInteger`（`bigint unsigned`）
- **nullable**: ✅ YES
- **位置**: `id` カラムの後
- **外部キー制約**: ❌ **存在しない**（`foreignId()` や `constrained()` が使用されていない）

### gbp_review_id のindex状況

- **単独インデックス**: ❌ 存在しない
- **複合インデックス**: ✅ 存在する（`['snapshot_id', 'gbp_review_id']` のUNIQUE制約の一部）
- **UNIQUE制約**: ✅ 存在する（`['snapshot_id', 'gbp_review_id']` の複合UNIQUE制約）

### shop_id のindex状況

- **単独インデックス**: ✅ 存在する（`foreignId('shop_id')` により自動生成）
- **外部キー制約**: ✅ 存在する（`shops.id` を参照、`CASCADE` 削除）

### 現在のunique制約

**1つのUNIQUE制約のみ存在**:

- **`reviews_snapshot_id_gbp_review_id_unique`**
  - カラム: `['snapshot_id', 'gbp_review_id']`
  - 意味: 同じ `snapshot_id` 内で同じ `gbp_review_id` は1件のみ保存可能
  - **問題点**: 異なる `snapshot_id` では同じ `gbp_review_id` が複数保存可能（重複保存される）

---

## 【6】現在の設計の問題点

### 重複保存の問題

現在のUNIQUE制約 `['snapshot_id', 'gbp_review_id']` により：

- ✅ 同じ `snapshot_id` 内では同じ `gbp_review_id` は1件のみ（重複防止）
- ❌ 異なる `snapshot_id` では同じ `gbp_review_id` が複数保存可能（重複発生）

**例**:
```
snapshot_id=1, gbp_review_id='111' → 保存可能
snapshot_id=2, gbp_review_id='111' → 保存可能（重複）
```

### snapshot_id の外部キー制約がない問題

- `snapshot_id` は `unsignedBigInteger` として定義されているが、`foreignId()` や `constrained()` が使用されていない
- 外部キー制約がないため、参照整合性が保証されない
- `gbp_snapshots` テーブルに存在しない `snapshot_id` でも保存可能

---

## 【7】テーブル構造の完全な定義

### 最終的なテーブル構造（migration 実行後）

```sql
CREATE TABLE `reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_id` bigint unsigned NULL,
  `gbp_review_id` varchar(255) NULL,
  `shop_id` bigint unsigned NOT NULL,
  `author_name` varchar(255) NOT NULL,
  `rating` int NOT NULL,
  `comment` text NULL,
  `create_time` datetime NOT NULL,
  `reply_text` text NULL,
  `replied_at` datetime NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_snapshot_id_gbp_review_id_unique` (`snapshot_id`, `gbp_review_id`),
  KEY `reviews_shop_id_foreign` (`shop_id`),
  CONSTRAINT `reviews_shop_id_foreign` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**注意**: `snapshot_id` の外部キー制約は存在しないため、上記のSQLには含まれていません。









