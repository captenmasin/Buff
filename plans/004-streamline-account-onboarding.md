# Plan 004: Separate registration from authenticated onboarding

> **Executor instructions**: Follow this plan step by step. Run every verification command and confirm the expected result before moving on. If a STOP condition occurs, stop and report; do not improvise. Never call the real Buff API from tests. When done, update this plan's row in `plans/README.md` unless a reviewer owns the index.
>
> **Drift check (run first)**: Run both `git diff --stat 21884fc -- routes/web.php app/Http/Controllers/AccountController.php app/Http/Controllers/OnboardingController.php resources/js/Pages/Account.vue resources/js/Pages/Onboarding.vue tests/Feature/BuffAuthenticationTest.php tests/Feature/OnboardingTest.php tests/Feature/ViewConfigurationTest.php` and `git status --short --untracked-files=all -- routes/web.php app/Http/Controllers/AccountController.php app/Http/Controllers/OnboardingController.php resources/js/Pages/Account.vue resources/js/Pages/Onboarding.vue tests/Feature/BuffAuthenticationTest.php tests/Feature/OnboardingTest.php tests/Feature/ViewConfigurationTest.php`.
> If an in-scope file changed, compare the excerpts below with live code. Any behavioral mismatch is a STOP condition.

## Status

- **Priority**: P1
- **Effort**: L
- **Risk**: HIGH
- **Depends on**: none
- **Category**: security
- **Planned at**: commit `21884fc`, 2026-08-16

## Why this matters

Registration is currently embedded as a conditional first onboarding step even though Account already owns login, recovery, reset, and verification. Onboarding also inherits the signed-in AppShell, exposing primary navigation during setup. Moving registration into Account produces a clear account lifecycle and a two-step setup, but it must preserve the device's offline-account barrier before any remote registration request.

## Current state

- `routes/web.php:22-23` already defines public GET/POST `/account/register`; GET redirects indirectly through onboarding. GET `/onboarding` is public at `:28`, while POST is account-protected at `:35`.
- `AccountController::registerPage` currently redirects to `/onboarding`; `register` validates and contacts `auth/register`, then calls `finishAuthentication` and redirects back to onboarding.
- `AccountController::finishAuthentication:218-227` rejects a different remote identity only **after** the API response. A `SyncState` may exist even when `credentials->account()` is null, so a new preflight is required before `auth/register`.
- `AccountController::destroy:203` redirects to onboarding after wiping the account.
- `resources/js/Pages/Account.vue:9` is already shell-less. Its screen union lacks `register`, and the login link at `:113` points to `/onboarding`.
- `resources/js/app.ts:20-26` assigns `AppShell` to every page without an explicit `layout` property. `Onboarding.vue` has no `defineOptions`, so setup currently shows Home/Goals/Add/Progress/Settings.
- `Onboarding.vue:25-60` derives whether registration is required and builds three or four steps. `:130-154` renders account fields; Units, Goals, and Body are separate screens.
- Account already renders shared flash messages with `role="status"`; shell-less Onboarding must render the registration-success flash itself.
- `tests/TestCase.php:16` disables `EnsureBuffAccount` globally. `BuffAuthenticationTest` re-enables it, but `OnboardingTest` currently does not; protected-route success tests must explicitly authenticate.

## Commands you will need

| Purpose | Command | Expected on success |
|---|---|---|
| Route audit | `php artisan route:list --except-vendor --path=onboarding -v` | GET and POST both use `EnsureBuffAccount` |
| PHP format | `vendor/bin/pint --dirty --format agent routes/web.php app/Http/Controllers/AccountController.php app/Http/Controllers/OnboardingController.php tests/Feature/BuffAuthenticationTest.php tests/Feature/OnboardingTest.php tests/Feature/ViewConfigurationTest.php` | exit 0; out-of-scope PHP untouched |
| PHP tests | `php artisan test --compact tests/Feature/BuffAuthenticationTest.php tests/Feature/OnboardingTest.php tests/Feature/ViewConfigurationTest.php tests/Feature/DashboardTest.php` | all pass; no real HTTP |
| Typecheck | `pnpm run type-check` | exit 0, no errors |
| Build | `pnpm run build` | exit 0 |

## Suggested executor toolkit

- Invoke `inertia-vue-development` for Account/Onboarding page state and explicit layouts.
- Invoke `laravel-best-practices` and `pest-testing` for routing, identity guards, HTTP fakes, and tests.

## Scope

**In scope**:

- `routes/web.php`
- `app/Http/Controllers/AccountController.php`
- `app/Http/Controllers/OnboardingController.php`
- `resources/js/Pages/Account.vue`
- `resources/js/Pages/Onboarding.vue`
- `tests/Feature/BuffAuthenticationTest.php`
- `tests/Feature/OnboardingTest.php`
- `tests/Feature/ViewConfigurationTest.php`
- `plans/README.md` (status only)

