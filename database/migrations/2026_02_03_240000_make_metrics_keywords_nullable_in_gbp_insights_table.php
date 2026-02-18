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
        Schema::table('gbp_insights', function (Blueprint $table) {
            // CSVからのインポート時にはAPIの生レスポンスが存在しないため、nullable に変更
            $table->json('metrics_response')->nullable()->change();
            $table->json('keywords_response')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gbp_insights', function (Blueprint $table) {
            $table->json('metrics_response')->nullable(false)->change();
            $table->json('keywords_response')->nullable(false)->change();
        });
    }
};

