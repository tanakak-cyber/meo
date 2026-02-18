<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // integration_type カラムを追加
            if (!Schema::hasColumn('shops', 'integration_type')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('integration_type')->nullable();
                } else {
                    $table->string('integration_type')->nullable()->after('blog_fallback_image_url');
                }
            }
        });

        // データ移行処理
        $this->migrateData();

        // 古いカラムを削除（SQLite の場合はスキップ）
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('shops', function (Blueprint $table) {
                if (Schema::hasColumn('shops', 'blog_auto_post_enabled')) {
                    $table->dropColumn('blog_auto_post_enabled');
                }
                if (Schema::hasColumn('shops', 'instagram_auto_post_enabled')) {
                    $table->dropColumn('instagram_auto_post_enabled');
                }
            });
        } else {
            // SQLite の場合はカラムを削除しない（データ保持のため）
            // アプリケーション側で integration_type を使用するため、古いカラムは無視される
            Log::info('INTEGRATION_TYPE_MIGRATION_SKIP_DROP_COLUMN', [
                'driver' => 'sqlite',
                'message' => 'SQLite のため dropColumn をスキップしました。古いカラムは残りますが、アプリケーション側で無視されます。',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // 古いカラムを復元
            if (!Schema::hasColumn('shops', 'blog_auto_post_enabled')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->boolean('blog_auto_post_enabled')->default(false);
                } else {
                    $table->boolean('blog_auto_post_enabled')->default(false)->after('blog_fallback_image_url');
                }
            }
            if (!Schema::hasColumn('shops', 'instagram_auto_post_enabled')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->boolean('instagram_auto_post_enabled')->default(false);
                } else {
                    $table->boolean('instagram_auto_post_enabled')->default(false)->after('blog_fallback_image_url');
                }
            }
        });

        // データを復元
        DB::table('shops')->where('integration_type', 'blog')->update([
            'blog_auto_post_enabled' => true,
            'instagram_auto_post_enabled' => false,
        ]);

        DB::table('shops')->where('integration_type', 'instagram')->update([
            'blog_auto_post_enabled' => false,
            'instagram_auto_post_enabled' => true,
        ]);

        DB::table('shops')->whereNull('integration_type')->update([
            'blog_auto_post_enabled' => false,
            'instagram_auto_post_enabled' => false,
        ]);

        // integration_type を削除（SQLite の場合はスキップ）
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('shops', function (Blueprint $table) {
                if (Schema::hasColumn('shops', 'integration_type')) {
                    $table->dropColumn('integration_type');
                }
            });
        } else {
            // SQLite の場合はカラムを削除しない（データ保持のため）
            // アプリケーション側で古いカラムを使用するため、integration_type は残りますが無視されます
            Log::info('INTEGRATION_TYPE_MIGRATION_ROLLBACK_SKIP_DROP_COLUMN', [
                'driver' => 'sqlite',
                'message' => 'SQLite のため dropColumn(integration_type) をスキップしました。カラムは残りますが、アプリケーション側で古いカラムを使用します。',
            ]);
        }
    }

    /**
     * データ移行処理
     * 
     * 移行ルール:
     * - instagram_auto_post_enabled = true → integration_type = 'instagram' (優先)
     * - blog_auto_post_enabled = true かつ instagram_auto_post_enabled != true → integration_type = 'blog'
     * - 両方 false または null → integration_type = null
     * - 両方 true の場合は Instagram を優先し、警告ログを出力
     */
    private function migrateData(): void
    {
        $totalShops = 0;
        $bothTrueCount = 0;

        // chunk() を使用してメモリ効率を向上
        DB::table('shops')->orderBy('id')->chunk(100, function ($shops) use (&$totalShops, &$bothTrueCount) {
            foreach ($shops as $shop) {
                $totalShops++;

                // カラムの値を安全に取得（null の場合は false として扱う）
                $blogEnabled = !empty($shop->blog_auto_post_enabled);
                $instagramEnabled = !empty($shop->instagram_auto_post_enabled);

                $integrationType = null;

                // データ移行ロジック
                if ($instagramEnabled) {
                    // Instagram が有効な場合（優先）
                    $integrationType = 'instagram';
                    
                    // 両方 true の場合は警告ログを出力
                    if ($blogEnabled) {
                        $bothTrueCount++;
                        Log::warning('INTEGRATION_BOTH_TRUE_DETECTED', [
                            'shop_id' => $shop->id,
                            'shop_name' => $shop->name ?? 'Unknown',
                            'blog_auto_post_enabled' => $blogEnabled,
                            'instagram_auto_post_enabled' => $instagramEnabled,
                            'action' => 'Instagram優先で設定',
                            'migration' => 'unify_integration_type_in_shops_table',
                        ]);
                    }
                } elseif ($blogEnabled) {
                    // Blog のみ有効な場合
                    $integrationType = 'blog';
                }
                // どちらも false または null の場合は null のまま

                // integration_type を更新
                DB::table('shops')
                    ->where('id', $shop->id)
                    ->update(['integration_type' => $integrationType]);
            }
        });

        // 移行完了ログ
        Log::info('INTEGRATION_TYPE_MIGRATION_COMPLETED', [
            'total_shops' => $totalShops,
            'both_true_count' => $bothTrueCount,
            'migration' => 'unify_integration_type_in_shops_table',
        ]);
    }
};

