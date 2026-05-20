<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_goals', function (Blueprint $table): void {
            $table->decimal('height_cm', 5, 2)->nullable()->after('macro_calories');
            $table->decimal('target_weight_kg', 8, 2)->nullable()->after('height_cm');
            $table->decimal('target_body_fat_percent', 5, 2)->nullable()->after('target_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('daily_goals', function (Blueprint $table): void {
            $table->dropColumn(['height_cm', 'target_weight_kg', 'target_body_fat_percent']);
        });
    }
};
