<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_media_assets', function (Blueprint $table) {
            $table->string('public_url')->nullable()->after('file_path')->comment('公開URL（storage:link経由）');
        });
    }

    public function down(): void
    {
        Schema::table('shop_media_assets', function (Blueprint $table) {
            $table->dropColumn('public_url');
        });
    }
};









