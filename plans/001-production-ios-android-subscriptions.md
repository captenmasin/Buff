# Plan 001: Put AI meal analysis behind production iOS and Android subscriptions

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan in
> `plans/README.md`.
>
> Work in both repositories: `/Users/mason/Sites/Buff` is the NativePHP mobile
> client and `/Users/mason/Sites/buff-server` is its Laravel API. Read each
> repository's `AGENTS.md` before editing. Invoke the `nativephp-mobile`,
> `laravel-best-practices`, `inertia-vue-development`, and `pest-testing` skills
> if they are available. Search the installed Laravel/Inertia documentation
> before each corresponding code phase.
>
> **Drift check (run first)**:
>
> ```sh
> git diff --stat 9b8de32..HEAD -- app config resources/js routes tests native-plugins composer.json composer.lock package.json pnpm-lock.yaml .env.example
> git -C ../buff-server diff --stat 519ce32..HEAD -- app config database routes tests composer.json composer.lock .env.example README.md
> ```
>
> If either command reports an in-scope change, compare the "Current state"
> excerpts against the live code before proceeding. A material mismatch is a
> STOP condition.

## Status

- **Priority**: P1
- **Effort**: L — approximately 8–12 engineering days plus store review time
- **Risk**: HIGH — money, native store state, account identity, and a server-side paywall
- **Depends on**: none
- **Category**: direction
- **Planned at**: client commit `9b8de32`, server commit `519ce32`, 2026-08-26

## Why this matters

The AI meal calculator invokes a paid AI provider but is currently available to
every authenticated user. The UI can advertise a subscription, but only the API
can safely enforce it because the same analysis service is also called through
MCP. This plan adds one cross-platform entitlement, uses RevenueCat to normalize
the two store lifecycles, and keeps Buff's server authoritative before any AI
work or photo storage begins.

RevenueCat is deliberate scope reduction, not a new billing abstraction. Direct
StoreKit and Google Play verification would require two receipt-verification
stacks, notification formats, acknowledgement rules, reconciliation paths, and
security reviews. If RevenueCat or its Pro webhook plan is not acceptable, stop:
that is a different plan.

## Product contract and fixed decisions

- The only entitlement is `ai_meal_analysis`.
- App Store Connect and Play Console each provide monthly and annual recurring
  products. Product IDs, prices, trials, display names, and legal URLs are
  operator decisions; do not invent or hard-code them.
- The existing daily quota remains in force for subscribers. A subscription
  grants access; it does not make analysis unlimited.
- The API is authoritative. A native purchase/restore result never unlocks the
  feature by itself. After native success, the client asks the API to refresh
  RevenueCat state and unlocks only from that response.
- `MealAnalysisService::analyze()` and `followUp()` require the entitlement.
  `show`, `confirm`, `destroy`, meal-photo retrieval, history, and ordinary food
  logging remain available after expiry so users can access and clean up data.
- Non-subscribers receive HTTP 403 with `code: subscription_required`.
- Users must be signed into Buff before purchase. RevenueCat is configured only
  with a server-generated, non-guessable custom App User ID. Never configure an
  anonymous RevenueCat user and never call RevenueCat `logOut()`; switch Buff
  accounts with RevenueCat `logIn(newAppUserId)`.
- Use RevenueCat's **Transfer to new App User ID** restore behavior. This is the
  vendor-recommended behavior for account-based apps that want reliable restore.
  A transfer removes the entitlement from the previous Buff account, and the
  webhook refresh must update both sides.
- Deleting a Buff account does not cancel an App Store or Google Play
  subscription. Show the management link and this warning before deletion.
- Keep the RevenueCat secret API key, webhook authorization value, and webhook
  signing secret only on `buff-server`. The iOS and Android SDK keys are public
  build configuration and must be platform-specific.
- Keep `SUBSCRIPTIONS_ENFORCE_AI_MEAL_ANALYSIS=false` until both production app
  versions are approved and available. It is also the emergency kill switch for
  a billing incident; entitlement status itself must never fail open.

## Current state

### NativePHP client: `/Users/mason/Sites/Buff`

- `routes/web.php:98-100` exposes the local proxy endpoints:

  ```php
  Route::post('/meal-analyses', [MealAnalysisController::class, 'store']);
  Route::post('/meal-analyses/{analysis}/follow-up', [MealAnalysisController::class, 'followUp']);
  Route::delete('/meal-analyses/{analysis}', [MealAnalysisController::class, 'destroy']);
  ```

- `app/Http/Controllers/MealAnalysisController.php:15-35` validates local image
  input and delegates to `BuffApiClient`.
- `app/Services/BuffApiClient.php:166-184` already preserves an API error's
  `code`, and `app/Http/Controllers/Controller.php:11-27` already maps a generic
  forbidden result back to HTTP 403. Do not add a second error protocol.
