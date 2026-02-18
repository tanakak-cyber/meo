<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('meo_rank_logs')) {
            Schema::create('meo_rank_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meo_keyword_id')->constrained()->onDelete('cascade');
                $table->integer('rank')->nullable(); // null=圏外
                $table->date('checked_at');
                $table->timestamps();
                
                // 同じキーワードの同じ日付の重複を防ぐ
                $table->unique(['meo_keyword_id', 'checked_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meo_rank_logs');
    }
};






















