# Instagram→WordPress自動投稿機能 現状仕様調査結果

## A. 現状仕様まとめ

### 1. 呼び出しフロー
- **入口**: `POST /shops/{shop}/instagram-test` → `InstagramTestController@run`
- **処理順序**:
  1. `InstagramTestController@run` - テスト投稿ボタン押下時のエントリーポイント
  2. `InstagramTestController@crawlWithHtml` - ScrapingBee経由でHTML取得
  3. `InstagramTestController@parseHtmlArticles` - DOM解析で画像URL・テキスト・permalink抽出
  4. `InstagramPostService@checkDuplicate` - 重複チェック
  5. `InstagramPostService@processArticle` - 記事処理（画像URL取得、テキスト整形）
  6. `InstagramPostService@postToGbp` - Google Business Profile APIに投稿
  7. `GbpPost::create` - DBに保存（`media_url`に画像URL保存）
  8. `PostToWordPressJob::dispatch` - WordPress投稿Jobをキューに追加（条件: `wp_post_enabled=true` かつ `wp_post_id=null` かつ `wp_post_status!='success'`）
  9. `PostToWordPressJob@handle` - Job実行
  10. `WordPressService@createPost` - WordPress REST APIに投稿

### 2. WordPressへのHTTPリクエスト生成箇所
- **ファイル**: `app/Services/WordPressService.php`
- **メソッド**: `createPost()` (18-185行目)
- **エンドポイント**: `{wp_base_url}/?rest_route=/wp/v2/{post_type}` (例: `https://fivewood.co.jp/?rest_route=/wp/v2/posts`)
- **認証方式**: Basic認証（`Http::withBasicAuth($username, $appPassword)`）
- **送信payload**:
  ```php
  [
      'title' => $payload['title'],
      'content' => $payload['content'],
      'status' => $postStatus, // 'publish' or 'draft'
      'categories' => $payload['categories'], // カテゴリID配列（現状は空配列）
  ]
  ```
- **Headers**:
  - `Content-Type`: `application/json` (Laravel HTTP Clientが自動設定)
  - `Authorization`: `Basic {base64(username:password)}` (withBasicAuthが自動設定)
  - `User-Agent`: Laravel HTTP Clientのデフォルト
  - `Timeout`: 30秒
  - `Retry`: なし（Laravel HTTP Clientのデフォルト動作）

### 3. 画像の取り扱い仕様
- **画像URL取得箇所**:
  - `InstagramTestController@parseHtmlArticles` (329-342行目): `img.instagram-gallery-item__media` から `src` または `data-src` 属性を取得
  - `InstagramPostService@processArticle` (86-95行目): 一覧から取得した画像URLを優先、なければInstagramページから取得
  - フォールバック: `$shop->blog_fallback_image_url` (98-100行目)
- **DB保存**: `GbpPost.media_url` カラムに画像URLを保存 (251行目)
- **WordPress投稿時の画像処理**: **存在しない**
  - `PostToWordPressJob` では `$gbpPost->media_url` を参照していない
  - `WordPressService@createPost` では `featured_media` を送信していない
  - `postPayload` に画像関連のフィールドが含まれていない
- **content内への画像埋め込み**: **存在しない**
  - `PostToWordPressJob` の `$content` には画像URLやimgタグが含まれていない

### 4. カテゴリの現状仕様
- **カテゴリ抽出**: `PostToWordPressJob@handle` (73-93行目)
  - タイトル・本文から `[カテゴリ]` 形式を正規表現で抽出
  - 抽出後、本文から `[カテゴリ]` を削除
- **カテゴリID取得**: `PostToWordPressJob@getCategoryIds` (175-188行目)
  - **常に空配列を返す**（実装されていない）
  - コメント: "現時点では空配列を返す（カテゴリは後で手動設定）"
- **WordPress側でのカテゴリ作成/紐付け**: **実装されていない**
  - `WordPressService` にカテゴリ取得・作成機能がない
  - `postPayload['categories']` は常に空配列

### 5. 失敗時の挙動
- **失敗時のDB更新**: `PostToWordPressJob@handle` (136-145行目)
  - `wp_post_status = 'failed'` を保存
  - `wp_post_id` は更新しない（nullのまま）
