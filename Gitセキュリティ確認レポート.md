# Gitセキュリティ確認レポート

**確認日時**: 2026-02-16  
**ブランチ**: `feature/rank-fetch-schedule`  
**リポジトリ状態**: クリーン（コミット待ちなし）

---

## ■ 1. 機密ファイルのGit追跡状況確認

### 1.1 確認コマンド実行結果

#### ✅ `.env` ファイル
```bash
git ls-files | findstr .env
```
**結果**: `.env.example` のみ（問題なし）

#### ✅ `vendor/` ディレクトリ
```bash
git ls-files | findstr vendor
```
**結果**: 追跡されていない（問題なし）

#### ✅ `node_modules/` ディレクトリ
```bash
git ls-files | findstr node_modules
```
**結果**: 追跡されていない（問題なし）

#### ⚠️ `storage/` ディレクトリ
```bash
git ls-files | findstr storage
```
**結果**: **大量のファイルが追跡されています**

**追跡されているファイル例**:
- `storage/puppeteer-runs/run-*/Default/History`
- `storage/puppeteer-runs/run-*/Default/Login Data`
- `storage/puppeteer-runs/run-*/Default/Cookies`
- `storage/puppeteer-runs/run-*/Default/Preferences`
- その他数百ファイル

**問題**: `storage/puppeteer-runs/` 配下の一時ファイルが追跡されています。

---

## ■ 2. .gitignore の内容確認

### 2.1 現在の .gitignore 内容

```gitignore
*.log
.DS_Store
.env
.env.backup
.env.production
.phpactor.json
.phpunit.result.cache
/.fleet
/.idea
/.nova
/.phpunit.cache
/.vscode
/.zed
/auth.json
/node_modules
/public/build
/public/hot
/public/storage
/storage/*.key
/storage/pail
/vendor
Homestead.json
Homestead.yaml
Thumbs.db
```

### 2.2 Laravel標準との比較

| 項目 | 現状 | 推奨 | 状態 |
|------|------|------|------|
| `/vendor` | ✅ 含まれている | 必須 | OK |
| `/node_modules` | ✅ 含まれている | 必須 | OK |
| `/.env` | ✅ 含まれている | 必須 | OK |
| `/storage/*.key` | ✅ 含まれている | 必須 | OK |
| `/storage/logs` | ❌ **不足** | 推奨 | **要追加** |
| `/storage/framework` | ❌ **不足** | 推奨 | **要追加** |
| `/bootstrap/cache/*.php` | ❌ **不足** | 推奨 | **要追加** |
| `/storage/puppeteer-runs` | ❌ **不足** | 必須 | **要追加** |

---

## ■ 3. 既に .env がコミットされている場合

### 3.1 確認結果

```bash
git log --all --full-history --source -- .env
```

**結果**: **`.env` が過去のコミットに含まれています**

**コミット情報**:
- **コミットハッシュ**: `6140b5ed4a8bab97d4fe3b160148a648970f5638`
- **ブランチ**: `refs/heads/master`
- **日時**: 2026-01-29 09:44:01
- **メッセージ**: "initial safe state"

### 3.2 リスク評価

**🔴 重大なリスク**

1. **APIキー流出リスク**: 
   - `SCRAPINGBEE_API_KEY` が含まれている可能性
   - その他の機密情報（DBパスワード、APP_KEY等）が含まれている可能性

2. **Git履歴からの完全削除が必要**:
   - `.env` は一度コミットされると、履歴から完全に削除しない限り残り続けます
   - GitHub/GitLab等にpush済みの場合、リポジトリ全体の再作成が必要な場合があります

3. **影響範囲**:
   - このコミットをクローンした全員が `.env` を取得可能
   - リモートリポジトリにpush済みの場合、公開リポジトリなら誰でもアクセス可能

---

## ■ 4. 修正手順

### 4.1 .gitignore の修正

以下の内容を `.gitignore` に追加してください：

```gitignore
# 既存の内容...

# Storage ディレクトリ（追加）
/storage/logs
/storage/framework
/storage/puppeteer-runs

# Bootstrap キャッシュ（追加）
/bootstrap/cache/*.php
```

### 4.2 既に追跡されているファイルの削除

