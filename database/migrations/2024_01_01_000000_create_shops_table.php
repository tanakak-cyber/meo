<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shops')) {
            Schema::create('shops', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('gbp_location_id')->nullable();
                $table->string('gbp_account_id')->nullable();
                $table->text('gbp_refresh_token')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};






















