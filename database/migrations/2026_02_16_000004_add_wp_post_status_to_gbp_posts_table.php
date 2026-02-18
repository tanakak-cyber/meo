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
            if (!Schema::hasColumn('gbp_posts', 'wp_post_status')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('wp_post_status')->nullable();
                } else {
                    $table->string('wp_post_status')->nullable()->after('wp_posted_at');
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
            if (Schema::hasColumn('gbp_posts', 'wp_post_status')) {
                $table->dropColumn('wp_post_status');
            }
        });
    }
};


