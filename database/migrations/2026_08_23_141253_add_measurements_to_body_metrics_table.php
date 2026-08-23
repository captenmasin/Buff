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
        Schema::table('body_metrics', function (Blueprint $table): void {
            $table->decimal('chest_cm', total: 6, places: 2)->nullable();
            $table->decimal('waist_cm', total: 6, places: 2)->nullable();
            $table->decimal('hips_cm', total: 6, places: 2)->nullable();
            $table->decimal('upper_arm_cm', total: 6, places: 2)->nullable();
            $table->decimal('thigh_cm', total: 6, places: 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('body_metrics', function (Blueprint $table): void {
            $table->dropColumn(['chest_cm', 'waist_cm', 'hips_cm', 'upper_arm_cm', 'thigh_cm']);
        });
    }
};
