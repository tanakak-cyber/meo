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
            // Add is_deleted column after id
            if (!Schema::hasColumn('gbp_posts', 'is_deleted')) {
                $table->boolean('is_deleted')->default(0)->after('id');
            }
        });

        // Add composite index on (shop_id, is_deleted, create_time)
        // Check if index already exists before creating
        $indexName = 'gbp_posts_shop_id_is_deleted_create_time_index';
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
                $table->index(['shop_id', 'is_deleted', 'create_time'], $indexName);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gbp_posts', function (Blueprint $table) {
            // Drop the composite index first
            $indexName = 'gbp_posts_shop_id_is_deleted_create_time_index';
            
            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement("ALTER TABLE gbp_posts DROP INDEX {$indexName}");
                } catch (\Exception $e) {
                    // Index might not exist, ignore
                }
            } else {
                $table->dropIndex($indexName);
            }
        });

        Schema::table('gbp_posts', function (Blueprint $table) {
            // Drop the column
            if (Schema::hasColumn('gbp_posts', 'is_deleted')) {
                $table->dropColumn('is_deleted');
            }
        });
    }
};

