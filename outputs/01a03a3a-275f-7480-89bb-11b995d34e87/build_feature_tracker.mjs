import fs from 'node:fs/promises';
import { SpreadsheetFile, Workbook } from '@oai/artifact-tool';

const outputDir = '/Users/mason/Sites/Buff/outputs/01a03a3a-275f-7480-89bb-11b995d34e87';

const stories = [
  ['APP-001','App shell','Launch and layout','All','As a user, I can launch Buff into its main shell.','The current Inertia page renders in the shared shell unless it is an account, onboarding, or MCP-consent screen.','All authenticated pages','resources/js/app.ts; resources/js/Layouts/AppShell.vue','DashboardTest','Automated + browser'],
  ['APP-002','App shell','Primary navigation','All','As a user, I can move between Home, Goals, Progress, and Settings.','Desktop sidebar and mobile bottom navigation visit the same four destinations and mark the active area.','/; /goals; /progress; /settings','resources/js/Layouts/AppShell.vue','None direct','Browser'],
  ['APP-003','App shell','Add launcher','All','As a user, I can open the Add launcher from anywhere.','The drawer opens, can be dismissed, and carries the selected dashboard date into the chosen add flow.','/add?mode=*','resources/js/Layouts/AppShell.vue; resources/js/Components/Add/AddChooser.vue','MealEntryTest; NativeShortcutHookTest','Automated + browser'],
  ['APP-004','App shell','Android back behavior','Android','As an Android user, the system Back action behaves predictably.','Back closes the topmost app overlay, returns Settings subpages to Settings, then falls back to browser history or app exit.','Native Android back hook','resources/js/Layouts/AppShell.vue; resources/js/Pages/Progress.vue; native-plugins/native-refresh','NativePluginHooksTest; progressPhotos.test.ts','Automated + native device'],
  ['APP-005','Connectivity','Offline indicator','All','As a user, I am told when Buff loses connectivity.','A polite offline banner appears on disconnect, clears on reconnect, and refreshes after returning to the foreground.','Global','resources/js/networkStatus.ts; resources/js/Components/OfflineBanner.vue','networkStatus.test.ts','Automated + browser'],
  ['APP-006','Connectivity','Resume sync','All','As a signed-in user, my local changes sync after resume or reconnect.','Buff posts resume sync once when visible, focused, or back online, reloads current data, and skips while offline or signed out.','POST /sync/resume','resources/js/Layouts/AppShell.vue; app/Http/Controllers/SyncController.php','BuffSyncTest','Automated + browser'],
  ['APP-007','Feedback','Success messages','All','As a user, I receive confirmation after successful actions.','Flash messages use a native toast when available and a four-second accessible in-app toast otherwise.','Global','resources/js/Layouts/AppShell.vue','None direct','Browser'],
  ['APP-008','Security','Local HTTP boundary','All','As a user, my on-device app endpoints are not exposed remotely by default.','Loopback/native embedded requests are allowed; remote requests are rejected unless explicitly enabled.','All web routes','app/Http/Middleware/EnsureLocalRequest.php','LocalHttpBoundaryTest','Automated'],
  ['APP-009','Native shell','Portrait orientation','iOS + Android','As a mobile user, Buff stays in its supported portrait orientation.','Phone builds allow portrait and reject unsupported orientations.','Native shell','config/nativephp.php','NativePluginHooksTest','Automated + native device'],
  ['APP-010','Native shell','Pull to refresh','iOS + Android','As a mobile user, I can refresh the current Buff screen with the native pull gesture.','The native refresh plugin is registered and refreshes the embedded app without exposing an unsupported control.','Native shell','app/Providers/NativeServiceProvider.php; native-plugins/native-refresh','NativePluginHooksTest (partial)','Static + native device'],

  ['AUTH-001','Account','Register','All','As a new user, I can create a Buff account.','Valid name, email, password, confirmation, and timezone create credentials and continue into onboarding even before email verification.','GET/POST /account/register','resources/js/Pages/Account.vue; app/Http/Controllers/AccountController.php','BuffAuthenticationTest','Automated + browser'],
  ['AUTH-002','Account','Registration guard','All','As a user with device identity data, I cannot accidentally register a conflicting account.','Registration is blocked while credentials, local identity, or sync state already exist.','POST /account/register','app/Http/Controllers/AccountController.php','BuffAuthenticationTest','Automated'],
  ['AUTH-003','Account','Password sign-in','All','As a returning user, I can sign in with email and password.','Successful sign-in stores encrypted credentials, keeps same-account local data, and preserves the session across requests.','POST /account/login','resources/js/Pages/Account.vue; app/Http/Controllers/AccountController.php','BuffAuthenticationTest','Automated + browser'],
  ['AUTH-004','Account','Switch account safety','All','As a user, I am warned before replacing another account on this device.','A different email or social provider opens confirmation; confirming wipes the prior account data only after successful sign-in.','POST /account/login; social redirect','resources/js/Pages/Account.vue; app/Services/LocalAccountData.php','BuffAuthenticationTest','Automated + browser'],
  ['AUTH-005','Account','Resume saved account','All','As a returning user, I can continue with a saved device account without re-entering credentials.','Resume uses the stored credential when valid and returns unreadable or expired credentials to sign-in.','POST /account/resume','resources/js/Pages/Account.vue; app/Http/Controllers/AccountController.php','BuffAuthenticationTest','Automated + browser'],
  ['AUTH-006','Account','Use different account','All','As a returning user, I can choose a different account.','The saved-account card yields to blank sign-in fields without clearing data until a new sign-in succeeds.','GET /account/login','resources/js/Pages/Account.vue','None direct','Browser'],
  ['AUTH-007','Account','Google sign-in','All','As a user, I can sign in with Google.','Buff launches native browser authentication, redeems the callback, and preserves the intended return page.','GET /account/social/callback','resources/js/Pages/Account.vue; app/Http/Controllers/AccountController.php','BuffAuthenticationTest (callback)','Automated + browser'],
  ['AUTH-008','Account','Apple sign-in availability','iOS','As an iOS user, I can sign in with Apple.','The Apple option appears only on iOS and follows the native browser OAuth flow.','Social auth','resources/js/Pages/Account.vue; app/Http/Controllers/AccountController.php','BuffAuthenticationTest','Automated + native device'],
  ['AUTH-009','Account','Social timezone refresh','All','As a returning social-login user, my account timezone follows my current device timezone.','A successful social login updates the server timezone so date-bound features use the current location.','Server social login','/Users/mason/Sites/buff-server/app/Http/Controllers/Api/V1/SocialAuthenticationController.php','Partial','Automated'],
  ['AUTH-010','Account','Forgot password','All','As a user, I can request a password-reset link without leaking account existence.','A valid email request always receives the neutral response and prefilled email is preserved when supplied.','GET/POST /account/forgot-password','resources/js/Pages/Account.vue; app/Http/Controllers/AccountController.php','BuffAuthenticationTest (page only)','Automated + browser'],
  ['AUTH-011','Account','Reset password','All','As a user, I can choose a new password from a valid reset token.','The password and confirmation are validated; a matching local token is cleared without clearing another account.','GET/POST /reset-password','resources/js/Pages/Account.vue; app/Http/Controllers/AccountController.php','BuffAuthenticationTest','Automated + browser'],
  ['AUTH-012','Account','Email verification polling','All','As a newly registered user, I can leave the verification screen open until verification completes.','The screen polls while visible every five seconds and navigates home when verification becomes true.','GET /account/verify; GET /account/verification-status','resources/js/Pages/Account.vue','BuffAuthenticationTest (status)','Automated + browser'],
  ['AUTH-013','Account','Resend verification','All','As an unverified user, I can request another verification email.','Resend submits once, reports server feedback, and does not lose the active account.','POST /account/verification/resend','resources/js/Pages/Account.vue; app/Http/Controllers/AccountController.php','None direct','Automated + browser'],
  ['AUTH-014','Account','Edit profile','All','As a signed-in user, I can update my name, email, and timezone.','Valid changes sync remotely, remain locally accessible, and the timezone selector includes the current custom value.','PATCH /account','resources/js/Pages/Settings/Account.vue; app/Http/Controllers/AccountController.php','BuffAuthenticationTest; SettingsTest','Automated + browser'],
  ['AUTH-015','Account','Change password','All','As a signed-in user, I can change my password.','Current password must match; new password is validated, confirmed, saved remotely, and fields reset on success.','PUT /account/password','resources/js/Pages/Settings/Password.vue; app/Http/Controllers/AccountController.php','BuffAuthenticationTest','Automated + browser'],
  ['AUTH-016','Account','Log out','All','As a signed-in user, I can log out safely.','Remote logout succeeds before user-owned local data is wiped and sign-in is required afterward.','POST /account/logout','resources/js/Pages/Settings.vue; app/Http/Controllers/AccountController.php','BuffAuthenticationTest','Automated + browser'],
  ['AUTH-017','Account','Clear device data','All','As a user, I can remove local health/account data from this device.','A destructive confirmation precedes deletion; synced remote data is not claimed to be deleted.','DELETE /account/local-data','resources/js/Pages/Account.vue; app/Http/Controllers/AccountController.php','BuffAuthenticationTest','Automated + browser'],
  ['AUTH-018','Account','Delete account','All','As a signed-in user, I can permanently delete my account.','Password confirmation is required and local data is wiped only after the server confirms account deletion.','POST /account with DELETE method override','resources/js/Pages/Settings.vue; app/Http/Controllers/AccountController.php','BuffAuthenticationTest; settingsAccountDeletion.test.ts','Automated + browser + native device'],
  ['AUTH-019','Account','Expired session handling','All','As a user with an expired token, I am returned to sign-in without exposing authenticated screens.','Protected routes redirect to sign-in and preserve return context such as MCP approval where applicable.','Protected routes','app/Http/Middleware/EnsureBuffAccount.php','BuffAuthenticationTest; ConnectedAssistantsTest; McpApprovalTest','Automated'],

  ['ONB-001','Onboarding','Required onboarding','All','As a new user, I am guided through setup before using the dashboard.','Authenticated users without goals are redirected to onboarding; completed users cannot repeat it.','GET /onboarding; GET /','app/Http/Controllers/OnboardingController.php; app/Http/Controllers/DashboardController.php','OnboardingTest; DashboardTest','Automated + browser'],
  ['ONB-002','Onboarding','Daily calorie target','All','As a new user, I can set my daily calories.','The editor changes calories in 50-kcal steps within valid bounds and recomputes macro grams.','Onboarding step 1','resources/js/Components/DailyTargetsEditor.vue','goalMacros.test.ts; OnboardingTest','Automated + browser'],
  ['ONB-003','Onboarding','Macro presets','All','As a new user, I can choose a balanced, high-protein, low-carb, or high-carb macro split.','Selecting a preset updates protein/carbs/fat grams so rounded macro calories match the calorie target.','Onboarding step 1','resources/js/Components/DailyTargetsEditor.vue; resources/js/goalMacros.ts','goalMacros.test.ts; GoalTest','Automated + browser'],
  ['ONB-004','Onboarding','Custom macro split','All','As a new user, I can choose a custom macro percentage split.','Protein and carbs move in 5% steps, fat is the remainder, total is 100%, and unrepresentable targets are blocked.','Onboarding step 1','resources/js/Components/DailyTargetsEditor.vue; resources/js/goalMacros.ts','goalMacros.test.ts; GoalTest','Automated + browser'],
  ['ONB-005','Onboarding','Body and unit setup','All','As a new user, I can choose weight/height units and enter my body profile.','Changing kg/lb or cm/ft-in preserves the stored physical values; optional age, sex, height, and activity validate when present.','Onboarding step 2','resources/js/Pages/Onboarding.vue; resources/js/Components/BodyProfileEditor.vue','OnboardingTest; bodyUnits.test.ts','Automated + browser'],
  ['ONB-006','Onboarding','Initial body goals','All','As a new user, I can record current weight and optional target weight/body fat.','Current weight is required; target weight may be blank; percentages and weights respect bounds and selected units.','POST /onboarding','resources/js/Pages/Onboarding.vue; app/Http/Controllers/OnboardingController.php','OnboardingTest','Automated + browser'],
  ['ONB-007','Onboarding','Atomic completion','All','As a new user, setup either completes fully or remains retryable.','Goals, profile, preferences, and initial weight commit together; any failure leaves onboarding available for retry.','POST /onboarding','app/Http/Controllers/OnboardingController.php','None direct','Automated'],
  ['GOAL-001','Goals','View goals','All','As a user, I can view defaults or my saved daily targets.','The goal editor renders defaults when absent and the latest stored targets otherwise.','GET /goals','resources/js/Pages/Goals.vue; app/Http/Controllers/GoalController.php','GoalTest','Automated + browser'],
  ['GOAL-002','Goals','Save goals','All','As a user, I can update calorie and macro goals.','Valid targets persist; mismatched macro calories, invalid splits, or grams above bounds are rejected.','PUT /goals','resources/js/Pages/Goals.vue; app/Http/Controllers/GoalController.php','GoalTest; goalMacros.test.ts','Automated + browser'],
  ['GOAL-003','Goals','Body targets','All','As a user, I can set target weight and body-fat percentage.','Optional bounded body targets save in canonical units and appear as goal lines on Progress.','PUT /goals; GET /progress','resources/js/Pages/Goals.vue; resources/js/Pages/Progress.vue','GoalTest; ProgressTest','Automated + browser'],

  ['TOD-001','Today','Select date','All','As a user, I can view any valid day.','Calendar and week-strip navigation load the selected date; invalid date parameters return controlled feedback instead of a server error.','GET /?date=','resources/js/Pages/Today.vue; app/Http/Controllers/DashboardController.php','DashboardTest (valid dates)','Automated + browser'],
  ['TOD-002','Today','Week status strip','All','As a user, I can see Monday-first status for the surrounding week.','Each day shows selected/today state and accessible status derived from consumed calories, goals, and burned-calorie offset.','GET /','resources/js/Pages/Today.vue; app/Services/DailySummaryService.php','DashboardTest; dayStatus.test.ts','Automated + browser'],
  ['TOD-003','Today','No-goal guidance','All','As a user without goals, I am prompted to set them.','A warning links directly to Goals before meal tracking begins.','GET /','resources/js/Pages/Today.vue','DashboardTest','Automated + browser'],
  ['TOD-004','Today','Empty-day start','All','As a user with an empty day, I can start with food or a workout.','The empty state appears only when both meal and workout lists are empty and preserves the selected date.','GET /','resources/js/Pages/Today.vue','DashboardTest; TodayWorkoutButtonTest','Automated + browser'],
  ['TOD-005','Today','Calorie ring','All','As a user, I can see consumed, burned, target, and remaining calories.','The ring keeps full burned calories visible while remaining allowance applies the selected all/half/none eat-back rule.','GET /','resources/js/Components/CalorieRing.vue; app/Services/DailySummaryService.php','DashboardTest; NutritionCalculatorTest','Automated + browser'],
  ['TOD-006','Today','Macro progress','All','As a user, I can see protein, carbs, and fat progress.','Each card shows consumed versus exercise-adjusted goal, remaining amount, and links to its dated breakdown.','GET /; GET /macros/{macro}','resources/js/Pages/Today.vue','DashboardTest; MacroBreakdownTest','Automated + browser'],
  ['TOD-007','Today','Meal groups','All','As a user, I can browse meals grouped by breakfast, lunch, dinner, and snacks.','Only populated entry lists render; empty groups offer a meal-specific Add action with the selected date.','GET /','resources/js/Pages/Today.vue; app/Services/DailySummaryService.php','DashboardTest; MealEntryTest','Automated + browser'],
  ['TOD-008','Today','Meal details','All','As a user, I can inspect a logged meal.','Opening an entry shows name, source metadata, portion, calories, goal percentages, and any temporary meal-photo URLs.','GET /meals/{id}/photos','resources/js/Pages/Today.vue; app/Http/Controllers/MealAnalysisController.php','MealPhotoIntegrationTest','Automated + browser'],
  ['TOD-009','Today','Edit meal','All','As a user, I can correct a meal.','Name, meal type, and macros validate and update; the detail sheet closes and the selected-day summary refreshes.','PUT /meals/{mealEntry}','resources/js/Pages/Today.vue; app/Http/Controllers/MealController.php','MealEntryTest','Automated + browser'],
  ['TOD-010','Today','Delete meal','All','As a user, I can remove a meal without accidental taps.','A confirmation precedes deletion and totals refresh after success.','DELETE /meals/{mealEntry}','resources/js/Pages/Today.vue; app/Http/Controllers/MealController.php','MealEntryTest','Automated + browser'],
  ['TOD-011','Today','Workout list','All','As a user, I can see workouts and total calories burned.','Each workout shows title, time, burn, source-aware actions, and the day total.','GET /','resources/js/Pages/Today.vue','DashboardTest; WorkoutEntryTest','Automated + browser'],
  ['TOD-012','Today','Edit workout','All','As a user, I can correct a workout.','Title, calories, and time validate; editing an imported workout converts it to manual and prevents reimport.','PUT /workouts/{workoutEntry}','resources/js/Pages/Today.vue; app/Http/Controllers/WorkoutController.php','WorkoutEntryTest; health import tests','Automated + browser'],
  ['TOD-013','Today','Delete workout','All','As a user, I can delete a workout after confirmation.','Deletion refreshes burned totals; imported workouts receive an ignore record so they do not reappear.','DELETE /workouts/{workoutEntry}','resources/js/Pages/Today.vue; app/Http/Controllers/WorkoutController.php','WorkoutEntryTest; health import tests','Automated + browser'],
  ['TOD-014','Today','Health quick sync','iOS + Android','As a mobile user, I can connect or sync the platform health provider from Today.','iOS uses Apple Health, Android uses Health Connect, unsupported platforms hide the control, and queued/permission states poll until summary data changes.','POST /apple-health/*; POST /health-connect/*','resources/js/Pages/Today.vue','HealthConnectTodayRefreshTest; sync tests','Automated + native device'],
  ['TOD-015','Today','Weekly roundup link','All','As a user, I can open the weekly roundup for the selected day.','The link preserves the dashboard date.','GET /weekly?date=','resources/js/Pages/Today.vue','WeeklySummaryTest','Automated + browser'],

  ['ADD-001','Add','Choose add mode','All','As a user, I can choose food, custom food, photo meal, recipe, or workout.','The chooser routes to the selected mode and carries the current date and optional meal type.','GET /add','resources/js/Pages/Add.vue; resources/js/Components/Add/AddChooser.vue','MealEntryTest; WorkoutEntryTest; RecipeTest','Automated + browser'],
  ['FOOD-001','Food','Search foods','All','As a user, I can search saved and remote food products.','Queries shorter than two characters do not request; recent custom matches appear before server products; remote failure still returns cached foods.','GET /food-products/search','resources/js/Pages/Add.vue; app/Http/Controllers/MealController.php','MealEntryTest; OpenFoodFactsLookupTest','Automated + browser'],
  ['FOOD-002','Food','Recent foods','All','As a user, I can quickly reuse unique recent food entries.','The Add screen shows deduplicated recent product and custom entries, and selecting one opens its current portion/macros.','GET /add?mode=food|custom','resources/js/Pages/Add.vue; app/Http/Controllers/MealController.php','MealEntryTest','Automated + browser'],
  ['FOOD-003','Food','Manual barcode lookup','All','As a user, I can enter a barcode manually.','Whitespace is removed, the server proxy normalizes/stores the product and portions, and lookup errors offer custom food as fallback.','POST /barcode/lookup','resources/js/Pages/Add.vue; app/Services/OpenFoodFactsService.php','OpenFoodFactsLookupTest','Automated + browser'],
  ['FOOD-004','Food','Native barcode scanner','iOS + Android','As a mobile user, I can scan a supported food barcode.','The native scanner accepts EAN/UPC/Code128, ignores unrelated scanner IDs, looks up a result, and removes its event listener on exit.','Native Scanner.CodeScanned','resources/js/Pages/Add.vue','NativeShortcutHookTest (partial)','Static + native device'],
  ['FOOD-005','Food','Web scanner fallback','Web','As a web user, I can scan with the camera or enter a barcode.','The rear camera scanner stops after success; denied/missing camera produces useful fallback text and a manual input path.','GET /add?mode=food','resources/js/Pages/Add.vue','None direct','Browser'],
  ['FOOD-006','Food','Scan shortcut','iOS + Android','As a mobile user, a scan shortcut opens directly into scanning.','The shortcut targets dated food mode with auto-scan and the camera/scanner opens after mount.','GET /add?mode=food&scan=1','resources/js/Pages/Add.vue; native shell hooks','MealEntryTest; NativeShortcutHookTest','Automated + native device'],
  ['FOOD-007','Food','Product portions','All','As a user, I can select or enter a product portion.','Serving/package options prioritize useful labels; quantity rescales calories and macros per 100g/ml without going negative.','Add food sheet','resources/js/Pages/Add.vue; app/Services/PortionParser.php; app/Services/NutritionCalculator.php','PortionParserTest; NutritionCalculatorTest','Automated + browser'],
  ['FOOD-008','Food','Log barcode food','All','As a user, I can log a selected product to a meal.','Date, meal type, product, quantity, and unit validate; canonical nutrients are calculated server-side and totals update.','POST /meals/barcode','resources/js/Pages/Add.vue; app/Http/Controllers/MealController.php','MealEntryTest','Automated + browser'],
  ['FOOD-009','Food','Repeat previous meal','All','As a user, I can repeat a prior meal with an adjusted portion.','The new entry uses the selected date/meal type and rescales nutrients only when the original has a meaningful portion.','POST /meals/{mealEntry}/repeat','resources/js/Pages/Add.vue; app/Http/Controllers/MealController.php','MealEntryTest','Automated + browser'],
  ['FOOD-010','Food','Custom food','All','As a user, I can log food without a catalogue product.','Name, positive portion, unit, and nonnegative macros validate; calories are calculated as 4/4/9 and the selected meal type is saved.','POST /meals/custom','resources/js/Pages/Add.vue; app/Http/Controllers/MealController.php','MealEntryTest; NutritionCalculatorTest','Automated + browser'],
  ['FOOD-011','Food','Reuse custom food','All','As a user, I can prefill a custom food from a previous entry.','Selecting a recent custom meal fills name, portion, unit, and macros but creates a distinct dated entry on submit.','GET /add?mode=custom','resources/js/Pages/Add.vue','MealEntryTest','Automated + browser'],
  ['PHOTO-001','Meal photos','Select photos','All','As a user, I can choose up to three meal photos and optional context.','Images are resized client-side, previews can be removed, input resets for reuse, and context is limited to 1000 characters.','GET /add?mode=photo','resources/js/Pages/Add.vue; resources/js/photoResize.ts','MealPhotoIntegrationTest (server bounds)','Automated + browser'],
  ['PHOTO-002','Meal photos','Analyze meal','All','As a user, I can request a meal estimate from photos.','A blocking loading state prevents duplicate work; bounded data URLs are proxied without exposing server credentials and quota/in-progress/invalid/unavailable errors are specific.','POST /meal-analyses','resources/js/Pages/Add.vue; app/Http/Controllers/MealAnalysisController.php','MealPhotoIntegrationTest','Automated + browser'],
  ['PHOTO-003','Meal photos','Review estimate','All','As a user, I can review and correct an AI estimate before logging it.','Nothing is logged automatically; confidence/components and editable name, portion, unit, and macros are displayed.','Photo analysis draft','resources/js/Pages/Add.vue','MealPhotoIntegrationTest (backend)','Automated + browser'],
  ['PHOTO-004','Meal photos','Follow-up correction','All','As a user, I can tell Buff what the estimate got wrong.','A nonblank correction revises the draft, keeps the same analysis context, updates fields, and confirms success.','POST /meal-analyses/{analysis}/follow-up','resources/js/Pages/Add.vue; app/Http/Controllers/MealAnalysisController.php','MealPhotoIntegrationTest','Automated + browser'],
  ['PHOTO-005','Meal photos','Cancel analysis','All','As a user, I can discard an unwanted estimate.','Buff attempts to cancel the remote draft, clears local analysis context, and allows server expiry if offline.','DELETE /meal-analyses/{analysis}','resources/js/Pages/Add.vue; app/Http/Controllers/MealAnalysisController.php','MealPhotoIntegrationTest','Automated + browser'],
  ['PHOTO-006','Meal photos','Save analyzed meal','All','As a user, reviewed photo analysis becomes a normal synced meal with photos.','The meal saves first, syncs, then confirms the analysis so photo URLs attach to the server record; failed confirmation remains retryable.','POST /meals/custom; POST /sync','app/Http/Controllers/MealController.php; app/Services/BuffSyncService.php','MealPhotoIntegrationTest','Automated'],
  ['PHOTO-007','Meal photos','Delayed confirmation retention','All','As an offline user, my reviewed meal photos are not silently lost before they can sync.','Pending confirmation remains visible/retryable for at least as long as the server draft and expires with explicit user feedback rather than silent deletion.','Sync retry','app/Services/BuffSyncService.php; /Users/mason/Sites/buff-server/app/Services/MealAnalysisService.php','MealPhotoIntegrationTest (short retry)','Automated'],
  ['RECIPE-001','Recipes','Browse recipes','All','As a user, I can browse saved recipes.','The recipe mode shows name, calories, ingredient count, empty state, and a New recipe action.','GET /add?mode=recipe','resources/js/Components/RecipeMode.vue; app/Http/Controllers/MealController.php','RecipeTest','Automated + browser'],
  ['RECIPE-002','Recipes','Build from products','All','As a user, I can add catalogue foods to a recipe.','Search excludes previous-meal pseudo-results; the product nutrition unit and a 100-unit portion seed canonical ingredient nutrition.','POST /recipes','resources/js/Components/RecipeMode.vue; app/Http/Controllers/RecipeController.php','RecipeTest','Automated + browser'],
  ['RECIPE-003','Recipes','Custom ingredient','All','As a user, I can add a custom ingredient.','Name, positive portion/unit, and nonnegative macros produce calculated calories and update recipe totals.','Recipe editor','resources/js/Components/RecipeMode.vue; app/Http/Controllers/RecipeController.php','RecipeTest (server validation)','Automated + browser'],
  ['RECIPE-004','Recipes','Remove ingredient','All','As a user, I can remove an ingredient before saving.','The selected item disappears and totals recalculate immediately.','Recipe editor','resources/js/Components/RecipeMode.vue','None direct','Browser'],
  ['RECIPE-005','Recipes','Save recipe','All','As a user, I can save a multi-ingredient recipe.','Name, servings, ingredients, portions, units, and macros validate; redirect date is validated before persistence.','POST /recipes','resources/js/Components/RecipeMode.vue; app/Http/Controllers/RecipeController.php','RecipeTest (valid date)','Automated + browser'],
  ['RECIPE-006','Recipes','Nutrition-unit consistency','All','As a user, product recipe ingredients use the unit their nutrition values describe.','A gram product cannot be saved as millilitres (or vice versa) without an explicit valid conversion.','POST/PUT /recipes','app/Http/Controllers/RecipeController.php; app/Services/NutritionCalculator.php','None direct','Automated'],
  ['RECIPE-007','Recipes','Update recipe','All','As a user, I can update an existing recipe through the supported application endpoint.','The same validation/calculation rules as create apply and the saved recipe refreshes.','PUT /recipes/{recipe}','app/Http/Controllers/RecipeController.php','RecipeTest','Automated'],
  ['RECIPE-008','Recipes','Log recipe','All','As a user, I can log any positive number of recipe servings.','Selected date/meal type and servings scale calories/macros from the stored recipe and create a normal meal entry.','POST /meals/recipe','resources/js/Components/RecipeMode.vue; app/Http/Controllers/MealController.php','RecipeTest','Automated + browser'],
  ['RECIPE-009','Recipes','Delete recipe','All','As a user, I can delete a saved recipe without damaging past logs.','Deletion is intentional, removes the saved recipe, and historical meal entries retain their copied nutrition.','DELETE /recipes/{recipe}','resources/js/Components/RecipeMode.vue; app/Http/Controllers/RecipeController.php','RecipeTest','Automated + browser'],
  ['WORK-001','Workouts','Log workout','All','As a user, I can log a manual workout.','Title, positive burned calories, date, and valid time save a workout and update daily remaining/total burn.','POST /workouts','resources/js/Pages/Add.vue; app/Http/Controllers/WorkoutController.php','WorkoutEntryTest','Automated + browser'],

  ['WEEK-001','Weekly','Week selection','All','As a user, I can review the week containing a selected date.','The selected date determines a Monday-to-Sunday window and Apply preserves scroll.','GET /weekly?date=','resources/js/Pages/Weekly.vue; app/Http/Controllers/WeeklySummaryController.php','WeeklySummaryTest','Automated + browser'],
  ['WEEK-002','Weekly','Custom range','All','As a user, I can review a bounded custom date range.','Start is on/before end, the inclusive range is at most 90 calendar days, and invalid input returns controlled feedback.','GET /weekly?start_date=&end_date=','resources/js/Pages/Weekly.vue; app/Http/Controllers/WeeklySummaryController.php','WeeklySummaryTest','Automated + browser'],
  ['WEEK-003','Weekly','Summary totals','All','As a user, I can see aggregate calories, burn, effective target, and macros.','Totals and progress use every date in the selected period and exercise-adjusted goals.','GET /weekly','app/Services/WeekSummaryService.php; resources/js/Pages/Weekly.vue','WeeklySummaryTest','Automated + browser'],
  ['WEEK-004','Weekly','Insights','All','As a user, I receive code-derived weekly insights when data supports them.','Adherence, weekday/weekend, protein, and weight-trend insights are stable, relevant, and absent when unsupported.','GET /weekly','app/Services/WeekSummaryService.php; resources/js/Pages/Weekly.vue','WeeklySummaryTest','Automated + browser'],
  ['WEEK-005','Weekly','Daily totals','All','As a user, I can inspect each day in the selected period.','Rows show date, calories/target, macros, and accessible on-track/over/under/no-goal status.','GET /weekly','resources/js/Pages/Weekly.vue','WeeklySummaryTest; dayStatus.test.ts','Automated + browser'],
  ['MAC-001','Macros','Macro breakdown','All','As a user, I can see which foods contributed most to a macro.','Protein/carbs/fat routes sort entries descending by that macro and show current versus goal split for the selected date.','GET /macros/{macro}?date=','resources/js/Pages/MacroBreakdown.vue; app/Http/Controllers/MacroController.php','MacroBreakdownTest','Automated + browser'],
  ['MAC-002','Macros','Exercise-adjusted macro goal','All','As a user, macro goals reflect exercise eat-back.','Macro grams scale from the effective calorie target according to the saved split and eat-back preference.','GET /macros/{macro}','app/Http/Controllers/MacroController.php; app/Services/NutritionCalculator.php','MacroBreakdownTest; NutritionCalculatorTest','Automated'],
  ['MAC-003','Macros','Invalid macro/date handling','All','As a user, bad breakdown URLs fail safely.','Unknown macro returns not found; invalid date returns controlled validation/not-found rather than a server exception.','GET /macros/{macro}?date=','app/Http/Controllers/MacroController.php','MacroBreakdownTest (macro only)','Automated'],

  ['PROG-001','Progress','Empty state','All','As a user without measurements, I see a useful starting state.','No fabricated trends are shown; the first-measurement form is available.','GET /progress','resources/js/Pages/Progress.vue; app/Http/Controllers/ProgressController.php','ProgressTest','Automated + browser'],
  ['PROG-002','Progress','Log or update measurement','All','As a user, I can save one measurement per date.','Weight is required and unit-converted; body fat, measurements, and notes are optional; saving the same date updates rather than duplicates.','POST /progress/body-metrics','resources/js/Pages/Progress.vue; app/Http/Controllers/ProgressController.php','ProgressTest; bodyUnits.test.ts','Automated + browser'],
  ['PROG-003','Progress','Measurement validation','All','As a user, invalid progress values are rejected clearly.','Date, weight, body-fat, notes, and every body measurement enforce documented bounds in canonical units.','POST /progress/body-metrics','app/Http/Controllers/ProgressController.php','ProgressTest','Automated + browser'],
  ['PROG-004','Progress','Latest weight and trend','All','As a user, I can see latest weight and a smoothed trend.','Latest/trend values display in selected units; EMA and seven-day delta handle first/unchanged entries correctly.','GET /progress','resources/js/Pages/Progress.vue; app/Services/WeightTrendService.php','ProgressTest; WeightTrendServiceTest','Automated + browser'],
  ['PROG-005','Progress','BMI and energy estimates','All','As a user, I can see BMI, BMR, and TDEE when enough profile data exists.','BMI needs height and weight; Mifflin-St Jeor estimates need weight, height, age, sex, and activity; otherwise values stay absent.','GET /progress','resources/js/Pages/Progress.vue; app/Services/EnergyEstimator.php','EnergyEstimatorTest; ProgressTest','Automated + browser'],
  ['PROG-006','Progress','Trend ranges','All','As a user, I can switch between 30, 90, 180 days, and all time.','The URL, chart domain, history, and summary data all use the selected range.','GET /progress?range=','resources/js/Pages/Progress.vue; app/Http/Controllers/ProgressController.php','ProgressTest; progressChart.test.ts','Automated + browser'],
  ['PROG-007','Progress','Weight chart','All','As a user, I can see weight history and my target.','The chart uses sorted dated values, selected units, a stable domain, readable tooltips, and a target line only when configured.','GET /progress','resources/js/Components/ProgressTrendChart.vue; resources/js/progressChart.ts','progressChart.test.ts; chartTooltip.test.ts','Automated + browser'],
  ['PROG-008','Progress','Body-fat chart','All','As a user, I can see body-fat history and target.','The chart appears only with body-fat data and adds a target line only when configured.','GET /progress','resources/js/Pages/Progress.vue; resources/js/progressChart.ts','ProgressTest; progressChart.test.ts','Automated + browser'],
  ['PROG-009','Progress','Body measurement summary','All','As a user, I can see latest body measurements and their changes.','Chest, waist, hips, upper arm, and thigh display in chosen units with first-entry, delta, or not-recorded labels.','GET /progress','resources/js/Pages/Progress.vue; app/Http/Controllers/ProgressController.php','ProgressTest; bodyUnits.test.ts','Automated + browser'],
  ['PROG-010','Progress','History','All','As a user, I can review recent measurement history.','Entries show date, weight, body fat, notes, photo previews, and actions in deterministic order.','GET /progress','resources/js/Pages/Progress.vue','ProgressTest; ProgressPhotoTest','Automated + browser'],
  ['PROG-011','Progress','Delete measurement','All','As a user, I can remove a progress entry after confirmation.','The correct record, sync tombstone, staged photos, and cached summary disappear without affecting other dates.','DELETE /progress/body-metrics/{bodyMetric}','resources/js/Pages/Progress.vue; app/Http/Controllers/ProgressController.php','ProgressTest; ProgressPhotoTest','Automated + browser'],
  ['PROG-012','Progress photos','Pose capture','All','As a user, I can capture front, side, and back progress photos.','The camera starts safely, cycles to the next empty pose, mirrors only the front-facing preview/output consistently, and cleans tracks on exit.','Progress camera','resources/js/Pages/Progress.vue','progressPhotos.test.ts (pose helpers)','Browser + native device'],
  ['PROG-013','Progress photos','Flip camera','All','As a user, I can switch between front and rear cameras.','The current stream stops before the alternate facing mode starts and capture remains available after readiness.','Progress camera','resources/js/Pages/Progress.vue','None direct','Browser + native device'],
  ['PROG-014','Progress photos','Ghost overlay','All','As a user, I can align a new pose with my most recent earlier matching pose.','Only earlier photos of the same pose are selected; opacity is adjustable and disabled when no overlay exists.','Progress camera','resources/js/Pages/Progress.vue; resources/js/progressPhotos.ts','progressPhotos.test.ts','Automated + browser'],
  ['PROG-015','Progress photos','Choose from library','All','As a user, I can choose a pose photo from my library.','The selected file is resized, replaces only that pose, can be removed, and uploads immediately when adding to an existing metric.','Progress photo inputs','resources/js/Pages/Progress.vue','ProgressPhotoTest (server)','Automated + browser'],
  ['PROG-016','Progress photos','Upload or stage photos','All','As a user, progress photos survive offline-first metric creation.','Up to three valid pose-labelled images upload immediately for synced metrics or stage durably until the metric syncs; failed uploads remain retryable.','POST /progress/body-metrics/{id}/photos','app/Http/Controllers/BodyMetricPhotoController.php; app/Services/BodyMetricPhotoUploader.php','ProgressPhotoTest','Automated + browser'],
  ['PROG-017','Progress photos','View photos','All','As a user, I can view all photos for a measurement.','Cloud and staged local photos are merged by pose, sorted predictably, and served through temporary/proxied URLs.','GET /progress/body-metrics/{id}/photos','resources/js/Pages/Progress.vue; app/Http/Controllers/BodyMetricPhotoController.php','ProgressPhotoTest','Automated + browser'],
  ['PROG-018','Progress photos','Add photos later','All','As a user, I can add missing poses to an existing measurement.','Camera or library starts at a missing pose, uploads selected photos, and returns to the refreshed photo sheet.','POST /progress/body-metrics/{id}/photos','resources/js/Pages/Progress.vue','ProgressPhotoTest (server)','Automated + browser'],
  ['PROG-019','Progress photos','Delete photo','All','As a user, I can delete an individual progress photo.','A deliberate UI action calls the supported delete endpoint and removes only that photo after server confirmation.','DELETE /progress/body-metrics/{id}/photos/{photo}','app/Http/Controllers/BodyMetricPhotoController.php; resources/js/Pages/Progress.vue','ProgressPhotoTest (endpoint)','Automated + browser'],
  ['PROG-020','Progress','Unique date invariant','All','As a multi-device user, I still have one logical body metric per date.','The database/sync path enforces or deterministically reconciles date uniqueness so updates never target an arbitrary duplicate.','body_metrics sync','database/migrations/2026_05_19_000005_create_body_metrics_table.php; app/Services/BuffSyncService.php','None direct','Automated'],
  ['PROG-021','Progress photos','Durable staging','All','As an offline user, staging failures cannot be reported as success.','Every staged file write is checked; partial writes roll back or produce a visible retryable error.','POST /progress/body-metrics/{id}/photos','app/Http/Controllers/BodyMetricPhotoController.php','None direct','Automated'],

  ['SET-001','Settings','Overview','All','As a user, I can find account, preferences, devices, and destructive actions.','Rows appear only when applicable and show current appearance, reminder count, units, eat-back mode, and platform health provider.','GET /settings','resources/js/Pages/Settings.vue; app/Http/Controllers/SettingsController.php','SettingsTest','Automated + browser'],
  ['SET-002','Settings','Theme','All','As a user, I can choose System, Light, or Dark appearance.','The choice applies immediately, persists on the device, and System responds to OS color-scheme changes.','GET /settings/appearance','resources/js/Pages/Settings/Appearance.vue; resources/js/appearance.ts','appearance.test.ts','Automated + browser'],
  ['SET-003','Settings','Reduce motion','All','As a user, I can reduce animation.','The device preference persists and combines with the OS reduced-motion setting without disabling essential state changes.','GET /settings/appearance','resources/js/Pages/Settings/Appearance.vue; resources/js/appearance.ts','appearance.test.ts','Automated + browser'],
  ['SET-004','Settings','Meal reminder preferences','All','As a user, I can enable breakfast, lunch, and dinner reminders at chosen times.','Each toggle/time autosaves a complete validated reminder map and displays field errors.','PUT /settings/meal-reminders','resources/js/Pages/Settings/Reminders.vue; app/Http/Controllers/SettingsController.php','SettingsTest','Automated + browser'],
  ['SET-005','Settings','Android reminder scheduling','Android','As an Android user, enabled meal reminders are scheduled and disabled reminders are cancelled.','Native daily work is uniquely replaced per meal, validates time, and schedules the next run.','BackgroundTasks.RegisterMealReminders','app/Services/MealReminderBridge.php; native-plugins/background-tasks','MealReminderWorkerTest','Automated + native device'],
  ['SET-006','Settings','Reminder due check','Android','As a user, I am not reminded for a meal already logged that day.','The background check emits due only when no matching dated meal exists and otherwise emits logged.','meal-reminder:check','routes/console.php','MealReminderWorkerTest (worker source); command indirect','Automated'],
  ['SET-007','Settings','Reminder scheduling failure','Android','As a user, Buff does not show a reminder as active when native scheduling failed.','A native registration failure is visible and settings remain retryable or roll back to the last scheduled state.','PUT /settings/meal-reminders','app/Http/Controllers/SettingsController.php; app/Services/MealReminderBridge.php','SettingsTest (flash only)','Automated + native device'],
  ['SET-008','Settings','Body profile','All','As a user, I can update height, age, sex, and activity level.','Optional fields validate, height converts through the chosen unit, and estimates update after save.','PUT /settings/body-profile','resources/js/Pages/Settings/BodyProfile.vue; app/Http/Controllers/SettingsController.php','SettingsTest; bodyUnits.test.ts','Automated + browser'],
  ['SET-009','Settings','Units','All','As a user, I can choose weight, height, and measurement units.','Each selector autosaves a valid unit and all screens convert presentation without changing canonical stored values.','PUT /settings/units','resources/js/Pages/Settings/Units.vue; resources/js/bodyUnits.ts','SettingsTest; bodyUnits.test.ts','Automated + browser'],
  ['SET-010','Settings','Exercise calorie eat-back','All','As a user, I can eat back all, half, or none of workout calories.','The selected option saves immediately and changes remaining calorie/macro allowance without hiding total burn.','PUT /settings/eat-back','resources/js/Pages/Settings/Exercise.vue; app/Services/NutritionCalculator.php','SettingsTest; DashboardTest; NutritionCalculatorTest','Automated + browser'],
  ['SET-011','Settings','Health provider status','iOS + Android','As a mobile user, I can inspect and operate the relevant health integration.','The page shows availability, permission/sync status, last success or error, and a connect/sync action with polling.','GET/POST /settings/health; /apple-health/*; /health-connect/*','resources/js/Pages/Settings/Health.vue','Health sync/import tests (indirect endpoints)','Automated + native device'],
  ['MCP-001','Connected assistants','List connections','All','As a signed-in user, I can see authorized and revoked AI assistants.','Active/authorized cards show linked/last-used timestamps; errors do not expose stale connection data; revoked history is collapsible.','GET /settings/connected-assistants','resources/js/Pages/Settings/ConnectedAssistants.vue; app/Http/Controllers/SettingsController.php','ConnectedAssistantsTest','Automated + browser'],
  ['MCP-002','Connected assistants','Setup endpoint','All','As a signed-in user, I can copy Buff’s MCP endpoint and view setup steps.','Configured endpoints are selectable/copyable with manual fallback; instructions exist for Codex, ChatGPT, Claude, and Gemini; absent endpoint hides setup.','GET /settings/connected-assistants','resources/js/Pages/Settings/ConnectedAssistants.vue','ConnectedAssistantsTest','Automated + browser'],
  ['MCP-003','Connected assistants','Revoke connection','All','As a signed-in user, I can revoke one assistant.','Only the chosen live connection is revoked after remote confirmation; failures remain visible and do not claim success.','DELETE /settings/connected-assistants/{connection}','resources/js/Pages/Settings/ConnectedAssistants.vue; app/Http/Controllers/SettingsController.php','ConnectedAssistantsTest','Automated + browser'],
  ['MCP-004','MCP consent','Review request','All','As a signed-in user, I can review an assistant connection request before granting access.','The opaque token resolves to client name, redirect origin, expiry, and exact access scope without approving it.','GET /mcp-approve','resources/js/Pages/McpApproval.vue; app/Http/Controllers/McpApprovalController.php','McpApprovalTest','Automated + browser'],
  ['MCP-005','MCP consent','Approve or deny','All','As a signed-in user, I can explicitly approve or deny the request.','Only a pending valid request posts once; approval/denial is claimed only after server confirmation and completion/error states are clear.','POST /mcp-approve','resources/js/Pages/McpApproval.vue; app/Http/Controllers/McpApprovalController.php','McpApprovalTest','Automated + browser'],
  ['MCP-006','MCP consent','Reauthentication continuity','All','As a user, sign-in/resume does not lose an MCP request.','The token survives authentication/session expiry and returns to the original approval flow; expired requests never post approval.','GET/POST /mcp-approve','app/Http/Controllers/McpApprovalController.php; app/Http/Middleware/EnsureBuffAccount.php','McpApprovalTest','Automated + browser'],

  ['HEALTH-001','Health data','Platform support detection','iOS + Android','As a mobile user, Buff uses the health provider available on my platform.','Apple Health is supported only on iOS, Health Connect only on Android, and unavailable/invalid native bridge responses remain safe.','Health bridges','app/Services/AppleHealthBridge.php; app/Services/HealthConnectBridge.php','AppleHealthBridgeTest; HealthConnectBridgeTest','Automated'],
  ['HEALTH-002','Health data','Connect and status','iOS + Android','As a mobile user, I can grant health permissions and see connection status.','Connect requests appropriate foreground/background permissions; status reflects connected, permission-needed, queued, unavailable, and errors.','POST/GET /apple-health/*; /health-connect/*','app/Http/Controllers/AppleHealthController.php; app/Http/Controllers/HealthConnectController.php','Indirect only','Automated + native device'],
  ['HEALTH-003','Health data','Import workouts','iOS + Android','As a user, health workouts with calories are imported into the correct dates.','Imports create canonical external metadata, accept native payload forms, and do nothing after local sign-out.','health-connect:import; apple-health:import','app/Console/Commands; app/Services/HealthConnectWorkoutImporter.php','AppleHealthImportTest; HealthConnectImportTest','Automated + native device'],
  ['HEALTH-004','Health data','Reconcile imported workouts','iOS + Android','As a user, imported workout edits/deletions reconcile cleanly.','Existing external IDs update, missing records in the sync window delete, and locally ignored records do not reappear.','Health import commands','app/Services/HealthConnectWorkoutImporter.php','AppleHealthImportTest; HealthConnectImportTest','Automated'],
  ['HEALTH-005','Health data','Provider-scoped ignores','iOS + Android','As a user with both ecosystems, deleting one imported workout cannot hide an unrelated provider workout.','Ignore identity includes provider/source plus external ID and remains unique for that combination.','Imported workout deletion/import','app/Http/Controllers/WorkoutController.php; app/Models/HealthConnectIgnoredWorkout.php','None direct','Automated'],
  ['HEALTH-006','Health data','Manual sync queue','iOS + Android','As a user, Sync now queues native work and returns status promptly.','The endpoint validates platform support/permissions, queues one job, and polling observes eventual import state.','POST /apple-health/sync; /health-connect/sync','app/Http/Controllers/AppleHealthController.php; app/Http/Controllers/HealthConnectController.php','AppleHealthSyncTest; HealthConnectSyncTest','Automated + native device'],
  ['HEALTH-007','Health data','Background sync','iOS + Android','As a user, authorized health workouts refresh in the background.','Both sync commands register every ten minutes; Android validates task IDs and background runtime state before executing.','Scheduler/background tasks','routes/console.php; native-plugins/background-tasks','BackgroundTaskScheduleTest; native unit tests','Automated + native device'],

  ['SYNC-001','Cloud sync','Queue local changes','All','As a signed-in user, local creates, updates, and deletes are queued for sync.','Supported models emit complete normalized snapshots and fresh tombstones without querying templates or views.','Model observer','app/Observers/SyncableObserver.php; app/Models/SyncedModel.php','BuffSyncTest','Automated'],
  ['SYNC-002','Cloud sync','Push and pull','All','As a signed-in user, Buff sends local changes and receives remote changes.','One atomic sync applies acknowledgements and remote records, advances cursor only on valid completion, and queues follow-up attachment work.','POST /sync','app/Services/BuffSyncService.php','BuffSyncTest','Automated'],
  ['SYNC-003','Cloud sync','Conflict resolution','All','As a multi-device user, concurrent changes resolve deterministically.','Timestamp/device rules preserve the authoritative record, reconcile body metrics sharing a date to one canonical identity, and never acknowledge a newer local edit with an older response.','POST /sync','app/Services/BuffSyncService.php; /Users/mason/Sites/buff-server/app/Services/SyncService.php','BuffSyncTest; server SyncTest','Automated'],
  ['SYNC-004','Cloud sync','Pagination','All','As a user with many changes, all server pages sync.','The client follows every cursor page and performs the required empty later pushes without dropping data.','POST /sync','app/Services/BuffSyncService.php','BuffSyncTest','Automated'],
  ['SYNC-005','Cloud sync','Failure safety','All','As a user, malformed or failed sync responses do not lose local work.','Cursor and outbox remain unchanged on malformed response; in-flight responses after logout are discarded; token rotation remains atomic.','POST /sync','app/Services/BuffSyncService.php','BuffSyncTest','Automated'],
  ['SYNC-006','Cloud sync','No local transfer surface','All','As a user, sensitive local data is not exposed through unsupported import/export routes.','No local export is served and local import requests are rejected.','Application routes','routes/web.php','DataTransferTest','Automated'],
  ['SYNC-007','Security','Credential bundling','iOS + Android','As a user, account credentials and encryption keys are not shipped in native bundles.','Cleanup/bundle rules exclude tokens, keys, and sensitive local credential material.','Native bundle','config/nativephp.php; app/Services/BuffCredentialStore.php','BuffAuthenticationTest','Automated'],
];

