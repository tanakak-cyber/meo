===========================================
Laravel Scheduler タスクスケジューラ登録手順
===========================================

【重要】管理者権限が必要です

方法1: コマンドプロンプト（管理者）で実行
----------------------------------------
1. Windowsキーを押して「cmd」と入力
2. 「コマンドプロンプト」を右クリック → 「管理者として実行」
3. 以下のコマンドを実行：

   cd C:\laragon\www\meo
   setup-scheduler.bat

方法2: 手動でコマンドを実行
----------------------------------------
管理者権限のコマンドプロンプトで以下を実行：

schtasks /Delete /TN "LaravelScheduler-MEO" /F

schtasks /Create /TN "LaravelScheduler-MEO" /TR "C:\laragon\www\meo\run-scheduler.bat" /SC MINUTE /MO 1 /ST 00:00 /RL HIGHEST /F

schtasks /Query /TN "LaravelScheduler-MEO" /V /FO LIST

schtasks /Run /TN "LaravelScheduler-MEO"

方法3: タスクスケジューラのGUIから設定
----------------------------------------
1. Windowsキー + R を押して「taskschd.msc」と入力してEnter
2. 右側の「タスクの作成」をクリック
3. 「全般」タブ：
   - 名前: LaravelScheduler-MEO
   - 「最上位の特権で実行する」にチェック
4. 「トリガー」タブ：
   - 「新規」をクリック
   - タスクの開始: スケジュールに従う
   - 設定: 繰り返し間隔
   - 間隔: 1分
   - 「有効」にチェック
5. 「操作」タブ：
   - 「新規」をクリック
   - 操作: プログラムの開始
   - プログラム/スクリプト: C:\laragon\www\meo\run-scheduler.bat
   - 開始: C:\laragon\www\meo
6. 「OK」をクリックして保存

確認方法
----------------------------------------
schtasks /Query /TN "LaravelScheduler-MEO" /V /FO LIST

テスト実行
----------------------------------------
schtasks /Run /TN "LaravelScheduler-MEO"














