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
            // AI返信時に必ず入れるキーワード（カンマ区切り or 改行区切りで複数キーワード可）
            $table->text('ai_reply_keywords')->nullable()->after('gbp_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('ai_reply_keywords');
        });
    }
};