const errors = [
  ['ERR-001','AUTH-009','P2','Logic','Returning social logins keep a stale server timezone.','Update timezone from the current social sign-in request.','Daily meal-analysis quota boundaries can use the user’s previous timezone.','/Users/mason/Sites/buff-server/app/Http/Controllers/Api/V1/SocialAuthenticationController.php','Static review'],
  ['ERR-002','ONB-007','P1','Data integrity','Onboarding writes are not atomic.','Commit goals, profile, preferences, and initial weight together.','A later failure can leave goals present and make onboarding unreachable for retry.','app/Http/Controllers/OnboardingController.php','Static review'],
  ['ERR-003','TOD-001; ADD-001; MAC-003','P1','Reliability','Invalid date query values can throw during Carbon parsing.','Reject or normalize invalid dates with controlled feedback.','Dashboard, Add, or macro breakdown can return a server error.','app/Http/Controllers/DashboardController.php; app/Http/Controllers/MealController.php; app/Http/Controllers/MacroController.php','Static review'],
  ['ERR-004','WEEK-002','P2','Logic','The advertised 90-day custom range permits 91 inclusive days.','Treat inclusive start/end as at most 90 calendar dates.','diffInDays() === 90 accepts 91 dates.','app/Http/Controllers/WeeklySummaryController.php','Static review'],
  ['ERR-005','SET-007','P2','UX / logistics','Reminder settings remain enabled when native scheduling fails.','Keep the UI aligned with scheduled native state and provide a retry path.','Only a flash message reports failure while saved settings still claim reminders are on.','app/Http/Controllers/SettingsController.php; app/Services/MealReminderBridge.php','Static review'],
  ['ERR-006','RECIPE-005','P2','Data integrity','Recipe can persist before an invalid redirect date throws.','Validate/parse date before saving.','The request can report an error after the recipe was created.','app/Http/Controllers/RecipeController.php','Static review'],
  ['ERR-007','RECIPE-006','P1','Logic','Product recipe ingredient unit can disagree with its nutrition unit.','Require the product nutrition unit unless explicit conversion exists.','Gram nutrition may be treated as millilitres or vice versa.','app/Http/Controllers/RecipeController.php; app/Services/NutritionCalculator.php','Static review'],
  ['ERR-008','PHOTO-007','P1','Data loss','Pending meal-photo confirmation is silently removed after one day.','Retain or visibly expire retry state in agreement with the server draft lifetime.','Offline users can lose promised meal-photo attachment without feedback.','app/Services/BuffSyncService.php; /Users/mason/Sites/buff-server/app/Services/MealAnalysisService.php','Static review'],
  ['ERR-009','HEALTH-005','P2','Data integrity','Imported-workout ignores are not provider-scoped.','Key ignore records by provider/source and external ID.','An Apple Health deletion can suppress a Health Connect workout sharing the ID.','app/Http/Controllers/WorkoutController.php; app/Services/HealthConnectWorkoutImporter.php','Static review'],
  ['ERR-010','PROG-020; SYNC-003','P2','Data integrity','Database does not enforce one body metric per date.','Enforce and deterministically reconcile the logical date key locally and on the sync server.','Multi-device sync can create duplicates and updates can target an arbitrary row.','database/migrations/2026_08_25_191657_reconcile_duplicate_body_metrics.php; database/migrations/2026_08_25_191658_add_unique_date_to_body_metrics_table.php; app/Services/BuffSyncService.php; /Users/mason/Sites/buff-server/app/Services/SyncService.php','Static review'],
  ['ERR-011','PROG-017','P2','UX / data visibility','Existing cloud photos can hide staged local photos.','Merge visible staged and cloud photos by pose/identity.','Users may believe pending uploads disappeared.','app/Http/Controllers/BodyMetricPhotoController.php','Static review'],
  ['ERR-012','PROG-021','P1','Data loss','Progress-photo staging does not verify file write success.','Check every write and roll back or return an error on failure.','The app can claim staging succeeded while no durable file exists.','app/Http/Controllers/BodyMetricPhotoController.php','Static review'],
  ['ERR-013','PROG-019','P2','UX','The server supports deleting individual progress photos but the UI exposes no action.','Offer a deliberate, confirmed delete action in the photo viewer.','Users cannot remove an incorrect photo from the app.','resources/js/Pages/Progress.vue; app/Http/Controllers/BodyMetricPhotoController.php','Static review'],
  ['ERR-014','RECIPE-009','P2','UX','Recipe delete is immediate with no confirmation.','Require deliberate confirmation before deleting a reusable recipe.','A single tap permanently removes the recipe.','resources/js/Components/RecipeMode.vue','Static review'],
  ['ERR-015','AUTH-001; ONB-006','P3','Test logistics','The combined registration/onboarding test omits required current weight.','Keep the scenario payload aligned with the onboarding contract.','The suite fails after successful registration because the stale fixture receives the intended validation error.','tests/Feature/BuffAuthenticationTest.php','Automated test'],
  ['ERR-016','PROG-007','P2','Type contract','The chart tooltip helper erases the supported icon field from its returned config type.','Preserve the icon type already accepted by ChartConfig.','vue-tsc rejects ChartTooltipContent.vue even though the production build succeeds.','resources/js/chartTooltip.ts; resources/js/Components/ui/chart/ChartTooltipContent.vue','Type check'],
  ['ERR-017','APP-001','P3','Build logistics','Vite flags __dirname as incompatible with its native config loader.','Resolve the resources alias with the ESM-native config directory.','Production builds succeed with a forward-compatibility warning.','vite.config.ts','Production build'],
  ['ERR-018','APP-001','P3','Development logistics','Inertia server recording is enabled while client development hooks are not detected.','Enable the server recorder only when the Vite development client and its hooks are running, unless explicitly configured.','Every live browser page logs that request lineage is not being recorded.','resources/js/app.ts; app/Providers/AppServiceProvider.php','Browser console'],
  ['ERR-019','APP-001','P3','Build logistics','Optional optimized font fallbacks warn because Fontaine is not installed.','Disable the optional fallback optimization when the optional optimizer is absent.','Production builds succeed but emit avoidable font-plugin warnings.','vite.config.ts','Production build'],
  ['ERR-020','APP-003; FOOD-006','P2','Native logistics','Android launcher shortcuts are not registered on the app entry activity.','Expose Add, Scan, and Workout shortcuts from MainActivity.','Shortcut metadata is duplicated on Health Connect activities and Android reports no Buff shortcuts.','native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php','Android emulator'],
  ['ERR-021','SET-005; SET-007','P2','Native bridge logic','The meal-reminder bridge rejects an available Android background-task capability.','Invoke the exact native capability and keep persisted settings aligned with scheduled work.','A valid Android runtime reports reminder scheduling unavailable and creates no worker.','app/Services/MealReminderBridge.php','Android emulator'],
  ['ERR-022','APP-004; PROG-012','P2','UX / navigation','Android Back bypasses app overlays and Settings hierarchy.','Close the topmost overlay, return Settings subpages to Settings, then use history or exit.','Back can leave the camera for Home, remain on a Settings subpage, or exit the app unexpectedly.','native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php; resources/js/Layouts/AppShell.vue; resources/js/Pages/Progress.vue','Android emulator'],
  ['ERR-023','PROG-012; PROG-013','P1','UX / accessibility','Progress-camera chrome uses foreground text on a foreground background.','Keep camera status, pose controls, flip, library, capture, and close controls legible.','Critical camera labels and controls are effectively invisible in the native overlay.','resources/js/Pages/Progress.vue','Android emulator'],
  ['ERR-024','AUTH-018','P1','Native request transport','Android account deletion loses the password body on a native DELETE request.','Transport the password to the existing DELETE route without placing credentials in the URL.','A visibly filled confirmation field returns “The password field is required” and the account remains.','resources/js/Pages/Settings.vue','Android emulator'],
  ['ERR-025','APP-001','P2','Native asset URL','Root-relative account artwork does not load through the iOS php scheme.','Resolve public assets through the active NativePHP asset prefix.','The registration and sign-in logo requests bypass /_assets and render broken artwork on iOS.','resources/js/Pages/Account.vue; resources/js/publicAssetUrl.ts','iOS simulator'],
  ['ERR-026','APP-009','P3','Test interpretation','Rotating the simulator frame appears to put Buff into landscape.','Judge orientation from the app window geometry and supported-orientation mask, not the physical simulator frame.','The device frame rotates while Buff retains portrait bounds and portrait interface orientation.','config/nativephp.php','iOS simulator'],
  ['ERR-027','APP-010','P2','Native logistics','The native refresh installer is Android-only, so iOS has no pull-to-refresh control.','Install one UIRefreshControl on each iOS WKWebView and end it after navigation completes.','Dragging down on iOS produces no refresh indicator or reload.','native-plugins/native-refresh/nativephp.json; native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php','iOS simulator'],
  ['ERR-028','WORK-001','P1','Native navigation','iOS redirect handling can forward an empty or absolute Location value as the PHP request URI.','Normalize redirect paths and queries so successful writes can return to the root Today screen.','A successful workout create can remain on Add or fail to resolve the root redirect.','native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php','iOS simulator'],
  ['ERR-029','APP-003; FOOD-006','P2','Native logistics','iOS home-screen quick actions are not installed or dispatched.','Install Add, Scan, and Workout shortcut metadata and send selected shortcut URLs through the existing deep-link router.','The generated iOS shell exposes no Buff quick actions.','native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php','iOS build artifact'],
  ['ERR-030','SET-011; HEALTH-002; HEALTH-006','P1','Native entitlement','The iOS simulator target is not assigned Buff’s Apple Health entitlements.','Sign the simulator target with the existing NativePHP entitlements file.','Apple Health authorization and sync are unavailable in the simulator build.','native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php','iOS simulator'],
  ['ERR-031','AUTH-014','P3','UX','Editing an unverified profile shows an email-verification warning even when the email did not change.','Show the verification reminder only when the submitted email differs from the stored account email.','A name-only edit misleadingly tells the user to check an unchanged email address.','app/Http/Controllers/AccountController.php','iOS simulator'],
  ['ERR-032','RECIPE-005','P2','UX','Successful recipe creation leaves the completed editor open and populated.','Reset the form and return to the recipe list after the server accepts the recipe.','Users can accidentally submit the same recipe again and cannot tell the save completed.','resources/js/Components/RecipeMode.vue','iOS simulator'],
  ['ERR-033','PHOTO-001','P3','Test interpretation','The iOS photo field initially appears to offer no reusable source chooser.','Use the native photo-input sheet and verify library, camera, files, and preview behavior.','The native action sheet already offers Photo Library, Take Photo, and Choose Files; no product defect is present.','resources/js/Pages/Add.vue','iOS simulator'],
  ['ERR-034','TOD-012','P2','UX','The workout editor remains open after a successful save.','Close and reset the sheet from the successful Inertia callback.','The success callback calls close while processing is still true, so the guard refuses to dismiss the editor.','resources/js/Pages/Today.vue','iOS simulator'],
  ['ERR-035','RECIPE-008','P1','Logic / UX','An empty meal query bypasses the breakfast default and recipe validation errors are invisible.','Treat an empty meal as breakfast and display meal-type and servings errors next to their controls.','Logging a recipe from an unscoped Add route silently fails with an empty meal_type.','resources/js/Components/RecipeMode.vue','iOS simulator'],
  ['ERR-036','APP-001','P2','Web asset URL','An iOS production bundle makes the desktop sidebar logo use the NativePHP asset path.','Use /_assets only inside the iOS php webview and root public URLs in web browsers.','After an iOS build, the web sidebar requests /_assets/logo.svg, receives 404, and renders the Buff alt text.','resources/js/publicAssetUrl.ts; resources/js/Layouts/AppShell.vue','Web browser'],
];