#### ステップ1: 追跡から削除（ファイルは保持）

```bash
# storage/puppeteer-runs を追跡から削除
git rm -r --cached storage/puppeteer-runs

# コミット
git commit -m "Remove storage/puppeteer-runs from tracking"
```

#### ステップ2: .gitignore を更新してコミット

```bash
# .gitignore を編集（上記の内容を追加）
# その後、コミット
git add .gitignore
git commit -m "Update .gitignore to exclude storage directories"
```

### 4.3 .env をGit履歴から完全に削除

**⚠️ 警告**: この操作は履歴を書き換えます。チームで共有しているリポジトリの場合は、全員に通知してください。

#### 方法1: git filter-branch（推奨）

```bash
# .env を履歴から完全に削除
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch .env" \
  --prune-empty --tag-name-filter cat -- --all

# 強制プッシュ（リモートにpush済みの場合）
# ⚠️ 注意: この操作は不可逆です
git push origin --force --all
git push origin --force --tags
```

#### 方法2: BFG Repo-Cleaner（より高速）

```bash
# BFGをダウンロード（初回のみ）
# https://rtyley.github.io/bfg-repo-cleaner/

# .env を履歴から削除
java -jar bfg.jar --delete-files .env

# リポジトリをクリーンアップ
git reflog expire --expire=now --all
git gc --prune=now --aggressive
```

#### 方法3: リポジトリ再作成（最も安全）

1. 新しいリポジトリを作成
2. `.env` を除外した状態で全ファイルをコピー
3. 初回コミットとして再作成

---

## ■ 5. GitHubへpush前の対応

### 5.1 必須対応

1. **`.env` を履歴から削除**（上記手順を実行）
2. **`.gitignore` を更新**（上記手順を実行）
3. **追跡されている `storage/` ファイルを削除**（上記手順を実行）
4. **APIキーを再生成**:
   - ScrapingBee APIキーを無効化し、新しいキーを生成
   - データベースパスワードを変更
   - `APP_KEY` を再生成（`php artisan key:generate`）

### 5.2 確認コマンド

```bash
# .env が履歴に含まれていないか再確認
git log --all --full-history --source -- .env

# 追跡されているファイルを再確認
git ls-files | findstr storage
git ls-files | findstr .env
```

---

## ■ 6. 修正後の .gitignore 完全版

```gitignore
*.log
.DS_Store
.env
.env.backup
.env.production
.phpactor.json
.phpunit.result.cache
/.fleet
/.idea
/.nova
/.phpunit.cache
/.vscode
/.zed
/auth.json
/node_modules
/public/build
/public/hot
/public/storage
/storage/*.key
/storage/pail
/storage/logs
/storage/framework
/storage/puppeteer-runs
/vendor
/bootstrap/cache/*.php
Homestead.json
Homestead.yaml
Thumbs.db
```

---

## ■ 7. チェックリスト

### デプロイ前確認

- [ ] `.env` がGit履歴から完全に削除されている
- [ ] `.gitignore` に `/storage/logs` が含まれている
- [ ] `.gitignore` に `/storage/framework` が含まれている
- [ ] `.gitignore` に `/storage/puppeteer-runs` が含まれている
- [ ] `.gitignore` に `/bootstrap/cache/*.php` が含まれている
- [ ] `storage/puppeteer-runs/` が追跡から削除されている
- [ ] APIキーが再生成されている（必要に応じて）
- [ ] `git ls-files | findstr .env` で `.env` が表示されない
- [ ] `git ls-files | findstr storage` で `storage/puppeteer-runs` が表示されない

---

## ■ 8. 緊急対応（既にpush済みの場合）

### 8.1 リモートリポジトリにpush済みの場合

1. **リモートリポジトリを一時的に非公開にする**
2. **上記の履歴削除手順を実行**
3. **APIキーを即座に無効化・再生成**
4. **強制プッシュで履歴を上書き**
5. **チーム全員に通知し、ローカルリポジトリを再クローンしてもらう**

### 8.2 GitHub Secrets の使用

本番環境では、環境変数は **GitHub Secrets** や **環境変数管理サービス** を使用することを強く推奨します。

---

**最終更新**: 2026-02-16

