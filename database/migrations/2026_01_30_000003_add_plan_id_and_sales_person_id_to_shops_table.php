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
            if (!Schema::hasColumn('shops', 'plan_id')) {
                $table->unsignedBigInteger('plan_id')->nullable();
            }
            if (!Schema::hasColumn('shops', 'sales_person_id')) {
                $table->unsignedBigInteger('sales_person_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'sales_person_id')) {
                $table->dropColumn('sales_person_id');
            }
            if (Schema::hasColumn('shops', 'plan_id')) {
                $table->dropColumn('plan_id');
            }
        });
    }
};

