<?php

use App\Models\AppPreference;
use App\Models\MealEntry;
use App\Models\Recipe;
use App\Services\MealReminderBridge;
use Inertia\Testing\AssertableInertia as Assert;
use Native\Mobile\Facades\System;

function logSuggestedMeal(array $attributes): MealEntry
{
    return MealEntry::query()->create([
        'date' => '2026-11-22',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Chicken wrap',
        'portion_quantity' => 200,
        'portion_unit' => 'g',
        'calories' => 400,
        'protein_g' => 30,
        'carbs_g' => 41,
        'fat_g' => 10,
        ...$attributes,
    ]);
}

it('suggests the last entry for each unlogged slot and shows an observation from comparable recent entries', function (): void {
    $this->travelTo(now()->parse('2026-11-23 12:00:00'));
    $wrap = logSuggestedMeal([]);
    $salad = logSuggestedMeal(['date' => '2026-11-20', 'name' => 'Bean salad', 'protein_g' => 15]);
    logSuggestedMeal(['date' => '2026-11-24', 'name' => 'Future lunch', 'protein_g' => 80]);
    logSuggestedMeal(['date' => '2026-11-10', 'name' => 'Old lunch', 'protein_g' => 65]);
    logSuggestedMeal(['meal_type' => 'breakfast', 'name' => 'Toast', 'protein_g' => 7]);
    logSuggestedMeal(['date' => '2026-11-23', 'meal_type' => 'breakfast', 'name' => 'Cereal', 'protein_g' => 8]);

    $this->get('/?skip_onboarding=1')->assertInertia(fn (Assert $page) => $page
        ->where('mealSuggestions.lunch.id', $wrap->id)
        ->where('mealSuggestions.lunch.portion_quantity', 200)
        ->missing('mealSuggestions.breakfast')
        ->where('weeklyMealObservation.id', $wrap->id)
        ->where('weeklyMealObservation.protein_g', 30));

    $this->get('/?date=2026-11-22&skip_onboarding=1')->assertInertia(fn (Assert $page) => $page
        ->has('mealSuggestions', 0)
        ->where('weeklyMealObservation', null));

    $this->get('/add?mode=food&date=2026-11-23&meal=lunch&reminder=1')->assertInertia(fn (Assert $page) => $page
        ->where('meal', 'lunch')
        ->where('reminderSuggestion.id', $wrap->id));

    $this->get("/add?mode=food&date=2026-11-23&meal=lunch&entry={$salad->id}")->assertInertia(fn (Assert $page) => $page
        ->where('selectedPreviousEntry.id', $salad->id)
        ->where('selectedPreviousEntry.portion_quantity', 200));

    logSuggestedMeal(['date' => '2026-11-23']);

    $this->get('/add?mode=food&date=2026-11-23&meal=lunch&reminder=1')->assertInertia(fn (Assert $page) => $page
        ->where('meal', 'lunch')
        ->where('reminderSuggestion', null));
});

it('suggests a single serving of a saved recipe and offers reminders after the first log', function (): void {
    $this->travelTo(now()->parse('2026-11-23 12:00:00'));
    $recipe = Recipe::query()->create([
        'name' => 'Lentil curry',
        'servings' => 4,
        'items' => [['name' => 'Lentils', 'calories' => 800, 'protein_g' => 60, 'carbs_g' => 120, 'fat_g' => 8]],
    ]);

    $this->get('/?skip_onboarding=1')->assertInertia(fn (Assert $page) => $page
        ->where('mealSuggestions.lunch.kind', 'recipe')
        ->where('mealSuggestions.lunch.id', $recipe->id)
        ->where('mealSuggestions.lunch.protein_g', 15)
        ->where('offerMealReminder', false)
        ->where('weeklyMealObservation', null));

    $this->get("/add?mode=recipe&meal=lunch&recipe={$recipe->id}")->assertInertia(fn (Assert $page) => $page
        ->where('meal', 'lunch')
        ->where('selectedRecipeId', $recipe->id));

    $meal = ['date' => '2026-11-23', 'meal_type' => 'lunch', 'recipe_id' => $recipe->id, 'servings' => 1, 'quick_log' => true];
    $this->post('/meals/recipe', $meal)
        ->assertRedirect('/?date=2026-11-23')
        ->assertSessionHas('offer_meal_reminder', true);
    $entry = MealEntry::query()->sole();

    $this->get('/?date=2026-11-23&skip_onboarding=1')->assertInertia(fn (Assert $page) => $page
        ->where('flash.quick_meal_log.id', $entry->id)
        ->where('offerMealReminder', true)
        ->missing('mealSuggestions.lunch'));

    $this->post('/meals/recipe', [...$meal, 'meal_type' => 'dinner'])->assertSessionMissing('offer_meal_reminder');
});

it('adjusts a serving and provides the new entry for undo', function (): void {
    $source = logSuggestedMeal(['portion_quantity' => 1, 'portion_unit' => null]);

    $this->post("/meals/{$source->id}/repeat", [
        'date' => '2026-11-23', 'meal_type' => 'lunch', 'portion_quantity' => 1.5, 'quick_log' => true,
    ])->assertRedirect('/?date=2026-11-23');

    $copy = MealEntry::query()->whereKeyNot($source->id)->sole();
    expect((float) $copy->protein_g)->toBe(45.0)->and($copy->calories)->toBe(600);

    $this->get('/?date=2026-11-23&skip_onboarding=1')->assertInertia(fn (Assert $page) => $page
        ->where('flash.quick_meal_log.id', $copy->id));

    $this->delete("/meals/{$copy->id}")->assertRedirect('/?date=2026-11-23');
    expect(MealEntry::query()->sole()->id)->toBe($source->id);
});

it('refreshes iOS suppression after a meal is logged or removed without requesting permission', function (): void {
    $this->travelTo(now()->parse('2026-11-23 09:00:00'));
    $preferences = AppPreference::current();
    $reminders = $preferences->mealReminders();
    $reminders['lunch']['enabled'] = true;
    $preferences->update(['meal_reminders' => $reminders]);
    System::shouldReceive('isIos')->andReturnTrue();
    $payloads = [];
    app()->instance(MealReminderBridge::class, new MealReminderBridge(function (string $name, string $payload) use (&$payloads): string {
        $payloads[] = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        return '{"status":"scheduled"}';
    }));

    $this->post('/meals/custom', [
        'date' => '2026-11-23', 'meal_type' => 'lunch', 'name' => 'Chicken wrap',
        'portion_quantity' => 200, 'portion_unit' => 'g', 'protein_g' => 30, 'carbs_g' => 41, 'fat_g' => 10,
    ])->assertRedirect('/?date=2026-11-23');
    $entry = MealEntry::query()->sole();

    expect($payloads)->toBe([[
        'reminders' => $reminders,
        'logged_meals' => ['2026-11-23:lunch'],
        'request_permission' => false,
    ]]);
    $this->get('/?date=2026-11-23&skip_onboarding=1')->assertInertia(fn (Assert $page) => $page
        ->where('offerMealReminder', false));

    $this->delete("/meals/{$entry->id}")->assertRedirect('/?date=2026-11-23');
    expect(end($payloads)['logged_meals'])->toBe([]);
});
