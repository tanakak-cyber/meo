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
        Schema::table('gbp_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('gbp_posts', 'summary')) {
                $table->text('summary')->nullable()->after('gbp_post_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gbp_posts', function (Blueprint $table) {
            if (Schema::hasColumn('gbp_posts', 'summary')) {
                $table->dropColumn('summary');
            }
        });
    }
};













