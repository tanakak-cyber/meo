const express = require('express');
const { chromium } = require('playwright');
const axios = require('axios');

const app = express();
app.use(express.json());

// 同時実行タブ数の制限（最大5）
const MAX_CONCURRENT_TABS = 5;
let activeTabs = 0;
const jobQueue = [];

// 人間Chromeに接続（9222ポート）
let browser = null;
let context = null;

async function connectToChrome() {
    try {
        browser = await chromium.connectOverCDP('http://localhost:9222');
        const contexts = browser.contexts();
        if (contexts.length > 0) {
            context = contexts[0];
        } else {
            context = await browser.newContext();
        }
        console.log('✅ 人間Chromeに接続しました');
    } catch (error) {
        console.error('❌ Chrome接続エラー:', error.message);
        throw error;
    }
}

// 初期接続
connectToChrome().catch(err => {
    console.error('初期接続に失敗しました。Chromeが9222ポートで起動しているか確認してください。');
});

// ランダム待機（2〜5秒）
function randomDelay() {
    return Math.floor(Math.random() * 3000) + 2000; // 2000-5000ms
}

// Google検索で順位を取得
async function fetchGoogleRank(keyword, shopName, gbpUrl, retryCount = 0) {
    const MAX_RETRIES = 3;
    
    try {
        // 同時実行タブ数の制限チェック
        while (activeTabs >= MAX_CONCURRENT_TABS) {
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
        
        activeTabs++;
        
        // 新しいタブを作成
        const page = await context.newPage();
        
        try {
            // Google検索
            const searchUrl = `https://www.google.com/search?q=${encodeURIComponent(keyword)}`;
            await page.goto(searchUrl, { waitUntil: 'networkidle', timeout: 30000 });
            await page.waitForTimeout(3000); // 読み込み待機
            
            // 検索結果から店舗名 or GBP URLでヒット順位を特定
            const results = await page.evaluate((targetName, targetUrl) => {
                const elements = Array.from(document.querySelectorAll('div.g'));
                return elements.map((el, index) => {
                    const link = el.querySelector('a');
                    const url = link ? link.href : '';
                    const title = el.querySelector('h3')?.textContent || '';
                    const snippet = el.querySelector('div[data-sncf]')?.textContent || '';
                    return { 
                        rank: index + 1, 
                        url, 
                        title,
                        snippet,
                        matches: title.includes(targetName) || 
                                snippet.includes(targetName) ||
                                (targetUrl && url.includes(targetUrl))
                    };
                }).filter(r => r.matches);
            }, shopName, gbpUrl);
            
            // タブを閉じる
            await page.close();
            activeTabs--;
            
            // 順位を返す（見つからなければnull=圏外）
            return results.length > 0 ? results[0].rank : null;
        } catch (error) {
            await page.close();
            activeTabs--;
            throw error;
        }
    } catch (error) {
        console.error(`順位取得エラー (リトライ ${retryCount}/${MAX_RETRIES}):`, error.message);
        
        // リトライ
        if (retryCount < MAX_RETRIES) {
            await new Promise(resolve => setTimeout(resolve, randomDelay()));
            return fetchGoogleRank(keyword, shopName, gbpUrl, retryCount + 1);
        }
        
        throw error;
    }
}

// ジョブを処理
async function processJob(jobData) {
    const { job_id, shop_id, date, keywords, shop_name, gbp_url } = jobData;
    
    console.log(`\n📊 ジョブ開始: job_id=${job_id}, shop_id=${shop_id}, date=${date}, keywords=${keywords.length}件`);
    
    try {
        // 各キーワードの順位を取得
        for (let i = 0; i < keywords.length; i++) {
            const keywordData = keywords[i];
            console.log(`  [${i + 1}/${keywords.length}] キーワード: "${keywordData.keyword}"`);
            
            try {
                const rank = await fetchGoogleRank(keywordData.keyword, shop_name, gbp_url);
                
                console.log(`    → 順位: ${rank || '圏外'}`);
                
                // Laravel APIに結果をPOST
                await axios.post('http://localhost:8000/api/rank-log', {
                    shop_id,
                    meo_keyword_id: keywordData.id,
                    rank: rank,
                    checked_at: date,
                }, {
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                
                // キーワード間にランダム待機（最後のキーワード以外）
                if (i < keywords.length - 1) {
                    const delay = randomDelay();
                    console.log(`    ⏳ ${delay}ms待機...`);
                    await new Promise(resolve => setTimeout(resolve, delay));
                }
            } catch (error) {
                console.error(`    ❌ エラー: ${error.message}`);
                // エラーが発生しても次のキーワードを続行
            }
        }
        
        // ジョブ完了通知
        await axios.post('http://localhost:8000/api/rank-fetch/finish', {
            job_id,
            status: 'success',
        }, {
            headers: {
                'Content-Type': 'application/json',
            },
        });
        
        console.log(`✅ ジョブ完了: job_id=${job_id}`);
    } catch (error) {
        console.error(`❌ ジョブ失敗: job_id=${job_id}, error=${error.message}`);
        
        // エラー通知
        await axios.post('http://localhost:8000/api/rank-fetch/finish', {
            job_id,
            status: 'failed',
            error_message: error.message,
        }, {
            headers: {
                'Content-Type': 'application/json',
            },
        }).catch(err => {
            console.error('エラー通知の送信に失敗:', err.message);
        });
    }
}

// ジョブキューを処理
async function processQueue() {
    while (true) {
        if (jobQueue.length > 0 && activeTabs < MAX_CONCURRENT_TABS) {
            const job = jobQueue.shift();
            // 非同期で処理（待機しない）
            processJob(job).catch(err => {
                console.error('ジョブ処理エラー:', err);
            });
        }
        await new Promise(resolve => setTimeout(resolve, 1000));
    }
}

// ジョブキュー処理を開始
processQueue();

// Laravelからジョブを受信
app.post('/api/fetch-ranks', async (req, res) => {
    const jobData = req.body;
    
    console.log('\n📥 ジョブ受信:', {
        job_id: jobData.job_id,
        shop_id: jobData.shop_id,
        date: jobData.date,
        keywords_count: jobData.keywords?.length || 0,
    });
    
    // キューに追加
    jobQueue.push(jobData);
    
    res.json({ success: true, message: 'ジョブをキューに追加しました' });
});

// ヘルスチェック
app.get('/health', (req, res) => {
    res.json({ 
        status: 'ok',
        activeTabs: activeTabs,
        queueLength: jobQueue.length,
        chromeConnected: browser !== null,
    });
});

const PORT = 3000;
app.listen(PORT, () => {
    console.log('Node.js server running on port 3000');
    console.log(`📊 ヘルスチェック: http://localhost:${PORT}/health`);
    console.log(`\n⚠️  注意: Chromeが9222ポートで起動している必要があります`);
    console.log(`   起動コマンド: chrome.exe --remote-debugging-port=9222\n`);
});

const { chromium } = require('playwright');
const axios = require('axios');

const app = express();
app.use(express.json());

// 同時実行タブ数の制限（最大5）
const MAX_CONCURRENT_TABS = 5;
let activeTabs = 0;
const jobQueue = [];

// 人間Chromeに接続（9222ポート）
let browser = null;
let context = null;

async function connectToChrome() {
    try {
        browser = await chromium.connectOverCDP('http://localhost:9222');
        const contexts = browser.contexts();
        if (contexts.length > 0) {
            context = contexts[0];
        } else {
            context = await browser.newContext();
        }
        console.log('✅ 人間Chromeに接続しました');
    } catch (error) {
        console.error('❌ Chrome接続エラー:', error.message);
        throw error;
    }
}

// 初期接続
connectToChrome().catch(err => {
    console.error('初期接続に失敗しました。Chromeが9222ポートで起動しているか確認してください。');
});

// ランダム待機（2〜5秒）
function randomDelay() {
    return Math.floor(Math.random() * 3000) + 2000; // 2000-5000ms
}

// Google検索で順位を取得
async function fetchGoogleRank(keyword, shopName, gbpUrl, retryCount = 0) {
    const MAX_RETRIES = 3;
    
    try {
        // 同時実行タブ数の制限チェック
        while (activeTabs >= MAX_CONCURRENT_TABS) {
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
        
        activeTabs++;
        
        // 新しいタブを作成
        const page = await context.newPage();
        
        try {
            // Google検索
            const searchUrl = `https://www.google.com/search?q=${encodeURIComponent(keyword)}`;
            await page.goto(searchUrl, { waitUntil: 'networkidle', timeout: 30000 });
            await page.waitForTimeout(3000); // 読み込み待機
            
            // 検索結果から店舗名 or GBP URLでヒット順位を特定
            const results = await page.evaluate((targetName, targetUrl) => {
                const elements = Array.from(document.querySelectorAll('div.g'));
                return elements.map((el, index) => {
                    const link = el.querySelector('a');
                    const url = link ? link.href : '';
                    const title = el.querySelector('h3')?.textContent || '';
                    const snippet = el.querySelector('div[data-sncf]')?.textContent || '';
                    return { 
                        rank: index + 1, 
                        url, 
                        title,
                        snippet,
                        matches: title.includes(targetName) || 
                                snippet.includes(targetName) ||
                                (targetUrl && url.includes(targetUrl))
                    };
                }).filter(r => r.matches);
            }, shopName, gbpUrl);
            
            // タブを閉じる
            await page.close();
            activeTabs--;
            
            // 順位を返す（見つからなければnull=圏外）
            return results.length > 0 ? results[0].rank : null;
        } catch (error) {
            await page.close();
            activeTabs--;
            throw error;
        }
    } catch (error) {
        console.error(`順位取得エラー (リトライ ${retryCount}/${MAX_RETRIES}):`, error.message);
        
        // リトライ
        if (retryCount < MAX_RETRIES) {
            await new Promise(resolve => setTimeout(resolve, randomDelay()));
            return fetchGoogleRank(keyword, shopName, gbpUrl, retryCount + 1);
        }
        
        throw error;
    }
}

// ジョブを処理
async function processJob(jobData) {
    const { job_id, shop_id, date, keywords, shop_name, gbp_url } = jobData;
    
    console.log(`\n📊 ジョブ開始: job_id=${job_id}, shop_id=${shop_id}, date=${date}, keywords=${keywords.length}件`);
    
    try {
        // 各キーワードの順位を取得
        for (let i = 0; i < keywords.length; i++) {
            const keywordData = keywords[i];
            console.log(`  [${i + 1}/${keywords.length}] キーワード: "${keywordData.keyword}"`);
            
            try {
                const rank = await fetchGoogleRank(keywordData.keyword, shop_name, gbp_url);
                
                console.log(`    → 順位: ${rank || '圏外'}`);
                
                // Laravel APIに結果をPOST
                await axios.post('http://localhost:8000/api/rank-log', {
                    shop_id,
                    meo_keyword_id: keywordData.id,
                    rank: rank,
                    checked_at: date,
                }, {
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                
                // キーワード間にランダム待機（最後のキーワード以外）
                if (i < keywords.length - 1) {
                    const delay = randomDelay();
                    console.log(`    ⏳ ${delay}ms待機...`);
                    await new Promise(resolve => setTimeout(resolve, delay));
                }
            } catch (error) {
                console.error(`    ❌ エラー: ${error.message}`);
                // エラーが発生しても次のキーワードを続行
            }
        }
        
        // ジョブ完了通知
        await axios.post('http://localhost:8000/api/rank-fetch/finish', {
            job_id,
            status: 'success',
        }, {
            headers: {
                'Content-Type': 'application/json',
            },
        });
        
        console.log(`✅ ジョブ完了: job_id=${job_id}`);
    } catch (error) {
        console.error(`❌ ジョブ失敗: job_id=${job_id}, error=${error.message}`);
        
        // エラー通知
        await axios.post('http://localhost:8000/api/rank-fetch/finish', {
            job_id,
            status: 'failed',
            error_message: error.message,
        }, {
            headers: {
                'Content-Type': 'application/json',
            },
        }).catch(err => {
            console.error('エラー通知の送信に失敗:', err.message);
        });
    }
}

// ジョブキューを処理
async function processQueue() {
    while (true) {
        if (jobQueue.length > 0 && activeTabs < MAX_CONCURRENT_TABS) {
            const job = jobQueue.shift();
            // 非同期で処理（待機しない）
            processJob(job).catch(err => {
                console.error('ジョブ処理エラー:', err);
            });
        }
        await new Promise(resolve => setTimeout(resolve, 1000));
    }
}

// ジョブキュー処理を開始
processQueue();

// Laravelからジョブを受信
app.post('/api/fetch-ranks', async (req, res) => {
    const jobData = req.body;
    
    console.log('\n📥 ジョブ受信:', {
        job_id: jobData.job_id,
        shop_id: jobData.shop_id,
        date: jobData.date,
        keywords_count: jobData.keywords?.length || 0,
    });
    
    // キューに追加
    jobQueue.push(jobData);
    
    res.json({ success: true, message: 'ジョブをキューに追加しました' });
});

// ヘルスチェック
app.get('/health', (req, res) => {
    res.json({ 
        status: 'ok',
        activeTabs: activeTabs,
        queueLength: jobQueue.length,
        chromeConnected: browser !== null,
    });
});

const PORT = 3000;
app.listen(PORT, () => {
    console.log('Node.js server running on port 3000');
    console.log(`📊 ヘルスチェック: http://localhost:${PORT}/health`);
    console.log(`\n⚠️  注意: Chromeが9222ポートで起動している必要があります`);
    console.log(`   起動コマンド: chrome.exe --remote-debugging-port=9222\n`);
});

const { chromium } = require('playwright');
const axios = require('axios');

const app = express();
app.use(express.json());

// 同時実行タブ数の制限（最大5）
const MAX_CONCURRENT_TABS = 5;
let activeTabs = 0;
const jobQueue = [];

// 人間Chromeに接続（9222ポート）
let browser = null;
let context = null;

async function connectToChrome() {
    try {
        browser = await chromium.connectOverCDP('http://localhost:9222');
        const contexts = browser.contexts();
        if (contexts.length > 0) {
            context = contexts[0];
        } else {
            context = await browser.newContext();
        }
        console.log('✅ 人間Chromeに接続しました');
    } catch (error) {
        console.error('❌ Chrome接続エラー:', error.message);
        throw error;
    }
}

// 初期接続
connectToChrome().catch(err => {
    console.error('初期接続に失敗しました。Chromeが9222ポートで起動しているか確認してください。');
});

// ランダム待機（2〜5秒）
function randomDelay() {
    return Math.floor(Math.random() * 3000) + 2000; // 2000-5000ms
}

// Google検索で順位を取得
async function fetchGoogleRank(keyword, shopName, gbpUrl, retryCount = 0) {
    const MAX_RETRIES = 3;
    
    try {
        // 同時実行タブ数の制限チェック
        while (activeTabs >= MAX_CONCURRENT_TABS) {
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
        
        activeTabs++;
        
        // 新しいタブを作成
        const page = await context.newPage();
        
        try {
            // Google検索
            const searchUrl = `https://www.google.com/search?q=${encodeURIComponent(keyword)}`;
            await page.goto(searchUrl, { waitUntil: 'networkidle', timeout: 30000 });
            await page.waitForTimeout(3000); // 読み込み待機
            
            // 検索結果から店舗名 or GBP URLでヒット順位を特定
            const results = await page.evaluate((targetName, targetUrl) => {
                const elements = Array.from(document.querySelectorAll('div.g'));
                return elements.map((el, index) => {
                    const link = el.querySelector('a');
                    const url = link ? link.href : '';
                    const title = el.querySelector('h3')?.textContent || '';
                    const snippet = el.querySelector('div[data-sncf]')?.textContent || '';
                    return { 
                        rank: index + 1, 
                        url, 
                        title,
                        snippet,
                        matches: title.includes(targetName) || 
                                snippet.includes(targetName) ||
                                (targetUrl && url.includes(targetUrl))
                    };
                }).filter(r => r.matches);
            }, shopName, gbpUrl);
            
            // タブを閉じる
            await page.close();
            activeTabs--;
            
            // 順位を返す（見つからなければnull=圏外）
            return results.length > 0 ? results[0].rank : null;
        } catch (error) {
            await page.close();
            activeTabs--;
            throw error;
        }
    } catch (error) {
        console.error(`順位取得エラー (リトライ ${retryCount}/${MAX_RETRIES}):`, error.message);
        
        // リトライ
        if (retryCount < MAX_RETRIES) {
            await new Promise(resolve => setTimeout(resolve, randomDelay()));
            return fetchGoogleRank(keyword, shopName, gbpUrl, retryCount + 1);
        }
        
        throw error;
    }
}

// ジョブを処理
async function processJob(jobData) {
    const { job_id, shop_id, date, keywords, shop_name, gbp_url } = jobData;
    
    console.log(`\n📊 ジョブ開始: job_id=${job_id}, shop_id=${shop_id}, date=${date}, keywords=${keywords.length}件`);
    
    try {
        // 各キーワードの順位を取得
        for (let i = 0; i < keywords.length; i++) {
            const keywordData = keywords[i];
            console.log(`  [${i + 1}/${keywords.length}] キーワード: "${keywordData.keyword}"`);
            
            try {
                const rank = await fetchGoogleRank(keywordData.keyword, shop_name, gbp_url);
                
                console.log(`    → 順位: ${rank || '圏外'}`);
                
                // Laravel APIに結果をPOST
                await axios.post('http://localhost:8000/api/rank-log', {
                    shop_id,
                    meo_keyword_id: keywordData.id,
                    rank: rank,
                    checked_at: date,
                }, {
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                
                // キーワード間にランダム待機（最後のキーワード以外）
                if (i < keywords.length - 1) {
                    const delay = randomDelay();
                    console.log(`    ⏳ ${delay}ms待機...`);
                    await new Promise(resolve => setTimeout(resolve, delay));
                }
            } catch (error) {
                console.error(`    ❌ エラー: ${error.message}`);
                // エラーが発生しても次のキーワードを続行
            }
        }
        
        // ジョブ完了通知
        await axios.post('http://localhost:8000/api/rank-fetch/finish', {
            job_id,
            status: 'success',
        }, {
            headers: {
                'Content-Type': 'application/json',
            },
        });
        
        console.log(`✅ ジョブ完了: job_id=${job_id}`);
    } catch (error) {
        console.error(`❌ ジョブ失敗: job_id=${job_id}, error=${error.message}`);
        
        // エラー通知
        await axios.post('http://localhost:8000/api/rank-fetch/finish', {
            job_id,
            status: 'failed',
            error_message: error.message,
        }, {
            headers: {
                'Content-Type': 'application/json',
            },
        }).catch(err => {
            console.error('エラー通知の送信に失敗:', err.message);
        });
    }
}

// ジョブキューを処理
async function processQueue() {
    while (true) {
        if (jobQueue.length > 0 && activeTabs < MAX_CONCURRENT_TABS) {
            const job = jobQueue.shift();
            // 非同期で処理（待機しない）
            processJob(job).catch(err => {
                console.error('ジョブ処理エラー:', err);
            });
        }
        await new Promise(resolve => setTimeout(resolve, 1000));
    }
}

// ジョブキュー処理を開始
processQueue();

// Laravelからジョブを受信
app.post('/api/fetch-ranks', async (req, res) => {
    const jobData = req.body;
    
    console.log('\n📥 ジョブ受信:', {
        job_id: jobData.job_id,
        shop_id: jobData.shop_id,
        date: jobData.date,
        keywords_count: jobData.keywords?.length || 0,
    });
    
    // キューに追加
    jobQueue.push(jobData);
    
    res.json({ success: true, message: 'ジョブをキューに追加しました' });
});

// ヘルスチェック
app.get('/health', (req, res) => {
    res.json({ 
        status: 'ok',
        activeTabs: activeTabs,
        queueLength: jobQueue.length,
        chromeConnected: browser !== null,
    });
});

const PORT = 3000;
app.listen(PORT, () => {
    console.log('Node.js server running on port 3000');
    console.log(`📊 ヘルスチェック: http://localhost:${PORT}/health`);
    console.log(`\n⚠️  注意: Chromeが9222ポートで起動している必要があります`);
    console.log(`   起動コマンド: chrome.exe --remote-debugging-port=9222\n`);
});

const { chromium } = require('playwright');
const axios = require('axios');

const app = express();
app.use(express.json());

// 同時実行タブ数の制限（最大5）
const MAX_CONCURRENT_TABS = 5;
let activeTabs = 0;
const jobQueue = [];

// 人間Chromeに接続（9222ポート）
let browser = null;
let context = null;

async function connectToChrome() {
    try {
        browser = await chromium.connectOverCDP('http://localhost:9222');
        const contexts = browser.contexts();
        if (contexts.length > 0) {
            context = contexts[0];
        } else {
            context = await browser.newContext();
        }
        console.log('✅ 人間Chromeに接続しました');
    } catch (error) {
        console.error('❌ Chrome接続エラー:', error.message);
        throw error;
    }
}

// 初期接続
connectToChrome().catch(err => {
    console.error('初期接続に失敗しました。Chromeが9222ポートで起動しているか確認してください。');
});

// ランダム待機（2〜5秒）
function randomDelay() {
    return Math.floor(Math.random() * 3000) + 2000; // 2000-5000ms
}

// Google検索で順位を取得
async function fetchGoogleRank(keyword, shopName, gbpUrl, retryCount = 0) {
    const MAX_RETRIES = 3;
    
    try {
        // 同時実行タブ数の制限チェック
        while (activeTabs >= MAX_CONCURRENT_TABS) {
            await new Promise(resolve => setTimeout(resolve, 1000));
        }
        
        activeTabs++;
        
        // 新しいタブを作成
        const page = await context.newPage();
        
        try {
            // Google検索
            const searchUrl = `https://www.google.com/search?q=${encodeURIComponent(keyword)}`;
            await page.goto(searchUrl, { waitUntil: 'networkidle', timeout: 30000 });
            await page.waitForTimeout(3000); // 読み込み待機
            
            // 検索結果から店舗名 or GBP URLでヒット順位を特定
            const results = await page.evaluate((targetName, targetUrl) => {
                const elements = Array.from(document.querySelectorAll('div.g'));
                return elements.map((el, index) => {
                    const link = el.querySelector('a');
                    const url = link ? link.href : '';
                    const title = el.querySelector('h3')?.textContent || '';
                    const snippet = el.querySelector('div[data-sncf]')?.textContent || '';
                    return { 
                        rank: index + 1, 
                        url, 
                        title,
                        snippet,
                        matches: title.includes(targetName) || 
                                snippet.includes(targetName) ||
                                (targetUrl && url.includes(targetUrl))
                    };
                }).filter(r => r.matches);
            }, shopName, gbpUrl);
            
            // タブを閉じる
            await page.close();
            activeTabs--;
            
            // 順位を返す（見つからなければnull=圏外）
            return results.length > 0 ? results[0].rank : null;
        } catch (error) {
            await page.close();
            activeTabs--;
            throw error;
        }
    } catch (error) {
        console.error(`順位取得エラー (リトライ ${retryCount}/${MAX_RETRIES}):`, error.message);
        
        // リトライ
        if (retryCount < MAX_RETRIES) {
            await new Promise(resolve => setTimeout(resolve, randomDelay()));
            return fetchGoogleRank(keyword, shopName, gbpUrl, retryCount + 1);
        }
        
        throw error;
    }
}

// ジョブを処理
async function processJob(jobData) {
    const { job_id, shop_id, date, keywords, shop_name, gbp_url } = jobData;
    
    console.log(`\n📊 ジョブ開始: job_id=${job_id}, shop_id=${shop_id}, date=${date}, keywords=${keywords.length}件`);
    
    try {
        // 各キーワードの順位を取得
        for (let i = 0; i < keywords.length; i++) {
            const keywordData = keywords[i];
            console.log(`  [${i + 1}/${keywords.length}] キーワード: "${keywordData.keyword}"`);
            
            try {
                const rank = await fetchGoogleRank(keywordData.keyword, shop_name, gbp_url);
                
                console.log(`    → 順位: ${rank || '圏外'}`);
                
                // Laravel APIに結果をPOST
                await axios.post('http://localhost:8000/api/rank-log', {
                    shop_id,
                    meo_keyword_id: keywordData.id,
                    rank: rank,
                    checked_at: date,
                }, {
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                
                // キーワード間にランダム待機（最後のキーワード以外）
                if (i < keywords.length - 1) {
                    const delay = randomDelay();
                    console.log(`    ⏳ ${delay}ms待機...`);
                    await new Promise(resolve => setTimeout(resolve, delay));
                }
            } catch (error) {
                console.error(`    ❌ エラー: ${error.message}`);
                // エラーが発生しても次のキーワードを続行
            }
        }
        
        // ジョブ完了通知
        await axios.post('http://localhost:8000/api/rank-fetch/finish', {
            job_id,
            status: 'success',
        }, {
            headers: {
                'Content-Type': 'application/json',
            },
        });
        
        console.log(`✅ ジョブ完了: job_id=${job_id}`);
    } catch (error) {
        console.error(`❌ ジョブ失敗: job_id=${job_id}, error=${error.message}`);
        
        // エラー通知
        await axios.post('http://localhost:8000/api/rank-fetch/finish', {
            job_id,
            status: 'failed',
            error_message: error.message,
        }, {
            headers: {
                'Content-Type': 'application/json',
            },
        }).catch(err => {
            console.error('エラー通知の送信に失敗:', err.message);
        });
    }
}

// ジョブキューを処理
async function processQueue() {
    while (true) {
        if (jobQueue.length > 0 && activeTabs < MAX_CONCURRENT_TABS) {
            const job = jobQueue.shift();
            // 非同期で処理（待機しない）
            processJob(job).catch(err => {
                console.error('ジョブ処理エラー:', err);
            });
        }
        await new Promise(resolve => setTimeout(resolve, 1000));
    }
}

// ジョブキュー処理を開始
processQueue();

// Laravelからジョブを受信
app.post('/api/fetch-ranks', async (req, res) => {
    const jobData = req.body;
    
    console.log('\n📥 ジョブ受信:', {
        job_id: jobData.job_id,
        shop_id: jobData.shop_id,
        date: jobData.date,
        keywords_count: jobData.keywords?.length || 0,
    });
    
    // キューに追加
    jobQueue.push(jobData);
    
    res.json({ success: true, message: 'ジョブをキューに追加しました' });
});

// ヘルスチェック
app.get('/health', (req, res) => {
    res.json({ 
        status: 'ok',
        activeTabs: activeTabs,
        queueLength: jobQueue.length,
        chromeConnected: browser !== null,
    });
});

const PORT = 3000;
app.listen(PORT, () => {
    console.log('Node.js server running on port 3000');
    console.log(`📊 ヘルスチェック: http://localhost:${PORT}/health`);
    console.log(`\n⚠️  注意: Chromeが9222ポートで起動している必要があります`);
    console.log(`   起動コマンド: chrome.exe --remote-debugging-port=9222\n`);
});














