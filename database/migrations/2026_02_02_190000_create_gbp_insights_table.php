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
        Schema::create('gbp_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->string('location_id'); // GBP location ID
            $table->date('from_date'); // 開始日
            $table->date('to_date'); // 終了日
            $table->json('metrics_response'); // KPIメトリクスのJSONレスポンス
            $table->json('keywords_response'); // キーワードのJSONレスポンス
            $table->timestamps();
            
            // 同じ店舗・同じ期間のデータは1つだけ（ユニーク制約）
            $table->unique(['shop_id', 'from_date', 'to_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gbp_insights');
    }
};

