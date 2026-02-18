<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (!Schema::hasColumn('shops', 'blog_option')) {
                // SQLite では after() がサポートされていないため、条件分岐
                if (DB::getDriverName() === 'sqlite') {
                    $table->boolean('blog_option')->default(false);
                } else {
                    $table->boolean('blog_option')->default(false)->after('contract_end_date');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'blog_option')) {
                $table->dropColumn('blog_option');
            }
        });
    }
};

