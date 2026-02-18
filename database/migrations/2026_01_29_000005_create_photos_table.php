<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('photos')) {
            Schema::create('photos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained()->onDelete('cascade');
                $table->string('gbp_media_id')->unique(); // Google Business Profileのmedia ID
                $table->string('gbp_media_name')->nullable(); // フルパス形式のmedia name
                $table->string('media_format')->nullable(); // PHOTO, VIDEO等
                $table->string('google_url')->nullable(); // Google URL
                $table->string('thumbnail_url')->nullable(); // サムネイルURL
                $table->datetime('create_time'); // 写真の作成日時
                $table->integer('width_pixels')->nullable();
                $table->integer('height_pixels')->nullable();
                $table->string('location_association_category')->nullable(); // EXTERIOR, INTERIOR, LOGO, COVER等
                $table->timestamps();
                
                // インデックス
                $table->index('shop_id');
                $table->index('create_time');
                $table->index(['shop_id', 'create_time']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};



















