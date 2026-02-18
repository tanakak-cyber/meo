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
        Schema::create('operator_shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('operation_persons')->onDelete('cascade');
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // ユニークキー: 同じオペレーターが同じ店舗を重複して担当できない
            $table->unique(['operator_id', 'shop_id'], 'operator_shops_unique');
            
            // インデックス
            $table->index('operator_id');
            $table->index('shop_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operator_shops');
    }
};
















