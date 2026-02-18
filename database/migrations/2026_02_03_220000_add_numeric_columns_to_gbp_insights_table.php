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
            // CSVから取り込む確定値を保存する数値カラム
            $table->integer('impressions')->nullable()->after('keywords_response');
            $table->integer('directions')->nullable()->after('impressions');
            $table->integer('website')->nullable()->after('directions');
            $table->integer('phone')->nullable()->after('website');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gbp_insights', function (Blueprint $table) {
            $table->dropColumn(['impressions', 'directions', 'website', 'phone']);
        });
    }
};

