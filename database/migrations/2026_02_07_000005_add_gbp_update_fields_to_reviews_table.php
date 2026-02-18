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
        Schema::table('reviews', function (Blueprint $table) {
            // Google側の更新時刻を保持（差分同期のキー）
            $table->timestamp('gbp_update_time')->nullable()->after('update_time')->comment('Google review.updateTime');
            $table->timestamp('gbp_create_time')->nullable()->after('gbp_update_time')->comment('Google review.createTime');
            $table->timestamp('gbp_reply_update_time')->nullable()->after('replied_at')->comment('Google reviewReply.updateTime');
            
            // 返信の有無を明示的に保存
            $table->boolean('has_reply')->default(false)->after('gbp_reply_update_time');
            
            // 返信関連フィールド（既存のreply_textがある場合は追加不要だが、明示的に保存する）
            // 既存のreply_textカラムがある場合は追加不要
            // $table->text('reply_comment')->nullable()->after('has_reply');
            // $table->string('reply_author')->nullable()->after('reply_comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn([
                'gbp_update_time',
                'gbp_create_time',
                'gbp_reply_update_time',
                'has_reply',
            ]);
        });
    }
};








