# snapshot 作成失敗の原因特定レポート

## 【1】ReportController::sync() の snapshot 作成コードの前の return 文

### snapshot 作成前の return 文一覧

#### ① 期間指定のバリデーション（728-731行目）

```728:731:app/Http/Controllers/ReportController.php
if ($startDateCarbon && $endDateCarbon && $startDateCarbon->gt($endDateCarbon)) {
    $routeName = $operatorId ? 'operator.reports.show' : 'reports.show';
    return redirect()->route($routeName, ['shop' => $shop->id, 'from' => $from, 'to' => $to])
        ->with('error', '開始日が終了日より後になっています。');
}
```

**条件**: `startDate > endDate` の場合に return

#### ② 契約が終了している場合（738-741行目）

```738:741:app/Http/Controllers/ReportController.php
if (!$shop->isContractActive()) {
    $routeName = $operatorId ? 'operator.reports.show' : 'reports.show';
    return redirect()->route($routeName, ['shop' => $shop->id, 'from' => $from, 'to' => $to])
        ->with('error', '契約が終了している店舗の同期はできません。');
}
```

**条件**: `shop->isContractActive()` が `false` の場合に return

#### ③ Google連携が完了していない場合（745-748行目）

```745:748:app/Http/Controllers/ReportController.php
if (!$shop->gbp_location_id || !$shop->gbp_refresh_token) {
    $routeName = $operatorId ? 'operator.reports.show' : 'reports.show';
    return redirect()->route($routeName, ['shop' => $shop->id, 'from' => $from, 'to' => $to])
        ->with('error', 'Google連携が完了していません。');
}
```

**条件**: `gbp_location_id` または `gbp_refresh_token` が存在しない場合に return

#### ④ アクセストークンの取得に失敗した場合（755-758行目）

```755:758:app/Http/Controllers/ReportController.php
if (!$accessToken) {
    $routeName = $operatorId ? 'operator.reports.show' : 'reports.show';
    return redirect()->route($routeName, ['shop' => $shop->id, 'from' => $from, 'to' => $to])
        ->with('error', 'アクセストークンの取得に失敗しました。');
}
```

**条件**: `getAccessToken($shop)` が `null` を返した場合に return

---

## 【2】snapshot を作成する処理の正確なコード箇所

### GbpSnapshot::create の行番号

**行番号**: **763行目**

```761:772:app/Http/Controllers/ReportController.php
// スナップショットを作成
$currentUserId = \App\Helpers\AuthHelper::getCurrentUserId();
$snapshot = GbpSnapshot::create([
    'shop_id' => $shop->id,
    'user_id' => $currentUserId,
    'synced_by_operator_id' => $operatorId, // 同期実行者（ログ用、nullable）
    'synced_at' => now(),
    'sync_params' => [
        'start_date' => $startDateCarbon?->format('Y-m-d'),
        'end_date' => $endDateCarbon?->format('Y-m-d'),
    ],
]);
```

---

## 【3】snapshot 作成前にチェックしている条件をすべて列挙

### チェック条件一覧

1. **期間指定のバリデーション**（728行目）
   - 条件: `startDateCarbon > endDateCarbon`
   - エラー時: `'開始日が終了日より後になっています。'`

2. **契約が有効か**（738行目）
   - 条件: `!$shop->isContractActive()`
   - `isContractActive()` の実装:
     ```123:130:app/Models/Shop.php
     public function isContractActive(): bool
     {
         if (is_null($this->contract_end_date)) {
             return true; // 契約終了日が設定されていない場合は契約中とみなす
         }
         
         return $this->contract_end_date->isFuture() || $this->contract_end_date->isToday();
     }
     ```
   - エラー時: `'契約が終了している店舗の同期はできません。'`

3. **Google連携が完了しているか**（745行目）
   - 条件: `!$shop->gbp_location_id || !$shop->gbp_refresh_token`
   - エラー時: `'Google連携が完了していません。'`

4. **アクセストークンの取得**（755行目）
   - 条件: `!$accessToken`（`getAccessToken($shop)` が `null` を返す）
   - `getAccessToken()` の処理フロー:
     1. `gbp_access_token` が存在する場合、tokeninfo で有効性を確認
     2. 無効または期限切れの場合、`refreshAccessToken()` を呼び出し
     3. リフレッシュトークンから新しいアクセストークンを取得
     4. 新しいアクセストークンを tokeninfo で確認（email が存在するか）
     5. email が存在しない場合（App-only トークン）、`null` を返す
   - エラー時: `'アクセストークンの取得に失敗しました。'`

---

## 【4】テストで作成している Shop の値と sync() 内の必須条件の比較

### テストで作成している Shop の値