- **リトライ条件**: `ShopController@retryWordPressPost` (3416-3452行目)
  - `wp_post_enabled = true`
  - `wp_post_id = null` (既に投稿済みの場合はエラー)
  - `wp_post_status = 'failed'` または `null`
- **リトライ回数**: **制限なし**（手動で再実行するまで）
  - Laravel Queueの `tries` 設定に依存（デフォルトは無制限）
- **失敗時の保存内容**:
  - `wp_post_status`: `'failed'`
  - `wp_post_id`: `null` (更新しない)
  - `wp_posted_at`: 更新しない

## B. 呼び出しフロー図

```
[テスト投稿ボタン押下]
    ↓
POST /shops/{shop}/instagram-test
    ↓
InstagramTestController@run (36行目)
    ↓
InstagramTestController@crawlWithHtml (145行目)
    ├─ ScrapingBeeFetcher->fetchHtml() (158行目)
    └─ InstagramTestController@parseHtmlArticles (235行目)
        └─ 画像URL・テキスト・permalink抽出
    ↓
InstagramPostService@checkDuplicate (90行目)
    ↓
InstagramPostService@processArticle (104行目)
    ├─ fetchArticleWithGuzzle() (83行目) - Instagramページ取得
    ├─ 画像URL取得 (86-100行目)
    ├─ テキスト取得 (133-142行目)
    └─ buildGbpSummary() (145行目)
    ↓
InstagramPostService@postToGbp (172行目)
    ├─ GoogleBusinessProfileService->createLocalPost() (223行目)
    └─ GbpPost::create() (243行目)
        └─ media_url に画像URL保存 (251行目)
    ↓
PostToWordPressJob::dispatch() (266行目) [条件: wp_post_enabled=true]
    ↓
[キュー実行]
    ↓
PostToWordPressJob@handle (34行目)
    ├─ テキスト整形 (65-99行目)
    │   ├─ タイトル抽出（1行目）
    │   ├─ 本文抽出（2行目以降）
    │   ├─ [カテゴリ] 抽出・削除
    │   └─ Instagramリンク追加
    ├─ getCategoryIds() (102行目) → 常に空配列
    └─ WordPressService@createPost() (114行目)
        └─ Http::withBasicAuth()->post() (107行目)
            └─ POST {wp_base_url}/?rest_route=/wp/v2/posts
                └─ payload: {title, content, status, categories: []}
    ↓
[成功時]
    └─ GbpPost->update(['wp_post_id', 'wp_posted_at', 'wp_post_status' => 'success'])
[失敗時]
    └─ GbpPost->update(['wp_post_status' => 'failed'])
```

## C. 関連ファイル一覧

### エントリーポイント
- `routes/web.php` (74行目, 156行目): `POST /shops/{shop}/instagram-test` → `InstagramTestController@run`

### Controller
- `app/Http/Controllers/InstagramTestController.php`
  - `run()` (36行目): テスト投稿ボタン押下時の処理
  - `crawlWithHtml()` (145行目): ScrapingBee経由でHTML取得
  - `parseHtmlArticles()` (276行目): DOM解析で画像URL・テキスト・permalink抽出

- `app/Http/Controllers/ShopController.php`
  - `retryWordPressPost()` (3416行目): WordPress再投稿処理

### Service
- `app/Services/InstagramPostService.php`
  - `checkDuplicate()` (29行目): 重複チェック
  - `processArticle()` (56行目): 記事処理（画像URL取得、テキスト整形）
  - `postToGbp()` (193行目): Google Business Profile APIに投稿
  - `fetchArticleWithGuzzle()` (296行目): Instagramページ取得
  - `buildGbpSummary()` (327行目): GBP投稿用summary生成

- `app/Services/WordPressService.php`
  - `createPost()` (18行目): WordPress REST APIに投稿
  - `buildRestUrl()` (206行目): `?rest_route=` 形式のエンドポイント生成
  - `getWordPressUrl()` (193行目): WordPressサイトURL取得
  - `getWordPressUsername()` (222行目): WordPressユーザー名取得
  - `getWordPressAppPassword()` (233行目): Application Password取得

