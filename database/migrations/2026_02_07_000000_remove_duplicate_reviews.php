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
        // 同じ shop_id + gbp_review_id の組み合わせで、idが大きい（後から作られた）方を削除
        // 最新1件のみ残す
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                DELETE r1 FROM reviews r1
                INNER JOIN reviews r2
                    ON r1.shop_id = r2.shop_id
                    AND r1.gbp_review_id = r2.gbp_review_id
                    AND r1.id > r2.id
            ");
        } elseif (DB::getDriverName() === 'sqlite') {
            // SQLite の場合
            DB::statement("
                DELETE FROM reviews
                WHERE id IN (
                    SELECT r1.id
                    FROM reviews r1
                    INNER JOIN reviews r2
                        ON r1.shop_id = r2.shop_id
                        AND r1.gbp_review_id = r2.gbp_review_id
                        AND r1.id > r2.id
                )
            ");
        } else {
            // PostgreSQL など
            DB::statement("
                DELETE FROM reviews r1
                USING reviews r2
                WHERE r1.shop_id = r2.shop_id
                    AND r1.gbp_review_id = r2.gbp_review_id
                    AND r1.id > r2.id
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 何もしない
    }
};