**Out of scope**:

- buff-server endpoints/requests, credential encryption, sync behavior, verification policy, login, password reset, and account update fields.
- Requiring verified email before onboarding; unverified authenticated accounts continue to set up Buff.
- Changing nutrition/body validation or persistence in `OnboardingController::store`.
- A new Account page/component per lifecycle state; keep the existing shared `Account.vue`.

## Git workflow

- Branch: `codex/004-streamline-account-onboarding`
- Prefer two commits because the security guard should review cleanly: `Guard local identity before registration`, then `Separate registration from onboarding`.
- Stage only the explicit in-scope implementation files and `plans/README.md`; never use `git add -A` in this dirty worktree.
- Do not push or open a PR unless instructed.

## Steps

### Step 1: Lock down the access-state matrix with HTTP fakes

Update `BuffAuthenticationTest.php`, retaining `EnsureBuffAccount` in `beforeEach` and the existing fake-client patterns. Add `Http::preventStrayRequests()` in setup so an unmatched fake can never fall through to the configured API:

- Fresh guest GET `/account/register` returns Inertia `Account` with `screen=register`.
- Fresh guest GET `/onboarding` redirects to `/account/login` once GET is protected.
- An active token blocks register GET and POST by redirecting home; both cases assert `Http::assertNothingSent()`.
- A stored credential account without an active token blocks register GET and POST.
- A `SyncState` without stored credentials also blocks register GET and POST.
- Each blocked POST calls `Http::assertNothingSent()`; this is the security regression test.
- Successful fake registration still redirects `/onboarding`, stores credentials/sync state, permits an unverified account, and then allows onboarding storage.
- Successful account deletion redirects `/account/register` after local data is wiped.

Do not copy real credentials into tests or this plan. Continue using `Http::fake` for expected calls and keep stray requests prevented; no test may contact the configured API host.

**Verify**: `php artisan test --compact tests/Feature/BuffAuthenticationTest.php` → new render/redirect assertions fail against current production behavior; unrelated authentication cases pass and no stray HTTP is attempted.

### Step 2: Add one registration preflight used by both GET and POST

In `AccountController`, add one private helper returning `?RedirectResponse`, used at the top of both `registerPage` and `register`:

- If an active token exists, redirect to `/`.
- If no token exists but either `credentials->account()` is present or `SyncState::query()->exists()`, redirect to named `account.login` with a concise message explaining that local data belongs to an existing account.
- Otherwise return null and allow registration.

This is one shared root guard, not duplicated conditionals. `register` must run it **before validation and before** `$this->api->post('auth/register', ...)`. `registerPage` returns `$this->page('register')` when unblocked and updates its return type to `Response|RedirectResponse`.

Keep `finishAuthentication`'s existing post-response identity check as defense in depth. Do not weaken or delete it.

**Verify**: `php artisan test --compact tests/Feature/BuffAuthenticationTest.php` → all registration guard cases pass and every blocked/active-token case satisfies `Http::assertNothingSent()`.

### Step 3: Make onboarding authenticated-only and simplify controller branches

Move GET `/onboarding` into the existing `EnsureBuffAccount` route group beside POST `/onboarding`.

In `OnboardingTest.php`, add setup that calls `$this->withMiddleware(EnsureBuffAccount::class)` and stores a synthetic token/account through the existing `BuffCredentialStore` test pattern before render/store/existing-goal success cases. Keep the unauthenticated redirect assertion in `BuffAuthenticationTest`; do not disable middleware to make Onboarding tests pass.

In `OnboardingController::create`:

- Remove the credential parameter and the guest/offline `SyncState` branch; middleware now owns access.
- Remove unused `BuffCredentialStore` and `SyncState` imports.
- Keep the existing-goal redirect and defaults unchanged.

In `AccountController::destroy`, redirect to named `account.register` after deletion. `LocalAccountData::wipe()` must remain before the redirect.

**Verify**: `php artisan route:list --except-vendor --path=onboarding -v` → GET and POST list `EnsureBuffAccount`; run `BuffAuthenticationTest.php` and `OnboardingTest.php` → all access cases pass.

### Step 4: Add Register mode to the existing shell-less Account page

In `Account.vue`:

- Extend the screen union/title map with `register` / `Create account`.
- Add one registration `useForm` containing name, email, password, password confirmation, and the existing browser-derived timezone.
- Add a Register card branch using the same labels, autocomplete attributes, field errors, processing disable state, and Button/Input components as Login/Reset.
- POST to `/account/register` and link back to `/account/login`.
- Point Login's `Create account` link to `/account/register`.
- Keep forgot, reset, verification polling, logout, flash, and `defineOptions({ layout: null })` unchanged.

**Verify**: `pnpm run type-check` → exit 0; `php artisan test --compact tests/Feature/BuffAuthenticationTest.php` → all pass.

### Step 5: Reduce Onboarding to Daily Targets and Body & Units

