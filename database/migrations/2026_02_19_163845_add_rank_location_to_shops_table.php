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
            // 順位計測用の座標（店舗ごとに設定）
            $table->decimal('rank_lat', 10, 7)->nullable()->after('google_place_id');
            $table->decimal('rank_lng', 10, 7)->nullable()->after('rank_lat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['rank_lat', 'rank_lng']);
        });
    }
};

