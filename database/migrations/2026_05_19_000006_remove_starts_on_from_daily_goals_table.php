<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('daily_goals', 'starts_on')) {
            return;
        }

        $goalToKeep = DB::table('daily_goals')
            ->orderByDesc('starts_on')
            ->orderByDesc('updated_at')
            ->first(['id']);

        if ($goalToKeep) {
            DB::table('daily_goals')
                ->where('id', '!=', $goalToKeep->id)
                ->delete();
        }

        Schema::table('daily_goals', function (Blueprint $table): void {
            $table->dropUnique(['starts_on']);
            $table->dropColumn('starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('daily_goals', function (Blueprint $table): void {
            if (! Schema::hasColumn('daily_goals', 'starts_on')) {
                $table->date('starts_on')->nullable()->unique()->after('id');
            }
        });
    }
};
