# Google Business Profile API 写真投稿（Media Upload）仕様

## 1. 使用するエンドポイント

**エンドポイント:**
```
POST https://mybusiness.googleapis.com/v4/{locationName}/media
```

**locationName の形式:**
- `locations/{locationId}` の形式（例: `locations/14533069664155190447`）
- 現在のシステムでは `shops.gbp_location_id` にこの形式で保存されている

## 2. 画像バイナリを送る方法

**推奨: Multipart Upload**

Google Business Profile API では以下のアップロード方法が利用可能：

- **Simple Upload**: 小さいファイル用（5MB以下推奨）
- **Multipart Upload**: メタデータと画像データを1つのリクエストで送信（推奨）
- **Resumable Upload**: 大きなファイル（5MB以上）やネットワークが不安定な環境用

**Multipart Upload の構造:**
```
Content-Type: multipart/related; boundary="boundary_string"

--boundary_string
Content-Type: application/json; charset=UTF-8

{
  "mediaFormat": "PHOTO",
  "sourceUrl": "optional_url"
}

--boundary_string
Content-Type: image/jpeg

[バイナリデータ]

--boundary_string--
```

## 3. 必要なOAuthスコープ

**スコープ:**
```
https://www.googleapis.com/auth/business.manage
```

現在のシステムで使用している `business.manage` スコープで十分です。
このスコープにより、ロケーション情報とメディア（写真）の管理が可能です。

## 4. レスポンスで返ってくる mediaItem の構造

**成功時のレスポンス例:**
```json
{
  "name": "accounts/{accountId}/locations/{locationId}/media/{mediaId}",
  "mediaUrl": "https://lh3.googleusercontent.com/p/AF1Qip...",
  "googleUrl": "https://www.google.com/maps/place/...",
  "thumbnailUrl": "https://lh3.googleusercontent.com/p/AF1Qip...",
  "createTime": "2024-01-15T10:30:00Z",
  "mediaFormat": "PHOTO",
  "widthPixels": 1920,
  "heightPixels": 1080,
  "locationAssociation": {
    "category": "COVER",
    "displayName": "Cover photo"
  }
}
```

**主要フィールド:**
- `name`: メディアのリソース名（一意の識別子）
  - 形式: `accounts/{accountId}/locations/{locationId}/media/{mediaId}`
- `mediaUrl`: Google によってホストされた画像URL（高解像度）
- `googleUrl`: Google マップでの表示URL
- `thumbnailUrl`: サムネイル画像URL
- `createTime`: アップロード時刻（ISO 8601形式）
- `mediaFormat`: メディアタイプ（"PHOTO" または "VIDEO"）
- `widthPixels`, `heightPixels`: 画像のサイズ
- `locationAssociation`: ロケーションとの関連情報（カテゴリなど）

## 5. location に紐づけるために使うリソース名の形式

**リソース名の形式:**
```
locations/{locationId}
```

**例:**
```
locations/14533069664155190447
```

**現在のシステムでの保存形式:**
- `shops.gbp_location_id` に `"locations/14533069664155190447"` の形式で保存されている
- この値をそのままエンドポイントの `{locationName}` に使用可能

**エンドポイント例:**
```
POST https://mybusiness.googleapis.com/v4/locations/14533069664155190447/media
```

## 注意事項

1. **ファイルサイズ制限:**
   - 推奨: 5MB以下
   - 最大: 75MB（Resumable Upload使用時）

2. **対応画像形式:**
   - JPEG (.jpg, .jpeg)
   - PNG (.png)
   - GIF (.gif)
   - WebP (.webp)

3. **エラーレスポンス:**
   ```json
   {
     "error": {
       "code": 400,
       "message": "Invalid media format",
       "status": "INVALID_ARGUMENT"
     }
   }
   ```

4. **レート制限:**
   - 1分間に100リクエストまで（デフォルト）






















