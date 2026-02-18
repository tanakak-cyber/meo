<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. 外部キー制約を削除
        Schema::table('gbp_posts', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'gbp_posts'
                    AND COLUMN_NAME = 'operator_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                foreach ($foreignKeys as $fk) {
                    DB::statement("ALTER TABLE gbp_posts DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                }
            } else {
                try {
                    $table->dropForeign(['operator_id']);
                } catch (\Exception $e) {
                    // 外部キーが存在しない場合は無視
                }
            }
        });

        Schema::table('gbp_posts', function (Blueprint $table) {
            // 2. インデックスを削除
            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement('ALTER TABLE gbp_posts DROP INDEX gbp_posts_unique');
                } catch (\Throwable $e) {
                    // index が無くても無視
                }
                try {
                    DB::statement('ALTER TABLE gbp_posts DROP INDEX gbp_posts_operator_id_index');
                } catch (\Throwable $e) {
                    // index が無くても無視
                }
                try {
                    DB::statement('ALTER TABLE gbp_posts DROP INDEX gbp_posts_shop_id_operator_id_create_time_index');
                } catch (\Throwable $e) {
                    // index が無くても無視
                }
            } else {
                try {
                    $table->dropUnique('gbp_posts_unique');
                    $table->dropIndex(['operator_id']);
                    $table->dropIndex(['shop_id', 'operator_id', 'create_time']);
                } catch (\Exception $e) {
                    // インデックスが存在しない場合は無視
                }
            }
        });

        Schema::table('gbp_posts', function (Blueprint $table) {
            // 3. operator_idカラムを削除
            if (Schema::hasColumn('gbp_posts', 'operator_id')) {
                $table->dropColumn('operator_id');
            }
            
            // 新しいユニークキーを追加（shop_id, gbp_post_idのみ）
            $table->unique(['shop_id', 'gbp_post_id'], 'gbp_posts_unique');
            // インデックスを再作成
            $table->index(['shop_id', 'create_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gbp_posts', function (Blueprint $table) {
            // ユニークキーを削除
            $table->dropUnique(['shop_id', 'gbp_post_id']);
            $table->dropIndex(['shop_id', 'create_time']);
            
            // operator_idカラムを追加
            $table->foreignId('operator_id')->nullable()->after('shop_id')->constrained('operation_persons')->onDelete('cascade');
            $table->index('operator_id');
            $table->index(['shop_id', 'operator_id', 'create_time']);
            
            // 元のユニークキーを復元
            $table->unique(['shop_id', 'operator_id', 'gbp_post_id'], 'gbp_posts_unique');
        });
    }
};




