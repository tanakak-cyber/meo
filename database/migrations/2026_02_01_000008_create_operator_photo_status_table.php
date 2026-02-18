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
        Schema::create('operator_photo_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('operation_persons')->onDelete('cascade');
            $table->foreignId('photo_id')->constrained('photos')->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, checked, approved, rejected
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // ユニークキー: 同じオペレーターが同じ写真を重複して管理できない
            $table->unique(['operator_id', 'photo_id'], 'operator_photo_status_unique');
            
            // インデックス
            $table->index('operator_id');
            $table->index('photo_id');
            $table->index('status');
            $table->index(['operator_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operator_photo_status');
    }
};
















