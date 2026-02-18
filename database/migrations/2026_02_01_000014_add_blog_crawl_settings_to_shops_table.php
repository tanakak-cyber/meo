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
            if (!Schema::hasColumn('shops', 'blog_list_url')) {
                $table->string('blog_list_url')->nullable();
            }
            if (!Schema::hasColumn('shops', 'blog_link_selector')) {
                $table->string('blog_link_selector')->nullable();
            }
            if (!Schema::hasColumn('shops', 'blog_date_selector')) {
                $table->string('blog_date_selector')->nullable();
            }
            if (!Schema::hasColumn('shops', 'blog_image_selector')) {
                $table->string('blog_image_selector')->nullable();
            }
            if (!Schema::hasColumn('shops', 'blog_content_selector')) {
                $table->string('blog_content_selector')->nullable();
            }
            if (!Schema::hasColumn('shops', 'blog_crawl_time')) {
                $table->time('blog_crawl_time')->nullable();
            }
            if (!Schema::hasColumn('shops', 'blog_auto_post_enabled')) {
                $table->boolean('blog_auto_post_enabled')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'blog_auto_post_enabled')) {
                $table->dropColumn('blog_auto_post_enabled');
            }
            if (Schema::hasColumn('shops', 'blog_crawl_time')) {
                $table->dropColumn('blog_crawl_time');
            }
            if (Schema::hasColumn('shops', 'blog_content_selector')) {
                $table->dropColumn('blog_content_selector');
            }
            if (Schema::hasColumn('shops', 'blog_image_selector')) {
                $table->dropColumn('blog_image_selector');
            }
            if (Schema::hasColumn('shops', 'blog_date_selector')) {
                $table->dropColumn('blog_date_selector');
            }
            if (Schema::hasColumn('shops', 'blog_link_selector')) {
                $table->dropColumn('blog_link_selector');
            }
            if (Schema::hasColumn('shops', 'blog_list_url')) {
                $table->dropColumn('blog_list_url');
            }
        });
    }
};




