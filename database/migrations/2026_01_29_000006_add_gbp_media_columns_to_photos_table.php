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
        Schema::table('photos', function (Blueprint $table) {
            if (!Schema::hasColumn('photos', 'gbp_media_id')) {
                $table->string('gbp_media_id')->nullable()->unique();
            }
            if (!Schema::hasColumn('photos', 'gbp_media_name')) {
                $table->string('gbp_media_name')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            if (Schema::hasColumn('photos', 'gbp_media_id')) {
                $table->dropColumn('gbp_media_id');
            }
            if (Schema::hasColumn('photos', 'gbp_media_name')) {
                $table->dropColumn('gbp_media_name');
            }
        });
    }
};



















