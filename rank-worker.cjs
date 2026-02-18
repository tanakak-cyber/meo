console.log("🔥 REAL WORKER LOADED 🔥", __filename);
console.log("ARGV", process.argv);
console.log("FILE", __filename);

const { chromium } = require('playwright');
const fs = require('fs').promises;
const path = require('path');
require('dotenv').config();

// ① 起動直後にログ
console.log("===== RANK WORKER BOOT =====");
console.log("node:", process.version);
console.log("cwd:", process.cwd());
console.log("env DB_CONNECTION:", process.env.DB_CONNECTION);
if (process.env.DB_CONNECTION === 'mysql') {
    console.log("MySQL database:", process.env.DB_DATABASE || 'laravel');
} else {
    console.log("sqlite path:", process.env.DB_DATABASE || "database/database.sqlite");
}

// データベース接続の設定
const dbConnection = process.env.DB_CONNECTION || 'sqlite';
let db = null;

// SQLiteまたはMySQLに応じて接続を初期化
if (dbConnection === 'sqlite') {
    const Database = require('better-sqlite3');
    // Laravelのデフォルトパス: database/database.sqlite
    // .envにDB_DATABASEが設定されていない場合は、database/database.sqliteを使用
    let dbPath = process.env.DB_DATABASE;
    if (!dbPath) {
        dbPath = path.join(__dirname, 'database', 'database.sqlite');
    } else if (!path.isAbsolute(dbPath)) {
        // 相対パスの場合は、プロジェクトルートからのパスとして扱う
        dbPath = path.join(__dirname, dbPath);
    }
    db = new Database(dbPath);
    console.log('📊 SQLiteデータベースに接続:', dbPath);
    // ② DB接続直後
    console.log("DB CONNECT OK");
} else {
    const mysql = require('mysql2/promise');
    db = {
        config: {
            host: process.env.DB_HOST || '127.0.0.1',
            port: process.env.DB_PORT || 3306,
            database: process.env.DB_DATABASE || 'laravel',
            user: process.env.DB_USERNAME || 'root',
            password: process.env.DB_PASSWORD || '',
            charset: process.env.DB_CHARSET || 'utf8mb4',
        },
        type: 'mysql'
    };
    console.log('📊 MySQLデータベース設定:', {
        host: db.config.host,
        port: db.config.port,
        database: db.config.database,
        user: db.config.user,
    });
    // ② DB接続直後（MySQLは接続時にログ出力）
    console.log("DB CONNECT OK");
}

/**
 * rank_fetch_jobs から status = 'queued' のジョブを1件取得し、
 * status = 'running' に更新する
 */
