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
        Schema::create('operator_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_id')->constrained('operation_persons')->onDelete('cascade');
            $table->foreignId('gbp_review_id')->nullable()->constrained('reviews')->onDelete('cascade');
            $table->string('action_type'); // reply, check, resolve, sync など
            $table->text('action_data')->nullable(); // JSON形式で返信内容などを保存
            $table->text('replied_text')->nullable(); // 返信テキスト（action_type=replyの場合）
            $table->timestamps();
            
            // インデックス
            $table->index('operator_id');
            $table->index('gbp_review_id');
            $table->index('action_type');
            $table->index(['operator_id', 'action_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operator_actions');
    }
};
















