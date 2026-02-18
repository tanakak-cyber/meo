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
        // SQLiteの場合はchange()が使えないため、別の方法で対応
        if (DB::getDriverName() === 'sqlite') {
            // SQLiteではALTER TABLEでカラム型を変更できないため、スキップ
            // アプリケーション側でlongTextとして扱う
            return;
        }

        Schema::table('gbp_posts', function (Blueprint $table) {
            $table->longText('media_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // SQLiteの場合はchange()が使えないため、スキップ
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('gbp_posts', function (Blueprint $table) {
            $table->string('media_url', 255)->nullable()->change();
        });
    }
};

