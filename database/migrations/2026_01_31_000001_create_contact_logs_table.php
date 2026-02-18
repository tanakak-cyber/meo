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
        Schema::create('contact_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->onDelete('cascade');
            $table->date('contact_date');
            $table->time('contact_time');
            $table->text('content');
            $table->timestamps();
            
            // 同じ店舗で同じ日付の連絡履歴は1件まで（オプション）
            $table->unique(['shop_id', 'contact_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_logs');
    }
};



















