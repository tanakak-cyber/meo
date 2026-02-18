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
        Schema::table('gbp_insights', function (Blueprint $table) {
            // 期間タイプ: 'daily' または 'monthly'
            $table->enum('period_type', ['daily', 'monthly'])->default('daily')->after('to_date');
            
            // 月次データ用の年月フィールド
            $table->integer('year')->nullable()->after('period_type');
            $table->integer('month')->nullable()->after('year');
            
            // インデックスを追加（検索高速化のため）
            $table->index('period_type', 'gbp_insights_period_type_index');
            $table->index('year', 'gbp_insights_year_index');
            $table->index('month', 'gbp_insights_month_index');
            $table->index(['shop_id', 'period_type'], 'gbp_insights_shop_period_index');
            $table->index(['shop_id', 'year', 'month'], 'gbp_insights_shop_year_month_index');
            
            // ユニーク制約を変更（period_type, year, month も含める）
            $table->dropUnique(['shop_id', 'from_date', 'to_date']);
        });
        
        // 既存データは daily として扱う
        \DB::table('gbp_insights')->update(['period_type' => 'daily']);
        
        // ユニーク制約を追加（daily と monthly で異なる）
        Schema::table('gbp_insights', function (Blueprint $table) {
            // daily 用のユニーク制約
            $table->unique(['shop_id', 'from_date', 'to_date', 'period_type'], 'gbp_insights_daily_unique');
            
            // monthly 用のユニーク制約（year, month が null でない場合のみ有効）
            // 注意: MySQLでは nullable カラムを含むユニーク制約は NULL 値を複数許可するため、
            // 実際のユニーク性はアプリケーション側で保証する必要があります
            $table->unique(['shop_id', 'year', 'month', 'period_type'], 'gbp_insights_monthly_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gbp_insights', function (Blueprint $table) {
            // インデックスを削除
            $table->dropIndex('gbp_insights_period_type_index');
            $table->dropIndex('gbp_insights_year_index');
            $table->dropIndex('gbp_insights_month_index');
            $table->dropIndex('gbp_insights_shop_period_index');
            $table->dropIndex('gbp_insights_shop_year_month_index');
            
            // ユニーク制約を削除
            $table->dropUnique(['shop_id', 'from_date', 'to_date', 'period_type']);
            $table->dropUnique(['shop_id', 'year', 'month', 'period_type']);
            
            // カラムを削除
            $table->dropColumn(['period_type', 'year', 'month']);
            
            // 元のユニーク制約を復元
            $table->unique(['shop_id', 'from_date', 'to_date']);
        });
    }
};

