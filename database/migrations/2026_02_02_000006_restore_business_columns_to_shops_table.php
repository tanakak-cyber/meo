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

            if (!Schema::hasColumn('shops','price')) {
                $table->integer('price')->nullable();
            }
            if (!Schema::hasColumn('shops','contract_date')) {
                $table->date('contract_date')->nullable();
            }
            if (!Schema::hasColumn('shops','contract_end_date')) {
                $table->date('contract_end_date')->nullable();
            }
            if (!Schema::hasColumn('shops','review_monthly_target')) {
                $table->integer('review_monthly_target')->nullable();
            }
            if (!Schema::hasColumn('shops','photo_monthly_target')) {
                $table->integer('photo_monthly_target')->nullable();
            }
            if (!Schema::hasColumn('shops','gbp_account_id')) {
                $table->string('gbp_account_id')->nullable();
            }
            if (!Schema::hasColumn('shops','gbp_location_id')) {
                $table->string('gbp_location_id')->nullable();
            }
            if (!Schema::hasColumn('shops','gbp_refresh_token')) {
                $table->text('gbp_refresh_token')->nullable();
            }

            for ($i = 1; $i <= 5; $i++) {
                if (!Schema::hasColumn('shops', "report_email_$i")) {
                    $table->string("report_email_$i")->nullable();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 空の実装
    }
};