- `app/Services/BuffCredentialStore.php:57-60` already updates the encrypted
  cached account, and `app/Http/Middleware/HandleInertiaRequests.php:25-29`
  shares it as `buff.account` to every Inertia page.
- `resources/js/Components/Add/AddChooser.vue:40-44` is the Photo entry point.
- `resources/js/Pages/Add.vue:721-748` submits analysis and maps server error
  codes. Extend this existing map for `subscription_required`.
- `resources/js/Pages/Settings.vue:145-168` is the settings hub. Its existing
  `SettingsGroup` and `SettingsRow` components are the subscription entry-point
  pattern to reuse.
- `composer.json` already registers local NativePHP plugins as Composer path
  repositories. `native-plugins/apple-health/nativephp.json` and
  `native-plugins/health-connect/nativephp.json` are the iOS and Android manifest
  exemplars. `app/Providers/NativeServiceProvider.php:28-38` explicitly
  registers native plugins.
- `tests/Unit/AppleHealthPluginTest.php` is the existing lightweight manifest
  and native-source contract-test pattern.
- The project already uses `#nativephp` and its event API in
  `resources/js/Pages/Add.vue`; extend `resources/js/vite-env.d.ts` rather than
  introducing an untyped global.

### API: `/Users/mason/Sites/buff-server`

- `app/Services/MealAnalysisService.php:30-40` enters the shared analysis path:

  ```php
  public function analyze(User $user, array $photos, ?string $note): MealAnalysis
  {
      // validation...
      return Cache::lock("meal-analysis:user:{$user->id}", 120)
          ->block(5, function () use ($user, $photos, $note): MealAnalysis {
              $this->ensureQuotaAvailable($user);
  ```

- `app/Services/MealAnalysisService.php:112-126` performs another paid AI call
  from `followUp()`.
- `app/Services/McpActionService.php:248-252` calls the same `analyze()` method,
  so a guard there covers both HTTP and MCP paths.
- `app/Http/Controllers/Api/V1/MealAnalysisController.php:29-70` keeps view,
  confirm, and delete separate from AI generation. Do not put subscription
  middleware around the entire controller.
- `app/Models/User.php:18-71` has no billing identifier or entitlement state.
  The primary key is a sequential integer, which must not be sent to RevenueCat.
- `app/Http/Resources/UserResource.php:15-24` is the single account response
  shape used by login, resume, rotation, and account fetch.
- `routes/api.php:34-65` contains authenticated versioned routes. The RevenueCat
  webhook must be public but independently authenticated and must not sit inside
  the Sanctum group.
- `config/buff.php:5-10` owns AI quota/provider configuration.
- `config/services.php` is the existing third-party credential configuration
  file. The current `apple` keys are Sign in with Apple credentials and must not
  be reused for subscriptions.
- `.env.example:58` uses the database queue, and `README.md:38-48` already makes
  a queue worker and shared cache production requirements. Use those rather
  than adding another worker system.
- `tests/Feature/MealAnalysisTest.php` and the MCP feature tests are the existing
  API and shared-service regression patterns. Use Laravel `Http::fake()` and
  `Queue::fake()` for provider and webhook tests.

## Commands you will need

Run commands from the repository named in the first column.

| Repository | Purpose | Command | Expected on success |
|------------|---------|---------|---------------------|
| `buff-server` | Targeted subscription tests | `php artisan test --compact --no-interaction tests/Feature/SubscriptionTest.php tests/Feature/RevenueCatWebhookTest.php tests/Feature/MealAnalysisTest.php tests/Feature/McpDraftAndMediaToolsTest.php` | all pass |
| `buff-server` | Full tests | `composer run test` | exit 0, all pass |
| `buff-server` | PHP format | `vendor/bin/pint --dirty --format agent` | exit 0 |
| `Buff` | Targeted PHP tests | `php artisan test --compact --no-interaction tests/Feature/SubscriptionTest.php tests/Feature/MealPhotoIntegrationTest.php tests/Feature/SettingsTest.php tests/Unit/SubscriptionsPluginTest.php` | all pass |
| `Buff` | Frontend tests | `pnpm test:frontend` | exit 0, all pass |
| `Buff` | Type check | `pnpm type-check` | exit 0, no errors |
| `Buff` | PHP format | `vendor/bin/pint --dirty --format agent` | exit 0 |
| `Buff` | Plugin validation | `php artisan native:plugin:validate --no-interaction` | subscription plugin is valid |
| `Buff` | Plugin discovery | `php artisan native:plugin:list --no-interaction` | `buff/in-app-purchases` and its bridge functions appear |

Do not run native build/run commands as an agent. Step 7 lists the commands the
operator must run manually on each platform.

## Suggested executor toolkit

- Search version-matched Laravel and Inertia documentation before editing, as
  required by the repositories' `AGENTS.md` files.
