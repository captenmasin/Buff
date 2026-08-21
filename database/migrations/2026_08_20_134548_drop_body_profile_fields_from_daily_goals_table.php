<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter(
            ['height_cm', 'age', 'sex', 'activity_level'],
            fn (string $column): bool => Schema::hasColumn('daily_goals', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('daily_goals', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        Schema::table('daily_goals', function (Blueprint $table): void {
            if (! Schema::hasColumn('daily_goals', 'height_cm')) {
                $table->decimal('height_cm', 5, 2)->nullable()->after('macro_calories');
            }

            if (! Schema::hasColumn('daily_goals', 'age')) {
                $table->unsignedTinyInteger('age')->nullable()->after('height_cm');
            }

            if (! Schema::hasColumn('daily_goals', 'sex')) {
                $table->string('sex', 16)->nullable()->after('age');
            }

            if (! Schema::hasColumn('daily_goals', 'activity_level')) {
                $table->string('activity_level', 32)->nullable()->after('sex');
            }
        });
    }
};
