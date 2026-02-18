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
        Schema::table('meo_rank_logs', function (Blueprint $table) {
            // 既存の外部キー制約を削除
            if (DB::getDriverName() === 'mysql') {
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'meo_rank_logs'
                    AND COLUMN_NAME = 'meo_keyword_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                foreach ($foreignKeys as $fk) {
                    DB::statement("ALTER TABLE meo_rank_logs DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                }
            } else {
                try {
                    $table->dropForeign(['meo_keyword_id']);
                } catch (\Exception $e) {
                    // 外部キーが存在しない場合は無視
                }
            }
            
            // meo_keyword_idをnullableに変更
            $table->unsignedBigInteger('meo_keyword_id')->nullable()->change();
            
            // 外部キー制約を再作成（onDelete('set null')）
            $table->foreign('meo_keyword_id')
                ->references('id')
                ->on('meo_keywords')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meo_rank_logs', function (Blueprint $table) {
            // 外部キー制約を削除
            if (DB::getDriverName() === 'mysql') {
                $foreignKeys = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'meo_rank_logs'
                    AND COLUMN_NAME = 'meo_keyword_id'
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                foreach ($foreignKeys as $fk) {
                    DB::statement("ALTER TABLE meo_rank_logs DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                }
            } else {
                try {
                    $table->dropForeign(['meo_keyword_id']);
                } catch (\Exception $e) {
                    // 外部キーが存在しない場合は無視
                }
            }
            
            // meo_keyword_idをnullableでなくする（既存のnull値がある場合は注意が必要）
            $table->unsignedBigInteger('meo_keyword_id')->nullable(false)->change();
            
            // 外部キー制約を再作成（onDelete('cascade')）
            $table->foreign('meo_keyword_id')
                ->references('id')
                ->on('meo_keywords')
                ->onDelete('cascade');
        });
    }
};

