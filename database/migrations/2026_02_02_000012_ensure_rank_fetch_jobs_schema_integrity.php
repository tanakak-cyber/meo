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
     * rank-worker.cjs が MySQL で動作するために必要な
     * rank_fetch_jobs, meo_rank_logs, meo_keywords の
     * スキーマ整合性を保証する修復マイグレーション
     * 
     * Schema::hasColumn() は信頼できないため、
     * information_schema.columns (MySQL) または
     * PRAGMA table_info() (SQLite) で直接確認する
     */
    public function up(): void
    {
        // ============================================
        // rank_fetch_jobs テーブルの修復
        // ============================================
        if (Schema::hasTable('rank_fetch_jobs')) {
            $isMysql = DB::getDriverName() === 'mysql';
            
            // shop_id カラムの追加（存在しない場合）
            if (!$this->hasColumn('rank_fetch_jobs', 'shop_id')) {
                if ($isMysql) {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `shop_id` BIGINT UNSIGNED NOT NULL AFTER `id`');
                } else {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `shop_id` INTEGER NOT NULL');
                }
            }
            
            // meo_keyword_id カラムの追加（存在しない場合）※最重要
            if (!$this->hasColumn('rank_fetch_jobs', 'meo_keyword_id')) {
                if ($isMysql) {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `meo_keyword_id` BIGINT UNSIGNED NOT NULL AFTER `shop_id`');
                } else {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `meo_keyword_id` INTEGER NOT NULL');
                }
            }
            
            // target_date カラムの追加（存在しない場合）
            if (!$this->hasColumn('rank_fetch_jobs', 'target_date')) {
                if ($isMysql) {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `target_date` DATE NOT NULL AFTER `meo_keyword_id`');
                } else {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `target_date` DATE NOT NULL');
                }
            }
            
            // status カラムの追加（存在しない場合）
            if (!$this->hasColumn('rank_fetch_jobs', 'status')) {
                if ($isMysql) {
                    DB::statement("ALTER TABLE `rank_fetch_jobs` ADD COLUMN `status` ENUM('queued', 'running', 'success', 'failed') NOT NULL DEFAULT 'queued' AFTER `target_date`");
                } else {
                    DB::statement("ALTER TABLE `rank_fetch_jobs` ADD COLUMN `status` VARCHAR(255) NOT NULL DEFAULT 'queued'");
                }
            }
            
            // requested_by_type カラムの追加（存在しない場合）
            if (!$this->hasColumn('rank_fetch_jobs', 'requested_by_type')) {
                if ($isMysql) {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `requested_by_type` VARCHAR(255) NULL AFTER `status`');
                } else {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `requested_by_type` VARCHAR(255) NULL');
                }
            }
            
            // requested_by_id カラムの追加（存在しない場合）
            if (!$this->hasColumn('rank_fetch_jobs', 'requested_by_id')) {
                if ($isMysql) {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `requested_by_id` BIGINT UNSIGNED NULL AFTER `requested_by_type`');
                } else {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `requested_by_id` INTEGER NULL');
                }
            }
            
            // started_at カラムの追加（存在しない場合）
            if (!$this->hasColumn('rank_fetch_jobs', 'started_at')) {
                if ($isMysql) {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `started_at` TIMESTAMP NULL AFTER `requested_by_id`');
                } else {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `started_at` TIMESTAMP NULL');
                }
            }
            
            // finished_at カラムの追加（存在しない場合）
            if (!$this->hasColumn('rank_fetch_jobs', 'finished_at')) {
                if ($isMysql) {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `finished_at` TIMESTAMP NULL AFTER `started_at`');
                } else {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `finished_at` TIMESTAMP NULL');
                }
            }
            
            // error_message カラムの追加（存在しない場合）
            if (!$this->hasColumn('rank_fetch_jobs', 'error_message')) {
                if ($isMysql) {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `error_message` TEXT NULL AFTER `finished_at`');
                } else {
                    DB::statement('ALTER TABLE `rank_fetch_jobs` ADD COLUMN `error_message` TEXT NULL');
                }
            }
            
            // UNIQUE制約の追加（存在しない場合のみ）
            $this->ensureUniqueIndex('rank_fetch_jobs', 'rank_fetch_jobs_shop_id_meo_keyword_id_target_date_unique', ['shop_id', 'meo_keyword_id', 'target_date']);
            
            // インデックスの追加（存在しない場合のみ）
            $this->ensureIndex('rank_fetch_jobs', 'rank_fetch_jobs_shop_id_index', ['shop_id']);
            $this->ensureIndex('rank_fetch_jobs', 'rank_fetch_jobs_meo_keyword_id_index', ['meo_keyword_id']);
            
            // 外部キー制約の追加（存在しない場合のみ）
            $this->ensureForeignKey('rank_fetch_jobs', 'rank_fetch_jobs_shop_id_foreign', 'shop_id', 'shops', 'id');
            $this->ensureForeignKey('rank_fetch_jobs', 'rank_fetch_jobs_meo_keyword_id_foreign', 'meo_keyword_id', 'meo_keywords', 'id');
        }
        
        // ============================================
        // meo_rank_logs テーブルの修復
        // ============================================
        if (Schema::hasTable('meo_rank_logs')) {
            $isMysql = DB::getDriverName() === 'mysql';
            
            // meo_keyword_id カラムの追加（存在しない場合）
            if (!$this->hasColumn('meo_rank_logs', 'meo_keyword_id')) {
                if ($isMysql) {
                    DB::statement('ALTER TABLE `meo_rank_logs` ADD COLUMN `meo_keyword_id` BIGINT UNSIGNED NOT NULL AFTER `id`');
                } else {
                    DB::statement('ALTER TABLE `meo_rank_logs` ADD COLUMN `meo_keyword_id` INTEGER NOT NULL');
                }
            }
            
            // rank カラムの追加（存在しない場合）
            if (!$this->hasColumn('meo_rank_logs', 'rank')) {
                if ($isMysql) {
                    DB::statement('ALTER TABLE `meo_rank_logs` ADD COLUMN `rank` INT NULL AFTER `meo_keyword_id`');
                } else {
                    DB::statement('ALTER TABLE `meo_rank_logs` ADD COLUMN `rank` INTEGER NULL');
                }
            }
            
            // checked_at カラムの追加（存在しない場合）
            if (!$this->hasColumn('meo_rank_logs', 'checked_at')) {
                if ($isMysql) {
                    DB::statement('ALTER TABLE `meo_rank_logs` ADD COLUMN `checked_at` DATE NOT NULL AFTER `rank`');
                } else {
                    DB::statement('ALTER TABLE `meo_rank_logs` ADD COLUMN `checked_at` DATE NOT NULL');
                }
            }
            
            // UNIQUE制約の追加（存在しない場合のみ）
            $this->ensureUniqueIndex('meo_rank_logs', 'meo_rank_logs_meo_keyword_id_checked_at_unique', ['meo_keyword_id', 'checked_at']);
            
            // 外部キー制約の追加（存在しない場合のみ）
            $this->ensureForeignKey('meo_rank_logs', 'meo_rank_logs_meo_keyword_id_foreign', 'meo_keyword_id', 'meo_keywords', 'id');
        }
        
        // ============================================
        // meo_keywords テーブルの修復
        // ============================================
        if (Schema::hasTable('meo_keywords')) {
            $isMysql = DB::getDriverName() === 'mysql';
            
            // keyword カラムの追加（存在しない場合）
            if (!$this->hasColumn('meo_keywords', 'keyword')) {
                if ($isMysql) {
                    DB::statement('ALTER TABLE `meo_keywords` ADD COLUMN `keyword` VARCHAR(255) NOT NULL AFTER `shop_id`');
                } else {
                    DB::statement('ALTER TABLE `meo_keywords` ADD COLUMN `keyword` VARCHAR(255) NOT NULL');
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 修復マイグレーションなので、down() は空にする
        // 既存データを破壊しないため
    }
    
    /**
     * カラムが存在するか確認（information_schema.columns または PRAGMA table_info を使用）
     * Schema::hasColumn() は信頼できないため、直接SQLクエリで確認
     */
    private function hasColumn(string $table, string $column): bool
    {
        if (DB::getDriverName() === 'mysql') {
            // MySQL: information_schema.columns で確認
            try {
                $result = DB::selectOne("
                    SELECT COUNT(*) as count
                    FROM information_schema.columns
                    WHERE table_schema = DATABASE()
                      AND table_name = ?
                      AND column_name = ?
                ", [$table, $column]);
                
                return $result && $result->count > 0;
            } catch (\Throwable $e) {
                // エラー時は false を返す（カラムが存在しないと判断）
                return false;
            }
        } else {
            // SQLite: PRAGMA table_info で確認
            try {
                $columns = DB::select("PRAGMA table_info(`{$table}`)");
                foreach ($columns as $col) {
                    // SQLiteのPRAGMA table_info()は 'name' フィールドでカラム名を返す
                    $colName = is_object($col) ? $col->name : (is_array($col) ? $col['name'] : null);
                    if ($colName === $column) {
                        return true;
                    }
                }
                return false;
            } catch (\Throwable $e) {
                // エラー時は false を返す（カラムが存在しないと判断）
                return false;
            }
        }
    }
    
    /**
     * UNIQUEインデックスが存在しない場合に追加
     */
    private function ensureUniqueIndex(string $table, string $indexName, array $columns): void
    {
        if (DB::getDriverName() === 'mysql') {
            try {
                $exists = DB::selectOne("
                    SELECT COUNT(*) as count
                    FROM information_schema.statistics
                    WHERE table_schema = DATABASE()
                      AND table_name = ?
                      AND index_name = ?
                ", [$table, $indexName]);
                
                if ($exists->count == 0) {
                    DB::statement("ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$indexName}` (" . implode(', ', array_map(fn($col) => "`{$col}`", $columns)) . ")");
                }
            } catch (\Throwable $e) {
                // エラーは無視（既に存在する可能性がある）
            }
        } else {
            // SQLiteの場合は Schema::table で処理
            try {
                Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                    $table->unique($columns, $indexName);
                });
            } catch (\Throwable $e) {
                // エラーは無視（既に存在する可能性がある）
            }
        }
    }
    
    /**
     * インデックスが存在しない場合に追加
     */
    private function ensureIndex(string $table, string $indexName, array $columns): void
    {
        if (DB::getDriverName() === 'mysql') {
            try {
                $exists = DB::selectOne("
                    SELECT COUNT(*) as count
                    FROM information_schema.statistics
                    WHERE table_schema = DATABASE()
                      AND table_name = ?
                      AND index_name = ?
                ", [$table, $indexName]);
                
                if ($exists->count == 0) {
                    DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (" . implode(', ', array_map(fn($col) => "`{$col}`", $columns)) . ")");
                }
            } catch (\Throwable $e) {
                // エラーは無視（既に存在する可能性がある）
            }
        } else {
            // SQLiteの場合は Schema::table で処理
            try {
                Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                    $table->index($columns, $indexName);
                });
            } catch (\Throwable $e) {
                // エラーは無視（既に存在する可能性がある）
            }
        }
    }
    
    /**
     * 外部キー制約が存在しない場合に追加
     */
    private function ensureForeignKey(string $table, string $fkName, string $column, string $referencedTable, string $referencedColumn): void
    {
        if (DB::getDriverName() === 'mysql') {
            try {
                $exists = DB::selectOne("
                    SELECT COUNT(*) as count
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = ?
                      AND CONSTRAINT_NAME = ?
                ", [$table, $fkName]);
                
                if ($exists->count == 0) {
                    DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}` (`{$referencedColumn}`) ON DELETE CASCADE");
                }
            } catch (\Throwable $e) {
                // エラーは無視（既に存在する可能性がある）
            }
        } else {
            // SQLiteの場合は Schema::table で処理
            try {
                Schema::table($table, function (Blueprint $table) use ($column, $referencedTable, $referencedColumn) {
                    $table->foreign($column)->references($referencedColumn)->on($referencedTable)->onDelete('cascade');
                });
            } catch (\Throwable $e) {
                // エラーは無視（既に存在する可能性がある）
            }
        }
    }
};

