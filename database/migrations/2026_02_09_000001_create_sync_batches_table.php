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
        Schema::create('sync_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'reviews', 'photos', 'posts', 'all'
            $table->integer('total_shops');
            $table->integer('completed_shops')->default(0);
            $table->integer('total_inserted')->default(0);
            $table->integer('total_updated')->default(0);
            $table->string('status')->default('pending'); // 'pending', 'running', 'finished', 'failed'
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_batches');
    }
};







