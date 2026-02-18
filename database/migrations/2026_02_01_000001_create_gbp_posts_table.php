<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gbp_posts')) {
            Schema::create('gbp_posts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained()->onDelete('cascade');
                $table->foreignId('operator_id')->nullable()->constrained('operation_persons')->onDelete('cascade');
                $table->string('gbp_post_id'); // name の最後の部分（例: zzz）
                $table->datetime('create_time'); // Googleの createTime
                $table->datetime('fetched_at'); // 同期した時刻
                $table->timestamps();
                
                // UNIQUE KEY: (shop_id, operator_id, gbp_post_id)
                $table->unique(['shop_id', 'operator_id', 'gbp_post_id'], 'gbp_posts_unique');
                
                // インデックス
                $table->index('shop_id');
                $table->index('operator_id');
                $table->index('create_time');
                $table->index(['shop_id', 'operator_id', 'create_time']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_posts');
    }
};

















