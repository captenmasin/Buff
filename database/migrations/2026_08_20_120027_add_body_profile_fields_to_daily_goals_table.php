<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_goals', function (Blueprint $table): void {
            $table->unsignedTinyInteger('age')->nullable()->after('height_cm');
            $table->string('sex', 16)->nullable()->after('age');
            $table->string('activity_level', 32)->nullable()->after('sex');
        });
    }

    public function down(): void
    {
        Schema::table('daily_goals', function (Blueprint $table): void {
            $table->dropColumn(['age', 'sex', 'activity_level']);
        });
    }
};
