<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                $indexes = collect(DB::select("SHOW INDEX FROM reviews"))
                    ->pluck('Key_name')
                    ->unique()
                    ->toArray();

                if (in_array('reviews_snapshot_id_gbp_review_id_unique', $indexes)) {
                    DB::statement('ALTER TABLE reviews DROP INDEX reviews_snapshot_id_gbp_review_id_unique');
                }

                if (!in_array('reviews_shop_id_gbp_review_id_unique', $indexes)) {
                $table->unique(
                        ['shop_id', 'gbp_review_id'],
                        'reviews_shop_id_gbp_review_id_unique'
                    );
                }
            } else {
                // SQLite や PostgreSQL の場合
                try {
                    $table->dropUnique('reviews_snapshot_id_gbp_review_id_unique');
                } catch (\Throwable $e) {
                    // index が無くても無視
                }

                $table->unique(
                    ['shop_id', 'gbp_review_id'],
                    'reviews_shop_id_gbp_review_id_unique'
                );
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                $indexes = collect(DB::select("SHOW INDEX FROM reviews"))
                    ->pluck('Key_name')
                    ->unique()
                    ->toArray();

                if (in_array('reviews_shop_id_gbp_review_id_unique', $indexes)) {
                    DB::statement('ALTER TABLE reviews DROP INDEX reviews_shop_id_gbp_review_id_unique');
                }

                if (!in_array('reviews_snapshot_id_gbp_review_id_unique', $indexes)) {
                    $table->unique(
                        ['snapshot_id', 'gbp_review_id'],
                        'reviews_snapshot_id_gbp_review_id_unique'
                    );
                }
            } else {
                // SQLite や PostgreSQL の場合
                try {
                    $table->dropUnique('reviews_shop_id_gbp_review_id_unique');
                } catch (\Throwable $e) {
                    // index が無くても無視
                }

                $table->unique(
                    ['snapshot_id', 'gbp_review_id'],
                    'reviews_snapshot_id_gbp_review_id_unique'
                );
            }
        });
    }
};

