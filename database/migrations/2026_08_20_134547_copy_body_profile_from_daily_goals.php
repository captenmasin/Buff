<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $goal = DB::table('daily_goals')->orderByDesc('updated_at')->first();

        if ($goal === null) {
            return;
        }

        $heightCm = $goal->height_cm ?? null;
        $age = $goal->age ?? null;
        $sex = $goal->sex ?? null;
        $activityLevel = $goal->activity_level ?? null;

        if ($heightCm === null && $age === null && $sex === null && $activityLevel === null) {
            return;
        }

        $now = now()->format('Y-m-d H:i:s.u');
        $id = '00000000-0000-0000-0000-000000000002';

        DB::table('body_profiles')->insert([
            'id' => $id,
            'height_cm' => $heightCm,
            'age' => $age,
            'sex' => $sex,
            'activity_level' => $activityLevel,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (Schema::hasTable('sync_outboxes')) {
            DB::table('sync_outboxes')->updateOrInsert(
                [
                    'record_type' => 'body_profiles',
                    'record_id' => $id,
                ],
                [
                    'payload' => json_encode([
                        'height_cm' => $heightCm !== null ? (float) $heightCm : null,
                        'age' => $age !== null ? (int) $age : null,
                        'sex' => $sex,
                        'activity_level' => $activityLevel,
                    ]),
                    'client_updated_at' => $now,
                    'is_deleted' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('body_profiles')->where('id', '00000000-0000-0000-0000-000000000002')->delete();

        if (Schema::hasTable('sync_outboxes')) {
            DB::table('sync_outboxes')->where('record_type', 'body_profiles')->where('record_id', '00000000-0000-0000-0000-000000000002')->delete();
        }
    }
};
