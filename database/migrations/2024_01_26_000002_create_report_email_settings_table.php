<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('report_email_settings')) {
            Schema::create('report_email_settings', function (Blueprint $table) {
                $table->id();
                $table->text('subject')->comment('メール件名');
                $table->text('body')->comment('メール本文');
                $table->string('admin_email')->nullable()->comment('管理者メールアドレス');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_email_settings');
    }
};






















