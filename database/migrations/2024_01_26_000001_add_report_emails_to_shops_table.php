<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('report_email_1')->nullable()->after('gbp_refresh_token');
            $table->string('report_email_2')->nullable()->after('report_email_1');
            $table->string('report_email_3')->nullable()->after('report_email_2');
            $table->string('report_email_4')->nullable()->after('report_email_3');
            $table->string('report_email_5')->nullable()->after('report_email_4');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['report_email_1', 'report_email_2', 'report_email_3', 'report_email_4', 'report_email_5']);
        });
    }
};






