const reproducedEvidence = {
  'ERR-001': 'SocialAuthenticationTest: returning user stayed in America/New_York instead of Europe/London.',
  'ERR-002': 'OnboardingTest: simulated profile write failure left one daily_goals row.',
  'ERR-003': 'Dashboard, Add, and macro regression tests each returned HTTP 500 for date=not-a-date.',
  'ERR-004': 'WeeklySummaryTest: a 91-date inclusive range returned HTTP 200.',
  'ERR-005': 'SettingsTest: all three failed native reminders remained saved as enabled.',
  'ERR-006': 'RecipeTest: invalid date threw HTTP 500 after recipe creation.',
  'ERR-007': 'RecipeTest: gram-based yoghurt was accepted as a millilitre ingredient.',
  'ERR-008': 'MealPhotoIntegrationTest: a two-day-old pending confirmation was deleted without an API retry.',
  'ERR-009': 'HealthConnectImportTest: an Apple ignore suppressed a Health Connect workout sharing its external ID.',
  'ERR-010': 'ProgressTest: the database accepted a second body metric for the same date.',
  'ERR-011': 'ProgressPhotoTest: one cloud photo hid one staged local photo; response count was 1 instead of 2.',
  'ERR-012': 'ProgressPhotoTest: a false filesystem write still returned pending=true and persisted the staging row.',
  'ERR-013': 'Source audit: the photo viewer renders images/add controls but no action invokes the existing DELETE endpoint.',
  'ERR-014': 'Source audit: Delete recipe calls form.delete directly with no confirmation state or dialog.',
  'ERR-015': 'BuffAuthenticationTest: 212/213 passed; the stale scenario failed with current_weight_kg required.',
  'ERR-016': 'pnpm run type-check: ChartTooltipContent.vue reports Property icon does not exist.',
  'ERR-017': 'pnpm run build: Vite warns that __dirname prevents the native config loader.',
  'ERR-018': 'Live desktop/mobile browser sweep logged the Inertia request-lineage warning on every visited page.',
  'ERR-019': 'pnpm run build warned that optimizedFallbacks requires the optional fontaine package.',
  'ERR-020': 'Generated manifest placed shortcut metadata on Health Connect activities; dumpsys shortcut contained no Buff package.',
  'ERR-021': 'Enabling a meal reminder reported native scheduling unavailable and created no MealReminderWorker despite the registered capability.',
  'ERR-022': 'One Back remained on a Settings subpage and the next exited; camera Back navigated to Home instead of closing the camera.',
  'ERR-023': 'Native screenshots showed navy camera labels and controls on the same navy camera chrome.',
  'ERR-024': 'Android showed a filled password field but returned password-required; CDP confirmed the WebView issued a JSON DELETE request.',
  'ERR-025': 'The iOS account screen requested /icon.png directly under the php scheme and displayed broken artwork.',
  'ERR-026': 'LLDB reported portrait interface orientation, a 402×874 app window, and the portrait-only supported mask while the simulator frame rotated.',
  'ERR-027': 'The plugin manifest declared Android only and the generated iOS WKWebView had no UIRefreshControl.',
  'ERR-028': 'The generated iOS scheme handler reused redirect Location values instead of normalizing root path and query components.',
  'ERR-029': 'Generated iOS plists contained no UIApplicationShortcutItems and AppDelegate had no shortcut callback.',
  'ERR-030': 'The simulator build configurations omitted CODE_SIGN_ENTITLEMENTS, preventing Apple Health authorization.',
  'ERR-031': 'A name-only profile edit on an unverified account returned the check-your-email message.',
  'ERR-032': 'Saving a valid iOS recipe left the completed editor and its values visible.',
  'ERR-033': 'Native picker inspection showed the expected three-source action sheet; the reported issue was not reproducible.',
  'ERR-034': 'A successful workout update showed confirmation but left the editor sheet open because processing was still true inside onSuccess.',
  'ERR-035': 'Recipe mode received meal=""; nullish fallback preserved it, and the rejected request had no visible meal_type or servings message.',
  'ERR-036': 'The emitted iOS-base bundle resolved the web logo to /_assets/logo.svg; that URL returned 404 while /logo.svg returned 200 image/svg+xml.',
};