async function fetchAndLockJob() {
    let connection = null;
    
    try {
        // ③ ジョブ取得前後
        console.log("JOB FETCH START");
        
        if (dbConnection === 'sqlite') {
            // SQLiteの場合
            console.log('✅ SQLite接続成功');
            
            // トランザクション開始
            db.exec('BEGIN TRANSACTION');
            console.log('📦 トランザクション開始');
            
            // SELECT ... FOR UPDATE でロックをかけてジョブを取得
            // SQLiteでは SKIP LOCKED はサポートされていないため、通常の SELECT を使用
            const stmt = db.prepare(`
                SELECT id, shop_id, meo_keyword_id, target_date, status, requested_by_type, requested_by_id, created_at
                FROM rank_fetch_jobs
                WHERE status = 'queued'
                ORDER BY id ASC
                LIMIT 1
            `);
            const rows = stmt.all();
            
            if (rows.length === 0) {
                db.exec('ROLLBACK');
                // ジョブが無い場合
                console.log("NO QUEUED JOB FOUND");
                return null;
            }
            
            const job = rows[0];
            console.log('🔍 ジョブを取得:', {
                id: job.id,
                shop_id: job.shop_id,
                meo_keyword_id: job.meo_keyword_id,
                target_date: job.target_date,
                status: job.status,
                requested_by_type: job.requested_by_type,
                requested_by_id: job.requested_by_id,
                created_at: job.created_at,
            });
            
            // 店舗情報を取得
            const shopStmt = db.prepare('SELECT name FROM shops WHERE id = ?');
            const shopRows = shopStmt.all(job.shop_id);
            
            if (shopRows.length === 0) {
                db.exec('ROLLBACK');
                console.error('❌ 店舗が見つかりません');
                return null;
            }
            
            const shopName = shopRows[0].name;
            
            // キーワードを取得（meo_keyword_idから直接取得）
            const keywordStmt = db.prepare('SELECT id, keyword FROM meo_keywords WHERE id = ?');
            const keywordRows = keywordStmt.all(job.meo_keyword_id);
            
            if (keywordRows.length === 0) {
                db.exec('ROLLBACK');
                console.error('❌ キーワードが見つかりません');
                return null;
            }
            
            const keyword = keywordRows[0].keyword;
            
            // ジョブを 'running' に更新し、started_at を設定
            const now = new Date().toISOString();
            const updateStmt = db.prepare(`
                UPDATE rank_fetch_jobs
                SET status = 'running',
                    started_at = ?
                WHERE id = ?
            `);
            const updateResult = updateStmt.run(now, job.id);
            
            if (updateResult.changes === 0) {
                db.exec('ROLLBACK');
                console.error('❌ ジョブの更新に失敗しました');
                return null;
            }
            
            // トランザクションをコミット
            db.exec('COMMIT');
            console.log('✅ トランザクションコミット完了');
            console.log('📝 ジョブを running に更新:', {
                id: job.id,
                status: 'running',
                started_at: now,
            });
            
            return {
                id: job.id,
                shop_id: job.shop_id,
                shop_name: shopName,
                target_date: job.target_date,
                keyword: keyword,
                keyword_id: keywordRows[0].id,
                status: 'running',
                started_at: now,
                requested_by_type: job.requested_by_type,
                requested_by_id: job.requested_by_id,
            };
            
        } else {
            // MySQLの場合
            const mysql = require('mysql2/promise');
            connection = await mysql.createConnection(db.config);
            console.log('✅ MySQL接続成功');
            
            // トランザクション開始
            await connection.beginTransaction();
            console.log('📦 トランザクション開始');
            
            // SELECT ... FOR UPDATE でロックをかけてジョブを取得
            const [rows] = await connection.execute(
                `SELECT id, shop_id, meo_keyword_id, target_date, status, requested_by_type, requested_by_id, created_at
                 FROM rank_fetch_jobs
                 WHERE status = 'queued'
                 ORDER BY id ASC
                 LIMIT 1
                 FOR UPDATE SKIP LOCKED`,
                []
            );
            
            if (rows.length === 0) {
                await connection.rollback();
                // ジョブが無い場合
                console.log("NO QUEUED JOB FOUND");
                return null;
            }
            
            const job = rows[0];
            // ジョブがあった場合
            console.log("JOB FOUND", JSON.stringify(job, null, 2));
            console.log('🔍 ジョブを取得:', {
                id: job.id,
                shop_id: job.shop_id,
                meo_keyword_id: job.meo_keyword_id,
                target_date: job.target_date,
                status: job.status,
                requested_by_type: job.requested_by_type,
                requested_by_id: job.requested_by_id,
                created_at: job.created_at,
            });
            
            // 店舗情報を取得
            const [shopRows] = await connection.execute(
                `SELECT name FROM shops WHERE id = ?`,
                [job.shop_id]
            );
            
            if (shopRows.length === 0) {
                await connection.rollback();
                console.error('❌ 店舗が見つかりません');
                return null;
            }
            
            const shopName = shopRows[0].name;
            
            // キーワードを取得（meo_keyword_idから直接取得）
            const [keywordRows] = await connection.execute(
                `SELECT id, keyword FROM meo_keywords WHERE id = ?`,
                [job.meo_keyword_id]
            );
            
            if (keywordRows.length === 0) {
                await connection.rollback();
                console.error('❌ キーワードが見つかりません');
                return null;
            }
            
            const keyword = keywordRows[0].keyword;
            
            // ジョブを 'running' に更新し、started_at を設定
            const now = new Date();
            const [updateResult] = await connection.execute(
                `UPDATE rank_fetch_jobs
                 SET status = 'running',
                     started_at = ?
                 WHERE id = ?`,
                [now, job.id]
            );
            
            if (updateResult.affectedRows === 0) {
                await connection.rollback();
                console.error('❌ ジョブの更新に失敗しました');
                return null;
            }
            
            // トランザクションをコミット
            await connection.commit();
            console.log('✅ トランザクションコミット完了');
            console.log('📝 ジョブを running に更新:', {
                id: job.id,
                status: 'running',
                started_at: now,
            });
            
            return {
                id: job.id,
                shop_id: job.shop_id,
                shop_name: shopName,
                target_date: job.target_date,
                keyword: keyword,
                keyword_id: keywordRows[0].id,
                status: 'running',
                started_at: now,
                requested_by_type: job.requested_by_type,
                requested_by_id: job.requested_by_id,
            };
        }
        
    } catch (error) {
        // エラーが発生した場合はロールバック
        if (dbConnection === 'sqlite') {
            try {
                db.exec('ROLLBACK');
                console.error('❌ エラーが発生したためロールバックしました');
            } catch (rollbackError) {
                // ロールバックエラーは無視
            }
        } else if (connection) {
            await connection.rollback();
            console.error('❌ エラーが発生したためロールバックしました');
        }
        
        console.error('❌ エラー:', error.message);
        console.error('スタックトレース:', error.stack);
        return null;
        
    } finally {
        // MySQL接続を閉じる（SQLiteは閉じない）
        if (dbConnection !== 'sqlite' && connection) {
            await connection.end();
            console.log('🔌 MySQL接続を閉じました');
        }
    }
}

