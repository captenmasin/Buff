<?php

namespace App\Http\Controllers;

use App\Models\WorkoutEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
        ]);

        return redirect('/?date='.$validated['date'])->with('message', 'Workout added.');
    }

    public function destroy(WorkoutEntry $workoutEntry): RedirectResponse
    {
        $date = $workoutEntry->date->toDateString();

        $workoutEntry->delete();

        return redirect('/?date='.$date)->with('message', 'Workout removed.');
    }
}