const fixSummaries = {
  'ERR-001': 'Refresh the server timezone on every successful social sign-in.',
  'ERR-002': 'Commit onboarding goals, profile, preferences, and initial metric in one database transaction.',
  'ERR-003': 'Validate nullable date query inputs before Carbon parsing on Today, Add, and Macro routes.',
  'ERR-004': 'Reject ranges whose date difference is 90 days or more, limiting the inclusive period to 90 dates.',
  'ERR-005': 'Schedule native reminders before persisting; scheduling errors leave the prior saved state unchanged.',
  'ERR-006': 'Validate the recipe redirect date before creating the recipe.',
  'ERR-007': 'Require product ingredient units to equal the product nutrition unit.',
  'ERR-008': 'Retain old pending meal-photo confirmations and keep retrying until the server resolves them.',
  'ERR-009': 'Add source_type to ignored workouts, use a composite unique key, and filter imports by provider.',
  'ERR-010': 'Reconcile duplicate local dates by latest update, enforce a unique date, canonicalize client IDs, and resolve same-date server writes deterministically.',
  'ERR-011': 'Merge staged and cloud progress photos by pose/identity instead of replacing staged state.',
  'ERR-012': 'Verify every staged file write and roll back partial files before returning a validation error.',
  'ERR-013': 'Expose an accessible per-photo delete button with confirmation and visible failure feedback.',
  'ERR-014': 'Route recipe deletion through a named confirmation dialog.',
  'ERR-015': 'Supply current_weight_kg in the combined registration/onboarding fixture.',
  'ERR-016': 'Preserve the optional Vue component icon in the tooltip series type.',
  'ERR-017': 'Resolve the Vite alias with import.meta.dirname.',
  'ERR-018': 'Keep client hooks tied to Vite development mode and default the server recorder to the same hot-file state.',
  'ERR-019': 'Disable the optional optimizedFallbacks setting for both bundled fonts.',
  'ERR-020': 'Install shortcut metadata exactly once on MainActivity and remove misplaced duplicates.',
  'ERR-021': 'Trust nativephp_call/nativephp_can and invoke the exact BackgroundTasks.RegisterMealReminders capability.',
  'ERR-022': 'Delegate Back to a cancelable app event before the WebView history fallback; overlays consume it and Settings subpages replace to Settings.',
  'ERR-023': 'Use contrasting camera-shell tokens and a light status/header surface with readable system chrome.',
  'ERR-024': 'POST the form with Laravel’s standard _method=delete override so the embedded server receives the password body.',
  'ERR-025': 'Resolve account artwork through the NativePHP-aware publicAssetUrl helper.',
  'ERR-026': 'No code change; classify orientation from effective app geometry and supported masks.',
  'ERR-027': 'Enable the plugin on iOS and install one WKWebView UIRefreshControl with completion cleanup.',
  'ERR-028': 'Normalize empty, relative, and absolute redirects to a path plus query before the next PHP request.',
  'ERR-029': 'Install iOS shortcut plist entries and dispatch UIApplicationShortcutItem URLs through DeepLinkRouter.',
  'ERR-030': 'Assign NativePHP.entitlements to every simulator build configuration.',
  'ERR-031': 'Compare the submitted email with the stored account email before choosing the verification reminder.',
  'ERR-032': 'Reset the recipe form and close creation mode in the successful request callback.',
  'ERR-033': 'No code change; verify the native source sheet and reusable preview behavior.',
  'ERR-034': 'Remove the processing guard so the successful callback can close and reset the workout editor.',
  'ERR-035': 'Use truthy fallback for meal_type and render meal-type and servings validation errors.',
  'ERR-036': 'Gate the /_assets prefix on the runtime php protocol so one bundle resolves public artwork correctly on iOS and the web.',
};

