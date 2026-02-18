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
        Schema::create('gbp_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->foreignId('operator_id')->constrained('operation_persons')->onDelete('cascade'); // オペレーターID
            $table->timestamp('synced_at'); // 同期実行日時
            $table->integer('photos_count')->default(0); // 同期した写真数
            $table->integer('reviews_count')->default(0); // 同期した口コミ数
            $table->json('sync_params')->nullable(); // 同期パラメータ（start_date, end_dateなど）
            $table->timestamps();
            
            // インデックス: オペレーターごとの最新スナップショット取得用
            $table->index(['shop_id', 'operator_id', 'synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gbp_snapshots');
    }
};

