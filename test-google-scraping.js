import { chromium } from 'playwright';

async function scrapeGoogleSearch(keyword) {
    console.log(`\n=== Google検索スクレイピングテスト: "${keyword}" ===\n`);
    
    const browser = await chromium.launch({
        headless: false, // ブラウザを表示
        slowMo: 500, // 動作を遅くして確認しやすくする
    });
    
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    });
    
    const page = await context.newPage();
    
    try {
        console.log('1. Google検索ページにアクセス中...');
        await page.goto('https://www.google.com', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        
        console.log('2. 検索キーワードを入力中...');
        const searchBox = await page.locator('textarea[name="q"]').first();
        await searchBox.fill(keyword);
        await page.waitForTimeout(1000);
        
        console.log('3. 検索実行中...');
        await page.keyboard.press('Enter');
        await page.waitForSelector('#search', { timeout: 10000 });
        await page.waitForTimeout(3000);
        
        console.log('4. 検索結果を取得中...');
        const results = [];
        
        // 検索結果のリンクを取得
        const resultElements = await page.locator('#search a h3').all();
        
        for (let i = 0; i < Math.min(10, resultElements.length); i++) {
            try {
                const titleElement = resultElements[i];
                const title = await titleElement.textContent();
                
                // 親要素からリンクを取得
                const linkElement = await titleElement.locator('..').locator('..');
                const href = await linkElement.getAttribute('href');
                
                if (title && href) {
                    results.push({
                        rank: i + 1,
                        title: title.trim(),
                        url: href.startsWith('/url?q=') 
                            ? decodeURIComponent(href.split('/url?q=')[1].split('&')[0])
                            : href,
                    });
                }
            } catch (e) {
                console.log(`  順位 ${i + 1}: 取得失敗 - ${e.message}`);
            }
        }
        
        console.log('\n=== 検索結果（1位〜10位）===');
        results.forEach(result => {
            console.log(`${result.rank}位: ${result.title}`);
            console.log(`  URL: ${result.url}\n`);
        });
        
        console.log(`取得件数: ${results.length}件`);
        
        // ブラウザを5秒間開いたままにする（確認用）
        console.log('\n5秒後にブラウザを閉じます...');
        await page.waitForTimeout(5000);
        
        return results;
        
    } catch (error) {
        console.error('エラーが発生しました:', error.message);
        console.log('\n10秒後にブラウザを閉じます...');
        await page.waitForTimeout(10000);
        throw error;
    } finally {
        await browser.close();
    }
}

// メイン実行
(async () => {
    const keyword = '渋谷 外壁塗装';
    await scrapeGoogleSearch(keyword);
})();


async function scrapeGoogleSearch(keyword) {
    console.log(`\n=== Google検索スクレイピングテスト: "${keyword}" ===\n`);
    
    const browser = await chromium.launch({
        headless: false, // ブラウザを表示
        slowMo: 500, // 動作を遅くして確認しやすくする
    });
    
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    });
    
    const page = await context.newPage();
    
    try {
        console.log('1. Google検索ページにアクセス中...');
        await page.goto('https://www.google.com', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        
        console.log('2. 検索キーワードを入力中...');
        const searchBox = await page.locator('textarea[name="q"]').first();
        await searchBox.fill(keyword);
        await page.waitForTimeout(1000);
        
        console.log('3. 検索実行中...');
        await page.keyboard.press('Enter');
        await page.waitForSelector('#search', { timeout: 10000 });
        await page.waitForTimeout(3000);
        
        console.log('4. 検索結果を取得中...');
        const results = [];
        
        // 検索結果のリンクを取得
        const resultElements = await page.locator('#search a h3').all();
        
        for (let i = 0; i < Math.min(10, resultElements.length); i++) {
            try {
                const titleElement = resultElements[i];
                const title = await titleElement.textContent();
                
                // 親要素からリンクを取得
                const linkElement = await titleElement.locator('..').locator('..');
                const href = await linkElement.getAttribute('href');
                
                if (title && href) {
                    results.push({
                        rank: i + 1,
                        title: title.trim(),
                        url: href.startsWith('/url?q=') 
                            ? decodeURIComponent(href.split('/url?q=')[1].split('&')[0])
                            : href,
                    });
                }
            } catch (e) {
                console.log(`  順位 ${i + 1}: 取得失敗 - ${e.message}`);
            }
        }
        
        console.log('\n=== 検索結果（1位〜10位）===');
        results.forEach(result => {
            console.log(`${result.rank}位: ${result.title}`);
            console.log(`  URL: ${result.url}\n`);
        });
        
        console.log(`取得件数: ${results.length}件`);
        
        // ブラウザを5秒間開いたままにする（確認用）
        console.log('\n5秒後にブラウザを閉じます...');
        await page.waitForTimeout(5000);
        
        return results;
        
    } catch (error) {
        console.error('エラーが発生しました:', error.message);
        console.log('\n10秒後にブラウザを閉じます...');
        await page.waitForTimeout(10000);
        throw error;
    } finally {
        await browser.close();
    }
}

// メイン実行
(async () => {
    const keyword = '渋谷 外壁塗装';
    await scrapeGoogleSearch(keyword);
})();


