<?php

use App\Models\MealEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('meal-reminder:check {meal} {date}', function (): int {
    $meal = (string) $this->argument('meal');
    $date = (string) $this->argument('date');

    if (! in_array($meal, ['breakfast', 'lunch', 'dinner'], true) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1) {
        $this->error('Invalid meal reminder.');

        return Command::INVALID;
    }

    $logged = MealEntry::query()
        ->whereDate('date', $date)
        ->where('meal_type', $meal)
        ->exists();

    $this->line(($logged ? 'BUFF_MEAL_REMINDER_LOGGED:' : 'BUFF_MEAL_REMINDER_DUE:').$meal);

    return Command::SUCCESS;
})->purpose('Check whether a meal reminder is still due');

Schedule::command('health-connect:sync')
    ->everyTenMinutes()
    ->description('health-connect-sync');

Schedule::command('apple-health:sync')
    ->everyTenMinutes()
    ->description('apple-health-sync');
