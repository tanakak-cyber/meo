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
        Schema::table('gbp_snapshots', function (Blueprint $table) {
            // user_idカラムを追加（nullable、後で既存データを修復）
            $table->foreignId('user_id')->nullable()->after('shop_id')->constrained('users')->onDelete('cascade');
            
            // インデックスを追加（shop_id + user_id でスナップショットを区別）
            $table->index(['shop_id', 'user_id', 'synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. 外部キー制約を削除
        Schema::table('gbp_snapshots', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'gbp_snapshots'
                    AND COLUMN_NAME = 'user_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                foreach ($foreignKeys as $fk) {
                    DB::statement("ALTER TABLE gbp_snapshots DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                }
            } else {
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Exception $e) {
                    // 外部キーが存在しない場合は無視
                }
            }
        });
        
        // 2. インデックスを削除
        Schema::table('gbp_snapshots', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement('ALTER TABLE gbp_snapshots DROP INDEX gbp_snapshots_shop_id_user_id_synced_at_index');
                } catch (\Throwable $e) {
                    // index が無くても無視
                }
            } else {
                try {
                    $table->dropIndex(['shop_id', 'user_id', 'synced_at']);
                } catch (\Exception $e) {
                    // インデックスが存在しない場合は無視
                }
            }
        });
        
        // 3. カラムを削除
        Schema::table('gbp_snapshots', function (Blueprint $table) {
            if (Schema::hasColumn('gbp_snapshots', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });
    }
};