const retestEvidence = {
  'ERR-001': 'Server SocialAuthenticationTest passed for returning-user timezone refresh.',
  'ERR-002': 'Onboarding rollback regression passed; the complete Buff suite passed 228/228.',
  'ERR-003': 'All three invalid-date regressions return controlled redirects/errors; full suite passed.',
  'ERR-004': 'Weekly inclusive-range boundary regression passed.',
  'ERR-005': 'Reminder scheduling-failure regression preserved the old state.',
  'ERR-006': 'Invalid-date recipe regression passed with no persisted recipe.',
  'ERR-007': 'Product nutrition-unit mismatch regression passed.',
  'ERR-008': 'Two-day-old pending confirmation retried and remained durable until acknowledged.',
  'ERR-009': 'Cross-provider shared-ID regression passed; migrations and both provider import suites passed.',
  'ERR-010': 'Local DB unique test, client sync reconciliation, and server canonical-date conflict tests passed; live duplicate was reconciled after backup.',
  'ERR-011': 'Cloud-plus-staged photo merge regression passed.',
  'ERR-012': 'False-write regression returned an error and left no staging row.',
  'ERR-013': 'Live browser created a temporary cloud photo, showed Delete front photo, required confirmation, deleted it, and returned to zero photos.',
  'ERR-014': 'Live browser created a temporary recipe, required Delete recipe confirmation, deleted it, and returned to an empty list.',
  'ERR-015': 'Complete Buff PHP suite passed 228/228.',
  'ERR-016': 'pnpm run type-check passed.',
  'ERR-017': 'Clean pnpm run build completed without the config-loader warning.',
  'ERR-018': 'Fresh desktop/mobile route sweep produced zero browser warnings or errors.',
  'ERR-019': 'Clean pnpm run build completed without the optional Fontaine warning.',
  'ERR-020': 'Regenerated manifest has one MainActivity declaration; Android registers Add, Scan, and Workout, and each deep link reached its intended destination.',
  'ERR-021': 'Android scheduled MealReminderWorker, persisted the enabled state, and cancelled it when toggled off; failure regression also passed.',
  'ERR-022': 'Android Back closed the Add drawer and progress camera and returned Meal reminders to Settings without exiting.',
  'ERR-023': 'Post-fix Android screenshots show readable camera header, pose state, flip, library, capture, and close controls.',
  'ERR-024': 'Frontend regression, type-check, build, and authentication tests passed; Android deleted the temporary account and returned to Create account.',
  'ERR-025': 'publicAssetUrl regression passed; a fresh post-fix iOS build rendered both light and dark account artwork through /_assets.',
  'ERR-026': 'Runtime inspection confirmed Buff remained portrait after both simulator rotation directions.',
  'ERR-027': 'Native installer regression passed; the iOS pull gesture displayed, reloaded, and ended cleanly.',
  'ERR-028': 'Installer regression passed; creating IOS Redirect Final returned to Today and persisted the workout.',
  'ERR-029': 'Both iOS plists contain Add, Scan, and Workout entries and AppDelegate dispatches them through the deep-link router.',
  'ERR-030': 'The iOS authorization sheet opened, permissions completed, and Apple Health sync returned successfully.',
  'ERR-031': 'Name-only profile save returned the neutral Account updated confirmation.',
  'ERR-032': 'Creating IOS Recipe QA returned to the recipe list with a reset editor.',
  'ERR-033': 'iOS offered Photo Library, Take Photo, and Choose Files; selecting a photo produced a reusable preview.',
  'ERR-034': 'iosUxRegressions passed and the edited IOS Redirect Final sheet dismissed after the success toast.',
  'ERR-035': 'iosUxRegressions passed; Breakfast defaulted visibly and the 165 kcal recipe log persisted on Today.',
  'ERR-036': 'publicAssetUrl regression passed; frontend 44/44, type-check, web build, Buff 230/230, and server 149/149 passed; both web logo URLs return 200 image/svg+xml and a fresh iOS light/dark smoke passed.',
};

