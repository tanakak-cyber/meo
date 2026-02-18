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
        // ① 重複削除（最新IDのみ残す）
        DB::statement("
            DELETE p1 FROM photos p1
            INNER JOIN photos p2
            WHERE
                p1.id < p2.id
                AND p1.shop_id = p2.shop_id
                AND p1.gbp_media_name = p2.gbp_media_name
        ");

        // ② gbp_update_time カラム追加（未存在の場合のみ）
        if (!Schema::hasColumn('photos', 'gbp_update_time')) {
            Schema::table('photos', function (Blueprint $table) {
                $table->timestamp('gbp_update_time')->nullable()->after('google_url');
            });
        }

        // ③ unique追加（既存のgbp_media_idのunique制約を削除してから追加）
        // 既存のgbp_media_idのunique制約がある場合は削除
        $indexes = DB::select("SHOW INDEXES FROM photos WHERE Key_name = 'photos_gbp_media_id_unique'");
        if (!empty($indexes)) {
            Schema::table('photos', function (Blueprint $table) {
                $table->dropUnique('photos_gbp_media_id_unique');
            });
        }
        
        // shop_id + gbp_media_name のunique制約を追加（存在しない場合のみ）
        $uniqueIndexes = DB::select("SHOW INDEXES FROM photos WHERE Key_name = 'photos_shop_id_gbp_media_name_unique'");
        if (empty($uniqueIndexes)) {
            Schema::table('photos', function (Blueprint $table) {
                $table->unique(['shop_id', 'gbp_media_name'], 'photos_shop_id_gbp_media_name_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // unique制約を削除
            $table->dropUnique(['shop_id', 'gbp_media_name']);
        });
        
        // gbp_update_timeカラムを削除
        if (Schema::hasColumn('photos', 'gbp_update_time')) {
            Schema::table('photos', function (Blueprint $table) {
                $table->dropColumn('gbp_update_time');
            });
        }
    }
};

