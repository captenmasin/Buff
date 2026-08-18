# Plan 001: Make `/add` the sole add launcher

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving on. If a STOP condition occurs, stop and report; do not improvise. When done, update this plan's row in `plans/README.md` unless a reviewer owns the index.
>
> **Drift check (run first)**: Run both `git diff --stat 21884fc -- resources/js/Layouts/AppShell.vue resources/js/Pages/Add.vue resources/css/app.css app/Http/Controllers/DashboardController.php native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php tests/Feature/NativePluginHooksTest.php tests/Unit/NativeShortcutHookTest.php` and `git status --short --untracked-files=all -- resources/js/Layouts/AppShell.vue resources/js/Pages/Add.vue resources/css/app.css app/Http/Controllers/DashboardController.php native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php tests/Feature/NativePluginHooksTest.php tests/Unit/NativeShortcutHookTest.php`.
> If an in-scope file changed, compare the excerpts below with live code. Any behavioral mismatch is a STOP condition.

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: MED
- **Depends on**: none
- **Category**: direction
- **Planned at**: commit `21884fc`, 2026-08-16

## Why this matters

Buff currently owns two add menus: an AppShell drawer with five choices and `/add` with three choices. They disagree about whether Search, Scan, and Custom are top-level jobs, and the drawer carries its own history/back state. Making `/add` canonical removes duplicate navigation code and preserves every deep link. Photo and Workout keep their tap count; Search, Scan, and Custom intentionally gain one Food-selection tap in exchange for one stable hierarchy.

## Current state

- `resources/js/Layouts/AppShell.vue:16-17` owns drawer state:

  ```ts
  const addDrawerOpen = ref(false);
  const drawerHistoryActive = ref(false);
  ```

- `resources/js/Layouts/AppShell.vue:37-114` pushes browser history, handles drawer-specific Back behavior, builds mode URLs, and consumes legacy `?add=1`.
- `resources/js/Layouts/AppShell.vue:235-243` and `:371-379` call `openAddDrawer`; `:269-345` repeats Search, Scan, Custom food, Photo meal, and Workout.
- `resources/js/Pages/Add.vue:913-948` is already the desired top-level chooser: Food, Workout, Photo. Its Food mode already exposes Search, Scan, and Custom at `:1009-1040`.
- `app/Http/Controllers/MealController.php:25-47` already canonicalizes legacy `barcode`/`search` modes to `food`, defaults missing/invalid modes to `choose`, preserves `date`, and passes `autoScan`.
- `native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php:131` emits the old Add shortcut `nativephp:///?add=1`; Scan and Workout already target `/add`.
- `resources/css/app.css:149` contains `.bottom-drawer` safe-area rules used only by the duplicate drawer.

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| PHP format | `vendor/bin/pint --dirty --format agent app/Http/Controllers/DashboardController.php native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php tests/Feature/NativePluginHooksTest.php tests/Unit/NativeShortcutHookTest.php` | exit 0; out-of-scope PHP untouched |
| PHP tests | `php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php tests/Feature/WorkoutEntryTest.php tests/Feature/NativePluginHooksTest.php tests/Unit/NativeShortcutHookTest.php tests/Unit/MealReminderWorkerTest.php` | all pass |
| Typecheck | `pnpm run type-check` | exit 0, no errors |
| Build | `pnpm run build` | exit 0 |

## Suggested executor toolkit

- Invoke `inertia-vue-development` for the AppShell navigation changes.
- Invoke `laravel-best-practices` and `pest-testing` for the compatibility redirect and tests.
- Invoke `nativephp-mobile` before changing the Android shortcut source. Do not run NativePHP build/run commands; ask the operator for the platform first if device validation becomes necessary.

## Scope

**In scope**:

- `resources/js/Layouts/AppShell.vue`
- `resources/js/Pages/Add.vue`
- `resources/css/app.css`
- `app/Http/Controllers/DashboardController.php`
- `native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php`
- `tests/Feature/DashboardTest.php`
- `tests/Feature/MealEntryTest.php`
- `tests/Feature/NativePluginHooksTest.php`
- `tests/Unit/NativeShortcutHookTest.php`
- `plans/README.md` (status only)

**Out of scope**:

- Scanner, barcode lookup, product search, photo analysis, workout submission, or their endpoints.
- `native-plugins/background-tasks/.../BackgroundTaskFunctions.kt`; its `/add?mode=food&meal=…` link stays unchanged.
- Generated `public/build` files and new navigation/test dependencies.
- A different desktop-only launcher; mobile and desktop intentionally share one route.
- `tests/Feature/WorkoutEntryTest.php` and `tests/Unit/MealReminderWorkerTest.php`; run them as read-only deep-link regression gates.

## Git workflow

- Branch: `codex/001-consolidate-add-launcher`
- One logical commit after all gates pass: `Make add page the canonical launcher`
- Stage only the explicit in-scope implementation files and `plans/README.md`; never use `git add -A` in this dirty worktree.
- Do not push or open a PR unless instructed.

## Steps

### Step 1: Characterize canonical and legacy entry points

Update tests before production code:

- In `tests/Feature/MealEntryTest.php`, assert GET `/add` renders `Add` with `mode=choose`; add `where('mode', 'custom')` to the existing custom-page case; retain legacy `barcode|search` and `mode=food&scan=1` coverage. Add a request with `date=2026-05-19` and assert that date is passed unchanged.
- In `tests/Feature/DashboardTest.php`, add a goal-backed request to `/?add=1` and expect a redirect to `/add`. Also cover `/?add=1&date=2026-05-19` redirecting to `/add?date=2026-05-19` so old links never discard an explicit date.
- In `tests/Unit/NativeShortcutHookTest.php` and `tests/Feature/NativePluginHooksTest.php`, expect `nativephp://add` for Add and retain canonical Scan/Workout assertions.