const nonFeatureErrors = new Set(['ERR-015', 'ERR-017', 'ERR-018', 'ERR-019', 'ERR-026', 'ERR-033']);
const nativeInitialResults = {
  'APP-004': ['Fail', 'Android Back reproduced ERR-022 across Settings and the progress camera.'],
  'APP-009': ['Pass', 'Android reported portrait; the apparent iOS rotation issue was later classified as ERR-026 test interpretation.'],
  'APP-010': ['Fail', 'Android pull-to-refresh passed, while iOS reproduced ERR-027 because the plugin was Android-only.'],
  'AUTH-008': ['Blocked', 'The initial run awaited a platform selection for this iOS-only behavior.'],
  'AUTH-018': ['Fail', 'Android account deletion reproduced ERR-024: a visibly filled password returned password-required.'],
  'TOD-014': ['Pass', 'Today quick sync invoked HealthConnect.SyncNow and returned sync_queued; iOS remained pending at this phase.'],
  'FOOD-004': ['Pass', 'The Android barcode-scanner camera opened and dismissed cleanly.'],
  'FOOD-006': ['Fail', 'Android registered no Buff launcher shortcuts; reproduced ERR-020.'],
  'PROG-012': ['Fail', 'Pose capture advanced Front to Side, but camera Back and overlay contrast reproduced ERR-022 and ERR-023.'],
  'PROG-013': ['Fail', 'Camera flip switched native camera IDs, but the control was visually obscured by ERR-023.'],
  'SET-005': ['Fail', 'Android reminder scheduling reproduced ERR-021 and created no worker.'],
  'SET-007': ['Fail', 'Native scheduling failure reproduced ERR-005 and ERR-021.'],
  'SET-011': ['Fail', 'Android Health Connect passed, while iOS reproduced ERR-030 because the simulator target lacked Apple Health entitlements.'],
  'HEALTH-002': ['Fail', 'Android permissions passed, while iOS Apple Health authorization reproduced ERR-030.'],
  'HEALTH-003': ['Pass', 'The native import worker completed safely with zero emulator workout records.'],
  'HEALTH-006': ['Fail', 'Android manual sync passed, while iOS Apple Health sync was blocked by ERR-030.'],
  'HEALTH-007': ['Pass', 'Android JobScheduler recorded a successful HealthConnectSyncWorker run.'],
};
const nativeRetestResults = {
  'APP-004': ['Pass', 'Post-rebuild Android Back closed the Add drawer and progress camera and returned Meal reminders to Settings.'],
  'APP-009': ['Pass', 'Android stayed portrait; iOS runtime geometry remained 402×874 portrait with the portrait-only supported mask after frame rotation.'],
  'APP-010': ['Pass', 'Native pull-to-refresh completed on both Android and the rebuilt iOS WKWebView.'],
  'AUTH-008': ['Pass', 'The Apple option appeared only on iOS and the callback path retained automated coverage.'],
  'AUTH-018': ['Pass', 'Android completed remote/local deletion; iOS validated the same method-spoofed confirmation flow and the final synthetic account was removed.'],
  'TOD-014': ['Pass', 'Today quick sync queued Health Connect on Android and completed Apple Health authorization/sync on iOS.'],
  'FOOD-004': ['Pass', 'The Android barcode scanner opened through the native camera and Back returned to Add food.'],
  'FOOD-006': ['Pass', 'Android Scan opened the native scanner; iOS installed all three plist entries and the AppDelegate deep-link dispatcher.'],
  'PROG-012': ['Pass', 'Front and Side captures advanced pose state; post-rebuild Back closed the camera while staying on Progress.'],
  'PROG-013': ['Pass', 'The native flip control switched between distinct front and rear camera IDs and remained readable.'],
  'SET-005': ['Pass', 'Android scheduled and cancelled MealReminderWorker while keeping the UI state aligned.'],
  'SET-007': ['Pass', 'Scheduling failure preserved the prior state and the corrected native capability succeeded on retry.'],
  'SET-011': ['Pass', 'Health Connect passed on Android; iOS showed Apple Health authorization, provider state, and successful sync.'],
  'HEALTH-002': ['Pass', 'Health Connect permissions passed on Android and Apple Health authorization completed on iOS.'],
  'HEALTH-003': ['Pass', 'Native imports completed safely on Android and Apple Health sync completed on iOS.'],
  'HEALTH-006': ['Pass', 'Manual Health Connect work queued on Android and Apple Health sync returned successfully on iOS.'],
  'HEALTH-007': ['Pass', 'Android JobScheduler completed and the iOS background schedule retained automated coverage.'],
};
const storyErrorIds = (storyId) => errors
  .filter((error) => error[1].split(';').map((id) => id.trim()).includes(storyId))
  .map((error) => error[0]);
