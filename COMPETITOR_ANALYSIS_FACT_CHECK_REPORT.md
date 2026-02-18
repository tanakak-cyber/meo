# CompetitorAnalysis 数値・論理整合性 事実確認レポート

## 目的
CompetitorAnalysisのAI分析結果に「数値と論理が矛盾した説明」が混ざっていないかを、コードベースとロジックから事実確認する。

---

## 【確認①】数値比較と文章の整合性チェック

### 確認対象項目
- `review_count`（口コミ総数）
- `review_frequency`（口コミ頻度）
- `post_frequency`（投稿頻度）
- `photo_count`（写真枚数）
- `video_count`（動画本数）

### ① payload上の実数値の取得方法

**コード確認結果：**

#### Controller側のログ出力（`CompetitorAnalysisController::run`）
```php
// 自社データのログ出力（259-267行目）
Log::info('[CompetitorAnalysis] OWN shop data', [
    'shop_name' => $ownShop['shop_name'] ?? null,
    'own_rank' => $ownShop['own_rank'] ?? null,
    'review_count' => $ownShop['review_count'] ?? null,  // ✅ ログ出力あり
    'post_frequency' => $ownShop['post_frequency'] ?? null,  // ✅ ログ出力あり
    'photo_count' => $ownShop['photo_count'] ?? null,  // ✅ ログ出力あり
    'has_menu' => $ownShop['has_menu'] ?? null,
    'has_video' => $ownShop['has_video'] ?? null,
]);

// 競合①データのログ出力（271-278行目）
Log::info('[CompetitorAnalysis] COMPETITOR1 shop data', [
    'shop_name' => $competitor1Shop['shop_name'] ?? null,
    'review_count' => $competitor1Shop['review_count'] ?? null,  // ✅ ログ出力あり
    'post_frequency' => $competitor1Shop['post_frequency'] ?? null,  // ✅ ログ出力あり
    'photo_count' => $competitor1Shop['photo_count'] ?? null,  // ✅ ログ出力あり
    'has_menu' => $competitor1Shop['has_menu'] ?? null,
    'has_video' => $competitor1Shop['has_video'] ?? null,
]);
```

**問題点：**
- ❌ `review_frequency`がログ出力されていない
- ❌ `video_count`がログ出力されていない
- → デバッグ時に「自社 vs 競合」の数値比較が困難

#### normalize()メソッドの処理（328-355行目）
```php
private function normalize(array $data): array
{
    foreach ($data['shops'] as &$shop) {
        // own_rankは数値として保持
        if (isset($shop['own_rank'])) {
            if ($shop['role'] === 'own') {
                $shop['own_rank'] = (int)$shop['own_rank'];
            } else {
                unset($shop['own_rank']);
            }
        }

        foreach ($shop as $key => $value) {
            if ($key === 'own_rank') continue;
            
            if ($value === '' || $value === null) {
                $shop[$key] = '__MISSING__';
            }
            // number項目は0のまま（review_count, photo_count, video_countは0も有効な値）
        }
    }
    return $data;
}
```

**潜在的な問題：**
- ⚠️ 数値項目が`0`の場合、それが「未設定（入力されていない）」なのか「0件（実際に0件）」なのか区別できない
- ⚠️ `normalize()`のコメントには「number項目は0のまま」とあるが、実際には`0`と`__MISSING__`の区別がつかない

### ② AIが出力した文章内の評価内容の検証方法

**コード確認結果：**

#### プロンプト内の数値比較ルール（414-424行目）
```php
【数値比較ルール（絶対遵守）】
1. review_count（口コミ総数）は必ず数値で比較すること
   → 自社の review_count が競合より多い場合、「口コミが不足」とは言わない
2. photo_count（写真枚数）は必ず数値で比較すること
   → 自社の photo_count が競合より多い場合、「写真が不足」とは言わない
3. video_count（動画数）は必ず数値で比較すること
   → 自社の video_count が競合より多い場合、「動画が不足」とは言わない
4. post_frequency（投稿頻度）は頻度の強弱で比較すること
   → 自社の post_frequency が競合より頻繁な場合、「投稿が不足」とは言わない
```

**問題点：**
- ❌ AIの出力結果を自動検証する仕組みがない
- ❌ プロンプトにルールは書かれているが、実際に遵守されているかを検証できない
- ❌ `situation`フィールド内の文章をパースして数値比較の矛盾を検出する機能がない

