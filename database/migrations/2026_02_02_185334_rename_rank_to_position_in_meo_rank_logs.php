<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meo_rank_logs', function (Blueprint $table) {
            if (Schema::hasColumn('meo_rank_logs', 'rank')) {
                $table->renameColumn('rank', 'position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meo_rank_logs', function (Blueprint $table) {
            if (Schema::hasColumn('meo_rank_logs', 'position')) {
                $table->renameColumn('position', 'rank');
            }
        });
    }
};
