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
            // gbp_media_id: string unique nullable
            if (!Schema::hasColumn('photos', 'gbp_media_id')) {
                $table->string('gbp_media_id')->nullable()->unique();
            }
            
            // gbp_media_name: string nullable
            if (!Schema::hasColumn('photos', 'gbp_media_name')) {
                $table->string('gbp_media_name')->nullable();
            }
            
            // media_format: string nullable
            if (!Schema::hasColumn('photos', 'media_format')) {
                $table->string('media_format')->nullable();
            }
            
            // google_url: text nullable
            if (!Schema::hasColumn('photos', 'google_url')) {
                $table->text('google_url')->nullable();
            }
            
            // thumbnail_url: text nullable
            if (!Schema::hasColumn('photos', 'thumbnail_url')) {
                $table->text('thumbnail_url')->nullable();
            }
            
            // create_time: timestamp nullable
            if (!Schema::hasColumn('photos', 'create_time')) {
                $table->timestamp('create_time')->nullable();
            }
            
            // width_pixels: integer nullable
            if (!Schema::hasColumn('photos', 'width_pixels')) {
                $table->integer('width_pixels')->nullable();
            }
            
            // height_pixels: integer nullable
            if (!Schema::hasColumn('photos', 'height_pixels')) {
                $table->integer('height_pixels')->nullable();
            }
            
            // location_association_category: string nullable
            if (!Schema::hasColumn('photos', 'location_association_category')) {
                $table->string('location_association_category')->nullable();
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
            if (Schema::hasColumn('photos', 'media_format')) {
                $table->dropColumn('media_format');
            }
            if (Schema::hasColumn('photos', 'google_url')) {
                $table->dropColumn('google_url');
            }
            if (Schema::hasColumn('photos', 'thumbnail_url')) {
                $table->dropColumn('thumbnail_url');
            }
            if (Schema::hasColumn('photos', 'create_time')) {
                $table->dropColumn('create_time');
            }
            if (Schema::hasColumn('photos', 'width_pixels')) {
                $table->dropColumn('width_pixels');
            }
            if (Schema::hasColumn('photos', 'height_pixels')) {
                $table->dropColumn('height_pixels');
            }
            if (Schema::hasColumn('photos', 'location_association_category')) {
                $table->dropColumn('location_association_category');
            }
        });
    }
};

