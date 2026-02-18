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
        // tanaka_k@cho-tensha.jp を管理者に設定
        DB::table('users')
            ->where('email', 'tanaka_k@cho-tensha.jp')
            ->update(['is_admin' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバック時は何もしない（安全のため）
    }
};
















