<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private $apiKey;
    private $apiUrl = 'https://api.openai.com/v1/chat/completions';
    protected string $currentAnalysisMode = 'single';
    private array $currentData = [];

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
        
        if (empty($this->apiKey)) {
            throw new \Exception('OPENAI_API_KEY is not set in .env file');
        }
    }

    /**
     * 競合分析を実行
     * 
     * @param array $data STEP1の入力データ（keyword と shops 配列）
     * @return array 分析結果
     */
    public function analyzeCompetitor(array $data): array
    {
        // 分析モードを判定してプロパティにセット
        // 注意: $data['analysis_mode'] が既に設定されている場合はそれを使用（Controller側で判定済み）
        if (isset($data['analysis_mode'])) {
            // Controller側で判定済みの分析モードを使用（pattern_a/b/c → compare_2/1/single に変換）
            $mode = $data['analysis_mode'];
            if ($mode === 'pattern_a') {
                $this->currentAnalysisMode = 'compare_2';
            } elseif ($mode === 'pattern_b') {
                $this->currentAnalysisMode = 'compare_1';
            } else {
                $this->currentAnalysisMode = 'single';
            }
        } else {
            // フォールバック: 自前で判定
            $competitor1Exists = false;
            $competitor2Exists = false;
            
            foreach ($data['shops'] ?? [] as $shop) {
                if (($shop['role'] ?? '') === 'competitor1' && !empty($shop['shop_name'] ?? '')) {
                    $competitor1Exists = true;
                }
                if (($shop['role'] ?? '') === 'competitor2' && !empty($shop['shop_name'] ?? '')) {
                    $competitor2Exists = true;
                }
            }
            
            // 分析モードを設定
            if ($competitor1Exists && $competitor2Exists) {
                $this->currentAnalysisMode = 'compare_2';
            } elseif ($competitor1Exists) {
                $this->currentAnalysisMode = 'compare_1';
            } else {
                $this->currentAnalysisMode = 'single';
            }
        }
        
        // 店舗データを保持（comparison_table生成時に使用）
        $this->currentData = $data;
        
        Log::info('[CompetitorAnalysis] analysis mode set', [
            'mode' => $this->currentAnalysisMode,
            'input_mode' => $data['analysis_mode'] ?? 'not_set',
        ]);
        
        $prompt = $this->buildPrompt($data);

        try {
            $model = 'gpt-4o-mini';
            $promptLength = mb_strlen($prompt);

            Log::info('[CompetitorAnalysis] OpenAI request start', [
                'model' => $model,
                'prompt_length' => $promptLength,
                'api_url' => $this->apiUrl,
            ]);

            // プロンプトが短すぎる場合はエラー
            if ($promptLength === 0 || $promptLength < 100) {
                Log::error('[CompetitorAnalysis] prompt too short before sending', [
                    'length' => $promptLength,
                ]);
                throw new \Exception('Prompt is too short or empty before sending to OpenAI');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(120)->post($this->apiUrl, [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'response_format' => ['type' => 'json_object'],
            ]);

            Log::info('[CompetitorAnalysis] OpenAI response raw', [
                'status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (!$response->successful()) {
                Log::error('[CompetitorAnalysis] OpenAI API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('OpenAI API request failed: ' . $response->body());
            }

            $responseData = $response->json();
            $content = $responseData['choices'][0]['message']['content'] ?? null;

            // 【① OpenAIの生レスポンスを必ずログ出力】
            Log::info('[CompetitorAnalysis] [OpenAI RAW RESPONSE]', [
                'raw_response' => $content,
                'raw_response_length' => $content ? mb_strlen($content) : 0,
                'raw_response_preview' => $content ? mb_substr($content, 0, 500) : null,
            ]);

            if (!$content) {
                Log::error('[CompetitorAnalysis] No content in OpenAI response', [
                    'response_data' => $responseData,
                ]);
                throw new \Exception('No content in OpenAI response');
            }

            // 【④ 一時的な安全策】JSONをパース（失敗時も500にせずエラーメッセージを返す）
            $analysis = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('[CompetitorAnalysis] JSON parse error', [
                    'json_error' => json_last_error_msg(),
                    'json_error_code' => json_last_error(),
                    'raw_content' => $content,
                    'raw_content_length' => mb_strlen($content),
                ]);
                // 【④ 一時的な安全策】500にせず、エラーメッセージを返す
                throw new \Exception('OpenAI response format error: ' . json_last_error_msg() . '. Raw response logged.');
            }

            Log::info('[CompetitorAnalysis] JSON parse success', [
                'analysis_keys' => array_keys($analysis),
                'analysis_structure' => $this->analyzeActualStructure($analysis),
            ]);

            // 【② 想定している正しい構造を再確認】必須フィールドのチェック（分析モードに応じて）
            // 【修正④ 存在しない場合の保険】getExpectedStructure()が存在しない場合のエラーハンドリング
            try {
                $expectedStructure = $this->getExpectedStructure($this->currentAnalysisMode);
            } catch (\Throwable $e) {
                Log::error('[CompetitorAnalysis] getExpectedStructure failed', [
                    'error' => $e->getMessage(),
                    'analysis_mode' => $this->currentAnalysisMode,
                ]);
                throw new \Exception('Analysis structure undefined: ' . $e->getMessage());
            }
            
            // 【修正④ 存在しない場合の保険】getAnalysisStructure()は必ず存在するためnullチェック不要
            $formatDefinition = $this->getAnalysisStructure();
            Log::info('[CompetitorAnalysis] Expected structure', [
                'analysis_mode' => $this->currentAnalysisMode,
                'expected_keys' => $expectedStructure,
                'format_definition' => $formatDefinition,
            ]);
            
            if ($this->currentAnalysisMode === 'single') {
                // single の場合は comparison_table は null で OK、代わりに self_checklist が必要
                $requiredKeys = ['self_checklist', 'rank_summary', 'meo_premise', 'situation', 'differentiation_opportunities', 'initial_settings', 'operations', 'execution_plan_initial', 'execution_plan_operations'];
                $missingKeys = [];
                foreach ($requiredKeys as $key) {
                    if (!isset($analysis[$key])) {
                        $missingKeys[] = $key;
                    }
                }
                
                if (!empty($missingKeys)) {
                    Log::error('[CompetitorAnalysis] Invalid analysis structure (single)', [
                        'analysis_mode' => $this->currentAnalysisMode,
                        'expected_keys' => $requiredKeys,
                        'actual_keys' => array_keys($analysis),
                        'missing_keys' => $missingKeys,
                        'analysis_structure' => $this->analyzeActualStructure($analysis),
                    ]);
                    throw new \Exception('Invalid analysis structure from OpenAI (single). Missing keys: ' . implode(', ', $missingKeys));
                }
                
                // comparison_table が null でない場合は警告
                if (isset($analysis['comparison_table']) && $analysis['comparison_table'] !== null) {
                    Log::warning('[CompetitorAnalysis] comparison_table should be null in single mode', [
                        'comparison_table_value' => $analysis['comparison_table'],
                    ]);
                }
            } else {
                // compare_2 / compare_1 の場合は comparison_table は optional（null でも OK）
                // comparison_table が null の場合は後で自動生成するため、必須から除外
                $requiredKeys = ['rank_summary', 'meo_premise', 'situation', 'differentiation_opportunities', 'initial_settings', 'operations', 'execution_plan_initial', 'execution_plan_operations'];
                $missingKeys = [];
                foreach ($requiredKeys as $key) {
                    if (!isset($analysis[$key])) {
                        $missingKeys[] = $key;
                    }
                }
                
                if (!empty($missingKeys)) {
                    Log::error('[CompetitorAnalysis] Invalid analysis structure', [
                        'analysis_mode' => $this->currentAnalysisMode,
                        'expected_keys' => $requiredKeys,
                        'actual_keys' => array_keys($analysis),
                        'missing_keys' => $missingKeys,
                        'analysis_structure' => $this->analyzeActualStructure($analysis),
                        'comparison_table_exists' => isset($analysis['comparison_table']),
                        'comparison_table_value' => $analysis['comparison_table'] ?? null,
                    ]);
                    throw new \Exception('Invalid analysis structure from OpenAI. Missing keys: ' . implode(', ', $missingKeys));
                }
                
                // comparison_table が null または未定義の場合は警告（後で自動生成される）
                if (!isset($analysis['comparison_table']) || $analysis['comparison_table'] === null) {
                    Log::info('[CompetitorAnalysis] comparison_table is null, will be auto-generated', [
                        'analysis_mode' => $this->currentAnalysisMode,
                    ]);
                }
            }

            // situationの文字数チェック（最低400文字）
            $situationLength = mb_strlen($analysis['situation'] ?? '');
            if ($situationLength < 400) {
                Log::warning('[CompetitorAnalysis] situation too short', [
                    'length' => $situationLength,
                    'required' => 400,
                ]);
            }

            // 【② comparison_table が null または未定義の場合は自動生成】
            if ($this->currentAnalysisMode !== 'single') {
                if (!isset($analysis['comparison_table']) || $analysis['comparison_table'] === null || trim($analysis['comparison_table']) === '') {
                    Log::info('[CompetitorAnalysis] comparison_table is null, auto-generating', [
                        'analysis_mode' => $this->currentAnalysisMode,
                    ]);
                    $analysis['comparison_table'] = $this->buildComparisonTable($this->currentAnalysisMode, $this->currentData);
                    Log::info('[CompetitorAnalysis] comparison_table auto-generated', [
                        'length' => mb_strlen($analysis['comparison_table']),
                    ]);
                }
            }

            Log::info('[CompetitorAnalysis] Analysis structure valid');

            return $analysis;

        } catch (\Throwable $e) {
            Log::error('[CompetitorAnalysis] OpenAI analysis error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * プロンプトを構築
     */
    private function buildPrompt(array $data): string
    {
        $prompt = <<<'PROMPT'
あなたはMEO（Googleビジネスプロフィール）専門の
コンサルタントチーム5名です。

以下のJSONは
人間が実際に競合調査して入力した
「完全な生データ」です。

【重要：データ構造の理解】
- role="own" の店舗が「自社」です
- role="competitor1" の店舗が「上位1位の競合」です（存在する場合）
- role="competitor2" の店舗が「上位2位の競合」です（存在する場合）
- own_rank は「このキーワードでの自社の現在順位（数値）」です
- この own_rank 位になっている理由を説明することが目的です

【分析モードの判定（絶対遵守）】
現在の分析モードに応じて、以下の3パターンで分析を切り替えること：

・compare_2（競合①と競合②の両方が存在する場合）：
  → 分析モード：「自社 vs 競合① vs 競合②」
  → 比較は常に自社基準
  → 両競合との差分を横断的に分析
  → 比較サマリー表は「自社 / 競合① / 競合②」の3列で出力

・compare_1（競合①のみ存在する場合）：
  → 分析モード：「自社 vs 競合①」
  → 競合②の存在を前提にした表現は禁止
  → 「上位2社」という表現は禁止
  → 「上位競合1社」として分析する
  → 比較サマリー表は「自社 / 競合①」の2列で出力

・single（競合が存在しない場合）：
  → 分析モード：「自社単独分析」
  → 比較表現は一切禁止（「競合より劣っている」などの表現は使わない）
  → 以下の観点で分析する：
    - 業界ベストプラクティス
    - GBP機能の網羅性
    - 上位表示店舗に一般的に見られる要素
  → 比較サマリー表は出さない
  → 代わりに「自社チェックリスト型サマリー」を出力（self_checklist）
  → 出力例：「競合データが未入力のため、上位表示店舗の一般的傾向と業界標準をもとに分析しています」

【分析時の表現ルール（重要）】
- 存在しない競合をあたかも存在するかのように扱わない
- 比較不能な項目を無理に比較しない
- 必ず「今の入力条件（analysis_mode）」を前提にした分析にする
- single の場合は「比較」ではなく「評価・チェック」として分析する

【業界説明の扱い（絶対遵守）】
- industry_description はユーザーが入力した「業界の説明」です
- この industry_description を絶対的な前提として分析すること
- 業種推測・憶測は一切禁止
- industry_description 以外から業種を推測してはいけない
- industry_description の内容を前提に、その業界における「あるべきMEO設定」を評価すること

【全入力項目の完全走査（絶対義務）】
- JSONに含まれる全フィールドを必ず確認すること
- 主要項目・サブ項目の区別なく、すべて分析対象とする
- "__MISSING__" や "なし" の項目も「なぜ設定されていないか」「設定する意味があるか」を必ず言及すること
- 入力項目を無視する分析は禁止

【必須評価項目（省略禁止・全項目必ず言及）】
以下の全項目は、いずれか一つでも触れない分析は禁止です。

1. レビュー
   - review_count（口コミ総数：数値で比較）
   - review_rating（評価：数値で比較）
   - monthly_review_count（月間の口コミ数：数値で比較）

2. 投稿
   - monthly_post_count（月間の投稿数：数値で比較）

3. 写真
   - photo_count（写真枚数：数値で比較）
   - photo_atmosphere（雰囲気がわかるか：わかる/わからない）

4. 動画（最重要・必須評価）
   - has_video（動画の有無：ある/なし）
   - video_count（動画本数：数値で比較）
   ※ 近年のMEOでは動画の重要度が写真・投稿以上に高い
   ※ 自社に動画が無い／少ない場合、それが順位に与えている影響を必ず説明
   ※ 競合が動画を活用している場合は明確な差分として指摘

5. ビジネス説明文（必須評価）
   - business_description の有無
   - 情報量・具体性・専門性の評価
   - カテゴリ・キーワードとの一致度
   ※ 説明文の質・網羅性に必ず言及すること

6. Google機能の活用（必須評価）
   - has_menu（メニュー有無）
   - reservation_link（予約リンク）
   - qa（Q&A）
   - service_link（サービスリンク）

7. NAP・実在性（必須評価）
   - website_nap_match（WEBサイトNAP一致）
   - sns_nap_match（SNS NAP一致）

8. 自社順位（own_rank）
   - なぜその順位なのかを上記1〜7の項目と結びつけて説明

9. サブ項目（属性情報・省略禁止・全項目必ず評価）
   以下の属性情報は、自社・競合が「なし / 未設定」の場合でも必ず評価対象とする：
   - barrier_free（バリアフリー）
   - plan（プラン）
   - pet（ペット）
   - child（子供）
   - customer_segment（客層）
   - payment_method（決済方法）
   - feature（特徴）
   - meal（食事）
   - parking（駐車場）
   - その他 GBP 属性項目すべて
   
   【属性情報の評価ルール（必須）】
   - 各属性について、自社が「なし / 未設定」でも必ず評価対象とする
   - 競合が「なし / 未設定」でも必ず評価対象とする
   - 特に「競合が設定していない属性」は差別化チャンスとして「すぐ設定すべき項目」として提案する
   - バリアフリー"だけ"に触れるのは禁止（他の属性も必ず含める）
   - 属性情報は「まとめて一括評価」してもよいが、提案として必ず明示すること
   
   出力例（必須）：
   「バリアフリーや決済方法などの属性項目は、競合店舗では未設定のケースが多いため、
    自社が先に網羅的に設定することでGoogle機能活用度と情報充実度で優位に立てます」

【比較ルール（絶対遵守）】
1. 比較は必ず「自社（role=own） vs 競合（role=competitor1/competitor2）」で行うこと
2. 自社が競合より優れている点は「課題」として出さないこと
   → 自社が優れている点は「強み」または「維持要因」として扱う
3. 自社が競合より劣っている点のみを「なぜその順位なのか」の根拠として挙げること

【数値比較ルール（絶対遵守・厳格適用）】
【修正⑥】比較ルールの明文化：数値比較を厳格に定義

1. 数値項目の比較ルール（必ず数値型として比較すること）：
   - 自社の数値 > 競合の数値 → 「優れている」→ 順位低下の原因として使用禁止
   - 自社の数値 < 競合の数値 → 「劣っている」→ 順位低下の原因として使用可能
   - 自社の数値 = 競合の数値 → 「同等」→ 順位への影響は限定的と表現

2. 対象数値項目：
   - review_count（口コミ総数）
   - photo_count（写真枚数）
   - video_count（動画数）
   - monthly_review_count（月間の口コミ数）※純粋な数値比較項目
   - monthly_post_count（月間の投稿数）※純粋な数値比較項目

3. 【Critical】monthly_review_count / monthly_post_count の厳格な扱い：
   これらの項目は「純粋な数値比較項目」として扱うこと。以下を厳守：
   
   - 自社.monthly_review_count > 競合.monthly_review_count の場合：
     → 「劣っている」「不足」「弱い」等のネガティブ評価表現を使用してはならない
     → 必ず「優位」「問題ではない」「順位低下要因ではない」と明示すること
   
   - 自社.monthly_post_count > 競合.monthly_post_count の場合：
     → 「劣っている」「不足」「弱い」等のネガティブ評価表現を使用してはならない
     → 必ず「優位」「問題ではない」「順位低下要因ではない」と明示すること
   
   - 数値比較結果と矛盾する文章生成は禁止
     → 矛盾が発生する場合は「この項目は順位に影響していない」と処理すること
   
   - 自社の数値が競合より大きい場合、その項目について言及する際は：
     「月間の口コミ数（投稿数）は自社がX件、競合がY件であり自社が上回っている。
     この点は自社の強みであり、順位低下の要因ではない。」
     という形式で必ず明示すること
   - 逆接表現（「しかし」「だが」「一方で」「ですが」）を使用禁止
   - 「事実→評価→結論」の固定構造で完結させること

4. 比較時の注意事項：
   - 数値は必ず数値型として比較すること（文字列比較禁止）
   - 0 と __MISSING__ は異なる意味を持つため、区別して評価すること
     → 0：実際に0件であることを意味する（例：レビュー0件、写真0枚）
     → __MISSING__：未設定・未入力であることを意味する（データが存在しない）
   - 自社が優れている項目を「不足」「課題」として表現することは禁止
   - 「多い」「少ない」などの表現は必ず数値比較に基づくこと

5. 表現ルール（最終固定）：
   ■ 自社 > 競合 の場合（Critical：誤判定完全遮断）
   使用可能：
   - 優位
   - 強み
   - 問題ではない
   - 順位低下要因ではない
   - 維持要因
   - 「自社が上回っている」
   - 「優位である」
   - 「順位低下の要因ではない」
   
   使用禁止（Critical）：
   - 劣っている
   - 不足している
   - 弱い
   - 改善が必要
   - 課題がある
   - 競合の方が多い／高い
   - 競合を持ち上げる評価（「信頼性を高める要因」等の競合称賛文）
   - 自社が上回っている項目で競合を称賛することは禁止
   
   ■ 自社 = 競合 の場合（Critical：同等評価の文章制御）
   使用可能：
   - 同等
   - 同水準
   - 影響は限定的
   - 順位差の要因ではない
   
   使用禁止（Critical）：
   - 改善要求（強化が必要、弱い、不足、課題）を記述禁止
   - 逆接表現（しかし、だが、ですが、一方で）を使用禁止
   - 「自社は〜がない」
   - 「改善が必要」
   - 「強化が求められる」
   - 「不足している」
   
   ■ 自社 < 競合 の場合
   - 劣っている
   - 改善余地がある
   - 順位低下要因になり得る

6. has_menu / reservation_link / qa / has_video などの有無は機能活用度として比較すること
   → 自社が「ある」で競合が「なし」の場合、それを課題として挙げない

【変更④】比較結果の確定タイミング（Critical）

▼ 比較結果の確定ルール（Critical）

monthly_review_count / monthly_post_count を含む
すべての数値比較項目について、
文章生成前に必ず以下の比較結果を確定させること：

・自社 > 競合
・自社 = 競合
・自社 < 競合

この比較結果は「確定値」であり、
文章生成中に再解釈・変更することは禁止。

文章は必ず
「確定した比較結果の理由説明」
としてのみ生成すること。

比較手順（必須）：
1. 自社の数値と競合の数値を数値型として比較
2. 比較結果（> / = / <）を確定
3. 確定した比較結果に基づいて文章を生成

禁止事項：
- 文章生成中に比較結果を変更・再解釈することは禁止
- 比較結果を後から変更することは禁止
- 数値比較と矛盾する文章を生成することは禁止

【変更⑤】矛盾発生時のフォールバック処理（Critical）

▼ 矛盾発生時の処理ルール（Critical）

以下のようなケースを「矛盾」と定義する：

- 数値比較結果が「自社 > 競合」なのに、
  文章内で「劣っている」「不足している」
  といったネガティブ表現が出る場合

- 数値比較結果が「自社 < 競合」なのに、
  根拠なく「問題ない」「影響はない」と書かれる場合

▼ 矛盾が発生した場合の処理：

- 当該項目は以下の定型表現で処理すること：

  「この項目は順位に影響していない」

- 矛盾したまま説明を継続することは禁止
- 矛盾を"言い換え"で誤魔化す行為は禁止

矛盾検出時の処理手順：
1. 矛盾を検出した時点で、当該項目の説明を停止
2. 当該項目は「この項目は順位に影響していない」として処理
3. 矛盾したまま説明を続行することは禁止

例：
- 自社の monthly_review_count が 4、競合が 1 なのに
  「自社は口コミ頻度で劣っている」と書くことは禁止
- この場合、「月間の口コミ数は順位に影響していない」と処理

【変更⑥】項目評価の独立性ルール（P0 / 最重要）

▼ ルール（Critical）

- 各評価項目は必ず独立して評価すること
- 他項目が劣っていても、
  自社 > 競合 と確定した項目を
  ネガティブ文脈に巻き込んではならない
- 評価語は当該項目の比較結果のみに基づいて決定する

▼ 具体例

NG例（禁止）：
- 自社の monthly_review_count = 4（競合 = 1）で「優位」と確定しているのに、
  他の項目（例：photo_count）が劣っていることを理由に
  「月間の口コミ数も改善が必要」と書くことは禁止

OK例（正しい）：
- 自社の monthly_review_count = 4（競合 = 1）で「優位」と確定している場合、
  他の項目の評価とは独立して
  「月間の口コミ数は競合より多く、この点では優位であるため順位低下の要因ではない」
  と評価すること

▼ 独立性の保証

- 各数値項目（review_count, photo_count, video_count, monthly_review_count, monthly_post_count）は
  それぞれ独立して比較・評価すること
- 1つの項目が劣っているからといって、
  他の優位な項目をネガティブに評価することは禁止
- 評価は必ず「当該項目の比較結果」のみに基づくこと

【Critical：文章構成ルール（最重要）】

▼ ① 逆接表現の使用制限（Critical）

- 自社 > 競合 と確定した項目については、
  「しかし」「だが」「一方で」「ですが」などの逆接表現を使用禁止
- 逆接は「弱み（自社 < 競合）」の説明にのみ使用可能
- 自社 > 競合 の項目で逆接を使用することは、論理破綻として扱う

▼ ② 強み項目の固定構造（必須）

自社 > 競合 の項目は、必ず以下の順序で完結させること：

【事実】数値の提示
【評価】優位・強みであることの明示
【結論】順位低下要因ではない、と明言

例（正しい形式）：
「月間の口コミ数は自社が4件、競合が1件であり自社が上回っている。
この点は自社の強みであり、順位低下の要因ではない。」

禁止例（NG）：
×「月間の口コミ数は多いですが、順位に影響していません」
×「自社は数値で上回っているが、影響要因ではありません」
×「強みだが影響していない」

▼ ③ 1文1評価軸ルール（Critical）

- 1つの文の中で、
  強み（自社 > 競合）と弱み（自社 < 競合）を混在させることを禁止
- 評価軸（レビュー数・評価・月間口コミ数など）は必ず分離して記述
- 1つの評価軸につき、1つの評価結果のみを記述すること

禁止例（NG）：
×「月間の口コミ数は多いが、写真数は少ない」
×「レビュー数は上回っているが、投稿頻度は劣っている」

正しい例（OK）：
○「月間の口コミ数は自社が4件、競合が1件であり自社が上回っている。この点は自社の強みであり、順位低下の要因ではない。」
○「写真数は自社が10枚、競合が50枚であり自社が劣っている。この点は改善の余地がある。」

▼ ④ 意味のない逆接・中立文の禁止（Critical）

以下のような「情報を言っているだけ」の文を禁止：

×「〜ですが、〜であり、順位に影響していません」
×「高いですが、影響要因ではありません」
×「多いですが、問題ではありません」

→ 必ず「なぜ影響していないか」を論理的に閉じること
→ 自社 > 競合 の項目は、逆接を使わずに「事実→評価→結論」で完結させること

正しい例（OK）：
○「月間の口コミ数は自社が4件、競合が1件であり自社が上回っている。この点は自社の強みであり、順位低下の要因ではない。」

▼ ⑤ 「同等（自社 = 競合）」の必須文章構造（Critical）

比較結果が「自社 = 競合」の場合、以下のいずれかで必ず完結させること：

・「同等であり、この点は順位差の要因ではない」
・「同水準のため、順位に与える影響は限定的である」

禁止例（NG）：
×「自社は月間の口コミ数が同等だが、改善が必要」
×「同水準であるが、強化が求められる」
×「同等である一方で、不足している」

正しい例（OK）：
○「月間の口コミ数は自社が4件、競合が4件であり同等である。この点は順位差の要因ではない。」
○「月間の投稿数は自社が4件、競合が4件であり同水準のため、順位に与える影響は限定的である。」

▼ ⑥ フィールド参照の一貫性（Critical）

- 文章生成時に参照する数値は、比較判定に使用したフィールドと必ず同一のものを使用すること
- 別フィールド参照は禁止
- 例：monthly_review_count で比較した場合は、monthly_review_count の値のみを参照すること
- review_count や review_rating などの別フィールドを混在させて評価することは禁止

▼ ⑦ 同等評価の矛盾検出時のフォールバック（Critical）

「同等（自社 = 競合）」と判定された項目で、
ネガティブ評価文が生成されそうな場合は、
当該項目を以下の一文で強制終了する：

「この項目は自社・競合で同等のため、順位への影響は限定的である。」

これ以上の説明は禁止。

【Critical追加：レビュー数・評価の誤判定完全遮断】

▼ ① 比較結果が「自社 > 競合」の場合の厳格ルール（Critical）

review_count / review_rating を含むすべての比較項目について、以下を厳守すること：

使用禁止表現（Critical）：
- 劣っている
- 不足している
- 弱い
- 改善が必要
- 課題がある
- 競合の方が多い／高い

必須表現ルール：
- 「自社が上回っている」
- 「優位である」
- 「順位低下の要因ではない」

▼ ② 数値の主語固定ルール（Critical）

数値を記述する場合は必ず以下の形式を守ること：

「自社：◯◯、競合：◯◯」

この順序を逆にしてはならない。
大小関係と矛盾する形容は禁止。

正しい例（OK）：
○「レビュー数は自社：100件、競合：50件であり自社が上回っている。この点は自社の強みであり、順位低下の要因ではない。」
○「レビュー評価は自社：4.5、競合：4.0であり自社が上回っている。この点は優位である。」

禁止例（NG）：
×「競合：50件、自社：100件であり〜」（順序が逆）
×「レビュー数は多いが、競合の方が信頼性が高い」（大小関係と矛盾）

▼ ③ 矛盾即時フォールバック（Critical）

以下の矛盾を検出した場合：
- 自社 > 競合 なのにネガティブ評価
- 数値大小と文章評価が逆

→ 当該項目の説明を即時中断し、
以下の1文で強制終了する：

「本項目は自社が競合を上回っているため、順位低下の要因ではない。」

これ以上の説明は禁止。

▼ ④ 禁止事項（絶対）

- 競合を持ち上げる評価を、自社が上回っている項目で行うことを禁止
- 「信頼性を高める要因」等の競合称賛文を自社優位項目で使用禁止
- 自社 > 競合 と確定した項目で、競合を称賛・持ち上げる表現は一切禁止

【サブ項目の評価ルール（重要）】
- 競合が設定していない項目は「自社が先に設定すれば差別化になる要素」として評価する
- 設定の有無だけでなく「その業種において設定する意味があるか」をMEO視点で説明する
- "__MISSING__" や "なし" は「未設定＝劣っている」ではなく「未設定＝今やると差別化できる」と解釈する

例：
- バリアフリー：実利用が少なくてもGoogleの実在性・安心感シグナルとして有効
- 決済方法：来店前不安の解消、CV率に影響
- ペット・子供：該当しない業種でも明示することで情報不足を防げる

【業界説明×Google機能の「あるべき姿」を比較（必須評価）】
industry_description を前提に、以下を必ず評価・提案する：
- その業界において本来設定されているべきGoogle機能
- 現在設定されていない項目
- なぜそれが順位やCVに影響するか

例（買取業の場合）：
- service が未設定：買取品目（ブランド・時計・金・貴金属など）をサービスとして登録すべき
- photo はあるが video がない：査定風景・接客の様子を動画で補完すべき
- menu がない：買取価格目安・流れを疑似メニューとして表現可能

【禁止事項（重要）】
- 自社が競合より優れている点を「課題」として書くことは禁止
- 投稿・レビューだけで結論づける分析は禁止
- 表を出さず文章だけで済ませるのは禁止
- 動画・説明文に触れない分析は禁止
- 入力項目を無視する分析は禁止
- サブ項目を「触れない」判断は禁止
- 業種を考慮しない一般論は禁止

【出力フォーマット（必ずこのJSON形式・厳守）】
【重要】以下のJSON構造以外は一切出力しないでください。文章説明・Markdown・箇条書きは禁止です。JSONのみを出力してください。

【必須JSON構造（厳守）】
以下のキー名・型を1文字も変えないでください：
- situation: string（必須）
- initial_settings: array（必須）
- operations: array（必須）
- comparison_table: string|null（compare_2/compare_1の場合は必須、singleの場合はnull）
- self_checklist: string|null（singleの場合は必須、compare_2/compare_1の場合は不要）
- rank_summary: string（必須）
- meo_premise: string（必須）
- differentiation_opportunities: array（必須）
- execution_plan_initial: string（必須）
- execution_plan_operations: string（必須）

現在の分析モードに応じて出力を切り替えること：

【compare_2 / compare_1 の場合】
以下のJSON構造を厳守してください。キー名・階層構造を1文字も変えないでください。
{
  "comparison_table": "Markdownテーブル形式の比較サマリー（全項目を含む・必須）",
  "rank_summary": "自社がown_rank位である要因の要約（2〜3文）",
  "meo_premise": "industry_descriptionを前提としたMEO的前提条件の説明（業界ごとの特徴・あるべき姿）",
  "situation": "なぜ自社はown_rank位なのか（全項目を横断的に説明・最低400文字以上）",
  "differentiation_opportunities": [
    "今やると差がつく項目（競合未設定項目中心・業界特化）"
  ],
  "initial_settings": [
    "初期設定で変えるべきこと（業界特化・具体的・優先度高→低）"
  ],
  "operations": [
    "運用で目指すべきこと（業界特化・具体的・優先度高→低）"
  ],
  "execution_plan_initial": "初期設定の実行計画（具体的な作業単位・数値・行動ベースで記述）",
  "execution_plan_operations": "運用の実行計画（月次・週次など継続運用前提・数値目標を含む）"
}

【single の場合（競合が存在しない）】
以下のJSON構造を厳守してください。キー名・階層構造を1文字も変えないでください。

{
  "comparison_table": null,
  "self_checklist": "自社チェックリスト型サマリー（Markdown形式、例：- レビュー数：〇 / - 動画：未設定 / - メニュー：未設定 / - 属性情報：一部未設定）",
  "rank_summary": "自社のMEO設定状況の要約（2〜3文）",
  "meo_premise": "industry_descriptionを前提としたMEO的前提条件の説明（業界ごとの特徴・あるべき姿）",
  "situation": "自社のMEO設定状況と改善点（業界ベストプラクティスと比較・最低400文字以上）",
  "differentiation_opportunities": [
    "今やると差がつく項目（業界標準・ベストプラクティスに基づく）"
  ],
  "initial_settings": [
    "初期設定で変えるべきこと（業界特化・具体的・優先度高→低）"
  ],
  "operations": [
    "運用で目指すべきこと（業界特化・具体的・優先度高→低）"
  ],
  "execution_plan_initial": "初期設定の実行計画（具体的な作業単位・数値・行動ベースで記述）",
  "execution_plan_operations": "運用の実行計画（月次・週次など継続運用前提・数値目標を含む）"
}

【出力ルール（絶対遵守）】
- 出力は「JSONのみ」です
- 説明文・Markdown・表記号は禁止です
- キー名・階層構造を厳守してください
- 1文字でもJSON外の文字があれば不正とします
- JSONの前後に余計な文字（説明文など）を付けないでください
- JSONの開始は { で、終了は } でなければなりません

※ single の場合、comparison_table は null にし、代わりに self_checklist を出力すること
※ single の場合、situation の冒頭に「競合データが未入力のため、上位表示店舗の一般的傾向と業界標準をもとに分析しています」と明記すること

【実行計画の出力ルール】
- execution_plan_initial は「初期設定で変えるべきこと」を具体的な"作業単位"に落とす
- 数値・行動ベースで書く（例：写真を50枚以上追加、動画を10本追加）
- execution_plan_operations は月次・週次など「継続運用前提」で記述
- 数値目標を含める（例：口コミ：月平均4件以上を継続獲得、投稿：週1回以上を継続）
- 分析 → 初期 → 運用 → 実行計画という一貫したストーリーで出力する

【比較サマリーテーブルの仕様（compare_2 / compare_1 の場合のみ）】
compare_2 または compare_1 の場合のみ、comparison_table を出力すること。
single の場合は comparison_table は null にし、代わりに self_checklist を出力すること。

comparison_table には必ず以下のMarkdownテーブルを含めること（全項目を網羅）：

最低限含める比較項目（必須）：
- 順位（rank）：自社のown_rank位、競合1の1位、競合2の2位
- review_count（レビュー数）：数値で比較
- monthly_review_count（月間の口コミ数）：数値で比較
- review_rating（レビュー評価）：数値で比較
- monthly_post_count（月間の投稿数）：数値で比較
- photo_count（写真数）：数値で比較
- has_video（動画有無）：ある/なし
- video_count（動画本数）：数値で比較
- has_menu（メニュー有無）：ある/なし
- service_link（サービスリンク）：ある/なし
- reservation_link（予約リンク）：ある/なし

その他の項目も含める：
- 説明文の充実度
- Q&A
- NAP一致
- バリアフリー
- 決済方法
- 駐車場
- その他属性

テーブル形式例：

【compare_2 の場合（3列）】
| 項目 | 自社 | 競合1 | 競合2 |
|------|------|-------|-------|

【compare_1 の場合（2列）】
| 項目 | 自社 | 競合1 |
|------|------|-------|
| Googleマップ順位 | own_rank位 | 1位 | 2位 |
| レビュー数 | [数値] | [数値] | [数値] |
| レビュー評価 | [評価] | [評価] | [評価] |
| 月間の口コミ数 | [数値] | [数値] | [数値] |
| 月間の投稿数 | [数値] | [数値] | [数値] |
| 写真数 | [数値] | [数値] | [数値] |
| 動画有無 | [ある/なし] | [ある/なし] | [ある/なし] |
| 動画本数 | [数値] | [数値] | [数値] |
| メニュー有無 | [ある/なし] | [ある/なし] | [ある/なし] |
| サービスリンク | [ある/なし] | [ある/なし] | [ある/なし] |
| 予約リンク | [ある/なし] | [ある/なし] | [ある/なし] |
| 説明文の充実度 | [評価] | [評価] | [評価] |
| Q&A | [ある/なし] | [ある/なし] | [ある/なし] |
| NAP一致 | [している/していない] | [している/していない] | [している/していない] |
| バリアフリー | [ある/なし/未設定] | [ある/なし/未設定] | [ある/なし/未設定] |
| 決済方法 | [ある/なし/未設定] | [ある/なし/未設定] | [ある/なし/未設定] |
| 駐車場 | [ある/なし/未設定] | [ある/なし/未設定] | [ある/なし/未設定] |
| その他属性 | [設定状況] | [設定状況] | [設定状況] |

※ 数値は数値で、あり/なしは明示的に表示する
※ "__MISSING__" は「未設定」と表記する
※ 表で出した内容を、situation の文章内でも必ず言語化すること
※ 全項目を網羅し、省略しないこと
※ このサマリー表は AI文章とは独立して必ず表示されるため、「見ただけで差が分かる」ことを最優先に記述すること

【文章量の要件】
- "situation" は最低400文字以上で詳細に説明すること
- 各必須評価項目について「競合との差 → 順位への影響」を必ず結びつけて書くこと
- 営業資料としてそのまま使えるボリュームにすること

データ：
PROMPT;

        // 業界説明を明示的にプロンプトに含める
        $industryDescription = $data['industry_description'] ?? '';
        if ($industryDescription) {
            $prompt .= "\n\n【業界説明（絶対的な前提）】\n";
            $prompt .= $industryDescription . "\n";
            $prompt .= "\n上記の業界説明を絶対的な前提として分析してください。\n";
            $prompt .= "業種推測・憶測は一切禁止です。\n";
        }

        // 【修正③ buildPrompt() に明示的に埋め込む】getAnalysisStructure()の内容をプロンプトに含める
        $formatDefinition = $this->getAnalysisStructure();
        $prompt .= "\n\n【必須JSON構造定義（厳守）】\n";
        $prompt .= "以下のJSON構造を厳守してください。キー名・型を1文字も変えないでください：\n";
        foreach ($formatDefinition as $key => $type) {
            $prompt .= "- {$key}: {$type}\n";
        }

        $prompt .= "\n\n";

        // STEP1の入力JSONをそのまま追加（加工・要約・抜粋は一切しない）
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $prompt .= "\n" . $jsonData;

        // プロンプト長をログに記録
        $promptLength = mb_strlen($prompt);
        Log::info('[CompetitorAnalysis] prompt length', [
            'length' => $promptLength,
        ]);

        // デバッグ：prompt全文をログ（先頭2000文字と末尾500文字）
        $promptPreview = mb_substr($prompt, 0, 2000);
        $promptSuffix = mb_strlen($prompt) > 2500 ? '...' . mb_substr($prompt, -500) : '';
        Log::info('[CompetitorAnalysis] prompt preview', [
            'preview' => $promptPreview . $promptSuffix,
            'full_length' => $promptLength,
        ]);

        if ($promptLength === 0 || $promptLength < 100) {
            Log::error('[CompetitorAnalysis] prompt too short', [
                'length' => $promptLength,
            ]);
            throw new \Exception('Prompt is too short or empty');
        }

        return $prompt;
    }

    /**
     * OpenAIの返却フォーマット定義を取得（JSONバリデーションの基準として使用）
     * 
     * @return array 期待されるJSON構造の定義
     */
    public function getAnalysisStructure(): array
    {
        return [
            'situation' => 'string',
            'initial_settings' => 'array',
            'operations' => 'array',
            'comparison_table' => 'string|null',
            'self_checklist' => 'string|null',
            'rank_summary' => 'string',
            'meo_premise' => 'string',
            'differentiation_opportunities' => 'array',
            'execution_plan_initial' => 'string',
            'execution_plan_operations' => 'string',
        ];
    }

    /**
     * 実際の分析結果の構造を取得（デバッグ用）
     * 
     * @param array $analysis 実際の分析結果
     * @return array 構造の詳細情報
     */
    private function analyzeActualStructure(array $analysis): array
    {
        $structure = [];
        foreach ($analysis as $key => $value) {
            if (is_array($value)) {
                $structure[$key] = [
                    'type' => 'array',
                    'count' => count($value),
                    'sample' => !empty($value) ? (is_string($value[0]) ? mb_substr($value[0], 0, 50) : gettype($value[0])) : null,
                ];
            } elseif (is_string($value)) {
                $structure[$key] = [
                    'type' => 'string',
                    'length' => mb_strlen($value),
                    'preview' => mb_substr($value, 0, 100),
                ];
            } else {
                $structure[$key] = [
                    'type' => gettype($value),
                    'value' => $value,
                ];
            }
        }
        return $structure;
    }

    /**
     * 比較サマリーテーブルを自動生成
     * 
     * @param string $analysisMode 分析モード（compare_2, compare_1, single）
     * @param array $data 元の入力データ（shops配列を含む）
     * @return string|null Markdown形式のテーブル（singleの場合はnull）
     */
    private function buildComparisonTable(string $analysisMode, array $data): ?string
    {
        if ($analysisMode === 'single') {
            return null;
        }

        $ownShop = collect($data['shops'] ?? [])->firstWhere('role', 'own');
        $competitor1Shop = collect($data['shops'] ?? [])->firstWhere('role', 'competitor1');
        $competitor2Shop = collect($data['shops'] ?? [])->firstWhere('role', 'competitor2');

        if (!$ownShop) {
            Log::warning('[CompetitorAnalysis] own shop not found for comparison table');
            return null;
        }

        // ヘッダー行を構築
        $headers = ['項目', '自社'];
        if ($competitor1Shop) {
            $headers[] = '競合①';
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $headers[] = '競合②';
        }

        $table = "| " . implode(' | ', $headers) . " |\n";
        $table .= "|" . str_repeat("---|", count($headers)) . "\n";

        // 値を取得するヘルパー関数
        $getValue = function($shop, $key, $default = '未設定') {
            if (!isset($shop[$key]) || $shop[$key] === null || $shop[$key] === '' || $shop[$key] === '__MISSING__') {
                return $default;
            }
            return $shop[$key];
        };

        // 順位
        $ownRank = $getValue($ownShop, 'own_rank', '未設定');
        $row = ["Googleマップ順位", $ownRank . "位"];
        if ($competitor1Shop) {
            $row[] = "1位";
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = "2位";
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        // レビュー数
        $row = ["レビュー数", $getValue($ownShop, 'review_count', '0')];
        if ($competitor1Shop) {
            $row[] = $getValue($competitor1Shop, 'review_count', '0');
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = $getValue($competitor2Shop, 'review_count', '0');
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        // レビュー評価
        $row = ["レビュー評価", $getValue($ownShop, 'review_rating', '未設定')];
        if ($competitor1Shop) {
            $row[] = $getValue($competitor1Shop, 'review_rating', '未設定');
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = $getValue($competitor2Shop, 'review_rating', '未設定');
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        // 月間の口コミ数
        $row = ["月間の口コミ数", $getValue($ownShop, 'monthly_review_count', '0')];
        if ($competitor1Shop) {
            $row[] = $getValue($competitor1Shop, 'monthly_review_count', '0');
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = $getValue($competitor2Shop, 'monthly_review_count', '0');
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        // 月間の投稿数
        $row = ["月間の投稿数", $getValue($ownShop, 'monthly_post_count', '0')];
        if ($competitor1Shop) {
            $row[] = $getValue($competitor1Shop, 'monthly_post_count', '0');
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = $getValue($competitor2Shop, 'monthly_post_count', '0');
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        // 写真数
        $row = ["写真数", $getValue($ownShop, 'photo_count', '0')];
        if ($competitor1Shop) {
            $row[] = $getValue($competitor1Shop, 'photo_count', '0');
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = $getValue($competitor2Shop, 'photo_count', '0');
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        // 動画有無
        $row = ["動画有無", $getValue($ownShop, 'has_video', 'なし')];
        if ($competitor1Shop) {
            $row[] = $getValue($competitor1Shop, 'has_video', 'なし');
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = $getValue($competitor2Shop, 'has_video', 'なし');
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        // 動画本数
        $row = ["動画本数", $getValue($ownShop, 'video_count', '0')];
        if ($competitor1Shop) {
            $row[] = $getValue($competitor1Shop, 'video_count', '0');
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = $getValue($competitor2Shop, 'video_count', '0');
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        // メニュー有無
        $row = ["メニュー有無", $getValue($ownShop, 'has_menu', 'なし')];
        if ($competitor1Shop) {
            $row[] = $getValue($competitor1Shop, 'has_menu', 'なし');
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = $getValue($competitor2Shop, 'has_menu', 'なし');
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        // サービスリンク
        $row = ["サービスリンク", $getValue($ownShop, 'service_link', 'なし')];
        if ($competitor1Shop) {
            $row[] = $getValue($competitor1Shop, 'service_link', 'なし');
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = $getValue($competitor2Shop, 'service_link', 'なし');
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        // 予約リンク
        $row = ["予約リンク", $getValue($ownShop, 'reservation_link', 'なし')];
        if ($competitor1Shop) {
            $row[] = $getValue($competitor1Shop, 'reservation_link', 'なし');
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = $getValue($competitor2Shop, 'reservation_link', 'なし');
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        // Q&A
        $row = ["Q&A", $getValue($ownShop, 'qa', 'なし')];
        if ($competitor1Shop) {
            $row[] = $getValue($competitor1Shop, 'qa', 'なし');
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = $getValue($competitor2Shop, 'qa', 'なし');
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        // NAP一致（WEBサイト）
        $row = ["NAP一致（WEB）", $getValue($ownShop, 'website_nap_match', '未設定')];
        if ($competitor1Shop) {
            $row[] = $getValue($competitor1Shop, 'website_nap_match', '未設定');
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = $getValue($competitor2Shop, 'website_nap_match', '未設定');
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        // 属性情報（まとめて）
        $attributeKeys = ['barrier_free', 'payment_method', 'parking', 'pet', 'child'];
        $ownAttributes = [];
        $comp1Attributes = [];
        $comp2Attributes = [];
        
        foreach ($attributeKeys as $attrKey) {
            $ownVal = $getValue($ownShop, $attrKey, null);
            if ($ownVal && $ownVal !== '未設定' && $ownVal !== '__MISSING__') {
                $ownAttributes[] = $attrKey;
            }
            if ($competitor1Shop) {
                $comp1Val = $getValue($competitor1Shop, $attrKey, null);
                if ($comp1Val && $comp1Val !== '未設定' && $comp1Val !== '__MISSING__') {
                    $comp1Attributes[] = $attrKey;
                }
            }
            if ($competitor2Shop && $analysisMode === 'compare_2') {
                $comp2Val = $getValue($competitor2Shop, $attrKey, null);
                if ($comp2Val && $comp2Val !== '未設定' && $comp2Val !== '__MISSING__') {
                    $comp2Attributes[] = $attrKey;
                }
            }
        }
        
        $row = ["属性情報", count($ownAttributes) > 0 ? count($ownAttributes) . "項目設定" : "未設定"];
        if ($competitor1Shop) {
            $row[] = count($comp1Attributes) > 0 ? count($comp1Attributes) . "項目設定" : "未設定";
        }
        if ($competitor2Shop && $analysisMode === 'compare_2') {
            $row[] = count($comp2Attributes) > 0 ? count($comp2Attributes) . "項目設定" : "未設定";
        }
        $table .= "| " . implode(' | ', $row) . " |\n";

        return $table;
    }

    /**
     * 期待される構造を取得（分析モードに応じて）
     * 
     * @param string $analysisMode 分析モード（compare_2, compare_1, single）
     * @return array 期待されるキーのリストと説明
     */
    public function getExpectedStructure(string $analysisMode): array
    {
        if ($analysisMode === 'single') {
            return [
                'self_checklist' => 'string (required)',
                'rank_summary' => 'string (required)',
                'meo_premise' => 'string (required)',
                'situation' => 'string (required, min 400 chars)',
                'differentiation_opportunities' => 'array (required)',
                'initial_settings' => 'array (required)',
                'operations' => 'array (required)',
                'execution_plan_initial' => 'string (required)',
                'execution_plan_operations' => 'string (required)',
            ];
        } else {
            // compare_2 / compare_1 の場合（comparison_table は optional、null の場合は自動生成）
            return [
                'comparison_table' => 'string (optional, auto-generated if null)',
                'rank_summary' => 'string (required)',
                'meo_premise' => 'string (required)',
                'situation' => 'string (required, min 400 chars)',
                'differentiation_opportunities' => 'array (required)',
                'initial_settings' => 'array (required)',
                'operations' => 'array (required)',
                'execution_plan_initial' => 'string (required)',
                'execution_plan_operations' => 'string (required)',
            ];
        }
    }

    /**
     * UTF-8正規化処理（再帰的）
     * 
     * @param mixed $value 正規化対象の値
     * @return mixed 正規化後の値
     */
    private function normalizeUtf8($value)
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                // キーも正規化
                $normalizedKey = is_string($key) ? $this->normalizeUtf8String($key) : $key;
                $normalized[$normalizedKey] = $this->normalizeUtf8($item);
            }
            return $normalized;
        } elseif (is_string($value)) {
            return $this->normalizeUtf8String($value);
        } elseif (is_object($value)) {
            // オブジェクトは文字列化してから正規化
            return $this->normalizeUtf8String((string)$value);
        }
        
        return $value;
    }

    /**
     * 文字列のUTF-8正規化
     * 
     * @param string $value 正規化対象の文字列
     * @return string 正規化後の文字列
     */
    private function normalizeUtf8String(string $value): string
    {
        // UTF-8正規化（不正文字を除去）
        $normalized = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        
        // さらに iconv で安全化（IGNORE で不正文字を除去）
        $normalized = @iconv('UTF-8', 'UTF-8//IGNORE', $normalized);
        
        // iconv が false を返した場合（すべて不正文字だった場合）
        if ($normalized === false) {
            // 最後の手段：正規表現で不正文字を除去
            $normalized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
            $normalized = mb_convert_encoding($normalized, 'UTF-8', 'UTF-8');
            // 再度 iconv で安全化
            $normalized = @iconv('UTF-8', 'UTF-8//IGNORE', $normalized);
            if ($normalized === false) {
                $normalized = '';
            }
        }
        
        return $normalized;
    }

    /**
     * UTF-8検証とログ出力
     * 
     * @param array $data 検証対象データ
     * @param string $context コンテキスト名
     */
    private function validateAndLogUtf8(array $data, string $context = '')
    {
        $invalidFields = [];
        
        foreach ($data as $key => $value) {
            $fieldPath = $context ? $context . '.' . $key : $key;
            
            if (is_string($value)) {
                $isValid = mb_check_encoding($value, 'UTF-8');
                if (!$isValid) {
                    $invalidFields[$fieldPath] = [
                        'is_valid' => false,
                        'length' => mb_strlen($value),
                        'preview' => mb_substr($value, 0, 50) . (mb_strlen($value) > 50 ? '...' : ''),
                        'mb_check_encoding' => false
                    ];
                }
            } elseif (is_array($value)) {
                // 再帰的に検証
                $nestedInvalid = $this->validateAndLogUtf8($value, $fieldPath);
                if (!empty($nestedInvalid)) {
                    $invalidFields = array_merge($invalidFields, $nestedInvalid);
                }
            }
        }
        
        if (!empty($invalidFields) && $context === '') {
            // 最上位の呼び出し時のみログ出力
            Log::warning('[AI返信生成] invalid_utf8_detected', [
                'invalid_fields' => $invalidFields,
                'field_count' => count($invalidFields)
            ]);
        }
        
        return $invalidFields;
    }

    /**
     * AI返信文を生成
     * 
     * @param array $data 口コミ情報（review_text, rating, shop_name, ai_reply_keywords）
     * @return string 生成された返信文（最低200文字）
     */
    public function generateReply(array $data): string
    {
        try {
            // 入力データの安全化（null → 空文字、型チェック）
            $reviewText = $this->normalizeUtf8($data['review_text'] ?? '');
            $rating = $this->normalizeUtf8($data['rating'] ?? '');
            $shopName = $this->normalizeUtf8($data['shop_name'] ?? '');
            $keywords = $this->normalizeUtf8($data['ai_reply_keywords'] ?? '');

            // 文字列型に統一（配列/オブジェクト混入防止）
            $reviewText = is_string($reviewText) ? $reviewText : '';
            $rating = is_string($rating) ? $rating : '';
            $shopName = is_string($shopName) ? $shopName : '';
            $keywords = is_string($keywords) ? $keywords : '';

            // 評価を数値に変換
            $ratingValue = '';
            if (!empty($rating)) {
                if (is_numeric($rating)) {
                    $ratingValue = (int)$rating . 'つ星';
                } else {
                    $ratingMap = [
                        'FIVE' => '5つ星',
                        'FOUR' => '4つ星',
                        'THREE' => '3つ星',
                        'TWO' => '2つ星',
                        'ONE' => '1つ星',
                    ];
                    $ratingValue = $ratingMap[strtoupper($rating)] ?? $rating;
                }
            }
            $ratingValue = $this->normalizeUtf8($ratingValue);

            // キーワードを整形（カンマ区切り or 改行区切り）
            $keywordsList = [];
            if (!empty($keywords)) {
                // 改行区切りとカンマ区切りの両方に対応
                $lines = preg_split('/[\r\n,、]+/', $keywords);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line)) {
                        $keywordsList[] = $this->normalizeUtf8($line);
                    }
                }
            }
            $keywordsText = !empty($keywordsList) ? implode('、', $keywordsList) : '（キーワード未設定）';
            $keywordsText = $this->normalizeUtf8($keywordsText);

            // プロンプトを構築
            $prompt = "あなたは実店舗のGoogle口コミ返信を専門に代行するプロの文章作成者です。
以下の条件を必ず守って返信文を作成してください。

【必須条件】
・最低200文字以上
・丁寧で誠実な日本語
・口コミ内容に具体的に言及すること
・クレームの場合は謝罪を含めること
・過度に定型文っぽくならないこと

【必ず自然に含めるキーワード】
{$keywordsText}

【店舗名】
{$shopName}

【口コミ内容】
{$reviewText}

【星評価】
{$ratingValue}

【出力ルール】
・文章のみを出力
・箇条書き禁止
・絵文字禁止
・署名は禁止（例：スタッフ一同など）";

            // プロンプトも正規化
            $prompt = $this->normalizeUtf8($prompt);

            // UTF-8検証とログ出力
            $this->validateAndLogUtf8([
                'review_text' => $reviewText,
                'shop_name' => $shopName,
                'keywords' => $keywordsText,
                'rating' => $ratingValue,
                'prompt' => $prompt
            ], 'generateReply_input');

            // OpenAI API payload を構築
            $payload = [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->normalizeUtf8('あなたは実店舗のGoogle口コミ返信を専門に代行するプロの文章作成者です。丁寧で誠実な日本語で、口コミ内容に具体的に言及した返信文を作成してください。')
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ];

            // payload全体を正規化
            $payload = $this->normalizeUtf8($payload);

            // json_encode 前の最終チェック
            $jsonTest = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($jsonTest === false) {
                $error = json_last_error_msg();
                Log::error('[AI返信生成] json_encode失敗（payload構築時）', [
                    'json_error' => $error,
                    'json_error_code' => json_last_error(),
                    'payload_preview' => mb_substr(print_r($payload, true), 0, 500)
                ]);
                throw new \Exception('文字コードエラー: 送信データに不正な文字が含まれています。');
            }

            Log::info('[AI返信生成] プロンプト送信', [
                'shop_name' => $shopName,
                'rating' => $ratingValue,
                'keywords_count' => count($keywordsList),
                'prompt_length' => mb_strlen($prompt),
                'payload_size' => strlen($jsonTest)
            ]);

            // OpenAI APIを呼び出し
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, $payload);

            if (!$response->successful()) {
                Log::error('[AI返信生成] OpenAI API エラー', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('OpenAI API エラー: ' . $response->status());
            }

            $responseData = $response->json();
            $replyText = $responseData['choices'][0]['message']['content'] ?? '';

            // 改行や余分な空白を除去
            $replyText = trim($replyText);

            // 返信文も正規化
            $replyText = $this->normalizeUtf8($replyText);

            // 200文字未満の場合はエラー
            if (mb_strlen($replyText) < 200) {
                Log::warning('[AI返信生成] 文字数不足', [
                    'length' => mb_strlen($replyText),
                    'text' => $replyText
                ]);
                throw new \Exception('生成された返信文が200文字未満です。');
            }

            Log::info('[AI返信生成] 成功', [
                'length' => mb_strlen($replyText),
                'preview' => mb_substr($replyText, 0, 50) . '...'
            ]);

            return $replyText;

        } catch (\JsonException $e) {
            Log::error('[AI返信生成] json_encode例外', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('返信文の生成に失敗しました（文字コードエラー）');
        } catch (\Exception $e) {
            // 既にログ出力済みのエラーは再ログしない
            if (strpos($e->getMessage(), '文字コードエラー') === false && 
                strpos($e->getMessage(), 'OpenAI API エラー') === false &&
                strpos($e->getMessage(), '200文字未満') === false) {
                Log::error('[AI返信生成] 予期しないエラー', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            throw $e;
        }
    }
}