- NativePHP Mobile 4 plugin documentation:
  - <https://nativephp.com/docs/mobile/4/plugins/using-plugins>
  - <https://nativephp.com/docs/mobile/4/plugins/bridge-functions>
  - <https://nativephp.com/docs/mobile/4/plugins/events>
  - <https://nativephp.com/docs/mobile/4/plugins/permissions-dependencies>
  - <https://nativephp.com/docs/mobile/4/plugins/validation-testing>
- RevenueCat integration contracts:
  - <https://www.revenuecat.com/docs/getting-started/configuring-sdk>
  - <https://www.revenuecat.com/docs/customers/identifying-customers>
  - <https://www.revenuecat.com/docs/getting-started/making-purchases>
  - <https://www.revenuecat.com/docs/getting-started/restoring-purchases>
  - <https://www.revenuecat.com/docs/projects/restore-behavior>
  - <https://www.revenuecat.com/docs/integrations/webhooks>
  - <https://www.revenuecat.com/docs/integrations/webhooks/event-types-and-fields>
  - <https://www.revenuecat.com/docs/api-v1>
- Platform console references:
  - <https://developer.apple.com/documentation/storekit/in-app-purchase>
  - <https://developer.android.com/google/play/billing/integrate>

## Scope

The names below are exact unless a migration timestamp or platform-native source
layout is generated by an existing Artisan/NativePHP command.

**In scope — API (`/Users/mason/Sites/buff-server`)**:

- `.env.example`
- `README.md`
- `config/buff.php`
- `config/services.php`
- `routes/api.php`
- `app/Models/User.php`
- `app/Http/Resources/UserResource.php`
- `app/Services/MealAnalysisService.php`
- `app/Services/RevenueCatService.php` (create)
- `app/Http/Controllers/Api/V1/SubscriptionController.php` (create)
- `app/Http/Controllers/Api/V1/RevenueCatWebhookController.php` (create)
- `app/Jobs/RefreshRevenueCatEntitlement.php` (create)
- `database/migrations/*_add_revenuecat_subscription_fields_to_users_table.php` (create via Artisan)
- `database/factories/UserFactory.php`
- `tests/Feature/SubscriptionTest.php` (create via Artisan/Pest)
- `tests/Feature/RevenueCatWebhookTest.php` (create via Artisan/Pest)
- `tests/Feature/MealAnalysisTest.php`
- `tests/Feature/McpDraftAndMediaToolsTest.php`

**In scope — NativePHP client (`/Users/mason/Sites/Buff`)**:

- `.env.example`
- `composer.json`
- `composer.lock`
- `app/Providers/NativeServiceProvider.php`
- `app/Services/BuffApiClient.php`
- `app/Services/BuffCredentialStore.php`
- `app/Http/Controllers/SubscriptionController.php` (create)
- `app/Http/Controllers/AccountController.php`
- `routes/web.php`
- `resources/js/vite-env.d.ts`
- `resources/js/subscriptions.ts` (create)
- `resources/js/Pages/Settings.vue`
- `resources/js/Pages/Settings/Subscription.vue` (create)
- `resources/js/Pages/Add.vue`
- `resources/js/Components/Add/AddChooser.vue`
- `native-plugins/in-app-purchases/**` (create, matching existing local-plugin layout)
- `tests/Feature/SubscriptionTest.php` (create via Artisan/Pest)
- `tests/Feature/MealPhotoIntegrationTest.php`
- `tests/Feature/SettingsTest.php`
- `tests/Unit/SubscriptionsPluginTest.php` (create via Artisan/Pest)
- `tests/subscriptions.test.ts` (create)

**Out of scope**:

- Stripe, Paddle, RevenueCat Web Billing, or browser checkout.
- A generic role, plan, feature-flag, or entitlement package.
- Paywalling barcode search, custom meals, recipes, workouts, body metrics, or
  already-created meal-analysis media.
- Direct App Store Server API or Google Play Developer API verification. Do not
  mix half of a direct-store architecture with RevenueCat.
- RevenueCat Paywalls/Targeting/A/B tests. Render the existing Vue design system.
- New analytics, attribution, coupon, family-plan, promo-code, or lifetime-purchase
  systems.
- Changing subscription price or product identifiers after the operator has
  approved them.
- Modifying generated Xcode/Gradle projects by hand; native dependencies and
  capabilities belong in the NativePHP plugin manifest.

## Git workflow

- Use branch `codex/production-subscriptions` in both repositories.
- Both repositories currently use short `WIP` commit subjects. Keep commits
  small and descriptive instead: one server commit, one native-plugin commit,
  one client UX commit, and one verification/rollout-doc commit.
- Do not include unrelated working-tree changes in a commit.
- Do not push or open a PR unless the operator explicitly asks.

## Steps

### Step 1: Complete the operator/store readiness gate

Before source changes, the operator must confirm all of the following. Record
the chosen non-secret identifiers in the implementation PR description, not in
new planning documentation:

1. RevenueCat Pro is approved because webhooks are a Pro integration.
2. App Store Connect agreements, banking, and tax setup are active. One
   subscription group contains approved monthly and annual products, complete
   localization, review notes/screenshots, and sandbox testers.
