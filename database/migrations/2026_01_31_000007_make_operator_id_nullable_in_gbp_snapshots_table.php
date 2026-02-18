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
            // 既存の外部キー制約を削除
            $table->dropForeign(['operator_id']);
        });
        
        Schema::table('gbp_snapshots', function (Blueprint $table) {
            // operator_idをnullableに変更（管理者が同期する場合にnullを許可）
            $table->foreignId('operator_id')->nullable()->change();
        });
        
        Schema::table('gbp_snapshots', function (Blueprint $table) {
            // 外部キー制約を再度追加
            $table->foreign('operator_id')->references('id')->on('operation_persons')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gbp_snapshots', function (Blueprint $table) {
            // 既存の外部キー制約を削除
            $table->dropForeign(['operator_id']);
        });
        
        Schema::table('gbp_snapshots', function (Blueprint $table) {
            // nullableを解除（既存のnull値がある場合はエラーになる可能性がある）
            $table->foreignId('operator_id')->nullable(false)->change();
        });
        
        Schema::table('gbp_snapshots', function (Blueprint $table) {
            // 外部キー制約を再度追加
            $table->foreign('operator_id')->references('id')->on('operation_persons')->onDelete('cascade');
        });
    }
};

