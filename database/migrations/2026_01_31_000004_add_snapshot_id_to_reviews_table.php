<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            // 既存のインデックスを削除（SQLite の場合はスキップ）
            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement('ALTER TABLE reviews DROP INDEX reviews_gbp_review_id_unique');
                } catch (\Throwable $e) {
                    // index が無くても無視
                }
            } elseif (DB::getDriverName() !== 'sqlite') {
                // SQLite 以外のデータベース（PostgreSQL など）の場合のみ dropUnique を実行
                try {
                    $table->dropUnique(['gbp_review_id']);
                } catch (\Exception $e) {
                    // インデックスが存在しない場合は無視
                }
            }
            // SQLite の場合は dropUnique をスキップ（テーブル再作成時に自動的に削除される）

            // snapshot_id 追加
            if (!Schema::hasColumn('reviews', 'snapshot_id')) {
                $table->unsignedBigInteger('snapshot_id')->nullable()->after('id');
            }

            // 新しいユニークインデックスを追加
            if (!Schema::hasColumn('reviews', 'gbp_review_id')) {
                $table->string('gbp_review_id')->nullable()->after('id');
            }
            $table->unique(['snapshot_id', 'gbp_review_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
