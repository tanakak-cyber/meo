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
            if (!Schema::hasColumn('shops', 'wp_base_url')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('wp_base_url')->nullable();
                } else {
                    $table->string('wp_base_url')->nullable()->after('wp_post_status');
                }
            }
            if (!Schema::hasColumn('shops', 'wp_username')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('wp_username')->nullable();
                } else {
                    $table->string('wp_username')->nullable()->after('wp_base_url');
                }
            }
            if (!Schema::hasColumn('shops', 'wp_app_password')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->text('wp_app_password')->nullable();
                } else {
                    $table->text('wp_app_password')->nullable()->after('wp_username');
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
            if (Schema::hasColumn('shops', 'wp_app_password')) {
                $table->dropColumn('wp_app_password');
            }
            if (Schema::hasColumn('shops', 'wp_username')) {
                $table->dropColumn('wp_username');
            }
            if (Schema::hasColumn('shops', 'wp_base_url')) {
                $table->dropColumn('wp_base_url');
            }
        });
    }
};