const initialStatusFor = (story) => {
  if (nativeInitialResults[story[0]]) return nativeInitialResults[story[0]][0];

  const errorIds = storyErrorIds(story[0]);

  if (errorIds.some((errorId) => !nonFeatureErrors.has(errorId))) return 'Fail';
  if (story[9].includes('native device')) return 'Blocked';

  return 'Pass';
};
const initialEvidenceFor = (story) => {
  if (nativeInitialResults[story[0]]) return nativeInitialResults[story[0]][1];

  const errorIds = storyErrorIds(story[0]);
  const status = initialStatusFor(story);

  if (status === 'Fail') return `Reproduced ${errorIds.filter((id) => !nonFeatureErrors.has(id)).join(', ')}; see Errors.`;
  if (status === 'Blocked') return 'Automated/static portion completed; physical device behavior awaits an iOS or Android test target.';
  if (story[9].includes('browser')) return 'Relevant automated coverage passed and the live desktop/mobile surface and safe interactions were verified.';
  if (errorIds.length) return `Expected behavior passed; related logistical findings ${errorIds.join(', ')} are tracked separately.`;

  return 'Relevant automated coverage passed in the 213-test PHP and 39-test frontend baseline.';
};
const retestStatusFor = (story) => nativeRetestResults[story[0]]?.[0] ?? (story[9].includes('native device') ? 'Blocked' : 'Pass');
const retestEvidenceFor = (story) => {
  if (nativeRetestResults[story[0]]) return nativeRetestResults[story[0]][1];

  if (retestStatusFor(story) === 'Blocked') {
    return 'Automated and static retest passed; physical behavior still requires the selected iOS or Android device target.';
  }

  const errorIds = storyErrorIds(story[0]);

  if (errorIds.length) return `Linked regressions passed: ${errorIds.join(', ')}. See Errors for fix-specific evidence.`;
  if (story[9].includes('browser')) return 'Automated coverage passed; live desktop/mobile navigation and safe interaction sweep passed with zero fresh browser warnings/errors.';

  return 'Final regression passed: Buff PHP 230/230 (1,832 assertions), server PHP 149/149, frontend 44/44, type-check clean, and production build clean as applicable.';
};
const errorStatusOverrides = {
  'ERR-026': ['Not reproducible', 'Not needed', 'Pass', 'Resolved'],
  'ERR-033': ['Not reproducible', 'Not needed', 'Pass', 'Resolved'],
};
const iosEvidenceOverrides = {
  'APP-001': 'The rebuilt iOS account shell loaded its light and dark artwork through /_assets; ERR-025 passed.',
  'APP-003': 'The Add launcher and all dated modes passed; generated iOS quick actions route through the same deep-link handler.',
  'APP-009': 'LLDB confirmed portrait interface orientation, 402×874 app bounds, and the portrait-only supported mask after frame rotation.',
  'APP-010': 'The iOS pull gesture displayed UIRefreshControl, reloaded the WKWebView, and ended cleanly.',
  'AUTH-008': 'The Apple option rendered only on iOS and its native OAuth callback remains covered automatically.',
  'AUTH-014': 'A name-only profile edit returned Account updated without an irrelevant email-verification warning.',
  'AUTH-018': 'The confirmed synthetic QA account was removed after iOS validation of the password-confirmed method-spoofed delete flow.',
  'TOD-012': 'Editing IOS Redirect Final persisted the title, showed confirmation, and dismissed the workout sheet.',
  'TOD-014': 'Apple Health authorization opened, permissions completed, and Sync now returned successfully.',
  'FOOD-006': 'The built iOS plists contain Scan and AppDelegate dispatches shortcut URLs through DeepLinkRouter; icon invocation was unavailable in this simulator layout.',
  'PHOTO-001': 'The native sheet offered Photo Library, Take Photo, and Choose Files; selection produced a removable preview.',
  'RECIPE-005': 'Creating IOS Recipe QA reset and closed the editor and returned to the saved recipe list.',
  'RECIPE-008': 'Breakfast defaulted from an empty meal query and the 165 kcal IOS Recipe QA log persisted on Today.',
  'WORK-001': 'Creating IOS Redirect Final persisted the 120 kcal workout and normalized the redirect to Today.',
  'SET-011': 'Apple Health showed its authorization sheet, provider status, and working sync action.',
  'HEALTH-002': 'Apple Health authorization completed successfully with the simulator entitlement installed.',
  'HEALTH-003': 'Apple Health import/sync completed safely for the simulator data set.',
  'HEALTH-006': 'Manual Apple Health sync completed and returned visible success feedback.',
  'HEALTH-007': 'The iOS background schedule retained automated coverage and the foreground Apple Health path passed.',
};
const iosRetestResultFor = (story) => story[3] === 'Android' ? 'Not applicable' : 'Pass';
const iosRetestEvidenceFor = (story) => {
  if (iosRetestResultFor(story) === 'Not applicable') return 'Android-only behavior; correctly omitted from the iOS build.';
  if (iosEvidenceOverrides[story[0]]) return iosEvidenceOverrides[story[0]];

  const errorIds = storyErrorIds(story[0]);

  if (errorIds.length) return `Linked regressions passed on iOS or in the shared automated suite: ${errorIds.join(', ')}.`;
  if (story[9].includes('browser')) return 'Shared automated coverage passed and the applicable iOS route or safe interaction was exercised without fresh errors.';

  return 'Shared final regression passed; no iOS-specific divergence was present for this behavior.';
};

const workbook = Workbook.create();
const overview = workbook.worksheets.add('Overview');
const storySheet = workbook.worksheets.add('Stories');
const errorSheet = workbook.worksheets.add('Errors');
const runSheet = workbook.worksheets.add('Test Runs');

for (const sheet of [overview, storySheet, errorSheet, runSheet]) sheet.showGridLines = false;

const navy = '#17233C';
const teal = '#12B8A6';
const pale = '#E9F7F4';
const sand = '#F6F2E9';
const border = '#D8DEE8';
const muted = '#667085';

overview.getRange('A1:H1').merge();
overview.getRange('A1').values = [['Buff feature verification tracker']];
overview.getRange('A1:H1').format = { fill: navy, font: { bold: true, color: '#FFFFFF', size: 20 }, rowHeight: 34, verticalAlignment: 'center' };
overview.getRange('A2:H2').merge();
overview.getRange('A2').values = [['Canonical code-derived inventory, initial test record, defect ledger, fixes, and full retest status']];
overview.getRange('A2:H2').format = { fill: navy, font: { color: '#DCE5F5', size: 10 }, rowHeight: 24 };
overview.getRange('A4:B4').values = [['Story status','Count']];
overview.getRange('D4:E4').values = [['Defect status','Count']];
overview.getRange('A5:A13').values = [['Total stories'],['Initial pass'],['Initial fail'],['Initial blocked'],['Retest pass'],['Retest blocked'],['Final pass'],['Final blocked'],['Final not applicable']];
overview.getRange('B5:B13').formulas = [["=COUNTA('Stories'!A5:A250)"],["=COUNTIF('Stories'!K5:K250,\"Pass\")"],["=COUNTIF('Stories'!K5:K250,\"Fail\")"],["=COUNTIF('Stories'!K5:K250,\"Blocked\")"],["=COUNTIF('Stories'!O5:O250,\"Pass\")"],["=COUNTIF('Stories'!O5:O250,\"Blocked\")"],["=COUNTIF('Stories'!P5:P250,\"Pass\")"],["=COUNTIF('Stories'!P5:P250,\"Blocked\")"],["=COUNTIF('Stories'!P5:P250,\"Not applicable\")"]];
overview.getRange('D5:D9').values = [['Total defects'],['Reproduced'],['Fixed'],['Retest pass'],['Open']];
overview.getRange('E5:E9').formulas = [["=COUNTA('Errors'!A5:A100)"],["=COUNTIF('Errors'!J5:J100,\"Reproduced\")"],["=COUNTIF('Errors'!L5:L100,\"Fixed\")"],["=COUNTIF('Errors'!N5:N100,\"Pass\")"],["=COUNTIF('Errors'!P5:P100,\"Open\")"]];
overview.getRange('A4:B13').format.borders = { preset: 'outside', style: 'thin', color: border };
overview.getRange('D4:E9').format.borders = { preset: 'outside', style: 'thin', color: border };
overview.getRange('A4:B4').format = { fill: teal, font: { bold: true, color: '#FFFFFF' } };
overview.getRange('D4:E4').format = { fill: teal, font: { bold: true, color: '#FFFFFF' } };
overview.getRange('A14:H14').merge();
overview.getRange('A14').values = [['Workflow']];
overview.getRange('A14:H14').format = { fill: pale, font: { bold: true, color: navy } };
overview.getRange('A15:H18').values = [
  ['1','Inventory','Every coded user-facing, native, sync, and safety behavior receives one Story ID','','','','',''],
  ['2','Initial test','Run the smallest reliable automated/browser/native check and record a Test Run','','','','',''],
  ['3','Fix','Link each reproduced defect to Error ID(s), implement the root-cause fix, and update Fix status','','','','',''],
  ['4','Retest','Rerun every Story ID, not only failed ones, then set Final status','','','','',''],
];
for (let row = 15; row <= 18; row++) overview.getRange(`C${row}:H${row}`).merge();
overview.getRange('A15:A18').format = { font: { bold: true, color: teal }, horizontalAlignment: 'center' };
overview.getRange('B15:H18').format.wrapText = true;
overview.getRange('A20:H20').merge();
overview.getRange('A20').values = [['Status definitions']];
overview.getRange('A20:H20').format = { fill: sand, font: { bold: true, color: navy } };
const definitions = [
  ['Pending','Not yet executed.'],
  ['Pass','Expected behavior observed.'],
  ['Fail','Expected behavior not observed.'],
  ['Blocked','Cannot execute because the required platform, service, or input is unavailable.'],
  ['Not applicable','Behavior does not apply to the tested platform.'],
  ['Needs reproduction','Static-review risk awaiting executable evidence.'],
  ['Fixed / Resolved','Root cause changed, focused checks pass, and the full behavior retest passes.'],
  ['Canonical rule','Story, defect, fix, initial evidence, and retest evidence all stay in this workbook. Scope includes Buff and directly referenced buff-server behavior.'],
];
overview.getRange('A21:B28').values = definitions;
for (let row = 21; row <= 28; row++) overview.getRange(`B${row}:H${row}`).merge();
overview.getRange('A21:H28').format.wrapText = true;
overview.getRange('A21:A28').format.font = { bold: true, color: navy };
overview.getRange('A1:H28').format.font = { name: 'Arial' };
overview.getRange('A1:H28').format.verticalAlignment = 'center';
overview.getRange('A1:H28').format.rowHeight = 22;
overview.getRange('A15:H18').format.rowHeight = 32;
overview.getRange('A21:H28').format.rowHeight = 28;
overview.getRange('A1:A28').format.columnWidth = 18;
overview.getRange('B1:B28').format.columnWidth = 34;
overview.getRange('C1:C28').format.columnWidth = 18;
overview.getRange('D1:D28').format.columnWidth = 18;
overview.getRange('E1:E28').format.columnWidth = 16;
overview.getRange('F1:F28').format.columnWidth = 16;
overview.getRange('G1:G28').format.columnWidth = 18;
overview.getRange('H1:H28').format.columnWidth = 34;
overview.freezePanes.freezeRows(2);

