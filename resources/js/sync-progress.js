/**
 * 同期バッチの進捗をポーリングで監視
 */
class SyncProgressTracker {
    constructor(batchId) {
        this.batchId = batchId;
        this.pollInterval = null;
        this.pollIntervalMs = 3000; // 3秒
    }

    /**
     * ポーリングを開始
     */
    start() {
        // 進捗表示エリアを作成
        this.createProgressArea();

        // 即座に1回実行
        this.checkProgress();

        // 3秒ごとにポーリング
        this.pollInterval = setInterval(() => {
            this.checkProgress();
        }, this.pollIntervalMs);
    }

    /**
     * 進捗表示エリアを作成
     */
    createProgressArea() {
        // 既存の進捗エリアがあれば削除
        const existing = document.getElementById('sync-progress-area');
        if (existing) {
            existing.remove();
        }

        // 進捗エリアを作成
        const progressArea = document.createElement('div');
        progressArea.id = 'sync-progress-area';
        progressArea.className = 'mb-6 bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden';
        progressArea.innerHTML = `
            <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-6 h-6 text-white animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-white">同期処理中...</h3>
                </div>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">進捗</span>
                        <span id="sync-progress-percentage" class="text-sm font-bold text-gray-900">0%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div id="sync-progress-bar" class="bg-gradient-to-r from-blue-500 to-indigo-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">完了店舗:</span>
                        <span id="sync-completed-shops" class="ml-2 font-semibold text-gray-900">0</span>
                        <span class="text-gray-500">/</span>
                        <span id="sync-total-shops" class="font-semibold text-gray-900">0</span>
                    </div>
                    <div>
                        <span class="text-gray-600">新規追加:</span>
                        <span id="sync-total-inserted" class="ml-2 font-semibold text-green-600">0</span>
                    </div>
                    <div>
                        <span class="text-gray-600">更新:</span>
                        <span id="sync-total-updated" class="ml-2 font-semibold text-blue-600">0</span>
                    </div>
                    <div>
                        <span class="text-gray-600">ステータス:</span>
                        <span id="sync-status" class="ml-2 font-semibold text-gray-900">running</span>
                    </div>
                </div>
            </div>
        `;

        // フォームの前に挿入
        const form = document.querySelector('form[action*="sync"]');
        if (form && form.parentElement) {
            form.parentElement.insertBefore(progressArea, form);
        } else {
            // フォームが見つからない場合は、bodyの先頭に追加
            document.body.insertBefore(progressArea, document.body.firstChild);
        }
    }

    /**
     * 進捗を確認
     */
    async checkProgress() {
        try {
            const response = await fetch(`/api/sync-batches/${this.batchId}`);
            const data = await response.json();

            // 進捗を更新
            this.updateProgress(data);

            // 完了した場合はポーリングを停止
            if (data.status === 'finished') {
                this.stop();
                this.showCompletionMessage(data);
            } else if (data.status === 'failed') {
                this.stop();
                this.showErrorMessage(data);
            }
        } catch (error) {
            console.error('進捗確認エラー:', error);
            // エラーが発生してもポーリングは継続
        }
    }

    /**
     * 進捗表示を更新
     */
    updateProgress(data) {
        const progressBar = document.getElementById('sync-progress-bar');
        const progressPercentage = document.getElementById('sync-progress-percentage');
        const completedShops = document.getElementById('sync-completed-shops');
        const totalShops = document.getElementById('sync-total-shops');
        const totalInserted = document.getElementById('sync-total-inserted');
        const totalUpdated = document.getElementById('sync-total-updated');
        const status = document.getElementById('sync-status');

        if (progressBar) {
            const percentage = data.progress_percentage || 0;
            progressBar.style.width = `${percentage}%`;
        }

        if (progressPercentage) {
            progressPercentage.textContent = `${Math.round(data.progress_percentage || 0)}%`;
        }

        if (completedShops) {
            completedShops.textContent = data.completed_shops || 0;
        }

        if (totalShops) {
            totalShops.textContent = data.total_shops || 0;
        }

        if (totalInserted) {
            totalInserted.textContent = data.total_inserted || 0;
        }

        if (totalUpdated) {
            totalUpdated.textContent = data.total_updated || 0;
        }

        if (status) {
            status.textContent = data.status || 'running';
        }
    }

    /**
     * 完了メッセージを表示
     */
    showCompletionMessage(data) {
        const progressArea = document.getElementById('sync-progress-area');
        if (progressArea) {
            const header = progressArea.querySelector('.bg-gradient-to-r');
            if (header) {
                header.className = 'bg-gradient-to-r from-green-500 to-emerald-600 px-6 py-4';
                const title = header.querySelector('h3');
                if (title) {
                    title.textContent = '同期処理が完了しました';
                }
            }

            // 完了時刻を表示
            const content = progressArea.querySelector('.p-6');
            if (content) {
                const finishedAt = data.finished_at ? new Date(data.finished_at).toLocaleString('ja-JP') : '';
                content.innerHTML += `
                    <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-sm text-green-800">
                            <strong>完了時刻:</strong> ${finishedAt}
                        </p>
                        <p class="text-sm text-green-800 mt-2">
                            <strong>結果:</strong> 新規追加 ${data.total_inserted || 0}件、更新 ${data.total_updated || 0}件
                        </p>
                    </div>
                `;
            }
        }
    }

    /**
     * エラーメッセージを表示
     */
    showErrorMessage(data) {
        const progressArea = document.getElementById('sync-progress-area');
        if (progressArea) {
            const header = progressArea.querySelector('.bg-gradient-to-r');
            if (header) {
                header.className = 'bg-gradient-to-r from-red-500 to-rose-600 px-6 py-4';
                const title = header.querySelector('h3');
                if (title) {
                    title.textContent = '同期処理が失敗しました';
                }
            }
        }
    }

    /**
     * ポーリングを停止
     */
    stop() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    }
}

// ページ読み込み時に、sync_batch_idがセッションに保存されているか確認
document.addEventListener('DOMContentLoaded', function() {
    // セッションからsync_batch_idを取得（Laravelのセッションから取得する方法）
    // 実際には、サーバーサイドでビューに渡す必要があります
    // ここでは、data属性から取得する方法を使用
    const syncBatchIdElement = document.querySelector('[data-sync-batch-id]');
    if (syncBatchIdElement) {
        const batchId = syncBatchIdElement.getAttribute('data-sync-batch-id');
        if (batchId) {
            const tracker = new SyncProgressTracker(batchId);
            tracker.start();
        }
    }
});