3. Play Console payments setup is active. Monthly and annual subscriptions have
   active base plans, required regions, license testers, and an internal test
   track.
4. One RevenueCat project contains the iOS and Android apps. One entitlement is
   named exactly `ai_meal_analysis`; one current offering maps monthly and annual
   packages to both stores.
5. Production restore behavior is `Transfer to new App User ID`. Use a sandbox
   override only when explicitly testing another behavior.
6. The canonical HTTPS API origin, privacy policy, terms, support URL, recurring
   billing copy, product IDs/prices/trials, iOS public SDK key, Android public
   SDK key, secret API key, webhook authorization value, and HMAC signing secret
   are available to the appropriate deployment/build owners.
7. The privacy disclosure covers RevenueCat's pseudonymous customer ID and
   purchase metadata. The account-deletion policy explicitly says deleting Buff
   does not cancel a store subscription.

**Verify**: with non-empty sandbox/test values exported locally, run the command
below. It prints key names only, never values, and must exit 0:

```sh
php -r '$names=["REVENUECAT_SECRET_API_KEY","REVENUECAT_WEBHOOK_AUTHORIZATION","REVENUECAT_WEBHOOK_SIGNING_SECRET","VITE_REVENUECAT_IOS_PUBLIC_SDK_KEY","VITE_REVENUECAT_ANDROID_PUBLIC_SDK_KEY"]; foreach($names as $name){if(!is_string(getenv($name))||getenv($name)===""){fwrite(STDERR,"missing $name\n");exit(1);} echo "$name set\n";}'
```

### Step 2: Add the server-owned customer identity and entitlement projection

In `/Users/mason/Sites/buff-server`, use Artisan with `--no-interaction` to
create the migration and Pest feature test. The migration adds these columns to
`users`:

```sh
php artisan make:migration add_revenuecat_subscription_fields_to_users_table --table=users --no-interaction
php artisan make:test --pest SubscriptionTest --no-interaction
```

- `revenuecat_app_user_id`: UUID, unique and non-null after backfill.
- `ai_meal_analysis_entitled_until`: nullable timestamp.
- `subscription_product_id`: nullable string.
- `subscription_store`: nullable string.
- `subscription_management_url`: nullable text.
- `subscription_checked_at`: nullable timestamp.

Backfill every existing user with a fresh UUID before applying the non-null and
unique constraints. Add a `User` creating hook so all future users and factory
records receive a UUID without controller duplication. Add datetime casts and:

```php
public function hasAiMealAnalysisEntitlement(): bool
```

The method returns true only when `ai_meal_analysis_entitled_until` exists and
is in the future. This product has only recurring subscriptions; a non-expiring
entitlement is a STOP condition because the data model would need an explicit
lifetime state.

Extend `UserResource` with:

```text
revenuecat_app_user_id
subscription.entitled
subscription.expires_at
subscription.product_id
subscription.store
subscription.management_url
```

Do not expose `subscription_checked_at` or any provider secret. Because all auth
responses already use `UserResource`, do not edit every auth controller action.
Update `UserFactory` only as required to keep its defaults explicit and stable.

**Verify**:

```sh
php artisan test --compact --no-interaction tests/Feature/SubscriptionTest.php
```

Expected: tests prove existing-user backfill, new-user UUID assignment and
uniqueness, active/expired entitlement calculation, and the exact resource
shape; all pass.

### Step 3: Add one RevenueCat status refresh path and secure webhook ingestion

Configure `services.revenuecat` in `config/services.php` with only environment
lookups for the secret API key, webhook authorization value, webhook signing
secret, and API base URL. Add the corresponding blank keys to `.env.example`.
In `config/buff.php`, add the fixed entitlement identifier and
`SUBSCRIPTIONS_ENFORCE_AI_MEAL_ANALYSIS`, defaulting false. Do not make product
IDs application configuration; RevenueCat offerings own that mapping.

Create one concrete `RevenueCatService`; do not add an interface or factory. It
must use Laravel's HTTP client with an explicit base URL, bearer secret,
`acceptJson()`, connect timeout, total timeout, bounded retry, and `throw()`.
Its `refresh(User $user): User` method must:

1. request the RevenueCat subscriber for the user's UUID;
2. read only the `ai_meal_analysis` entitlement;
3. compare its expiry with the server clock and treat active trials/grace periods
   exactly as RevenueCat represents them;
4. atomically update the entitlement expiry, product ID, store, management URL,
   and `subscription_checked_at`;
5. clear stale entitlement fields when the entitlement is expired, revoked,
   refunded, transferred away, or absent;
6. leave the last known projection untouched on transport/malformed-response
   failure and throw a sanitized service-unavailable error.

Add authenticated `POST /api/v1/subscription/refresh`, returning a fresh
`UserResource`. Rate-limit it. This endpoint is called after purchase/restore
and when the subscription settings page opens; it does not accept status,
expiry, product, store, or receipt data from the client.

