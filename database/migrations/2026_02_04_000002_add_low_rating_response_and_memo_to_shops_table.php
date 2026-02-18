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
            // ★2以下の時の対応（既に存在する場合はスキップ）
            if (!Schema::hasColumn('shops', 'low_rating_response')) {
                $table->text('low_rating_response')->nullable()->after('ai_reply_keywords');
            }
        });

        Schema::table('shops', function (Blueprint $table) {
            // メモ（既に存在する場合はスキップ）
            if (!Schema::hasColumn('shops', 'memo')) {
                // low_rating_responseが存在する場合はその後に、存在しない場合はai_reply_keywordsの後に追加
                if (Schema::hasColumn('shops', 'low_rating_response')) {
                    $table->text('memo')->nullable()->after('low_rating_response');
                } else {
                    $table->text('memo')->nullable()->after('ai_reply_keywords');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['low_rating_response', 'memo']);
        });
    }
};