/**
 * 順位をDBに保存し、ジョブを完了する
 */
async function saveRankAndCompleteJob(job, rank, errorMessage = null) {
    let connection = null;
    
    // デバッグログ: 開始
    console.log('SAVE_RANK_START', {
        job_id: job.id,
        meo_keyword_id: job.keyword_id,
        rank: rank,
        checked_at: job.target_date,
        dbConnection: dbConnection,
        errorMessage: errorMessage,
    });
    
    try {
        if (dbConnection === 'sqlite') {
            // SQLiteの場合
            // トランザクション開始
            db.exec('BEGIN TRANSACTION');
            console.log('📦 トランザクション開始（DB保存）');
            
            if (errorMessage) {
                // エラー時: rank_fetch_jobs を failed に更新
                const now = new Date().toISOString();
                const updateStmt = db.prepare(`
                    UPDATE rank_fetch_jobs
                    SET status = 'failed',
                        error_message = ?,
                        finished_at = ?
                    WHERE id = ?
                `);
                updateStmt.run(errorMessage, now, job.id);
                console.log('JOB_MARK_FAILED_OK', {
                    id: job.id,
                    status: 'failed',
                    error_message: errorMessage,
                    finished_at: now,
                });
            } else if (rank !== null) {
                // 成功時: meo_rank_logs に保存（SQLite用: INSERT OR REPLACE）
                const insertStmt = db.prepare(`
                    INSERT OR REPLACE INTO meo_rank_logs
                    (meo_keyword_id, position, checked_at, created_at, updated_at)
                    VALUES (?, ?, ?, datetime('now'), datetime('now'))
                `);
                insertStmt.run(job.keyword_id, rank, job.target_date);
                console.log('SAVE_RANK_UPSERT_OK', {
                    meo_keyword_id: job.keyword_id,
                    rank: rank,
                    checked_at: job.target_date,
                });
                
                // rank_fetch_jobs を success に更新
                const now = new Date().toISOString();
                const updateStmt = db.prepare(`
                    UPDATE rank_fetch_jobs
                    SET status = 'success',
                        finished_at = ?
                    WHERE id = ?
                `);
                updateStmt.run(now, job.id);
                console.log('JOB_MARK_SUCCESS_OK', {
                    id: job.id,
                    status: 'success',
                    finished_at: now,
                });
            } else {
                // rank が null の場合（圏外など）も success として扱う
                const now = new Date().toISOString();
                const updateStmt = db.prepare(`
                    UPDATE rank_fetch_jobs
                    SET status = 'success',
                        finished_at = ?
                    WHERE id = ?
                `);
                updateStmt.run(now, job.id);
                console.log('JOB_MARK_SUCCESS_OK', {
                    id: job.id,
                    status: 'success',
                    finished_at: now,
                    note: 'rank is null (out of range)',
                });
            }
            
            // トランザクションをコミット
            db.exec('COMMIT');
            console.log('✅ トランザクションコミット完了（DB保存）');
            return true;
            
        } else {
            // MySQLの場合
            const mysql = require('mysql2/promise');
            connection = await mysql.createConnection(db.config);
            console.log('✅ MySQL接続成功（DB保存）');
            
            // トランザクション開始
            await connection.beginTransaction();
            console.log('📦 トランザクション開始（DB保存）');
            
            if (errorMessage) {
                // エラー時: rank_fetch_jobs を failed に更新
                const now = new Date();
                await connection.execute(
                    `UPDATE rank_fetch_jobs
                     SET status = 'failed',
                         error_message = ?,
                         finished_at = ?
                     WHERE id = ?`,
                    [errorMessage, now, job.id]
                );
                console.log('JOB_MARK_FAILED_OK', {
                    id: job.id,
                    status: 'failed',
                    error_message: errorMessage,
                    finished_at: now,
                });
            } else if (rank !== null) {
                // 成功時: meo_rank_logs に保存（MySQL用: INSERT ... ON DUPLICATE KEY UPDATE）
                await connection.execute(
                    "INSERT INTO meo_rank_logs (`meo_keyword_id`, `position`, `checked_at`, `created_at`, `updated_at`) " +
                    "VALUES (?, ?, ?, NOW(), NOW()) " +
                    "ON DUPLICATE KEY UPDATE `position` = VALUES(`position`), `updated_at` = NOW()",
                    [job.keyword_id, rank, job.target_date]
                );
                console.log('SAVE_RANK_UPSERT_OK', {
                    meo_keyword_id: job.keyword_id,
                    rank: rank,
                    checked_at: job.target_date,
                });
                
                // rank_fetch_jobs を success に更新
                const now = new Date();
                await connection.execute(
                    `UPDATE rank_fetch_jobs
                     SET status = 'success',
                         finished_at = ?
                     WHERE id = ?`,
                    [now, job.id]
                );
                console.log('JOB_MARK_SUCCESS_OK', {
                    id: job.id,
                    status: 'success',
                    finished_at: now,
                });
            } else {
                // rank が null の場合（圏外など）も success として扱う
                const now = new Date();
                await connection.execute(
                    `UPDATE rank_fetch_jobs
                     SET status = 'success',
                         finished_at = ?
                     WHERE id = ?`,
                    [now, job.id]
                );
                console.log('JOB_MARK_SUCCESS_OK', {
                    id: job.id,
                    status: 'success',
                    finished_at: now,
                    note: 'rank is null (out of range)',
                });
            }
            
            // トランザクションをコミット
            await connection.commit();
            console.log('✅ トランザクションコミット完了（DB保存）');
            return true;
        }
        
    } catch (error) {
        // エラーが発生した場合はロールバック
        if (dbConnection === 'sqlite') {
            try {
                db.exec('ROLLBACK');
                console.error('❌ エラーが発生したためロールバックしました');
            } catch (rollbackError) {
                // ロールバックエラーは無視
            }
        } else if (connection) {
            try {
                await connection.rollback();
                console.error('❌ エラーが発生したためロールバックしました');
            } catch (rollbackError) {
                // ロールバックエラーは無視
            }
        }
        
        console.error('SAVE_RANK_FAILED', error);
        return false;
        
    } finally {
        // MySQL接続を閉じる（SQLiteは閉じない）
        if (dbConnection !== 'sqlite' && connection) {
            await connection.end();
            console.log('🔌 MySQL接続を閉じました（DB保存）');
        }
    }
}