In `Onboarding.vue`:

- Add `defineOptions({ layout: null })` and wrap the content in a standalone `min-h-dvh` mobile-width layout. Because `.app-main` supplied safe-area padding, include explicit `pt-[calc(env(safe-area-inset-top,0px)+2.5rem)]` and `pb-[calc(env(safe-area-inset-bottom,0px)+2.5rem)]` (or exact equivalent) plus the existing horizontal spacing/background.
- Remove registration form, timezone, `buff` page state, `requiresRegistration`, conditional steps, Account card, and unused imports.
- Use exactly two labels: `Daily Targets` and `Body & Units`.
- First step contains the existing calorie/macro inputs and mismatch feedback. Render server errors for calories, protein, carbs, and fat beside their fields.
- Second step contains Weight Unit and Height Unit selectors first, then height, target weight, and target body fat. Reuse existing `bodyUnits.ts` conversion/watcher behavior so changing units does not alter canonical values.
- Next advances locally only when macros match; Start submits the same complete payload to `/onboarding`. Give the final `form.post` an `onError` callback: if any calorie/protein/carbs/fat error is returned, set `step` back to Daily Targets so the error is visible; body/unit errors stay on Body & Units.
- Read shared flash from `usePage` and render it near the header with `role="status"`; this preserves `Account created…` feedback that AppShell used to show.
- Back is disabled on the first step; successful completion still redirects home.

In `ViewConfigurationTest.php`, add the smallest available source contract: read Account and Onboarding component files, assert each contains an explicit `defineOptions({ layout: null })`, and assert Onboarding contains both `safe-area-inset-top` and `safe-area-inset-bottom`. This avoids adding a browser-test dependency solely for layout metadata.

**Verify**: `pnpm run type-check` → exit 0. Then run `pnpm run build` → exit 0. Then run `php artisan test --compact tests/Feature/OnboardingTest.php tests/Feature/ViewConfigurationTest.php` → all pass.

### Step 6: Run the complete security/regression gate

**Verify**:

```bash
vendor/bin/pint --dirty --format agent routes/web.php app/Http/Controllers/AccountController.php app/Http/Controllers/OnboardingController.php tests/Feature/BuffAuthenticationTest.php tests/Feature/OnboardingTest.php tests/Feature/ViewConfigurationTest.php
php artisan test --compact tests/Feature/BuffAuthenticationTest.php tests/Feature/OnboardingTest.php tests/Feature/ViewConfigurationTest.php tests/Feature/DashboardTest.php
pnpm run type-check
pnpm run build
```

Expected: every command exits 0; HTTP assertions prove all remote calls are fake/expected.

## Test plan

- State matrix: fresh guest, active token, stored account/no token, SyncState/no credentials, authenticated/no goal, authenticated/existing goal.
- Remote boundary: blocked registration POST sends zero HTTP requests; successful fake registration sends exactly the expected endpoint/payload shape.
- Lifecycle: registration → onboarding → home; unverified account remains allowed; deletion → registration.
- Onboarding: authenticated render defaults, two-step payload persistence, unit conversion, macro mismatch server rejection returning visibly to Daily Targets, existing-goal redirect.
- Manual 390px: Account register validation/links; onboarding has no app navigation; top/bottom content clears simulated safe areas; flash visible; Back/Next/Start; kg/lb and cm/in conversion.

## Done criteria

- [ ] Registration is an Account screen at `/account/register`.
- [ ] Both register GET and POST preflight local credential-account and SyncState identity before any remote call.
- [ ] GET and POST onboarding are protected by `EnsureBuffAccount`.
- [ ] Onboarding explicitly opts out of AppShell and has exactly two steps.
- [ ] Shell-less onboarding restores top/bottom safe-area padding and returns target validation errors to the visible first step.
- [ ] Registration, verification, credentials, sync, and store payload contracts remain unchanged.
- [ ] Account deletion redirects to registration after local wipe.
- [ ] Focused tests, typecheck, and build pass with no real HTTP.
- [ ] No new out-of-scope paths appear beyond the initial status baseline; operator files and numbered plans are untouched.
- [ ] `plans/README.md` is updated to DONE.

## STOP conditions

- Anonymous/offline onboarding must remain supported; authenticated-only routing conflicts with that product requirement.
- A register POST can reach the remote API while either stored account metadata or SyncState exists.
- Product wants deletion to end at Login rather than Register.
- Removing AppShell would lose safe-area/layout or flash feedback without a local replacement.
- The remote registration request/response, credential storage, identity binding, or verification policy must change.
- Browser-level assertions are mandated; adding Pest Browser/Playwright needs separate dependency approval.

## Maintenance notes

- The local identity preflight and `finishAuthentication` check protect different moments; keep both.
- Reviewers should scrutinize middleware placement, `Http::assertNothingSent()`, and every redirect in the state matrix.
- If registration fields change later, update only Account; onboarding should remain profile setup.
