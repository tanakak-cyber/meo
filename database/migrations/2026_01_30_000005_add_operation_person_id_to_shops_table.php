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
            if (!Schema::hasColumn('shops', 'operation_person_id')) {
                $table->foreignId('operation_person_id')->nullable()->constrained('operation_persons')->onDelete('set null')->after('sales_person_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'operation_person_id')) {
                $table->dropConstrainedForeignId('operation_person_id');
            }
        });
    }
};



















