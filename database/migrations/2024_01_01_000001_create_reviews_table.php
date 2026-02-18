<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shop_id')->constrained()->onDelete('cascade');
                $table->string('author_name');
                $table->integer('rating');
                $table->text('comment')->nullable();
                $table->datetime('create_time');
                $table->text('reply_text')->nullable();
                $table->datetime('replied_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};






