async function scrapeGoogleSearch(keyword) {
    console.log(`\n=== Google検索スクレイピングテスト: "${keyword}" ===\n`);
    
    const browser = await chromium.launch({
        headless: false, // ブラウザを表示
        slowMo: 500, // 動作を遅くして確認しやすくする
    });
    
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    });
    
    const page = await context.newPage();
    
    try {
        console.log('1. Google検索ページにアクセス中...');
        await page.goto('https://www.google.com', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        
        console.log('2. 検索キーワードを入力中...');
        const searchBox = await page.locator('textarea[name="q"]').first();
        await searchBox.fill(keyword);
        await page.waitForTimeout(1000);
        
        console.log('3. 検索実行中...');
        await page.keyboard.press('Enter');
        await page.waitForSelector('#search', { timeout: 10000 });
        await page.waitForTimeout(3000);
        
        console.log('4. 検索結果を取得中...');
        const results = [];
        
        // 検索結果のリンクを取得
        const resultElements = await page.locator('#search a h3').all();
        
        for (let i = 0; i < Math.min(10, resultElements.length); i++) {
            try {
                const titleElement = resultElements[i];
                const title = await titleElement.textContent();
                
                // 親要素からリンクを取得
                const linkElement = await titleElement.locator('..').locator('..');
                const href = await linkElement.getAttribute('href');
                
                if (title && href) {
                    results.push({
                        rank: i + 1,
                        title: title.trim(),
                        url: href.startsWith('/url?q=') 
                            ? decodeURIComponent(href.split('/url?q=')[1].split('&')[0])
                            : href,
                    });
                }
            } catch (e) {
                console.log(`  順位 ${i + 1}: 取得失敗 - ${e.message}`);
            }
        }
        
        console.log('\n=== 検索結果（1位〜10位）===');
        results.forEach(result => {
            console.log(`${result.rank}位: ${result.title}`);
            console.log(`  URL: ${result.url}\n`);
        });
        
        console.log(`取得件数: ${results.length}件`);
        
        // ブラウザを5秒間開いたままにする（確認用）
        console.log('\n5秒後にブラウザを閉じます...');
        await page.waitForTimeout(5000);
        
        return results;
        
    } catch (error) {
        console.error('エラーが発生しました:', error.message);
        console.log('\n10秒後にブラウザを閉じます...');
        await page.waitForTimeout(10000);
        throw error;
    } finally {
        await browser.close();
    }
}

// メイン実行
(async () => {
    const keyword = '渋谷 外壁塗装';
    await scrapeGoogleSearch(keyword);
})();


async function scrapeGoogleSearch(keyword) {
    console.log(`\n=== Google検索スクレイピングテスト: "${keyword}" ===\n`);
    
    const browser = await chromium.launch({
        headless: false, // ブラウザを表示
        slowMo: 500, // 動作を遅くして確認しやすくする
    });
    
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    });
    
    const page = await context.newPage();
    
    try {
        console.log('1. Google検索ページにアクセス中...');
        await page.goto('https://www.google.com', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);
        
        console.log('2. 検索キーワードを入力中...');
        const searchBox = await page.locator('textarea[name="q"]').first();
        await searchBox.fill(keyword);
        await page.waitForTimeout(1000);
        
        console.log('3. 検索実行中...');
        await page.keyboard.press('Enter');
        await page.waitForSelector('#search', { timeout: 10000 });
        await page.waitForTimeout(3000);
        
        console.log('4. 検索結果を取得中...');
        const results = [];
        
        // 検索結果のリンクを取得
        const resultElements = await page.locator('#search a h3').all();
        
        for (let i = 0; i < Math.min(10, resultElements.length); i++) {
            try {
                const titleElement = resultElements[i];
                const title = await titleElement.textContent();
                
                // 親要素からリンクを取得
                const linkElement = await titleElement.locator('..').locator('..');
                const href = await linkElement.getAttribute('href');
                
                if (title && href) {
                    results.push({
                        rank: i + 1,
                        title: title.trim(),
                        url: href.startsWith('/url?q=') 
                            ? decodeURIComponent(href.split('/url?q=')[1].split('&')[0])
                            : href,
                    });
                }
            } catch (e) {
                console.log(`  順位 ${i + 1}: 取得失敗 - ${e.message}`);
            }
        }
        
        console.log('\n=== 検索結果（1位〜10位）===');
        results.forEach(result => {
            console.log(`${result.rank}位: ${result.title}`);
            console.log(`  URL: ${result.url}\n`);
        });
        
        console.log(`取得件数: ${results.length}件`);
        
        // ブラウザを5秒間開いたままにする（確認用）
        console.log('\n5秒後にブラウザを閉じます...');
        await page.waitForTimeout(5000);
        
        return results;
        
    } catch (error) {
        console.error('エラーが発生しました:', error.message);
        console.log('\n10秒後にブラウザを閉じます...');
        await page.waitForTimeout(10000);
        throw error;
    } finally {
        await browser.close();
    }
}

// メイン実行
(async () => {
    const keyword = '渋谷 外壁塗装';
    await scrapeGoogleSearch(keyword);
})();

