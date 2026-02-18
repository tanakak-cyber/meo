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
        // fetched_at を nullable に変更
        if (Schema::hasColumn('gbp_posts', 'fetched_at')) {
            // MySQL では ALTER TABLE を使用して nullable に変更
            if (DB::getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE gbp_posts MODIFY COLUMN fetched_at DATETIME NULL');
            } else {
                // SQLite の場合
                Schema::table('gbp_posts', function (Blueprint $table) {
                    $table->dateTime('fetched_at')->nullable()->change();
                });
            }
        }

        // その他の NOT NULL カラムも確認して nullable にする
        // gbp_post_id と create_time は Laravel が INSERT しているので、そのまま維持
        // ただし、create_time が NOT NULL でエラーになる可能性がある場合は nullable にする
        if (Schema::hasColumn('gbp_posts', 'create_time')) {
            if (DB::getDriverName() === 'mysql') {
                // create_time が NOT NULL の場合、nullable に変更
                DB::statement('ALTER TABLE gbp_posts MODIFY COLUMN create_time DATETIME NULL');
            } else {
                Schema::table('gbp_posts', function (Blueprint $table) {
                    $table->dateTime('create_time')->nullable()->change();
                });
            }
        }

        // gbp_post_id は Laravel が INSERT しているので、そのまま維持
        // create_time も Laravel が INSERT しているが、念のため nullable に変更済み
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 元に戻す場合は NOT NULL に戻す
        // ただし、既存データに NULL がある場合はエラーになる可能性があるため、
        // down() は実装しないか、慎重に実装する必要がある
        // 今回は down() は空にしておく
    }
};

