<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // review_monthly_target と photo_monthly_target が存在する場合のみ変更
            if (Schema::hasColumn('shops', 'review_monthly_target')) {
                $table->integer('review_monthly_target')->nullable()->change();
            }
            if (Schema::hasColumn('shops', 'photo_monthly_target')) {
                $table->integer('photo_monthly_target')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            // ロールバック時は NOT NULL に戻す（デフォルト値0を設定）
            if (Schema::hasColumn('shops', 'review_monthly_target')) {
                $table->integer('review_monthly_target')->default(0)->nullable(false)->change();
            }
            if (Schema::hasColumn('shops', 'photo_monthly_target')) {
                $table->integer('photo_monthly_target')->default(0)->nullable(false)->change();
            }
        });
    }
};






