#### 比較ルール（408-412行目）
```php
【比較ルール（絶対遵守）】
1. 比較は必ず「自社（role=own） vs 競合（role=competitor1/competitor2）」で行うこと
2. 自社が競合より優れている点は「課題」として出さないこと
   → 自社が優れている点は「強み」または「維持要因」として扱う
3. 自社が競合より劣っている点のみを「なぜその順位なのか」の根拠として挙げること
```

**問題点：**
- ❌ このルールが実際に守られているかを検証する仕組みがない
- ❌ `situation`フィールド内で「自社が優れている点を課題として書いている」箇所を自動検出できない

---

## 【確認②】論理破綻パターンの抽出

### コードベースから確認できる潜在的な問題パターン

#### パターン1: role判別の誤り
**確認箇所：** `OpenAIService::analyzeCompetitor()`（30-58行目）

```php
// 分析モードを判定
if (isset($data['analysis_mode'])) {
    $mode = $data['analysis_mode'];
    if ($mode === 'pattern_a') {
        $this->currentAnalysisMode = 'compare_2';
    } elseif ($mode === 'pattern_b') {
        $this->currentAnalysisMode = 'compare_1';
    } else {
        $this->currentAnalysisMode = 'single';
    }
}
```

**潜在的な問題：**
- ⚠️ Controller側で`pattern_a`/`pattern_b`/`pattern_c`として判定しているが、OpenAIに送られるJSON内の`role`フィールドが正しく設定されているかは別問題
- ⚠️ プロンプト内で`role="own"`が自社であることを明示しているが、実際のJSON内で`role`が正しく設定されているかを検証する仕組みがない

#### パターン2: 数値の型変換問題
**確認箇所：** `normalize()`メソッド（328-355行目）

```php
// number項目は0のまま（review_count, photo_count, video_countは0も有効な値）
```

**潜在的な問題：**
- ⚠️ フロントエンドから送られてくる数値が文字列型（`"100"`）の場合、そのまま比較すると文字列比較になる可能性
- ⚠️ `normalize()`で数値型への変換が行われていない（`review_count`, `photo_count`, `video_count`は文字列のままの可能性）

#### パターン3: プロンプト内の比較ルールの曖昧さ
**確認箇所：** `buildPrompt()`（414-424行目）

**問題点：**
- ⚠️ 「自社の review_count が競合より多い場合」という表現はあるが、「多い」の定義が不明確（例：自社100件 vs 競合50件は「多い」か？）
- ⚠️ `post_frequency`は「頻度の強弱」で比較とあるが、「毎日 > 週1 > 月1 > なし」という順序がプロンプト内で明示されていない

---

## 【確認③】原因の切り分け

### A. payloadの値自体が間違っている可能性

**確認箇所：** `CompetitorAnalysisController::run()`（102-323行目）

**検証方法：**
- `Log::info('[CompetitorAnalysis] run payload received (pretty)')`でpayload全体をログ出力している（109-112行目）
- `Log::info('[CompetitorAnalysis] OWN shop data')`で自社の主要項目をログ出力している（259-267行目）

**問題点：**
- ❌ `review_frequency`と`video_count`がログ出力されていないため、payloadの値が正しいかを確認できない
- ❌ フロントエンドから送られてくる値の型（文字列 vs 数値）が不明確

### B. role（own / competitor）の判別が崩れている可能性

**確認箇所：** 
- `CompetitorAnalysisController::run()`（232-256行目）
- `OpenAIService::analyzeCompetitor()`（30-58行目）

**検証方法：**
```php
// Controller側でrole判定
$ownShop = collect($validated['shops'])->firstWhere('role', 'own');
$competitor1Shop = collect($validated['shops'])->firstWhere('role', 'competitor1');
$competitor2Shop = collect($validated['shops'])->firstWhere('role', 'competitor2');
```

**問題点：**
- ⚠️ `firstWhere('role', 'own')`で取得しているが、複数の`role='own'`が存在する場合、最初の1つしか取得されない
- ⚠️ `role`フィールドが正しく設定されているかを検証するバリデーションがない（`'shops.*.role' => 'required|string|in:competitor1,competitor2,own'`はあるが、実際に`own`が1つだけ存在するかはチェックしていない）

### C. OpenAIの推論ミス（比較ルール未定義による誤解釈）の可能性

**確認箇所：** `buildPrompt()`（279-951行目）

**プロンプト内の比較ルール：**
- ✅ 数値比較ルールは明示されている（414-424行目）
- ✅ 比較ルール（自社が優れている点は課題として出さない）も明示されている（408-412行目）