```27:33:tests/Feature/ReportSyncTest.php
$shop = Shop::factory()->create([
    'name' => 'テスト店舗',
    'gbp_account_id' => '123456789012345678901',
    'gbp_location_id' => 'locations/987654321098765432109',
    'gbp_refresh_token' => 'dummy_refresh_token',
    'contract_end_date' => null, // 契約中
]);
```

### sync() 内の必須条件との比較

| 条件 | テストの値 | 必須条件 | 状態 |
|------|-----------|---------|------|
| **期間指定のバリデーション** | `from: '2024-01-01'`, `to: '2024-12-31'` | `startDate <= endDate` | ✅ 問題なし |
| **契約が有効か** | `contract_end_date: null` | `isContractActive() === true` | ✅ 問題なし（`null` の場合は `true` を返す） |
| **gbp_location_id** | `'locations/987654321098765432109'` | 存在する必要がある | ✅ 問題なし |
| **gbp_refresh_token** | `'dummy_refresh_token'` | 存在する必要がある | ✅ 問題なし |
| **アクセストークンの取得** | - | `getAccessToken($shop) !== null` | ❌ **問題あり** |

### 問題の原因

**最も可能性が高い原因**: **アクセストークンの取得に失敗している**

#### GoogleBusinessProfileService::getAccessToken() の処理フロー

1. **gbp_access_token が存在しない場合**（テストでは設定されていない）
   - `refreshAccessToken()` を呼び出し（155-162行目）

2. **refreshAccessToken() の処理**（266-274行目）
   ```php
   $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
       'client_id' => $this->clientId,
       'client_secret' => $this->clientSecret,
       'refresh_token' => $refreshToken,
       'grant_type' => 'refresh_token',
   ]);
   ```
   - テストでは `Http::fake()` で `'oauth2.googleapis.com/token'` をモックしている（50-53行目）

3. **リフレッシュ後の tokeninfo 確認**（168-208行目）
   ```php
   $tokenInfoUrl = "https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=" . urlencode($newAccessToken);
   $tokenInfoResponse = Http::get($tokenInfoUrl);
   ```
   - テストでは `Http::fake()` で `'oauth2.googleapis.com/tokeninfo*'` をモックしている（44-47行目）

4. **email の確認**（174-195行目）
   ```php
   $email = $tokenInfoData['email'] ?? null;
   if ($email) {
       return $newAccessToken; // ✅ 成功
   } else {
       return null; // ❌ 失敗（App-only トークン）
   }
   ```

### テストのモック設定

```42:53:tests/Feature/ReportSyncTest.php
Http::fake([
    // アクセストークン取得（tokeninfo）
    'oauth2.googleapis.com/tokeninfo*' => Http::response([
        'email' => 'test@example.com',
        'expires_in' => 3600,
    ], 200),
    
    // リフレッシュトークンでアクセストークン取得
    'oauth2.googleapis.com/token' => Http::response([
        'access_token' => 'dummy_access_token',
        'expires_in' => 3600,
    ], 200),
```

### 問題の可能性

1. **Http::fake() の URL マッチング**
   - `'oauth2.googleapis.com/tokeninfo*'` は `*` でワイルドカードマッチを期待しているが、Laravel の `Http::fake()` は完全一致またはパターンマッチが必要
   - 実際の URL: `https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=...`
   - モックパターン: `oauth2.googleapis.com/tokeninfo*`
   - **不一致の可能性**: `www.googleapis.com` と `oauth2.googleapis.com` が異なる

2. **tokeninfo の URL 構造**
   - 実際の URL: `https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=...`
   - モックパターン: `oauth2.googleapis.com/tokeninfo*`
   - **不一致**: ドメインが異なる（`www.googleapis.com` vs `oauth2.googleapis.com`）

---

## 結論

### 原因

**アクセストークンの取得に失敗している**（755行目で return）

### 詳細

1. `getAccessToken($shop)` が `null` を返している
2. 原因は **Http::fake() の URL マッチングが失敗している**可能性が高い
3. 実際の URL: `https://www.googleapis.com/oauth2/v3/tokeninfo?access_token=...`
4. モックパターン: `oauth2.googleapis.com/tokeninfo*`
5. **ドメインが異なるため、モックが適用されていない**

### 修正が必要な箇所

テストの `Http::fake()` の URL パターンを修正する必要がある：

```php
Http::fake([
    // 修正前
    'oauth2.googleapis.com/tokeninfo*' => Http::response([...]),
    
    // 修正後（推測）
    'www.googleapis.com/oauth2/v3/tokeninfo*' => Http::response([...]),
    // または
    '*tokeninfo*' => Http::response([...]),
]);
```









