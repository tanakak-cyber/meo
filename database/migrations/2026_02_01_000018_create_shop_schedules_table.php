<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->string('name')->comment('スケジュール名');
            $table->time('execution_time')->comment('実行時間（HH:mm形式）');
            $table->boolean('is_enabled')->default(true)->comment('有効/無効');
            $table->text('description')->nullable()->comment('説明');
            $table->timestamps();
            
            $table->index(['shop_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_schedules');
    }
};














