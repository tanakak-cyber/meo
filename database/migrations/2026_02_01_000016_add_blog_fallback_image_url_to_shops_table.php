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
            if (!Schema::hasColumn('shops', 'blog_fallback_image_url')) {
                $table->string('blog_fallback_image_url', 1024)->nullable()->after('blog_auto_post_enabled')
                    ->comment('記事画像が取得できない場合に使用する代替画像URL');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'blog_fallback_image_url')) {
                $table->dropColumn('blog_fallback_image_url');
            }
        });
    }
};













