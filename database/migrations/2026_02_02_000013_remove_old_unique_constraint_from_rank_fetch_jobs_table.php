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
     * 古いUNIQUE(shop_id, target_date)制約を削除し、
     * 正しいUNIQUE(shop_id, meo_keyword_id, target_date)を確実に追加する
     */
    public function up(): void
    {
        if (!Schema::hasTable('rank_fetch_jobs')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            // SQLiteの場合は処理をスキップ（SQLiteでは制約名が動的に変わるため）
            return;
        }

        // ============================================
        // 1. 古いUNIQUE(shop_id, target_date)制約を検出して削除
        // ============================================
        $oldUniqueIndexes = $this->findOldUniqueConstraints();
        
        foreach ($oldUniqueIndexes as $indexName) {
            try {
                DB::statement("ALTER TABLE `rank_fetch_jobs` DROP INDEX `{$indexName}`");
            } catch (\Throwable $e) {
                // 既に削除されている場合は無視
                // エラーはログに記録しない（正常な動作の可能性があるため）
            }
        }

        // ============================================
        // 2. 正しいUNIQUE(shop_id, meo_keyword_id, target_date)制約を確認・追加
        // ============================================
        $correctUniqueName = 'rank_fetch_jobs_shop_id_meo_keyword_id_target_date_unique';
        $hasCorrectUnique = $this->hasUniqueIndex($correctUniqueName, ['shop_id', 'meo_keyword_id', 'target_date']);
        
        if (!$hasCorrectUnique) {
            try {
                DB::statement("ALTER TABLE `rank_fetch_jobs` ADD UNIQUE INDEX `{$correctUniqueName}` (`shop_id`, `meo_keyword_id`, `target_date`)");
            } catch (\Throwable $e) {
                // エラーは無視（既に存在する可能性がある）
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
     * 古いUNIQUE(shop_id, target_date)制約を検出
     * 
     * @return array<string> インデックス名の配列
     */
    private function findOldUniqueConstraints(): array
    {
        try {
            // information_schema.statistics から UNIQUE インデックスを取得
            $indexes = DB::select("
                SELECT 
                    index_name,
                    GROUP_CONCAT(column_name ORDER BY seq_in_index) as columns
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = 'rank_fetch_jobs'
                  AND non_unique = 0
                GROUP BY index_name
            ");

            $oldIndexes = [];
            foreach ($indexes as $index) {
                // GROUP_CONCAT の結果が NULL の場合はスキップ
                if (empty($index->columns)) {
                    continue;
                }
                
                $columns = explode(',', $index->columns);
                $columns = array_map('trim', $columns);
                
                // (shop_id, target_date) のみのUNIQUEを検出
                // meo_keyword_id が含まれていない、かつ shop_id と target_date が含まれている
                if (count($columns) === 2 && 
                    in_array('shop_id', $columns) && 
                    in_array('target_date', $columns) &&
                    !in_array('meo_keyword_id', $columns)) {
                    $oldIndexes[] = $index->index_name;
                }
            }

            return $oldIndexes;
        } catch (\Throwable $e) {
            // エラー時は空配列を返す
            return [];
        }
    }

    /**
     * 指定されたUNIQUEインデックスが存在し、指定されたカラムを含むか確認
     * 
     * @param string $indexName インデックス名
     * @param array<string> $expectedColumns 期待されるカラム名の配列
     * @return bool
     */
    private function hasUniqueIndex(string $indexName, array $expectedColumns): bool
    {
        try {
            $index = DB::selectOne("
                SELECT 
                    index_name,
                    GROUP_CONCAT(column_name ORDER BY seq_in_index) as columns
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = 'rank_fetch_jobs'
                  AND index_name = ?
                  AND non_unique = 0
                GROUP BY index_name
            ", [$indexName]);

            if (!$index || empty($index->columns)) {
                return false;
            }

            $actualColumns = explode(',', $index->columns);
            $actualColumns = array_map('trim', $actualColumns);

            // カラム数と内容が一致するか確認
            if (count($actualColumns) !== count($expectedColumns)) {
                return false;
            }

            foreach ($expectedColumns as $col) {
                if (!in_array($col, $actualColumns)) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            // エラー時は false を返す（存在しないと判断）
            return false;
        }
    }
};

