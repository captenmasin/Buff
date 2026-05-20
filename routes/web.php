<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\HealthConnectController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\WorkoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class);

Route::get('/goals', [GoalController::class, 'edit']);
Route::put('/goals', [GoalController::class, 'update']);

Route::get('/progress', [ProgressController::class, 'index']);
Route::post('/progress/body-metrics', [ProgressController::class, 'store']);
Route::put('/progress/height', [ProgressController::class, 'updateHeight']);
Route::delete('/progress/body-metrics/{bodyMetric}', [ProgressController::class, 'destroy']);

Route::get('/add', [MealController::class, 'create']);
Route::post('/barcode/lookup', [MealController::class, 'lookupBarcode']);
Route::get('/food-products/search', [MealController::class, 'searchFoodProducts']);
Route::post('/meals/custom', [MealController::class, 'storeCustom']);
Route::post('/meals/barcode', [MealController::class, 'storeBarcode']);
Route::post('/meals/{mealEntry}/repeat', [MealController::class, 'repeat']);
Route::put('/meals/{mealEntry}', [MealController::class, 'update']);
Route::delete('/meals/{mealEntry}', [MealController::class, 'destroy']);

Route::post('/workouts', [WorkoutController::class, 'store']);
Route::delete('/workouts/{workoutEntry}', [WorkoutController::class, 'destroy']);

Route::get('/health-connect/status', [HealthConnectController::class, 'status']);
Route::post('/health-connect/connect', [HealthConnectController::class, 'connect']);
Route::post('/health-connect/sync', [HealthConnectController::class, 'sync']);
