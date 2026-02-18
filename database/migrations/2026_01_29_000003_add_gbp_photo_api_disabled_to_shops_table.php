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
            $table->boolean('gbp_photo_api_disabled')->default(false)->after('gbp_refresh_token');
            $table->text('gbp_photo_api_disabled_reason')->nullable()->after('gbp_photo_api_disabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['gbp_photo_api_disabled', 'gbp_photo_api_disabled_reason']);
        });
    }
};




