**Verify**: `php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php tests/Feature/WorkoutEntryTest.php tests/Feature/NativePluginHooksTest.php tests/Unit/NativeShortcutHookTest.php tests/Unit/MealReminderWorkerTest.php` → only the newly changed expectations fail against current production behavior; unrelated cases pass.

### Step 2: Move legacy shortcut compatibility to the request boundary

In `DashboardController::__invoke`, after the existing missing-goal onboarding guard and before parsing the date, detect exactly `$request->query('add') === '1'`. Redirect to `/add`, carrying only a filled `date` query parameter. Do not broaden compatibility to `add=true|yes|on`, do not forward `add=1`, and do not change ordinary Today requests.

This server redirect replaces `handleShortcutDrawerFlag`; it works before Vue mounts and keeps already-installed Android shortcuts functional. Accept platform-standard cold-launch Back behavior: a direct `nativephp://add` launch may exit Buff because there is no prior Today history entry. Do not recreate hidden drawer/history state solely to synthesize a Back destination.

**Verify**: `php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php` → all pass.

### Step 3: Replace both AppShell Add buttons with route links

In `AppShell.vue`:

- Add a computed `addHref`. When `page.props.summary?.date` exists, return `/add?date=<encoded date>`; otherwise return `/add`.
- Render both desktop and mobile Add buttons with `:as="Link"` and `:href="addHref"`.
- Delete `addDrawerOpen`, `drawerHistoryActive`, all drawer open/close/popstate functions, `openAddMode`, `handleShortcutDrawerFlag`, the backdrop, and drawer markup.
- Remove drawer-only icon imports and `hapticImpact`. Retain `router`, lifecycle hooks, fallback-toast behavior, resume sync, and the ordinary `handleNativeAndroidBack` history-back branch.
- Remove only event-listener setup/cleanup that served the drawer. Do not disturb native resume/flash listeners.

In `resources/css/app.css`, remove `.bottom-drawer` rules only after `rg -n "bottom-drawer" resources` confirms the AppShell drawer is their sole consumer.

**Verify**: `rg -n "addDrawerOpen|drawerHistoryActive|openAddDrawer|openAddMode|handleShortcutDrawerFlag|bottom-drawer" resources/js resources/css` → no matches; then `pnpm run type-check` → exit 0.

### Step 4: Keep one clear three-job hierarchy

Keep `Add.vue`'s existing chooser and all deep-link behavior. Change the Food subtitle to `Search, scan, or custom` so the child actions are discoverable. Do not extract a chooser component or alter mode names.

Change only the generated Add shortcut URI in `InstallNativePullRefreshCommand.php` from `nativephp:///?add=1` to `nativephp://add`. Leave Scan and Workout untouched.

**Verify**: `php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php tests/Feature/WorkoutEntryTest.php tests/Feature/NativePluginHooksTest.php tests/Unit/NativeShortcutHookTest.php tests/Unit/MealReminderWorkerTest.php` → all pass. Then run `pnpm run type-check` → exit 0.

### Step 5: Format and build

Run the formatter because PHP files changed, then repeat tests and build.

**Verify**:

```bash
vendor/bin/pint --dirty --format agent app/Http/Controllers/DashboardController.php native-plugins/native-refresh/src/Commands/InstallNativePullRefreshCommand.php tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php tests/Feature/NativePluginHooksTest.php tests/Unit/NativeShortcutHookTest.php
php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/MealEntryTest.php tests/Feature/WorkoutEntryTest.php tests/Feature/NativePluginHooksTest.php tests/Unit/NativeShortcutHookTest.php tests/Unit/MealReminderWorkerTest.php
pnpm run type-check
pnpm run build
```

Expected: every command exits 0.

## Test plan

- Default `/add` → `mode=choose`.
- Explicit date survives AppShell link construction and legacy server redirect.
- Legacy `/add?mode=barcode|search` remains `food`.
- `/add?mode=food&scan=1`, `/add?mode=custom`, and `/add?mode=workout` retain their props.
- The Android reminder worker retains `/add?mode=food&meal=<meal type>`.
- Generated native shortcut XML contains canonical Add, Scan, and Workout URIs.
- Manual 390px web smoke: Add from Today (today and historical date), Add from a non-Today page, Food → Search/Scan/Custom, Photo, Workout, browser Back.

## Done criteria

- [ ] AppShell contains no add drawer state, markup, or drawer-specific history logic.
- [ ] Desktop and mobile Add buttons link to `/add` and preserve a Today date when present.
- [ ] Old `/?add=1` links redirect to `/add` without looping.
- [ ] Search, Scan, Custom, Photo, and Workout remain reachable.
- [ ] Focused tests, typecheck, and production build pass.
- [ ] `git status --short --untracked-files=all` shows no new out-of-scope paths beyond the initial baseline; the two operator files and numbered plan files are untouched.
- [ ] `plans/README.md` is updated to DONE.

## STOP conditions

- Current drawer callers or `.bottom-drawer` consumers exist outside the files identified above.
- Product requires the desktop launcher to remain a drawer.
- Product does not accept the extra Food-selection tap for Search, Scan, and Custom.
- Historical Today dates should intentionally reset to today when Add is opened.
- A compatibility redirect for already-installed `/?add=1` shortcuts is not acceptable.
- Cold-launch Android Back must always land on Today rather than follow normal history/exit behavior.
- Completion appears to require changing scanner/native event code or adding a dependency.

## Maintenance notes

- Keep new entry methods inside Food unless they are distinct logging jobs with their own submission lifecycle.
- Reviewers should scrutinize native Back handling and selected-date preservation.
- Native device validation is deferred to a platform-specific release pass; automated XML coverage is the gate here.
