<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AppleHealthController;
use App\Http\Controllers\BodyMetricPhotoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\HealthConnectController;
use App\Http\Controllers\MacroController;
use App\Http\Controllers\McpApprovalController;
use App\Http\Controllers\MealAnalysisController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\WeeklySummaryController;
use App\Http\Controllers\WorkoutController;
use App\Http\Middleware\EnsureBuffAccount;
use Illuminate\Support\Facades\Route;

Route::get('/account/login', [AccountController::class, 'loginPage'])->name('account.login');
Route::post('/account/login', [AccountController::class, 'login']);
Route::post('/account/resume', [AccountController::class, 'resume']);
Route::delete('/account/local-data', [AccountController::class, 'destroyLocalData']);
Route::get('/account/social/callback', [AccountController::class, 'socialCallback']);
Route::get('/account/register', [AccountController::class, 'registerPage'])->name('account.register');
Route::post('/account/register', [AccountController::class, 'register']);
Route::get('/account/forgot-password', [AccountController::class, 'forgotPasswordPage']);
Route::post('/account/forgot-password', [AccountController::class, 'forgotPassword']);
Route::get('/reset-password', [AccountController::class, 'resetPasswordPage']);
Route::post('/reset-password', [AccountController::class, 'resetPassword']);

Route::middleware(EnsureBuffAccount::class)->group(function (): void {
    Route::get('/mcp-approve', [McpApprovalController::class, 'show'])->name('mcp.approval.show');
    Route::post('/mcp-approve', [McpApprovalController::class, 'store'])->name('mcp.approval.store');
    Route::get('/mcp-approve/complete', [McpApprovalController::class, 'complete'])->name('mcp.approval.complete');

    Route::get('/account/verify', [AccountController::class, 'verificationPage'])->name('account.verify');
    Route::get('/account/verification-status', [AccountController::class, 'verificationStatus']);
    Route::post('/account/verification/resend', [AccountController::class, 'resendVerification']);
    Route::post('/account/logout', [AccountController::class, 'logout']);
    Route::get('/onboarding', [OnboardingController::class, 'create']);
    Route::post('/onboarding/plan', [OnboardingController::class, 'plan']);
    Route::post('/onboarding', [OnboardingController::class, 'store']);

    Route::get('/', DashboardController::class);
    Route::get('/weekly', WeeklySummaryController::class);
    Route::get('/macros/{macro}', MacroController::class);

    Route::get('/goals', [GoalController::class, 'edit']);
    Route::put('/goals', [GoalController::class, 'update']);

    Route::get('/settings', [SettingsController::class, 'edit']);
    Route::get('/settings/account', [SettingsController::class, 'account']);
    Route::get('/settings/password', [SettingsController::class, 'password']);
    Route::get('/settings/appearance', [SettingsController::class, 'appearance']);
    Route::get('/settings/reminders', [SettingsController::class, 'reminders']);
    Route::get('/settings/body-profile', [SettingsController::class, 'bodyProfile']);
    Route::get('/settings/units', [SettingsController::class, 'units']);
    Route::get('/settings/exercise', [SettingsController::class, 'exercise']);
    Route::get('/settings/health', [SettingsController::class, 'health']);
    Route::get('/settings/connected-assistants', [SettingsController::class, 'connectedAssistants']);
    Route::delete('/settings/connected-assistants/{connection}', [SettingsController::class, 'revokeConnectedAssistant'])
        ->whereUuid('connection');
    Route::put('/settings/units', [SettingsController::class, 'updateUnits']);
    Route::put('/settings/eat-back', [SettingsController::class, 'updateEatBack']);
    Route::put('/settings/body-profile', [SettingsController::class, 'updateBodyProfile']);
    Route::put('/settings/meal-reminders', [SettingsController::class, 'updateMealReminders']);
    Route::patch('/account', [AccountController::class, 'update']);
    Route::put('/account/password', [AccountController::class, 'updatePassword']);
    Route::delete('/account', [AccountController::class, 'destroy']);
    Route::post('/sync', [SyncController::class, 'store']);
    Route::post('/sync/resume', [SyncController::class, 'resume']);

    Route::get('/progress', [ProgressController::class, 'index']);
    Route::post('/progress/body-metrics', [ProgressController::class, 'store']);
    Route::delete('/progress/body-metrics/{bodyMetric}', [ProgressController::class, 'destroy']);
    Route::post('/progress/body-metrics/{bodyMetric}/photos', [BodyMetricPhotoController::class, 'store']);
    Route::get('/progress/body-metrics/{bodyMetric}/photos', [BodyMetricPhotoController::class, 'index']);
    Route::get('/progress/body-metrics/{bodyMetric}/photos/pending/{pending}/{index}', [BodyMetricPhotoController::class, 'pending'])
        ->whereNumber('index');
    Route::delete('/progress/body-metrics/{bodyMetric}/photos/{photo}', [BodyMetricPhotoController::class, 'destroy']);

    Route::get('/add', [MealController::class, 'create']);
    Route::post('/barcode/lookup', [MealController::class, 'lookupBarcode']);
    Route::get('/food-products/search', [MealController::class, 'searchFoodProducts']);
    Route::post('/meals/custom', [MealController::class, 'storeCustom']);
    Route::post('/meals/barcode', [MealController::class, 'storeBarcode']);
    Route::post('/meals/recipe', [MealController::class, 'storeFromRecipe']);
    Route::post('/recipes', [RecipeController::class, 'store']);
    Route::put('/recipes/{recipe}', [RecipeController::class, 'update']);
    Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy']);
    Route::post('/meals/{mealEntry}/repeat', [MealController::class, 'repeat']);
    Route::put('/meals/{mealEntry}', [MealController::class, 'update']);
    Route::delete('/meals/{mealEntry}', [MealController::class, 'destroy']);
    Route::post('/meal-analyses', [MealAnalysisController::class, 'store']);
    Route::post('/meal-analyses/{analysis}/follow-up', [MealAnalysisController::class, 'followUp']);
    Route::delete('/meal-analyses/{analysis}', [MealAnalysisController::class, 'destroy']);
    Route::get('/meals/{mealEntry}/photos', [MealAnalysisController::class, 'photos']);

    Route::post('/workouts', [WorkoutController::class, 'store']);
    Route::put('/workouts/{workoutEntry}', [WorkoutController::class, 'update']);
    Route::delete('/workouts/{workoutEntry}', [WorkoutController::class, 'destroy']);

    Route::get('/health-connect/status', [HealthConnectController::class, 'status']);
    Route::post('/health-connect/connect', [HealthConnectController::class, 'connect']);
    Route::post('/health-connect/sync', [HealthConnectController::class, 'sync']);
    Route::delete('/health-connect', [HealthConnectController::class, 'destroy']);

    Route::get('/apple-health/status', [AppleHealthController::class, 'status']);
    Route::post('/apple-health/connect', [AppleHealthController::class, 'connect']);
    Route::post('/apple-health/sync', [AppleHealthController::class, 'sync']);
});
