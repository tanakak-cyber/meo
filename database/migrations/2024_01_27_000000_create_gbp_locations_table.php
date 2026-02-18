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
        if (!Schema::hasTable('gbp_locations')) {
            Schema::create('gbp_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained()->onDelete('cascade');
                $table->string('location_id')->unique(); // Google Business Profile APIのlocationId
                $table->string('account_id')->nullable(); // Google Business Profile APIのaccountId
                $table->string('name')->nullable(); // 店舗名（GBPから取得）
                $table->text('address')->nullable(); // 住所
                $table->string('phone_number')->nullable(); // 電話番号
                $table->string('website')->nullable(); // ウェブサイト
                $table->decimal('latitude', 10, 8)->nullable(); // 緯度
                $table->decimal('longitude', 11, 8)->nullable(); // 経度
                $table->json('metadata')->nullable(); // その他のメタデータ
                $table->timestamps();
                
                $table->index('shop_id');
                $table->index('location_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gbp_locations');
    }
};






















