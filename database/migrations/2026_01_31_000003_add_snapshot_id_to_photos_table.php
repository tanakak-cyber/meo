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
        Schema::table('photos', function (Blueprint $table) {
            $table->foreignId('snapshot_id')->nullable()->after('shop_id')->constrained('gbp_snapshots')->onDelete('cascade');
            
            // 既存のユニーク制約を削除
            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement('ALTER TABLE photos DROP INDEX photos_gbp_media_id_unique');
                } catch (\Throwable $e) {
                    // index が無くても無視
                }
            } else {
                try {
                    $table->dropUnique(['gbp_media_id']);
                } catch (\Exception $e) {
                    // インデックスが存在しない場合は無視
                }
            }
            
            // snapshot_idとgbp_media_idの組み合わせでユニークにする
            $table->unique(['snapshot_id', 'gbp_media_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['snapshot_id']);
            $table->dropUnique(['snapshot_id', 'gbp_media_id']);
            $table->dropColumn('snapshot_id');
            // 元のユニーク制約を復元
            $table->unique('gbp_media_id');
        });
    }
};







