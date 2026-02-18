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
        Schema::table('operation_persons', function (Blueprint $table) {
            if (!Schema::hasColumn('operation_persons', 'password_plain')) {
                $table->string('password_plain', 255)->nullable()->after('password_hash');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_persons', function (Blueprint $table) {
            if (Schema::hasColumn('operation_persons', 'password_plain')) {
                $table->dropColumn('password_plain');
            }
        });
    }
};



