### Job
- `app/Jobs/PostToWordPressJob.php`
  - `handle()` (34行目): WordPress投稿Job実行
  - `getCategoryIds()` (175行目): カテゴリID取得（実装されていない、常に空配列）

### Model
- `app/Models/GbpPost.php`
  - `$fillable`: `media_url` を含む（24行目）

## D. 現状仕様の問題点（画像が送れない理由）

### 根本原因
1. **`PostToWordPressJob` で画像URLを使用していない**
   - `$gbpPost->media_url` を参照していない（65-99行目）
   - 画像URLを `WordPressService@createPost` に渡していない

2. **`WordPressService@createPost` で画像を送信していない**
   - `postPayload` に `featured_media` フィールドが含まれていない（70-75行目）
   - WordPress REST APIの `featured_media` パラメータを送信していない

3. **画像アップロード処理が存在しない**
   - WordPress REST APIの `/wp/v2/media` エンドポイントを使用していない
   - 画像URLを直接 `featured_media` に指定する処理もない

### コード根拠
- `app/Jobs/PostToWordPressJob.php` (114行目):
  ```php
  $result = $wordPressService->createPost($shop, [
      'title' => $title,
      'content' => $content,
      'categories' => $categoryIds, // 画像URLが含まれていない
  ]);
  ```

- `app/Services/WordPressService.php` (70-75行目):
  ```php
  $postPayload = [
      'title' => $payload['title'] ?? '',
      'content' => $payload['content'] ?? '',
      'status' => $postStatus,
      'categories' => $payload['categories'] ?? [],
      // featured_media が含まれていない
  ];
  ```

## E. 画像対応を入れるなら、既存構成に沿ってどこへ実装するのが最短か（候補2案）

### 案1: WordPressService に画像アップロード機能を追加（推奨）

**実装箇所**:
- `app/Services/WordPressService.php` に新規メソッド追加:
  - `uploadMedia(Shop $shop, string $imageUrl): ?int` - 画像をアップロードしてメディアIDを取得
- `WordPressService@createPost` を修正:
  - `$payload['image_url']` が存在する場合、`uploadMedia()` を呼び出し
  - 取得したメディアIDを `$postPayload['featured_media']` に設定

**修正ファイル**:
- `app/Services/WordPressService.php`
  - `uploadMedia()` メソッド追加（新規）
  - `createPost()` メソッド修正（70-75行目付近）

**呼び出し側の修正**:
- `app/Jobs/PostToWordPressJob.php` (114行目):
  ```php
  $result = $wordPressService->createPost($shop, [
      'title' => $title,
      'content' => $content,
      'categories' => $categoryIds,
      'image_url' => $gbpPost->media_url, // 追加
  ]);
  ```

**メリット**:
- WordPress REST APIの標準的な方法（`/wp/v2/media` でアップロード → `featured_media` にID指定）
- 既存の `WordPressService` に機能を集約できる
- 他の箇所からも再利用可能

**デメリット**:
- 画像アップロード処理の実装が必要（HTTPリクエストで画像をダウンロード → WordPressにアップロード）

### 案2: PostToWordPressJob で画像URLをcontentに埋め込む（簡易実装）

**実装箇所**:
- `app/Jobs/PostToWordPressJob.php` (95-99行目付近) を修正:
  ```php
  // 記事末尾に「詳細はInstagramにて」リンクを追加
  $instagramLink = $gbpPost->source_url ?? '';
  if ($instagramLink) {
      $content .= "\n\n<p><a href=\"{$instagramLink}\" target=\"_blank\" rel=\"noopener noreferrer\">詳細はInstagramにて</a></p>";
  }
  
  // 画像をcontentに埋め込む（追加）
  if ($gbpPost->media_url) {
      $content = "<p><img src=\"{$gbpPost->media_url}\" alt=\"{$title}\" /></p>\n\n" . $content;
  }
  ```

**修正ファイル**:
- `app/Jobs/PostToWordPressJob.php` (95-99行目付近)

**メリット**:
- 実装が簡単（WordPress REST APIの変更不要）
- 画像アップロード処理が不要

**デメリット**:
- `featured_media` が設定されない（WordPressのアイキャッチ画像として認識されない）
- 画像が外部URLのまま（WordPress側で画像を管理できない）
- 画像URLが無効になった場合、表示されなくなる