/**
 * Google Maps検索で順位を取得
 */
async function fetchGoogleMapsRank(keyword, shopName) {
    let browser = null;
    let page = null;
    
    try {
        // ④ Playwright 起動直前
        console.log("PLAYWRIGHT LAUNCH START");
        
        // ⑤ chromium.launch() の直前
        const launchOptions = {
            headless: false,
            slowMo: 50, // 人間らしい動作速度
            args: [
                "--no-sandbox",
                "--disable-setuid-sandbox",
                "--disable-dev-shm-usage",
                "--ignore-certificate-errors",
                "--allow-running-insecure-content"
            ],
        };
        console.log("CHROMIUM OPTIONS", JSON.stringify(launchOptions, null, 2));
        
        // Chromiumを起動（人間のChromeとして動作）
        browser = await chromium.launch(launchOptions);
        
        // ⑥ browser が返った直後
        console.log("BROWSER OK");
        
        // ⑦ newContext() の直前と直後
        console.log("CONTEXT CREATE");
        // 実ブラウザのChromeに近いUser-Agent
        const context = await browser.newContext({
            ignoreHTTPSErrors: true,
            viewport: { width: 1280, height: 800 },
            locale: 'ja-JP',
            timezoneId: 'Asia/Tokyo',
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        });
        console.log("CONTEXT OK");
        
        // ⑧ page.newPage() の前後
        console.log("PAGE CREATE");
        page = await context.newPage();
        console.log("PAGE OK");
        console.log('🌐 ブラウザを起動しました（人間のChromeモード）');
        
        // Google Maps検索URL
        const searchUrl = `https://www.google.com/maps/search/${encodeURIComponent(keyword)}`;
        console.log('🔍 検索URL:', searchUrl);
        console.log('🔍 キーワード:', keyword);
        console.log('🏪 店舗名:', shopName);
        
        // ⑨ page.goto() の直前
        console.log("GOTO URL", searchUrl);
        
        // ページに移動
        await page.goto(searchUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
        
        // ⑩ page.goto() の成功後
        console.log("PAGE LOADED");
        await page.waitForTimeout(3000); // 読み込み待機
        
        // CAPTCHAチェック
        const captchaSelectors = [
            'iframe[src*="recaptcha"]',
            'div[class*="captcha"]',
            'div[id*="captcha"]',
            'iframe[title*="reCAPTCHA"]',
        ];
        
        let hasCaptcha = false;
        for (const selector of captchaSelectors) {
            const captchaElement = await page.$(selector);
            if (captchaElement) {
                hasCaptcha = true;
                console.warn('⚠️  CAPTCHAが検出されました');
                break;
            }
        }
        
        // ページのテキストからCAPTCHAを検出
        const pageText = await page.textContent('body');
        if (pageText && (
            pageText.includes('CAPTCHA') ||
            pageText.includes('captcha') ||
            pageText.includes('reCAPTCHA') ||
            pageText.includes('verify you\'re not a robot')
        )) {
            hasCaptcha = true;
            console.warn('⚠️  CAPTCHAが検出されました（テキスト検出）');
        }
        
        if (hasCaptcha) {
            // スクリーンショットを保存
            const screenshotPath = path.join(__dirname, `captcha-${Date.now()}.png`);
            await page.screenshot({ path: screenshotPath, fullPage: true });
            console.log('📸 CAPTCHAスクリーンショットを保存:', screenshotPath);
            return null;
        }
        
        // ⑪ feed selector 待機前
        console.log("WAITING FEED");
        
        // 左側の一覧（role="feed"）を取得
        console.log('📋 検索結果一覧を待機中...');
        const feed = await page.waitForSelector('div[role="feed"]', { timeout: 15000 }).catch(() => null);
        
        if (!feed) {
            console.warn('⚠️  検索結果一覧が見つかりませんでした');
            await page.screenshot({ path: 'maps-error.png' });
            console.log('📸 エラー時のスクリーンショットを保存: maps-error.png');
            return null;
        }
        
        // ⑫ feed 取得後
        console.log("FEED FOUND");
        
        // ⑬ スクロールのループ
        console.log('📜 検索結果をスクロールしてロード中...');
        for (let i = 0; i < 20; i++) {
            console.log("SCROLL LOOP", i);
            await feed.evaluate(el => el.scrollBy(0, 1200));
            await page.waitForTimeout(1000);
            if ((i + 1) % 5 === 0) {
                console.log(`  ${i + 1}/20 回スクロール完了`);
            }
        }
        console.log('✅ スクロール完了');
        
        // スクロール後に店舗名の検索と順位カウントを実行
        await page.waitForTimeout(2000); // 最終的な読み込み待機
        
        // ⑭ 店舗名取得前
        console.log("EXTRACT NAMES");
        
        // ⑮ RANK 計算前後
        console.log("CALC RANK");
        const rankResult = await page.evaluate((targetShopName) => {
            /**
             * スポンサー（広告）判定関数
             * 優先度順に複数の条件で判定（1つでも該当すればスポンサーと判定）
             */
            function isSponsored(article) {
                const reasons = [];
                
                // 【優先度 高】data-is-ad="1" を持つ要素が article の祖先/子孫に存在する
                const adAncestor = article.closest('[data-is-ad="1"]');
                if (adAncestor) {
                    reasons.push('data-is-ad-ancestor');
                    return { isSponsored: true, reason: 'data-is-ad-ancestor', reasons };
                }
                
                // article 自身または子孫に data-is-ad="1" が存在するか
                const adDescendant = article.querySelector('[data-is-ad="1"]');
                if (adDescendant) {
                    reasons.push('data-is-ad-descendant');
                    return { isSponsored: true, reason: 'data-is-ad-descendant', reasons };
                }
                
                // 【優先度 中】article 内のテキストに「スポンサー」「広告」「Sponsored」「Ad」が含まれる
                const articleText = article.textContent || article.innerText || '';
                const sponsorKeywords = ['スポンサー', '広告', 'Sponsored', 'Ad', '広告の表示について'];
                let foundKeyword = null;
                for (const keyword of sponsorKeywords) {
                    if (articleText.includes(keyword)) {
                        // バッジっぽい位置（最初の100文字以内）を優先
                        const firstPart = articleText.substring(0, 100);
                        if (firstPart.includes(keyword)) {
                            foundKeyword = keyword;
                            reasons.push(`text-${keyword}-badge`);
                            break;
                        } else if (!foundKeyword) {
                            foundKeyword = keyword;
                            reasons.push(`text-${keyword}`);
                        }
                    }
                }
                if (foundKeyword) {
                    return { 
                        isSponsored: true, 
                        reason: reasons[reasons.length - 1], 
                        reasons 
                    };
                }
                
                // 【優先度 中】aria-label="広告の表示について" 等の広告UIラベルが存在する
                const adAriaLabels = [
                    '広告の表示について',
                    '広告について',
                    'About this ad',
                    'Ad information'
                ];
                for (const label of adAriaLabels) {
                    const adLabelElement = article.querySelector(`[aria-label*="${label}"]`);
                    if (adLabelElement) {
                        reasons.push(`aria-label-${label}`);
                        return { isSponsored: true, reason: `aria-label-${label}`, reasons };
                    }
                }
                
                // 【優先度 低】その他、広告枠特有のDOM（class/jsname等）に依存するものは最後の手段
                // 注意: Google MapsのDOM構造が変わると動かなくなる可能性があるため、最後の手段として使用
                const adClassPatterns = [
                    '[class*="ad"]',
                    '[class*="sponsor"]',
                    '[jsname*="ad"]',
                    '[jsname*="sponsor"]'
                ];
                for (const pattern of adClassPatterns) {
                    const adElement = article.querySelector(pattern);
                    if (adElement) {
                        // より確実な判定のため、テキストも確認
                        const elementText = adElement.textContent || '';
                        if (elementText.includes('スポンサー') || elementText.includes('広告') || 
                            elementText.includes('Sponsored') || elementText.includes('Ad')) {
                            reasons.push(`class-${pattern}`);
                            return { isSponsored: true, reason: `class-${pattern}`, reasons };
                        }
                    }
                }
                
                return { isSponsored: false, reason: null, reasons: [] };
            }
            
            // スクロール後のDOMから全店舗名を取得
            // Google Mapsの検索結果は div[role="article"] に含まれる
            const articlesAll = Array.from(document.querySelectorAll('div[role="article"]'));
            
            if (articlesAll.length === 0) {
                return {
                    rank: -1,
                    allCount: 0,
                    organicCount: 0,
                    targetIndexAll: null,
                    targetIndexOrganic: null,
                    debugLog: []
                };
            }
            
            // スポンサー判定と店舗名抽出を同時に行う
            const articleData = [];
            for (let i = 0; i < articlesAll.length; i++) {
                const article = articlesAll[i];
                const sponsorCheck = isSponsored(article);
                
                // 店舗名を抽出
                let shopName = null;
                let href = null;
                const link = article.querySelector('a[href*="/maps/place/"]');
                if (link) {
                    shopName = (link.textContent || link.innerText || '').trim();
                    href = link.getAttribute('href') || '';
                }
                
                // 店舗名が見つからない場合は、記事全体のテキストから抽出を試みる
                if (!shopName) {
                    const text = article.textContent || article.innerText || '';
                    const lines = text.split('\n').filter(line => line.trim());
                    if (lines.length > 0) {
                        shopName = lines[0].trim();
                    }
                }
                
                articleData.push({
                    index: i,
                    shopName: shopName || '(店舗名不明)',
                    href: href || '(href不明)',
                    isSponsored: sponsorCheck.isSponsored,
                    reason: sponsorCheck.reason,
                    reasons: sponsorCheck.reasons
                });
            }
            
            // オーガニック結果のみを抽出
            const articlesOrganic = articleData.filter(a => !a.isSponsored);
            
            // 修正前: 全結果から順位を計算
            let targetIndexAll = null;
            for (let i = 0; i < articleData.length; i++) {
                if (articleData[i].shopName && articleData[i].shopName.includes(targetShopName)) {
                    targetIndexAll = i;
                    break;
                }
            }
            
            // 修正後: オーガニック結果のみから順位を計算
            let targetIndexOrganic = null;
            for (let i = 0; i < articlesOrganic.length; i++) {
                if (articlesOrganic[i].shopName && articlesOrganic[i].shopName.includes(targetShopName)) {
                    targetIndexOrganic = i;
                    break;
                }
            }
            
            // デバッグログ用: 上位10件の詳細情報
            const debugLog = articleData.slice(0, 10).map(a => ({
                index: a.index,
                shopName: a.shopName,
                href: a.href.substring(0, 100), // 長すぎる場合は切り詰め
                isSponsored: a.isSponsored,
                reason: a.reason,
                reasons: a.reasons
            }));
            
            // 最終的な順位（オーガニック結果のみから計算）
            const rank = targetIndexOrganic !== null ? targetIndexOrganic + 1 : null;
            
            return {
                rank: rank,
                allCount: articlesAll.length,
                organicCount: articlesOrganic.length,
                sponsoredCount: articlesAll.length - articlesOrganic.length,
                targetIndexAll: targetIndexAll !== null ? targetIndexAll + 1 : null,
                targetIndexOrganic: targetIndexOrganic !== null ? targetIndexOrganic + 1 : null,
                debugLog: debugLog
            };
        }, shopName);
        
        // ⑮ RANK 計算前後
        const rank = rankResult.rank;
        const allCount = rankResult.allCount;
        const organicCount = rankResult.organicCount;
        const sponsoredCount = rankResult.sponsoredCount;
        const targetIndexAll = rankResult.targetIndexAll;
        const targetIndexOrganic = rankResult.targetIndexOrganic;
        const debugLog = rankResult.debugLog;
        
        // ログ出力: 修正前後の比較
        console.log("=== 順位取得結果（スポンサー除外前後比較） ===");
        console.log(`全結果数: ${allCount}`);
        console.log(`オーガニック結果数: ${organicCount}`);
        console.log(`スポンサー結果数: ${sponsoredCount}`);
        console.log(`修正前の順位（全結果から）: ${targetIndexAll || '圏外'}`);
        console.log(`修正後の順位（オーガニックのみ）: ${targetIndexOrganic || '圏外'}`);
        if (targetIndexAll !== null && targetIndexOrganic !== null && targetIndexAll !== targetIndexOrganic) {
            console.log(`⚠️  順位のズレを検出: ${targetIndexAll}位 → ${targetIndexOrganic}位 (${targetIndexAll - targetIndexOrganic}位のズレ)`);
        }
        
        // デバッグログ: 上位10件の詳細情報
        console.log("\n=== 上位10件の詳細情報 ===");
        debugLog.forEach((item, idx) => {
            console.log(`${idx + 1}. [${item.index}] ${item.shopName}`);
            console.log(`   href: ${item.href}`);
            console.log(`   スポンサー: ${item.isSponsored ? 'YES' : 'NO'} (理由: ${item.reason || 'なし'})`);
            if (item.reasons && item.reasons.length > 0) {
                console.log(`   判定理由詳細: ${item.reasons.join(', ')}`);
            }
        });
        
        // 検索結果が出なかった場合
        if (rank === -1) {
            console.warn('⚠️  検索結果が見つかりませんでした');
            await page.screenshot({ path: 'maps-error.png' });
            console.log('📸 エラー時のスクリーンショットを保存: maps-error.png');
            return null;
        }
        
        // 店舗名が含まれる要素の順位を出力
        if (rank !== null) {
            console.log(`\n✅ 最終順位: ${rank}位（オーガニック結果のみから算出）`);
        } else {
            console.log('\n❌ RANK: null (圏外)');
        }
        
        // 追加ログ: ジョブ情報と一緒に出力（後でDBに保存する場合に備えて）
        console.log("\n=== 順位取得ログ（DB保存用） ===");
        console.log(JSON.stringify({
            keyword: keyword,
            shopName: shopName,
            allCount: allCount,
            organicCount: organicCount,
            sponsoredCount: sponsoredCount,
            targetIndexAll: targetIndexAll,
            targetIndexOrganic: targetIndexOrganic,
            finalRank: rank,
            debugLog: debugLog
        }, null, 2));
        
        return rank;
        
    } catch (error) {
        // ⑯ 例外キャッチ
        console.error("WORKER FATAL", error.stack || error);
        console.error('❌ Google Maps検索エラー:', error.message);
        
        // エラー時もスクリーンショットを保存
        if (page) {
            try {
                const screenshotPath = path.join(__dirname, `error-${Date.now()}.png`);
                await page.screenshot({ path: screenshotPath, fullPage: true });
                console.log('📸 エラー時のスクリーンショットを保存:', screenshotPath);
            } catch (screenshotError) {
                console.error('スクリーンショット保存エラー:', screenshotError.message);
            }
        }
        
        throw error;
        
    } finally {
        // ⑰ finally
        console.log("WORKER END");
        
        // ブラウザを閉じる
        if (browser) {
            await browser.close();
            console.log('🔌 ブラウザを閉じました');
        }
    }
}

/**
 * 1件のジョブを処理
 */
async function processOneJob() {
    if (dbConnection === 'sqlite') {
        console.log('📊 DB接続情報: SQLite');
    } else {
        console.log('📊 DB接続情報:', {
            host: db.config.host,
            port: db.config.port,
            database: db.config.database,
            user: db.config.user,
        });
    }
    
    const job = await fetchAndLockJob();
    
    if (job) {
        console.log('✅ ジョブを正常に取得・更新しました:');
        console.log(JSON.stringify(job, null, 2));
        
        // Google Maps検索を実行
        console.log('\n🔍 Google Maps検索を開始します...');
        let rank = null;
        let searchError = null;
        
        try {
            rank = await fetchGoogleMapsRank(job.keyword, job.shop_name);
            
            if (rank !== null) {
                console.log('RANK:', rank);
            } else {
                console.log('RANK: null (圏外またはCAPTCHA)');
            }
        } catch (error) {
            console.error('❌ Google Maps検索でエラーが発生しました:', error.message);
            searchError = error;
        }
        
        // DB保存とジョブ完了処理
        if (searchError) {
            // エラー時: rank_fetch_jobs を failed に更新
            await saveRankAndCompleteJob(job, null, searchError.message);
        } else if (rank !== null) {
            // 成功時: meo_rank_logs に保存し、rank_fetch_jobs を success に更新
            await saveRankAndCompleteJob(job, rank);
        } else {
            // rank が null の場合（圏外など）も success として扱う
            await saveRankAndCompleteJob(job, null);
        }
    } else {
        console.log('ℹ️  ジョブなし（5秒後に再チェック）');
    }
}

// メイン処理（常駐ワーカー）
async function main() {
    console.log('🚀 rank-worker.cjs を開始します（常駐モード）');
    
    while (true) {
        try {
            await processOneJob();
        } catch (e) {
            console.error('WORKER ERROR', e);
        }

        // 5秒待ってから次のキューを確認
        await new Promise(r => setTimeout(r, 5000));
    }
}

// スクリプト実行
main().catch(error => {
    // ⑯ 例外キャッチ
    console.error("WORKER FATAL", error.stack || error);
    console.error('❌ 致命的なエラー:', error);
    process.exit(1);
}).finally(() => {
    // ⑰ finally
    console.log("WORKER END");
    
    // SQLite接続を閉じる
    if (dbConnection === 'sqlite' && db) {
        db.close();
        console.log('🔌 SQLite接続を閉じました');
    }
});

