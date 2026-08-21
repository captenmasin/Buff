<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_preferences', function (Blueprint $table): void {
            $table->string('eat_back', 8)->default('all');
        });
    }

    public function down(): void
    {
        Schema::table('app_preferences', function (Blueprint $table): void {
            $table->dropColumn('eat_back');
        });
    }
};
