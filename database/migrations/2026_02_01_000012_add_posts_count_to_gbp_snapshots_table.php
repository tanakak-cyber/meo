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
        Schema::table('gbp_snapshots', function (Blueprint $table) {
            // posts_count カラムが存在しない場合のみ追加
            if (!Schema::hasColumn('gbp_snapshots', 'posts_count')) {
                // SQLite と MySQL の両方で動くように integer を使用
                $table->integer('posts_count')->default(0)->after('reviews_count');
            }
        });

        // 既存レコードの posts_count を 0 に初期化
        // SQLite と MySQL の両方で動くように、全てのレコードを更新
        // 注意: カラム追加直後は既存レコードの値が NULL または DEFAULT 値になるため、全て更新する
        DB::table('gbp_snapshots')->update(['posts_count' => 0]);
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

