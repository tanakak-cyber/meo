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
            if (!Schema::hasColumn('gbp_posts', 'source_url')) {
                $table->string('source_url')->nullable()->after('gbp_post_id');
            }
            if (!Schema::hasColumn('gbp_posts', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('source_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gbp_posts', function (Blueprint $table) {
            if (Schema::hasColumn('gbp_posts', 'source_url')) {
                $table->dropColumn('source_url');
            }
            if (Schema::hasColumn('gbp_posts', 'posted_at')) {
                $table->dropColumn('posted_at');
            }
        });
    }
};
















