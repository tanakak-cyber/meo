<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('wp_post_status')
                  ->nullable()
                  ->default('publish')
                  ->change();
        });
    }

    public function down()
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('wp_post_status')
                  ->nullable(false)
                  ->default('publish')
                  ->change();
        });
    }
};

