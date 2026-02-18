<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('shops', function (Blueprint $table) {
            if (!Schema::hasColumn('shops', 'contract_start_date')) {
                $table->date('contract_start_date')->nullable()->after('id');
            }
            if (!Schema::hasColumn('shops', 'contract_end_date')) {
                $table->date('contract_end_date')->nullable()->after('contract_start_date');
            }
        });
    }

    public function down()
    {
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'contract_start_date')) {
                $table->dropColumn('contract_start_date');
            }
            if (Schema::hasColumn('shops', 'contract_end_date')) {
                $table->dropColumn('contract_end_date');
            }
        });
    }
};