Generate the new Laravel classes/tests rather than hand-creating framework
boilerplate:

```sh
php artisan make:class Services/RevenueCatService --no-interaction
php artisan make:controller Api/V1/SubscriptionController --no-interaction
php artisan make:controller Api/V1/RevenueCatWebhookController --invokable --no-interaction
php artisan make:job RefreshRevenueCatEntitlement --no-interaction
php artisan make:test --pest RevenueCatWebhookTest --no-interaction
```

Add public `POST /api/v1/webhooks/revenuecat`, outside Sanctum and under a
reasonable IP rate limit. The invokable controller must, before JSON parsing:

- compare the configured Authorization header using `hash_equals`;
- parse `X-RevenueCat-Webhook-Signature` as `t=...,v1=...`;
- reject timestamps outside a five-minute window;
- recompute HMAC-SHA256 over `"<timestamp>.<raw request body>"` and compare in
  constant time;
- validate the event ID/type and cap/filter candidate identities to valid UUIDs.

Candidate identities come from `app_user_id`, `original_app_user_id`, `aliases`,
`transferred_from`, and `transferred_to`. Queue one
`RefreshRevenueCatEntitlement` job with the event ID, event type, environment,
and deduplicated UUID list, then return HTTP 200 immediately. The job must
implement Laravel `ShouldBeUnique`, use the event ID as `uniqueId()`, and refresh
every matching Buff user by that user's own stored UUID. The only side effect is
the idempotent authoritative refresh; unknown users are a sanitized log line,
not a failure. Set bounded job attempts/backoff and rely on the existing failed
jobs/worker operations.

This deliberately follows RevenueCat's recommendation to fetch current customer
state after a webhook instead of encoding every event transition. It makes
duplicates and out-of-order notifications harmless.

**Verify**:

```sh
php artisan test --compact --no-interaction tests/Feature/SubscriptionTest.php tests/Feature/RevenueCatWebhookTest.php
```

Expected: `Http::fake()` covers active, grace/trial, expired, absent,
transferred/revoked, malformed, timeout, and provider-error responses;
`Queue::fake()` proves missing/bad authorization, bad HMAC, stale timestamps,
and malformed payloads are rejected, while valid/duplicate/transfer/test events
queue the correct unique refresh work without logging payloads or secrets.

### Step 4: Enforce the entitlement at the shared AI boundary

Add one private entitlement guard in `MealAnalysisService`. It returns when the
rollout switch is false or the user has the active entitlement. Otherwise it
throws an `HttpResponseException` with HTTP 403, a stable user-facing message,
and `code: subscription_required`.

Call it at the very start of both `analyze()` and `followUp()`, before acquiring
a cache lock, storing photos, or calling the AI provider. Do not place the guard
on `quotaRemaining()`, `show`, `confirm`, `destroy`, or photo retrieval. Do not
add controller middleware: MCP reaches the service directly.

Extend existing tests to prove:

- enforcement off preserves current behavior;
- enforcement on rejects inactive, expired, revoked, and transferred-away users;
- active trial, paid, and grace-period projections allow analysis;
- rejection happens before photo storage and before the faked AI agent runs;
- follow-up is also rejected before the AI agent runs;
- show, confirm, delete, and existing photo access still work after expiry;
- MCP `analyze-meal` receives an actionable subscription-required failure from
  the same service guard;
- the subscriber's existing daily quota still applies.

**Verify**:

```sh
php artisan test --compact --no-interaction tests/Feature/MealAnalysisTest.php tests/Feature/McpDraftAndMediaToolsTest.php tests/Feature/SubscriptionTest.php
vendor/bin/pint --dirty --format agent
```

Expected: all tests pass; Pint exits 0; no AI fake or storage write occurs in
the inactive cases.

### Step 5: Build the smallest NativePHP RevenueCat bridge

Dependency/vendor approval from Step 1 authorizes the following change. Create
`native-plugins/in-app-purchases` as a local Composer package named
`buff/in-app-purchases`, matching `native-plugins/apple-health` and
`native-plugins/health-connect`. Add the path repository and package requirement
to client `composer.json`, update `composer.lock`, and register its service
provider in `NativeServiceProvider::plugins()`.

Start with NativePHP's generator, then trim its output to the five operations and
events required below:

```sh
php artisan native:plugin:create buff/in-app-purchases --namespace='Buff\InAppPurchases' --path=native-plugins/in-app-purchases --no-interaction
```

The plugin manifest must support iOS and Android, retain the app's current
minimums (iOS 18 and Android API 33), declare Apple's In-App Purchase capability,
and declare RevenueCat through Swift Package Manager on iOS and the official
Gradle dependency on Android. Pin current mutually compatible SDK versions from
the official installation docs; do not copy an old version from this plan.

Expose only these native operations:

