<?php

namespace App\Http\Controllers;

use App\Models\HealthConnectIgnoredWorkout;
use App\Models\WorkoutEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkoutController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:120'],
            'calories_burned' => ['required', 'integer', 'min:1', 'max:10000'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        WorkoutEntry::query()->create([
            'date' => $validated['date'],
            'title' => $validated['title'],
            'calories_burned' => $validated['calories_burned'],
            'logged_at' => Carbon::parse("{$validated['date']} {$validated['time']}"),
            'source_type' => WorkoutEntry::SOURCE_MANUAL,
        ]);

        return redirect('/?date='.$validated['date'])->with('message', 'Workout added.');
    }

    public function update(Request $request, WorkoutEntry $workoutEntry): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:120'],
            'calories_burned' => ['required', 'integer', 'min:1', 'max:10000'],
            'time' => ['required', 'date_format:H:i'],
        ]);

        DB::transaction(function () use ($validated, $workoutEntry): void {
            $this->ignoreImportedWorkout($workoutEntry);

            $workoutEntry->update([
                'date' => $validated['date'],
                'title' => $validated['title'],
                'calories_burned' => $validated['calories_burned'],
                'logged_at' => Carbon::parse("{$validated['date']} {$validated['time']}"),
                'source_type' => WorkoutEntry::SOURCE_MANUAL,
            ]);
        });

        return redirect('/?date='.$validated['date'])->with('message', 'Workout updated.');
    }

    public function destroy(WorkoutEntry $workoutEntry): RedirectResponse
    {
        $date = $workoutEntry->date->toDateString();

        $this->ignoreImportedWorkout($workoutEntry);

        $workoutEntry->delete();

        return redirect('/?date='.$date)->with('message', 'Workout removed.');
    }

    private function ignoreImportedWorkout(WorkoutEntry $workoutEntry): void
    {
        if ($workoutEntry->isImportedHealth() && $workoutEntry->external_id) {
            HealthConnectIgnoredWorkout::query()->firstOrCreate(
                [
                    'source_type' => $workoutEntry->source_type,
                    'external_id' => $workoutEntry->external_id,
                ],
                ['ignored_at' => now()],
            );
        }
    }
}
