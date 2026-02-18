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
            if (!Schema::hasColumn('shops', 'wp_post_enabled')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->boolean('wp_post_enabled')->default(false);
                } else {
                    $table->boolean('wp_post_enabled')->default(false)->after('instagram_item_selector');
                }
            }
            if (!Schema::hasColumn('shops', 'wp_post_type')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('wp_post_type')->nullable();
                } else {
                    $table->string('wp_post_type')->nullable()->after('wp_post_enabled');
                }
            }
            if (!Schema::hasColumn('shops', 'wp_post_status')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('wp_post_status')->default('publish');
                } else {
                    $table->string('wp_post_status')->default('publish')->after('wp_post_type');
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
            if (Schema::hasColumn('shops', 'wp_post_status')) {
                $table->dropColumn('wp_post_status');
            }
            if (Schema::hasColumn('shops', 'wp_post_type')) {
                $table->dropColumn('wp_post_type');
            }
            if (Schema::hasColumn('shops', 'wp_post_enabled')) {
                $table->dropColumn('wp_post_enabled');
            }
        });
    }
};


