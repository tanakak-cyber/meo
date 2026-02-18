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
            $table->timestamp('last_reviews_synced_at')->nullable()->after('gbp_name');
            $table->timestamp('last_photos_synced_at')->nullable()->after('last_reviews_synced_at');
            $table->timestamp('last_posts_synced_at')->nullable()->after('last_photos_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'last_reviews_synced_at',
                'last_photos_synced_at',
                'last_posts_synced_at',
            ]);
        });
    }
};