```text
Subscriptions.Configure(api_key, app_user_id)
Subscriptions.LoadOffering
Subscriptions.Purchase(package_identifier)
Subscriptions.Restore
Subscriptions.CustomerInfo
```

`Configure` rejects missing/invalid public key or UUID and is idempotent for the
same account. If already configured for a different signed-in Buff account, use
RevenueCat `logIn(newAppUserId)`; never call `logOut()`. `LoadOffering` returns
only the current offering's package identifier, product identifier, localized
price, localized period, and introductory/trial display data required by the
page. Never hard-code a price in Vue.

Purchase and restore are asynchronous UI operations. Return immediately and
dispatch typed NativePHP events on the main/UI thread for:

```text
OfferingLoaded | OfferingFailed
PurchaseCompleted | PurchaseCancelled | PurchaseFailed
RestoreCompleted | RestoreFailed
```

Keep event payloads minimal: package/product ID, cancellation/error category,
and whether RevenueCat CustomerInfo reports `ai_meal_analysis` active. Never
include store receipts, transaction tokens, RevenueCat secret keys, or full
provider objects. RevenueCat owns StoreKit transaction finishing and Google Play
acknowledgement; do not reimplement either.

Add TypeScript declarations in `resources/js/vite-env.d.ts` and a small
`resources/js/subscriptions.ts` wrapper around `#nativephp`. It owns bridge/event
names, normalizes native error categories, registers/unregisters listeners, and
returns an unsupported state in a browser. Do not add a state-management store.

**Verify**:

```sh
composer validate --no-check-publish
php artisan native:plugin:validate --no-interaction
php artisan native:plugin:list --no-interaction
php artisan test --compact --no-interaction tests/Unit/SubscriptionsPluginTest.php
pnpm type-check
```

Expected: all commands exit 0; the plugin list shows both platforms, five bridge
functions, and the declared events; tests assert the manifest, capabilities,
dependency declarations, parameter validation, main-thread event dispatch, and
absence of receipt/secret fields.

### Step 6: Add the server-refreshed subscription page and paywall UX

Add local client routes:

```text
GET  /settings/subscription
POST /subscription/refresh
```

Generate the controller and Pest test with the framework commands:

```sh
php artisan make:controller SubscriptionController --no-interaction
php artisan make:test --pest SubscriptionTest --no-interaction
```

The local `SubscriptionController` proxies the authenticated API refresh through
the existing `BuffApiClient`, updates `BuffCredentialStore` with the returned
user resource only on success, and returns the existing normalized error shape.
Do not accept client entitlement fields. Add a `BuffApiClient` convenience
method only if it removes repeated request code.

Create `resources/js/Pages/Settings/Subscription.vue` using the existing settings
header, row, card, button, and typography components. The page must:

- require a Buff account; otherwise link to sign in;
- show cached server entitlement immediately, then call the API refresh;
- on iOS/Android, configure the bridge with the platform public SDK key and the
  server-provided RevenueCat UUID, then load the current offering;
- render monthly/annual options using native localized price/period text;
- show recurring-billing/trial copy, links to privacy and terms, purchase,
  Restore Purchases, and Manage Subscription actions;
- after native purchase/restore success, call `/subscription/refresh` and show
  success/unlock only when the returned server resource is entitled;
- treat user cancellation as neutral, pending/unavailable/network cases as
  recoverable, and announce errors/status with accessible live regions;
- remove native event listeners on unmount and prevent duplicate purchase taps;
- on web/unsupported builds, explain that purchase and restore are available in
  the iOS/Android app without rendering dead native buttons.

Add a Subscription row to the settings hub. Show status such as Active or
Inactive from the cached server projection, not native CustomerInfo.

Mark the Photo tile in `AddChooser.vue` with an accessible Pro/lock indicator
when inactive. In `Add.vue`, selecting Photo while cached inactive routes to the
subscription page. Cached state is only an early UX shortcut: if analysis or
follow-up receives `subscription_required`, clear the stale assumption, show a
subscription CTA, and route on user action. Do not silently redirect away from
an unsaved form.

Update account deletion copy in `Settings.vue`: deleting Buff data does not
cancel the store subscription, and the user should use Manage Subscription
first if they want to cancel. Do not call a purchase SDK from the deletion
controller.

Extract only pure mapping/state helpers worth testing into
`resources/js/subscriptions.ts`; do not split the page into speculative
components.

**Verify**:

```sh
php artisan test --compact --no-interaction tests/Feature/SubscriptionTest.php tests/Feature/MealPhotoIntegrationTest.php tests/Feature/SettingsTest.php
pnpm test:frontend
pnpm type-check
vendor/bin/pint --dirty --format agent
```

Expected: all pass. Tests cover account-resource persistence, refresh success
and failure, preserved `subscription_required`, signed-out and browser states,
localized offering mapping, purchase cancellation/pending/failure, server-only
unlock, listener cleanup, Photo CTA behavior, and account-deletion warning.