**問題点：**
- ⚠️ プロンプト内で「自社が競合より多い場合」という表現はあるが、「多い」の定義が数値的に明確でない（例：10%多いのか、2倍多いのか）
- ⚠️ `post_frequency`の比較ルールが「頻度の強弱」とあるが、具体的な順序（毎日 > 週1 > 月1 > なし）がプロンプト内で明示されていない
- ⚠️ AIが実際に遵守しているかを検証する仕組みがない

---

## 【確認④】結論の整理

### なぜこのような文章が生成されたか

**推測される原因：**

1. **プロンプトの曖昧さ**
   - 「自社が競合より多い場合」という表現が数値的に明確でない
   - `post_frequency`の比較ルールが「頻度の強弱」とあるが、具体的な順序が明示されていない

2. **検証機能の欠如**
   - AIの出力結果を自動検証する仕組みがない
   - `situation`フィールド内の文章をパースして数値比較の矛盾を検出する機能がない

3. **ログ出力の不完全性**
   - `review_frequency`と`video_count`がログ出力されていない
   - デバッグ時に「自社 vs 競合」の数値比較が困難

### どの情報が誤って使われたか

**潜在的な問題：**

1. **数値の型変換**
   - `normalize()`で数値型への変換が行われていない
   - フロントエンドから送られてくる数値が文字列型の場合、文字列比較になる可能性

2. **0と未設定の区別**
   - `normalize()`で`0`と`__MISSING__`の区別がつかない
   - AIが「0件」を「未設定」と誤解釈する可能性

3. **role判別の不確実性**
   - 複数の`role='own'`が存在する場合、最初の1つしか取得されない
   - `role`フィールドが正しく設定されているかを検証するバリデーションが不十分

### プロンプト設計上の不足点

1. **数値比較の明確性不足**
   - 「多い」「少ない」の定義が数値的に明確でない
   - 例：「自社100件 vs 競合50件」は「多い」か？「自社60件 vs 競合50件」は「多い」か？

2. **頻度比較の順序未定義**
   - `post_frequency`の比較ルールが「頻度の強弱」とあるが、具体的な順序が明示されていない
   - 例：「毎日 > 週1 > 月1 > なし」という順序をプロンプト内で明示すべき

3. **検証機能の欠如**
   - AIの出力結果を自動検証する仕組みがない
   - プロンプトにルールは書かれているが、実際に遵守されているかを検証できない

---

## 推奨される改善点（修正案は出さないが、事実確認のための追加ログ）

### 追加すべきログ出力

1. **全数値項目のログ出力**
   ```php
   Log::info('[CompetitorAnalysis] OWN shop data (full)', [
       'review_count' => $ownShop['review_count'] ?? null,
       'review_frequency' => $ownShop['review_frequency'] ?? null,  // 追加
       'review_rating' => $ownShop['review_rating'] ?? null,  // 追加
       'post_frequency' => $ownShop['post_frequency'] ?? null,
       'photo_count' => $ownShop['photo_count'] ?? null,
       'video_count' => $ownShop['video_count'] ?? null,  // 追加
       'has_video' => $ownShop['has_video'] ?? null,
   ]);
   ```

2. **normalize()後のデータ型ログ**
   ```php
   Log::info('[CompetitorAnalysis] normalized data types', [
       'own_review_count_type' => gettype($normalized['shops'][0]['review_count']),
       'own_review_count_value' => $normalized['shops'][0]['review_count'],
   ]);
   ```

3. **OpenAIへの最終送信データのログ**
   ```php
   Log::info('[CompetitorAnalysis] final payload to OpenAI', [
       'payload' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
   ]);
   ```

---

## まとめ

### 確認できた事実

1. **ログ出力の不完全性**
   - `review_frequency`と`video_count`がログ出力されていない
   - デバッグ時に「自社 vs 競合」の数値比較が困難

2. **検証機能の欠如**
   - AIの出力結果を自動検証する仕組みがない
   - プロンプトにルールは書かれているが、実際に遵守されているかを検証できない

3. **プロンプトの曖昧さ**
   - 「多い」「少ない」の定義が数値的に明確でない
   - `post_frequency`の比較ルールが「頻度の強弱」とあるが、具体的な順序が明示されていない

4. **潜在的な型変換問題**
   - `normalize()`で数値型への変換が行われていない
   - フロントエンドから送られてくる数値が文字列型の場合、文字列比較になる可能性

### 次のステップ（修正は行わないが、事実確認のための追加ログ推奨）

1. 全数値項目のログ出力を追加
2. normalize()後のデータ型をログ出力
3. OpenAIへの最終送信データをログ出力
4. 実際のログを確認して、数値と論理の矛盾を特定












