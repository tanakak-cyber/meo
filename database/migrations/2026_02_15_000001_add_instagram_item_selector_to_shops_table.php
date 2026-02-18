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
            if (!Schema::hasColumn('shops', 'instagram_item_selector')) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('instagram_item_selector', 255)->nullable();
                } else {
                    $table->string('instagram_item_selector', 255)->nullable()->after('instagram_crawl_time')->comment('Instagram 投稿ブロックの親要素セレクター');
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
            if (Schema::hasColumn('shops', 'instagram_item_selector')) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropColumn('instagram_item_selector');
                }
            }
        });
    }
};






