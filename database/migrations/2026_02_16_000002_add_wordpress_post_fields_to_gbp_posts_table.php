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
        Schema::table('gbp_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('gbp_posts', 'wp_post_id')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->unsignedInteger('wp_post_id')->nullable();
                } else {
                    $table->unsignedInteger('wp_post_id')->nullable()->after('media_url');
                }
            }
            if (!Schema::hasColumn('gbp_posts', 'wp_posted_at')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->datetime('wp_posted_at')->nullable();
                } else {
                    $table->datetime('wp_posted_at')->nullable()->after('wp_post_id');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gbp_posts', function (Blueprint $table) {
            if (Schema::hasColumn('gbp_posts', 'wp_posted_at')) {
                $table->dropColumn('wp_posted_at');
            }
            if (Schema::hasColumn('gbp_posts', 'wp_post_id')) {
                $table->dropColumn('wp_post_id');
            }
        });
    }
};


