<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('GBP Insights CSVインポート') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-4">CSVファイルをアップロード</h3>
                        <div class="mb-4">
                            <a href="https://business.google.com/locations" target="_blank" rel="noopener noreferrer" 
                                class="inline-flex items-center px-4 py-2 bg-[#00afcc] hover:bg-[#0088a3] text-white font-semibold rounded-md transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                                CSVDLへ
                            </a>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            ファイル名に日付範囲を含めてください（例: GMB insights...2026-1-1 - 2026-1-31...）
                        </p>
                        <p class="text-sm text-gray-600 mb-4">
                            CSVの形式: 1行目（ヘッダー）と2行目（説明文）をスキップし、3行目からデータを読み込みます。
                        </p>
                        <p class="text-sm text-gray-600 mb-4">
                            データのマッピング:
                        </p>
                        <ul class="text-sm text-gray-600 mb-4 list-disc list-inside">
                            <li>2列目: ビジネス名（shops.gbp_name と完全一致で照合）</li>
                            <li>5-8列目: 表示回数（合計）</li>
                            <li>9列目: 通話</li>
                            <li>12列目: ルート</li>
                            <li>13列目: ウェブサイトクリック</li>
                        </ul>
                    </div>

                    <form action="{{ route('gbp-insights.import.store') }}" method="POST" enctype="multipart/form-data" id="importForm">
                        @csrf

                        <div class="mb-4">
                            <label for="csv_files" class="block text-sm font-medium text-gray-700 mb-2">
                                CSVファイル（複数選択可・ドラッグアンドドロップ対応）
                            </label>
                            
                            <!-- ドラッグアンドドロップエリア -->
                            <div id="dropZone" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-indigo-500 transition-colors">
                                <div id="dropZoneContent">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-600">
                                        <span class="font-semibold text-indigo-600">クリックしてファイルを選択</span> または ドラッグアンドドロップ
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">複数ファイルを一度にアップロードできます</p>
                                </div>
                                <div id="fileList" class="mt-4 hidden">
                                    <p class="text-sm font-medium text-gray-700 mb-2">選択されたファイル:</p>
                                    <ul id="fileListItems" class="text-sm text-gray-600 space-y-1"></ul>
                                </div>
                            </div>
                            
                            <input type="file" name="csv_files[]" id="csv_files" accept=".csv,.txt" multiple required
                                class="hidden @error('csv_files') border-red-500 @enderror">
                            @error('csv_files')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end">
                            <button type="submit" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                                インポート実行
                            </button>
                        </div>
                    </form>

                    @if(session('success_count') !== null || session('skip_count') !== null)
                        <div class="mt-6 p-4 bg-gray-50 rounded">
                            <h4 class="font-semibold mb-2">インポート結果</h4>
                            <p class="text-sm">
                                <span class="text-green-600 font-semibold">{{ session('success_count', 0) }}件成功</span>
                                @if(session('skip_count', 0) > 0)
                                    / <span class="text-orange-600 font-semibold">{{ session('skip_count', 0) }}件スキップ（店舗名不一致）</span>
                                @endif
                            </p>

                            @if(session('import_errors') && count(session('import_errors')) > 0)
                                <div class="mt-4">
                                    <h5 class="font-semibold text-sm mb-2">スキップ詳細（最初の10件）:</h5>
                                    <ul class="text-sm text-gray-600 list-disc list-inside">
                                        @foreach(array_slice(session('import_errors'), 0, 10) as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        // ドラッグアンドドロップ機能
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('csv_files');
        const fileList = document.getElementById('fileList');
        const fileListItems = document.getElementById('fileListItems');
        const dropZoneContent = document.getElementById('dropZoneContent');

        // クリックでファイル選択
        dropZone.addEventListener('click', () => {
            fileInput.click();
        });

        // ファイル選択時の処理
        fileInput.addEventListener('change', (e) => {
            updateFileList(e.target.files);
        });

        // ドラッグオーバー
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-indigo-500', 'bg-indigo-50');
        });

        // ドラッグリーブ
        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
        });

        // ドロップ
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                // FileListをDataTransferに変換してinputに設定
                const dataTransfer = new DataTransfer();
                Array.from(files).forEach(file => {
                    if (file.name.endsWith('.csv') || file.name.endsWith('.txt')) {
                        dataTransfer.items.add(file);
                    }
                });
                fileInput.files = dataTransfer.files;
                updateFileList(fileInput.files);
            }
        });

        // ファイルリストを更新
        function updateFileList(files) {
            if (files.length === 0) {
                fileList.classList.add('hidden');
                dropZoneContent.classList.remove('hidden');
                return;
            }

            fileList.classList.remove('hidden');
            dropZoneContent.classList.add('hidden');
            
            fileListItems.innerHTML = '';
            Array.from(files).forEach((file, index) => {
                const li = document.createElement('li');
                li.className = 'flex items-center justify-between p-2 bg-gray-50 rounded';
                li.innerHTML = `
                    <span class="flex-1">${file.name}</span>
                    <span class="text-xs text-gray-500 ml-2">${(file.size / 1024).toFixed(2)} KB</span>
                    <button type="button" class="ml-2 text-red-500 hover:text-red-700" onclick="removeFile(${index})">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                `;
                fileListItems.appendChild(li);
            });
        }

        // ファイルを削除
        function removeFile(index) {
            const dataTransfer = new DataTransfer();
            const files = Array.from(fileInput.files);
            files.splice(index, 1);
            files.forEach(file => dataTransfer.items.add(file));
            fileInput.files = dataTransfer.files;
            updateFileList(fileInput.files);
        }
    </script>
</x-app-layout>