### Step 7: Run sandbox/device acceptance on both stores

Automated checks cannot prove native store behavior. The operator—not the
agent—must build and run each platform manually. For iOS:

```sh
pnpm run build -- --mode=ios
php artisan native:run ios
```

For Android:

```sh
pnpm run build -- --mode=android
php artisan native:run android
```

Use App Store sandbox/TestFlight and Play internal testing/license testers on
physical devices. Record one pass/fail result per platform for:

- new monthly purchase and new annual purchase;
- trial/intro offer when configured;
- user-cancelled purchase;
- pending purchase on Android;
- renewal, billing retry/grace period, expiration, refund/revocation;
- restore after reinstall;
- switching between two Buff accounts without creating a RevenueCat anonymous
  ID;
- restore/transfer to a new Buff account and immediate removal from the old one;
- the same Buff account on iOS and Android seeing the same entitlement;
- app offline/provider unavailable after previous good status;
- duplicate and out-of-order webhook deliveries;
- Photo and MCP analysis blocked while ordinary food logging and access to
  existing analysis/photo data remain available.

Inspect API logs and RevenueCat delivery history. They must contain event IDs,
types, environment, and sanitized failures only—never authorization headers,
HMAC secrets, API keys, receipts, tokens, or full webhook bodies.

**Verify** after both manual passes:

```sh
php artisan native:plugin:validate --no-interaction
pnpm test:frontend
pnpm type-check
git status --short
git -C ../buff-server status --short
```

Expected: automated checks pass and only intentional in-scope files are shown.
Do not proceed with a failing or untested store state.

### Step 8: Deploy in a reversible order and enable enforcement

1. Deploy `buff-server` configuration, migration, API, webhook, queue job, and
   mobile refresh endpoint with enforcement false.
2. Confirm production has the canonical HTTPS URL, shared cache, queue worker,
   failed-job alerting, RevenueCat secret API key, webhook authorization value,
   webhook signing secret, and environment configuration.
3. Register the production webhook for production events, send a RevenueCat test
   event, and confirm HTTP 200 plus successful queued processing.
4. Release iOS and Android builds with their platform-specific public SDK keys.
   Complete App Review/Play review and staged production rollout.
5. Confirm both live store versions can purchase, restore, refresh, and manage
   subscriptions against production.
6. Set `SUBSCRIPTIONS_ENFORCE_AI_MEAL_ANALYSIS=true` and redeploy/reload server
   configuration. Do not enable while either platform's production build is
   unavailable.
7. For the first 48 hours, monitor webhook rejection/job failure counts,
   subscription refresh latency/errors, `subscription_required` responses,
   RevenueCat active-customer counts, store refund/revocation tests, and AI
   request volume/cost. Roll back enforcement—not subscription data—if the
   billing path is unhealthy.

Update `buff-server/README.md` only with the new environment key inventory,
queue/webhook operational requirement, safe rollout order, and a no-secret test
webhook command or dashboard action. Do not create a second operations guide.

**Verify** from each repository before production enablement:

```sh
composer run test
vendor/bin/pint --dirty --format agent
```

And from `Buff`:

```sh
pnpm test:frontend
pnpm type-check
php artisan native:plugin:validate --no-interaction
```

Expected: every command exits 0, the production RevenueCat test delivery is 200,
the queue has no failed subscription job, and both live app versions have a
completed production smoke test.

## Test plan

### Automated API tests

- `tests/Feature/SubscriptionTest.php`
  - migration/backfill and UUID uniqueness;
  - resource contract and absence of secrets;
  - authenticated refresh ignores client-supplied status and queries RevenueCat;
  - active paid/trial/grace, expired, missing, refunded/revoked, transferred,
    malformed, timeout, and provider-error status projection;
  - old projection survives provider transport failure;
  - rollout switch and entitlement access semantics.
- `tests/Feature/RevenueCatWebhookTest.php`
  - missing/wrong Authorization, malformed signature, wrong HMAC, stale/future
    timestamp, malformed/oversized identity data;
  - exact raw-body HMAC validation;
  - common lifecycle, TEST, alias, and TRANSFER payload identity extraction;
  - duplicate event uses the same unique job key;
  - unknown user and job retry behavior remain sanitized and idempotent.
- `tests/Feature/MealAnalysisTest.php`
  - analyze/follow-up allow and deny paths, no provider/storage work on denial,
    existing quota preserved, non-generating operations remain accessible.
- `tests/Feature/McpDraftAndMediaToolsTest.php`
  - MCP analysis is denied by the same service guard and returns an actionable
    error without an AI call.

### Automated client tests

- `tests/Feature/SubscriptionTest.php`
  - route/page auth states, API refresh proxy, credential-cache update, provider
    failures, and `subscription_required` preservation.
- `tests/Feature/MealPhotoIntegrationTest.php` and `tests/Feature/SettingsTest.php`
  - existing meal and settings behavior remains intact; deletion copy warns that
    store cancellation is separate.
