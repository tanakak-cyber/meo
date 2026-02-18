<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 既存のスナップショット（user_id が null のもの）を
     * 管理者ユーザー（is_admin = true）に割り当てる
     * 複数の管理者がいる場合は、最初の管理者に割り当てる
     */
    public function up(): void
    {
        // user_id が null のスナップショットを取得
        $snapshotsWithoutUserId = DB::table('gbp_snapshots')
            ->whereNull('user_id')
            ->get();
        
        if ($snapshotsWithoutUserId->isEmpty()) {
            return;
        }
        
        // 管理者ユーザーを取得（is_admin = true）
        $adminUser = DB::table('users')
            ->where('is_admin', true)
            ->orderBy('id')
            ->first();
        
        if (!$adminUser) {
            // 管理者がいない場合は、最初のユーザーを使用
            $adminUser = DB::table('users')
                ->orderBy('id')
                ->first();
        }
        
        if ($adminUser) {
            // 既存スナップショットを管理者ユーザーに割り当て
            DB::table('gbp_snapshots')
                ->whereNull('user_id')
                ->update(['user_id' => $adminUser->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ロールバック時は user_id を null に戻す
        DB::table('gbp_snapshots')
            ->update(['user_id' => null]);
    }
};













