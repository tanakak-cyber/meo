<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 既存のテーブルがある場合は削除
        Schema::dropIfExists('rank_fetch_jobs');
        
        Schema::create('rank_fetch_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->index()->constrained()->onDelete('cascade');
            $table->foreignId('meo_keyword_id')->index()->constrained('meo_keywords')->onDelete('cascade');
            $table->date('target_date')->comment('この日分の順位を取る');
            
            // SQLite対応: enumの代わりにstringを使用
            if (config('database.default') === 'sqlite') {
                $table->string('status')->default('queued');
            } else {
                $table->enum('status', ['queued', 'running', 'success', 'failed'])->default('queued');
            }
            
            $table->string('requested_by_type')->nullable()->comment('admin or operator');
            $table->unsignedBigInteger('requested_by_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            // UNIQUE制約：同一日同一店舗同一キーワードの二重起動防止
            $table->unique(['shop_id', 'meo_keyword_id', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_fetch_jobs');
    }
};
