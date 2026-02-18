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
        Schema::table('gbp_snapshots', function (Blueprint $table) {
            if (!Schema::hasColumn('gbp_snapshots', 'posts_count')) {
                $table->integer('posts_count')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gbp_snapshots', function (Blueprint $table) {
            if (Schema::hasColumn('gbp_snapshots', 'posts_count')) {
                $table->dropColumn('posts_count');
            }
        });
    }
};