- `tests/Unit/SubscriptionsPluginTest.php`
  - plugin manifest, iOS/Android dependencies/capability, bridge/event contract,
    UUID validation, main-thread dispatch, public-only configuration, and no
    receipt/token leakage.
- `tests/subscriptions.test.ts`
  - localized offering normalization, cached/server state transitions, native
    cancellation/pending/error categories, server-only unlock, unsupported web,
    and listener cleanup.

### Manual native/store matrix

Step 7 is required release evidence, not optional exploratory QA. At minimum,
keep a dated result for every named row on both platforms and include the store
test account/environment, app build number, webhook event ID, and pass/fail—no
receipts, tokens, keys, or personal payment data.

## Done criteria

All must hold:

- [ ] One RevenueCat entitlement named `ai_meal_analysis` maps the approved
  monthly/annual iOS and Android products.
- [ ] RevenueCat is configured only with Buff's non-guessable UUID; no anonymous
  RevenueCat customer is created during purchase, restore, logout, or account
  switch.
- [ ] API refresh and webhook jobs query RevenueCat; neither trusts client or
  webhook lifecycle fields as entitlement truth.
- [ ] Webhooks require both configured Authorization and valid five-minute HMAC
  signatures over raw bytes, acknowledge quickly, and queue idempotent refresh.
- [ ] Inactive users cannot call AI through mobile API, follow-up, or MCP; no AI
  or photo-storage work starts before denial.
- [ ] Existing drafts/photos and all non-AI logging remain accessible after
  expiry.
- [ ] Native pages display localized store pricing and unlock only after the API
  refresh returns entitled.
- [ ] Purchase, cancellation, pending, restore, transfer, renewal, grace,
  expiration, refund/revocation, offline, and provider-failure cases pass the
  automated/manual matrix.
- [ ] Account deletion explicitly warns that it does not cancel a store
  subscription and offers the manage-subscription path.
- [ ] All targeted and full commands in this plan pass; PHP files are formatted.
- [ ] Production server/webhook/queue ships before mobile releases; enforcement
  turns on only after both store builds pass smoke tests.
- [ ] `git status --short` in both repositories shows no unrelated files.
- [ ] `plans/README.md` marks this plan DONE.

## STOP conditions

Stop and report; do not improvise if:

- RevenueCat, its Pro webhook plan, the new native SDK dependencies, or the
  `Transfer to new App User ID` account policy is not approved.
- Product identifiers, prices, trials, privacy/terms/support URLs, or store
  account agreements are undecided. Do not invent commercial terms.
- The live code materially differs from the Current state excerpts or a required
  change falls outside Scope.
- Current RevenueCat SDKs require a minimum iOS/Android version above this app's
  iOS 18 / Android API 33 targets.
- A non-expiring/lifetime entitlement is added; the planned timestamp-only
  projection does not represent it.
- NativePHP Mobile 4 cannot declare the required RevenueCat SDK dependency or
  In-App Purchase capability in a plugin manifest. Do not patch generated native
  projects as a workaround.
- The RevenueCat SDK would create an anonymous customer, expose a secret/receipt,
  or require the client result to be trusted before server refresh.
- Store review rules require materially different purchase, restore, legal, or
  account-deletion behavior.
- Account deletion must erase RevenueCat-held pseudonymous purchase data through
  an API workflow. That privacy workflow needs an explicit vendor-tested
  extension before launch; do not silently claim local user deletion covers it.
- HMAC validation cannot access the exact raw request body or webhook processing
  logs request headers/body before redaction.
- A verification fails twice after one reasonable correction, production test
  webhook/job processing fails, or either platform lacks a completed sandbox and
  production smoke test.

## Maintenance notes

- Reviewer focus: server authority, raw-body HMAC handling, timestamp tolerance,
  UUID identity/transfer semantics, secrets in logs/bundles, and denial before
  storage/provider calls.
- RevenueCat webhook delivery is at-least-once and may be delayed. The queued job
  therefore fetches current subscriber state and must remain idempotent; never
  turn webhook event types into a hand-maintained subscription state machine.
- The local server projection lets known subscribers continue until their stored
  expiry during a RevenueCat outage. It does not grant access to a user with no
  known entitlement and does not extend expiry on failure.
- The existing daily analysis quota remains the cost-control calibration knob.
  Change it only from observed subscriber usage/cost, not as part of billing.
- If more paid features arrive, then consider a small entitlement table. One
  entitlement does not justify that abstraction now.
- If duplicate deliveries create measurable queue load or compliance requires an
  immutable audit trail, then add a webhook event ledger with retention rules.
- Direct Apple/Google server APIs are the fallback only if RevenueCat is removed;
  that requires a replacement design covering signed Apple payload validation,
  Google Pub/Sub authentication, acknowledgement, missed-event reconciliation,
  account binding, and provider-specific tests.
