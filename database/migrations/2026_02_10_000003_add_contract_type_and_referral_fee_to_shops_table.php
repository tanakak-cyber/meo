<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('contract_type')->nullable()->after('price')->comment('契約形態: own=自社契約, referral=紹介契約');
            $table->decimal('referral_fee', 10, 2)->nullable()->after('contract_type')->comment('月額紹介フィー');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['contract_type', 'referral_fee']);
        });
    }
};







