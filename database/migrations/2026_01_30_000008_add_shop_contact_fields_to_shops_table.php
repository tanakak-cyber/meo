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
            if (!Schema::hasColumn('shops', 'shop_contact_name')) {
                $table->string('shop_contact_name')->nullable()->after('operation_person_id');
            }
            if (!Schema::hasColumn('shops', 'shop_contact_phone')) {
                $table->string('shop_contact_phone')->nullable()->after('shop_contact_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'shop_contact_phone')) {
                $table->dropColumn('shop_contact_phone');
            }
            if (Schema::hasColumn('shops', 'shop_contact_name')) {
                $table->dropColumn('shop_contact_name');
            }
        });
    }
};



















