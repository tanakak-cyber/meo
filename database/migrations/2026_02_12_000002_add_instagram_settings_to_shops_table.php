<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (!Schema::hasColumn('shops', 'instagram_auto_post_enabled')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->boolean('instagram_auto_post_enabled')->default(false);
                } else {
                    $table->boolean('instagram_auto_post_enabled')->default(false)->after('blog_fallback_image_url');
                }
            }
            if (!Schema::hasColumn('shops', 'instagram_crawl_time')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->time('instagram_crawl_time')->nullable();
                } else {
                    $table->time('instagram_crawl_time')->nullable()->after('instagram_auto_post_enabled');
                }
            }
            if (!Schema::hasColumn('shops', 'instagram_user_id')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('instagram_user_id')->nullable();
                } else {
                    $table->string('instagram_user_id')->nullable()->after('instagram_crawl_time');
                }
            }
            if (!Schema::hasColumn('shops', 'instagram_access_token')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->text('instagram_access_token')->nullable();
                } else {
                    $table->text('instagram_access_token')->nullable()->after('instagram_user_id');
                }
            }
            if (!Schema::hasColumn('shops', 'instagram_token_expires_at')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->datetime('instagram_token_expires_at')->nullable();
                } else {
                    $table->datetime('instagram_token_expires_at')->nullable()->after('instagram_access_token');
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
            if (Schema::hasColumn('shops', 'instagram_token_expires_at')) {
                $table->dropColumn('instagram_token_expires_at');
            }
            if (Schema::hasColumn('shops', 'instagram_access_token')) {
                $table->dropColumn('instagram_access_token');
            }
            if (Schema::hasColumn('shops', 'instagram_user_id')) {
                $table->dropColumn('instagram_user_id');
            }
            if (Schema::hasColumn('shops', 'instagram_crawl_time')) {
                $table->dropColumn('instagram_crawl_time');
            }
            if (Schema::hasColumn('shops', 'instagram_auto_post_enabled')) {
                $table->dropColumn('instagram_auto_post_enabled');
            }
        });
    }
};





