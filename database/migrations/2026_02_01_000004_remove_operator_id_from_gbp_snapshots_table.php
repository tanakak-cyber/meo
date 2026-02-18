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
            // 1. 外部キー制約を削除（MySQLでは制約名を取得する必要がある場合がある）
            if (DB::getDriverName() === 'mysql') {
                // MySQLでは外部キー制約名を動的に取得して削除
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'gbp_snapshots'
                    AND COLUMN_NAME = 'operator_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                foreach ($foreignKeys as $fk) {
                    DB::statement("ALTER TABLE gbp_snapshots DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                }
            } else {
                $table->dropForeign(['operator_id']);
            }
        });

        Schema::table('gbp_snapshots', function (Blueprint $table) {
            // 2. インデックスを削除
            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement('ALTER TABLE gbp_snapshots DROP INDEX gbp_snapshots_shop_id_operator_id_synced_at_index');
                } catch (\Throwable $e) {
                    // index が無くても無視
                }
            } else {
                try {
                    $table->dropIndex(['shop_id', 'operator_id', 'synced_at']);
                } catch (\Exception $e) {
                    // インデックスが存在しない場合は無視
                }
            }
        });

        Schema::table('gbp_snapshots', function (Blueprint $table) {
            // 3. operator_idカラムを削除
            if (Schema::hasColumn('gbp_snapshots', 'operator_id')) {
                $table->dropColumn('operator_id');
            }
            
            // 同期実行者の記録用カラムを追加（ログ用、nullable）
            $table->foreignId('synced_by_operator_id')->nullable()->after('shop_id')->constrained('operation_persons')->onDelete('set null');
            
            // インデックスを再作成
            $table->index(['shop_id', 'synced_at']);
            $table->index('synced_by_operator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gbp_snapshots', function (Blueprint $table) {
            // synced_by_operator_idを削除
            $table->dropForeign(['synced_by_operator_id']);
            $table->dropIndex(['shop_id', 'synced_at']);
            $table->dropIndex(['synced_by_operator_id']);
            $table->dropColumn('synced_by_operator_id');
            
            // operator_idカラムを追加
            $table->foreignId('operator_id')->after('shop_id')->constrained('operation_persons')->onDelete('cascade');
            $table->index(['shop_id', 'operator_id', 'synced_at']);
        });
    }
};




