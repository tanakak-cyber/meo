<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * GBPデータ（reviews, photos, gbp_posts）から operator_id を完全に削除
     * これらのテーブルは Single Source of Truth として共有されるため、
     * operator_id で分断してはいけない
     */
    public function up(): void
    {
        // reviews テーブルから operator_id を削除（存在する場合）
        if (Schema::hasColumn('reviews', 'operator_id')) {
            // 1. 外部キー制約を削除
            Schema::table('reviews', function (Blueprint $table) {
                if (DB::getDriverName() === 'mysql') {
                    $foreignKeys = DB::select("
                        SELECT CONSTRAINT_NAME
                        FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'reviews'
                        AND COLUMN_NAME = 'operator_id'
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                    ");
                    foreach ($foreignKeys as $fk) {
                        DB::statement("ALTER TABLE reviews DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                    }
                } else {
                    try {
                        $table->dropForeign(['operator_id']);
                    } catch (\Exception $e) {
                        // 外部キーが存在しない場合は無視
                    }
                }
            });
            
            // 2. インデックスを削除
            Schema::table('reviews', function (Blueprint $table) {
                if (DB::getDriverName() === 'mysql') {
                    try {
                        DB::statement('ALTER TABLE reviews DROP INDEX reviews_operator_id_index');
                    } catch (\Throwable $e) {
                        // index が無くても無視
                    }
                } else {
                    try {
                        $table->dropIndex(['operator_id']);
                    } catch (\Exception $e) {
                        // インデックスが存在しない場合は無視
                    }
                }
            });
            
            // 3. カラムを削除
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropColumn('operator_id');
            });
        }
        
        // photos テーブルから operator_id を削除（存在する場合）
        if (Schema::hasColumn('photos', 'operator_id')) {
            // 1. 外部キー制約を削除
            Schema::table('photos', function (Blueprint $table) {
                if (DB::getDriverName() === 'mysql') {
                    $foreignKeys = DB::select("
                        SELECT CONSTRAINT_NAME
                        FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'photos'
                        AND COLUMN_NAME = 'operator_id'
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                    ");
                    foreach ($foreignKeys as $fk) {
                        DB::statement("ALTER TABLE photos DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                    }
                } else {
                    try {
                        $table->dropForeign(['operator_id']);
                    } catch (\Exception $e) {
                        // 外部キーが存在しない場合は無視
                    }
                }
            });
            
            // 2. インデックスを削除
            Schema::table('photos', function (Blueprint $table) {
                if (DB::getDriverName() === 'mysql') {
                    try {
                        DB::statement('ALTER TABLE photos DROP INDEX photos_operator_id_index');
                    } catch (\Throwable $e) {
                        // index が無くても無視
                    }
                } else {
                    try {
                        $table->dropIndex(['operator_id']);
                    } catch (\Exception $e) {
                        // インデックスが存在しない場合は無視
                    }
                }
            });
            
            // 3. カラムを削除
            Schema::table('photos', function (Blueprint $table) {
                $table->dropColumn('operator_id');
            });
        }
        
        // gbp_posts テーブルから operator_id を削除（存在する場合）
        // 注: 既に remove_operator_id_from_gbp_posts_table マイグレーションで削除済みの可能性があるが、
        // 念のため再度確認して削除
        if (Schema::hasColumn('gbp_posts', 'operator_id')) {
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
            
            // 2. インデックスを削除
            Schema::table('gbp_posts', function (Blueprint $table) {
                if (DB::getDriverName() === 'mysql') {
                    try {
                        DB::statement('ALTER TABLE gbp_posts DROP INDEX gbp_posts_operator_id_index');
                    } catch (\Throwable $e) {
                        // index が無くても無視
                    }
                } else {
                    try {
                        $table->dropIndex(['operator_id']);
                    } catch (\Exception $e) {
                        // インデックスが存在しない場合は無視
                    }
                }
            });
            
            // 3. カラムを削除
            Schema::table('gbp_posts', function (Blueprint $table) {
                $table->dropColumn('operator_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバック時は operator_id を追加しない
        // この設計は間違っているため、ロールバックは行わない
    }
};




