<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // 前回同期で確定的に取り込めた最新の review.updateTime（最大値）を保存
            // これが次回差分同期の cutoff（停止ライン）になる
            $table->timestamp('last_reviews_synced_update_time')->nullable()->after('last_reviews_synced_at');
            
            // 監視/デバッグ用（任意だが推奨）
            $table->timestamp('last_reviews_sync_started_at')->nullable()->after('last_reviews_synced_update_time');
            $table->timestamp('last_reviews_sync_finished_at')->nullable()->after('last_reviews_sync_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'last_reviews_synced_update_time',
                'last_reviews_sync_started_at',
                'last_reviews_sync_finished_at',
            ]);
        });
    }
};








