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
        Schema::table('gbp_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('gbp_posts', 'source_type')) {
                $table->string('source_type')->default('blog')->after('gbp_post_id');
            }
            if (!Schema::hasColumn('gbp_posts', 'source_external_id')) {
                $table->string('source_external_id')->nullable()->after('source_type');
            }
        });

        // インデックス追加
        $indexName = 'gbp_posts_shop_source_external_index';
        $indexExists = false;

        if (DB::getDriverName() === 'mysql') {
            $result = DB::selectOne(
                "SELECT COUNT(*) as count FROM information_schema.statistics 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'gbp_posts' 
                 AND index_name = ?",
                [$indexName]
            );
            $indexExists = $result && $result->count > 0;
        }

        if (!$indexExists) {
            Schema::table('gbp_posts', function (Blueprint $table) use ($indexName) {
                $table->index(['shop_id', 'source_type', 'source_external_id'], $indexName);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gbp_posts', function (Blueprint $table) {
            $indexName = 'gbp_posts_shop_source_external_index';
            
            // インデックス削除
            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement("ALTER TABLE gbp_posts DROP INDEX {$indexName}");
                } catch (\Exception $e) {
                    // Index might not exist, ignore
                }
            } else {
                try {
                    $table->dropIndex($indexName);
                } catch (\Exception $e) {
                    // Index might not exist, ignore
                }
            }
        });

        Schema::table('gbp_posts', function (Blueprint $table) {
            if (Schema::hasColumn('gbp_posts', 'source_external_id')) {
                $table->dropColumn('source_external_id');
            }
            if (Schema::hasColumn('gbp_posts', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });
    }
};