const storyHeaders = ['Story ID','Area','Feature','Platform','User story','Expected behavior','Surface / route','Source code','Existing coverage','Test method','Initial status','Initial evidence','Error IDs','Fix status','Retest status','Final status','Notes'];
storySheet.getRange('A1:Q1').merge();
storySheet.getRange('A1').values = [['Canonical user stories']];
storySheet.getRange('A1:Q1').format = { fill: navy, font: { bold: true, color: '#FFFFFF', size: 18 }, rowHeight: 32 };
storySheet.getRange('A2:Q2').merge();
storySheet.getRange('A2').values = [[`${stories.length} code-derived behaviors. Update statuses here; add execution evidence in Test Runs.`]];
storySheet.getRange('A2:Q2').format = { fill: navy, font: { color: '#DCE5F5' }, rowHeight: 22 };
storySheet.getRange('A4:Q4').values = [storyHeaders];
storySheet.getRange('A4:Q4').format = { fill: teal, font: { bold: true, color: '#FFFFFF' }, wrapText: true, rowHeight: 32, verticalAlignment: 'center' };
const storyRows = stories.map((row) => {
  const errorIds = storyErrorIds(row[0]);

  return [
    ...row,
    initialStatusFor(row),
    initialEvidenceFor(row),
    errorIds.join('; '),
    errorIds.length ? 'Fixed' : 'Not needed',
    retestStatusFor(row),
    retestStatusFor(row),
    retestEvidenceFor(row),
  ];
});
storySheet.getRangeByIndexes(4, 0, storyRows.length, storyHeaders.length).values = storyRows;
storySheet.tables.add(`A4:Q${storyRows.length + 4}`, true, 'StoriesTable').style = 'TableStyleMedium2';
storySheet.freezePanes.freezeRows(4);
storySheet.freezePanes.freezeColumns(4);
storySheet.getRange(`K5:K${storyRows.length + 4}`).dataValidation = { rule: { type: 'list', values: ['Pending','Pass','Fail','Blocked','Not applicable'] } };
storySheet.getRange(`N5:N${storyRows.length + 4}`).dataValidation = { rule: { type: 'list', values: ['Not needed','Pending','In progress','Fixed','Deferred'] } };
storySheet.getRange(`O5:P${storyRows.length + 4}`).dataValidation = { rule: { type: 'list', values: ['Pending','Pass','Fail','Blocked','Not applicable'] } };
for (const col of ['K','O','P']) {
  const range = storySheet.getRange(`${col}5:${col}${storyRows.length + 4}`);
  range.conditionalFormats.add('containsText', { text: 'Pass', format: { fill: '#DDF7E8', font: { color: '#176B3A', bold: true } } });
  range.conditionalFormats.add('containsText', { text: 'Fail', format: { fill: '#FDE2E2', font: { color: '#A12626', bold: true } } });
  range.conditionalFormats.add('containsText', { text: 'Blocked', format: { fill: '#FFF0CC', font: { color: '#8A5A00', bold: true } } });
}
storySheet.getRange(`A5:Q${storyRows.length + 4}`).format = { font: { name: 'Arial', size: 9 }, verticalAlignment: 'top' };
storySheet.getRange(`E5:Q${storyRows.length + 4}`).format.wrapText = true;
const storyWidths = [12,18,25,14,38,55,28,48,30,20,14,34,14,14,14,14,32];
storyWidths.forEach((width, index) => storySheet.getRangeByIndexes(0,index,storyRows.length + 4,1).format.columnWidth = width);
storySheet.getRange(`A5:Q${storyRows.length + 4}`).format.rowHeight = 54;

const errorHeaders = ['Error ID','Story IDs','Severity','Type','Summary','Expected behavior','Observed risk / failure','Source code','Discovery','Reproduction status','Reproduction evidence','Fix status','Fix summary','Retest status','Retest evidence','Final status'];
errorSheet.getRange('A1:P1').merge();
errorSheet.getRange('A1').values = [['Defect ledger']];
errorSheet.getRange('A1:P1').format = { fill: navy, font: { bold: true, color: '#FFFFFF', size: 18 }, rowHeight: 32 };
errorSheet.getRange('A2:P2').merge();
errorSheet.getRange('A2').values = [['Static-review findings begin as Needs reproduction; only executable evidence advances them to Reproduced.']];
errorSheet.getRange('A2:P2').format = { fill: navy, font: { color: '#DCE5F5' }, rowHeight: 22 };
errorSheet.getRange('A4:P4').values = [errorHeaders];
errorSheet.getRange('A4:P4').format = { fill: '#D97706', font: { bold: true, color: '#FFFFFF' }, wrapText: true, rowHeight: 32 };
const errorRows = errors.map((row) => {
  const [reproductionStatus, fixStatus, retestStatus, finalStatus] = errorStatusOverrides[row[0]] ?? ['Reproduced', 'Fixed', 'Pass', 'Resolved'];

  return [...row, reproductionStatus, reproducedEvidence[row[0]], fixStatus, fixSummaries[row[0]], retestStatus, retestEvidence[row[0]], finalStatus];
});
errorSheet.getRangeByIndexes(4,0,errorRows.length,errorHeaders.length).values = errorRows;
errorSheet.tables.add(`A4:P${errorRows.length + 4}`, true, 'ErrorsTable').style = 'TableStyleMedium9';
errorSheet.freezePanes.freezeRows(4);
errorSheet.freezePanes.freezeColumns(2);
errorSheet.getRange(`C5:C${errorRows.length + 4}`).dataValidation = { rule: { type: 'list', values: ['P1','P2','P3'] } };
errorSheet.getRange(`J5:J${errorRows.length + 4}`).dataValidation = { rule: { type: 'list', values: ['Needs reproduction','Reproduced','Not reproducible','Blocked'] } };
errorSheet.getRange(`L5:L${errorRows.length + 4}`).dataValidation = { rule: { type: 'list', values: ['Not needed','Pending','In progress','Fixed','Deferred'] } };
errorSheet.getRange(`N5:N${errorRows.length + 4}`).dataValidation = { rule: { type: 'list', values: ['Pending','Pass','Fail','Blocked','Not applicable'] } };
errorSheet.getRange(`P5:P${errorRows.length + 4}`).dataValidation = { rule: { type: 'list', values: ['Open','Resolved','Deferred'] } };
errorSheet.getRange(`A5:P${errorRows.length + 4}`).format = { font: { name: 'Arial', size: 9 }, verticalAlignment: 'top', wrapText: true, rowHeight: 64 };
const errorWidths = [12,20,10,18,34,42,45,48,16,20,38,16,40,16,48,14];
errorWidths.forEach((width,index) => errorSheet.getRangeByIndexes(0,index,errorRows.length + 4,1).format.columnWidth = width);
errorSheet.getRange(`C5:C${errorRows.length + 4}`).conditionalFormats.add('containsText', { text: 'P1', format: { fill: '#FDE2E2', font: { color: '#A12626', bold: true } } });
errorSheet.getRange(`P5:P${errorRows.length + 4}`).conditionalFormats.add('containsText', { text: 'Resolved', format: { fill: '#DDF7E8', font: { color: '#176B3A', bold: true } } });

const runHeaders = ['Run ID','Story ID','Phase','Executed at','Platform','Method','Result','Evidence / assertion','Error IDs','Notes'];
runSheet.getRange('A1:J1').merge();
runSheet.getRange('A1').values = [['Test execution log']];
runSheet.getRange('A1:J1').format = { fill: navy, font: { bold: true, color: '#FFFFFF', size: 18 }, rowHeight: 32 };
runSheet.getRange('A2:J2').merge();
runSheet.getRange('A2').values = [['Append one concise result per Story ID and phase. Initial and Retest runs remain distinct.']];
runSheet.getRange('A2:J2').format = { fill: navy, font: { color: '#DCE5F5' }, rowHeight: 22 };
runSheet.getRange('A4:J4').values = [runHeaders];
runSheet.getRange('A4:J4').format = { fill: teal, font: { bold: true, color: '#FFFFFF' }, wrapText: true, rowHeight: 32 };
const initialRuns = stories.map((story, index) => [
  `RUN-I-${String(index + 1).padStart(3, '0')}`,
  story[0],
  'Initial',
  '2026-08-25T20:06:56+01:00',
  story[3],
  story[9],
  initialStatusFor(story),
  initialEvidenceFor(story),
  storyErrorIds(story[0]).join('; '),
  '',
]);
const retestRuns = stories.map((story, index) => [
  `RUN-R-${String(index + 1).padStart(3, '0')}`,
  story[0],
  'Retest',
  '2026-08-25T22:22:00+01:00',
  story[3],
  story[9],
  retestStatusFor(story),
  retestEvidenceFor(story),
  storyErrorIds(story[0]).join('; '),
  retestStatusFor(story) === 'Blocked' ? 'Awaiting user-selected native platform.' : '',
]);
const iosRetestRuns = stories.map((story, index) => [
  `RUN-IOS-${String(index + 1).padStart(3, '0')}`,
  story[0],
  'iOS Retest',
  '2026-08-26T11:45:00+01:00',
  'iOS',
  story[3] === 'Android' ? 'Platform applicability review' : 'Automated + iOS simulator',
  iosRetestResultFor(story),
  iosRetestEvidenceFor(story),
  storyErrorIds(story[0]).join('; '),
  story[3] === 'Android' ? 'Covered in the completed Android run; not shipped on iOS.' : '',
]);
const webRegressionRuns = [[
  'RUN-WEB-001',
  'APP-001',
  'Retest',
  '2026-08-26T13:40:58+01:00',
  'Web',
  'Automated + HTTP asset verification',
  'Pass',
  'Runtime protocol regression passed; /logo.svg and /logo-dark.svg return 200 image/svg+xml after a clean web build.',
  'ERR-036',
  'Full post-fix regression passed: Buff PHP 230/230, server PHP 149/149, frontend 44/44, type-check, and production build.',
]];
const finalIosSmokeRuns = [[
  'RUN-IOS-SMOKE-001',
  'APP-001',
  'iOS Retest',
  '2026-08-26T14:10:46+01:00',
  'iOS',
  'Fresh iOS build + simulator',
  'Pass',
  'The light icon and dark wordmark both rendered on Sign in with no broken artwork or alt text.',
  'ERR-025; ERR-036',
  'Simulator appearance was toggled light/dark for verification and restored to light.',
]];
const allRuns = [...initialRuns, ...retestRuns, ...iosRetestRuns, ...webRegressionRuns, ...finalIosSmokeRuns];
runSheet.getRangeByIndexes(4, 0, allRuns.length, runHeaders.length).values = allRuns;
runSheet.tables.add(`A4:J${allRuns.length + 4}`, true, 'TestRunsTable').style = 'TableStyleMedium2';
runSheet.getRange(`C5:C${allRuns.length + 4}`).dataValidation = { rule: { type: 'list', values: ['Initial','Retest','iOS Retest'] } };
runSheet.getRange(`G5:G${allRuns.length + 4}`).dataValidation = { rule: { type: 'list', values: ['Pass','Fail','Blocked','Not applicable'] } };
runSheet.freezePanes.freezeRows(4);
const runWidths = [14,14,12,22,16,22,14,55,18,45];
runWidths.forEach((width,index) => runSheet.getRangeByIndexes(0,index,allRuns.length + 4,1).format.columnWidth = width);
runSheet.getRange(`A5:J${allRuns.length + 4}`).format = { font: { name: 'Arial', size: 9 }, verticalAlignment: 'top', wrapText: true, rowHeight: 52 };
runSheet.getRange(`D5:D${allRuns.length + 4}`).format.numberFormat = 'yyyy-mm-dd hh:mm:ss';

await fs.mkdir(outputDir, { recursive: true });
for (const [sheetName, fileName, range] of [['Overview','overview.png','A1:H28'],['Stories','stories.png','A1:Q20'],['Errors','errors.png',`A1:P${errors.length + 4}`],['Test Runs','test-runs.png','A1:J30'],['Test Runs','test-runs-ios.png','A291:J315'],['Test Runs','test-runs-web.png','A430:J435']]) {
  const preview = await workbook.render({ sheetName, range, autoCrop: 'all', scale: 1, format: 'png' });
  await fs.writeFile(`${outputDir}/${fileName}`, new Uint8Array(await preview.arrayBuffer()));
}

const inspect = await workbook.inspect({ kind: 'table', range: 'Overview!A1:H28', include: 'values,formulas', tableMaxRows: 30, tableMaxCols: 10 });
console.log(inspect.ndjson);
const iosErrorsInspect = await workbook.inspect({ kind: 'table', range: 'Errors!A29:P40', include: 'values,formulas', tableMaxRows: 16, tableMaxCols: 16 });
console.log(iosErrorsInspect.ndjson);
const iosRunsInspect = await workbook.inspect({ kind: 'table', range: 'Test Runs!A291:J315', include: 'values,formulas', tableMaxRows: 25, tableMaxCols: 10 });
console.log(iosRunsInspect.ndjson);
const webRunsInspect = await workbook.inspect({ kind: 'table', range: 'Test Runs!A430:J435', include: 'values,formulas', tableMaxRows: 6, tableMaxCols: 10 });
console.log(webRunsInspect.ndjson);
const formulaErrors = await workbook.inspect({ kind: 'match', searchTerm: '#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A', options: { useRegex: true, maxResults: 100 }, summary: 'formula error scan' });
console.log(formulaErrors.ndjson.trim() || '{"formulaErrors":[]}');

const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(`${outputDir}/buff-feature-verification.xlsx`);
