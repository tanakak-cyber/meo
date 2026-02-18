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
            // gbp_post_name: string, nullable, after gbp_post_id
            if (!Schema::hasColumn('gbp_posts', 'gbp_post_name')) {
                $table->string('gbp_post_name')->nullable()->after('gbp_post_id');
            }

            // summary: text, nullable
            if (!Schema::hasColumn('gbp_posts', 'summary')) {
                $table->text('summary')->nullable();
            }

            // media_url: string, nullable
            if (!Schema::hasColumn('gbp_posts', 'media_url')) {
                $table->string('media_url')->nullable();
            }

            // posted_at: datetime, nullable
            if (!Schema::hasColumn('gbp_posts', 'posted_at')) {
                $table->datetime('posted_at')->nullable();
            }

            // create_time: datetime, nullable (既存の場合はnullableに変更)
            // 既に存在する場合は、nullableに変更する必要があるかもしれませんが、
            // ユーザーの要求は「存在しなければ追加」なので、既存の場合は何もしません
            // ただし、既存のcreate_timeがnullableでない場合は、後で別途対応が必要かもしれません
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gbp_posts', function (Blueprint $table) {
            if (Schema::hasColumn('gbp_posts', 'posted_at')) {
                $table->dropColumn('posted_at');
            }
            if (Schema::hasColumn('gbp_posts', 'media_url')) {
                $table->dropColumn('media_url');
            }
            if (Schema::hasColumn('gbp_posts', 'summary')) {
                $table->dropColumn('summary');
            }
            if (Schema::hasColumn('gbp_posts', 'gbp_post_name')) {
                $table->dropColumn('gbp_post_name');
            }
            // create_timeは既存のカラムなので、down()では削除しません
        });
    }
};













