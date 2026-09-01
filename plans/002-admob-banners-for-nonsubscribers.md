# Plan 002: Show AdMob banners only to non-Buff+ users

> **Executor instructions**: Follow this plan in order and run each verification
> before continuing. Work in `/Users/mason/Sites/Buff` and its sibling API at
> `/Users/mason/Sites/buff-server`. Read both repositories' `AGENTS.md` files and
> the applicable `.ai/rules` before editing. Activate `nativephp-mobile`,
> `inertia-vue-development`, `laravel-best-practices`, and
> `testing-best-practices`. Use Laravel Boost `search-docs` before Laravel or
> Inertia API changes. Stop on any condition in **STOP conditions**; do not swap
> in another ad package or weaken the privacy/subscription gates.
>
> This plan supersedes Plan 001 only where it names the RevenueCat entitlement
> `ai_meal_analysis`. The paid product remains **Buff+**, but its RevenueCat
> entitlement becomes `buff_plus`. The existing database column and AI feature
> flag keep their current names; renaming storage adds migration risk without
> changing behavior.

## Drift check

Run first:

```sh
git status --short
git diff --stat 7fb4a31..HEAD -- app/Http/Middleware/HandleInertiaRequests.php app/Providers/NativeServiceProvider.php config native-plugins/admob native-plugins/in-app-purchases resources/css/app.css resources/js/ads.ts resources/js/Layouts/AppShell.vue resources/js/Pages/Settings.vue resources/js/subscriptions.ts routes tests composer.json composer.lock package.json pnpm-lock.yaml .env.example plans
git -C ../buff-server status --short
git -C ../buff-server diff --stat 1ab03ea..HEAD -- app config database routes tests composer.json composer.lock .env.example
```

If either command reports an in-scope change, compare this plan with the live
code before editing. A material mismatch in subscription refresh, account
sharing, app-shell navigation, body-profile age, or native plugin registration
is a STOP condition.

## Status

- **Status**: IN PROGRESS — source complete; external privacy, device, and release work remains
- **Priority**: P1
- **Effort**: L — approximately 6–9 engineering days plus AdMob/store review time
- **Risk**: HIGH — native SDK lifecycle, advertising consent, age treatment, and paid access state
- **Depends on**: Plan 001's working RevenueCat purchase/restore and server projection flow
- **Category**: direction
- **Planned at**: client commit `8646fba`, server commit `989de27`, 2026-08-30
- **Reconciled at**: client commit `7fb4a31`, API commit `1ab03ea`, 2026-09-01

## Source checkpoint — 2026-09-01

- [x] The `buff_plus` entitlement cutover is implemented in both repositories.
- [x] The AdMob plugin, fail-closed coordinator, audience policy, consent UI,
  banner integration, and automated source coverage are implemented.
- [x] The public privacy policy and `app-ads.txt` are published.
- [x] The Google Play Data Safety questionnaire accurately declares Buff's
  collected data and AdMob's shared approximate location, app interactions,
  diagnostics, and device IDs. It was submitted for review on 2026-09-01.

The bundled Play review also includes the authorized full rollout and track
resume for the closed-test AdMob release. The remaining AdMob/store
configuration, current native builds, device QA, live IDs, and staged release
remain external gates. The user owns native rebuilding and device QA.

## Fixed product contract

| State | Required behavior |
|---|---|
| `subscription.entitled === true` | Do not initialize, load, or show AdMob. Hide any existing banner immediately. |
| Entitlement refresh fails, is offline, or is malformed | Fail closed: hide ads and do not initialize the SDK. |
| Explicitly not entitled, age 18+, consent permits ads | Show one bottom anchored adaptive banner; personalized inventory is allowed only when UMP and, on iOS, ATT permit it. |
| Explicitly not entitled, age 13–17 | Show a non-personalized banner with under-age treatment; never request ATT. |
| Explicitly not entitled, age missing | Treat as a teen: non-personalized, under-age treatment, no ATT. |
| ATT denied or restricted | Show a non-personalized fallback banner. |
| Web/non-native build | No ad SDK calls and no banner. |

Placement is limited to the exact top-level paths `/`, `/goals`, and
`/progress`. The banner sits above the mobile bottom navigation. It is hidden
on Add, Settings, authentication, onboarding, subscription, and every future
route unless that route is deliberately added to the allowlist.

Do not add interstitial, rewarded, rewarded-interstitial, app-open, or native
ads. Do not add mediation, an ad analytics pipeline, server impression events,
or a generic feature-flag service.

## Selected integration

Vendor a pinned MIT fork of
[`blessedzulu/nativephp-admob` v1.3.4](https://github.com/blessedzulu/nativephp-admob/tree/v1.3.4)
at upstream commit `e85ac7fe27211b2715ffb2fa98663164d029c15f` into
`native-plugins/admob`. It already supplies banner lifecycle, a Vue-compatible
JavaScript API, UMP, ATT, test IDs, safe-area handling, and a kill switch. Its
published Composer constraint only accepts NativePHP v3, so compatibility with
this app's NativePHP 4.3 must be patched and proved on both platforms; it is not
assumed.

Name the local Composer package `buff/admob` at version `1.0.0`, matching the
other first-party plugins in this repository. Keep the upstream PHP/native
namespaces and license to minimize the fork. Do not rewrite the package or
introduce a second wrapper package. Leave unused upstream ad formats dormant
rather than wiring them into Buff.

NativePHP plugins can add native dependencies, bridge functions, and UI, but
must be explicitly registered; follow the official
[NativePHP plugin documentation](https://nativephp.com/docs/mobile/4/plugins/using-plugins).

## Phase 1 — External account and policy prerequisites

These operator-owned steps may run in parallel with code, but production ads
must remain disabled until all are complete.

1. In RevenueCat, create the entitlement `buff_plus` and attach the existing
   Buff+ monthly and annual products. Confirm there are no active subscribers
   on `ai_meal_analysis`; this plan intentionally has no dual-entitlement
   migration or backfill.
2. Create separate iOS and Android apps in AdMob using the real App Store bundle
   ID and Play package name.
3. Create one anchored adaptive banner unit per platform for the logical slot
   `app_shell`. Record the two app IDs and two unit IDs outside source control.
4. In AdMob Privacy & messaging, configure the required EEA/UK/Switzerland UMP
   message and applicable US-state messages. Google requires consent
   information to be refreshed on every app launch and ads to wait for
   `canRequestAds`; use the official [iOS](https://developers.google.com/admob/ios/privacy)
   and [Android](https://developers.google.com/admob/android/privacy) UMP flows.
5. Publish `app-ads.txt` at the root of the developer website listed in both
   store records, using the exact publisher line supplied by AdMob. Verify the
   URL is public over HTTPS and that AdMob recognizes it.
6. Update the public privacy policy, App Store privacy answers/ATT disclosure,
   and Google Play Data Safety form for AdMob and consent storage. State that
   Buff does not send body profile, nutrition, workout, health, account, or
   RevenueCat identifiers to AdMob.
7. Register all developer devices as test devices. Development and QA builds
   must use Google's demo units through the fork's existing test mode; never
   click live ads. See Google's [test-ad guidance](https://developers.google.com/admob/android/test-ads).

Expected secrets/configuration, with no real values committed:

```dotenv
ADMOB_ENABLED=false
ADMOB_TEST_MODE=true
ADMOB_APP_ID_ANDROID=
ADMOB_APP_ID_IOS=
ADMOB_BANNER_APP_SHELL_ANDROID=
ADMOB_BANNER_APP_SHELL_IOS=
ADMOB_UMP_ENABLED=true
ADMOB_ATT_ENABLED=true
ADMOB_UMP_DEBUG_GEOGRAPHY=DISABLED
```

Add names and safe defaults to `.env.example`. Keep `ADMOB_ENABLED=false` by
default; test builds opt in with `ADMOB_TEST_MODE=true`, and the release
candidate is the first production configuration allowed to use live IDs. To
exercise ads during QA, set both `ADMOB_ENABLED=true` and
`ADMOB_TEST_MODE=true` for that test build.

## Phase 2 — Replace the RevenueCat entitlement atomically

Make this change in both repositories before AdMob eligibility work so every
layer agrees on the same paid product.

### Server: `/Users/mason/Sites/buff-server`

1. Change `config/buff.php` subscription entitlement from
   `ai_meal_analysis` to `buff_plus`.
2. Add `User::hasBuffPlusEntitlement(): bool` and use it in
   `UserResource` and `MealAnalysisService`. If compatibility still needs the
   old method, make it a one-line delegate; otherwise remove it after all
   callers are changed.
3. Keep `ai_meal_analysis_entitled_until` and
   `SUBSCRIPTIONS_ENFORCE_AI_MEAL_ANALYSIS`. They describe existing storage and
   the gated AI feature, not the RevenueCat entitlement identifier. Do not add
   a database migration just to rename them.
4. Replace RevenueCat fixture keys with `buff_plus` in subscription, webhook,
   meal-analysis, and MCP tests.
5. Replace user-visible errors that expose `ai_meal_analysis` with “Buff+”.
   Internal feature flag and column names may remain internal.

### Client: `/Users/mason/Sites/Buff`

1. Replace the hardcoded RevenueCat entitlement key in:
   - `native-plugins/in-app-purchases/resources/android/src/com/buff/inapppurchases/SubscriptionsFunctions.kt`
   - `native-plugins/in-app-purchases/resources/ios/Sources/SubscriptionsFunctions.swift`
2. Add `subscription.entitled?: boolean` to `SubscriptionAccount` in
   `resources/js/subscriptions.ts`. Existing Buff+ UI may continue to show
   expiry text, but ad eligibility must use the explicit server boolean.
3. Update affected fixtures and user-visible errors to say Buff+.

Verification:

```sh
rg -n "ai_meal_analysis" app config resources/js native-plugins tests ../buff-server/app ../buff-server/config ../buff-server/tests
```

Expected remaining matches are only the deliberately retained database field,
AI enforcement flag, and tests for those names. There must be no RevenueCat
entitlement lookup or user-visible copy using the old identifier.

## Phase 3 — Import and reduce the AdMob fork to Buff's contract

1. Copy the pinned upstream tag into `native-plugins/admob` without its `.git`
   directory. Preserve `LICENSE`, upstream attribution, and the original source
   URL/commit in its existing README.
2. In the fork's `composer.json`, set the local package name/version and allow
   the installed NativePHP v4 and Laravel 13 versions. Add a Composer path
   repository in the root `composer.json`, require `buff/admob:^1.0`, and update
   only that package and its required lockfile entries.
3. Follow the NativePHP registration flow; the publish command must not use
   `--force` because the provider already exists:

   ```sh
   php artisan vendor:publish --tag=nativephp-plugins-provider --no-interaction
   php artisan native:plugin:register buff/admob --no-interaction
   php artisan native:plugin:list
   ```

   Confirm it added `BlessedZulu\NativePhpAdmob\AdmobServiceProvider` once to
   the existing `App\Providers\NativeServiceProvider`.
4. Publish/copy the fork's config to `config/admob.php`, containing only:
   - the existing master kill switch and test mode;
   - UMP/ATT switches and debug geography;
   - the platform-keyed `app_shell` banner slot;
   - safe-area behavior and a calibrated bottom-nav offset;
   - no configured full-screen slots or frequency caps.
5. Remove both `init_function` entries from `nativephp.json` and delete the
   automatic Google Mobile Ads startup path. Add an explicit
   `Admob.Initialize` bridge. Google warns that the SDK or mediation adapters
   may preload ads during initialization, so initialization must happen only
   after Buff+ and consent checks; follow the official
   [iOS quick start](https://developers.google.com/admob/ios/quick-start) and
   corresponding platform SDK guidance.
6. Add one policy configuration call before first initialization and before
   every later load/account change. It accepts only:
   - `under_age_of_consent: boolean`;
   - `non_personalized: boolean`;
   - `max_content_rating: "T"`.

   Never pass raw age, account ID, RevenueCat ID, route data, or health data.
   Apply Google's request configuration on both platforms before SDK
   initialization and feed the under-age flag into UMP request parameters. The
   app remains 13+; set child-directed treatment to false and never combine a
   child-directed `true` flag with under-age-of-consent treatment. Reapply the
   policy before every request so an account or age-band switch cannot inherit
   the previous user's settings. The
   platform targeting APIs are documented for
   [iOS](https://developers.google.com/admob/ios/targeting) and
   [Android](https://developers.google.com/admob/android/targeting).
7. Centralize native banner request creation on each platform so the
   non-personalized request extra is applied consistently. Do not scatter this
   flag across load methods.
8. Extend UMP with:
   - privacy-options requirement status;
   - `showPrivacyOptionsForm`;
   - an explicit result/error for every request, rather than treating unknown
     state as consent.
9. Include the rendered banner height in the existing `AdLoaded` event. The
   app shell needs it to reserve content space and prevent the native overlay
   from covering the bottom of a page.
10. Keep the native banner's existing hide/teardown behavior. Ensure a load or
    show call is a no-op while disabled, before initialization, or before UMP
    reports `canRequestAds`.

Add or adapt fork tests for the manifest, v4 package constraint, disabled
state, no auto-init, policy mapping, UMP privacy options, non-personalized
requests, banner height event, and demo-unit selection. Do not add tests for
unused ad formats beyond retaining upstream coverage that still runs.

Plugin verification:

```sh
composer --working-dir=native-plugins/admob validate
composer --working-dir=native-plugins/admob install
composer --working-dir=native-plugins/admob test
php artisan native:plugin:list
php artisan native:plugin:validate native-plugins/admob
```

Expected: the package is registered once, its bridge list includes manual
initialize/policy/privacy-options operations, and neither platform manifest has
an `init_function`.

## Phase 4 — Add one fail-closed ad coordinator

Create `resources/js/ads.ts` as the single coordinator. Do not create a store,
repository, provider hierarchy, or per-page ad component.

### Inputs

1. In `HandleInertiaRequests`, share `buff.ad_audience` derived from the local
   `BodyProfile`:
   - `adult` only when age is 18 or older;
   - `teen` for ages 13–17 or a missing profile/age.

   Share the band, not the raw age. Add a focused middleware/Inertia test for
   adult, teen, and missing-age results.
2. Pass the current account, `page.url`, audience band, and the measured mobile
   bottom-nav offset from `AppShell.vue` into the coordinator.
3. Treat only iOS and Android as supported. A browser/Vite build returns before
   importing or calling the native AdMob bridge.

### Reconciliation sequence

On mount, successful Inertia navigation, foreground/resume, and reconnect:

1. Hide immediately if signed out, route is not exactly `/`, `/goals`, or
   `/progress`, or the native platform is unsupported.
2. Deduplicate concurrent reconciliations for the same account. Reset cached
   eligibility on logout/account change and require a new check after the app
   returns to the foreground.
3. `POST /subscription/refresh`. This existing controller already asks the
   authoritative API and replaces the encrypted local account projection.
4. Continue only when the returned value is exactly
   `data.subscription.entitled === false`. `true`, missing, malformed, timeout,
   HTTP failure, or offline all call `hide()` and stop before SDK initialization.
5. Configure the policy:
   - adult: under-age false; UMP decides consent; request ATT on iOS only after
     the UMP form finishes;
   - teen/missing age: under-age true, non-personalized true, never request ATT;
   - adult iOS ATT denied/restricted: non-personalized true.
6. Request fresh UMP consent information, show the form if required, and stop
   unless `canRequestAds` is true.
7. Initialize Google Mobile Ads once per process only after the preceding gates.
8. Load and show the `app_shell` banner at the bottom. On phones, offset it by
   the bottom-nav content height; when the `sm` sidebar replaces the bottom nav,
   use zero extra offset. Keep the plugin's system safe-area inset separate.
9. When `AdLoaded` supplies its height, add only that height to the app-main
   bottom padding. Clear it on hide/failure so pages do not retain a blank gap.

Call `hide()` before navigation to an excluded route and during unmount. A
later `entitled === true` result must hide an already-loaded banner immediately.
Do not poll subscription state; purchase/restore, navigation, reconnect, and
foreground reconciliation cover the meaningful transitions.

### App-shell and settings integration

1. Reuse the current `AppShell.vue` mount, Inertia-success, focus, online, and
   visibility lifecycle hooks. Add the coordinator beside subscription setup;
   do not create a second app shell.
2. Preserve the current bottom navigation. Measure its rendered content height
   rather than hardcoding per-device safe-area values; retain one config offset
   as a calibration fallback for device QA.
3. In `resources/js/Pages/Settings.vue`, show an “Ad privacy choices” action
   only when the native UMP privacy-options status says it is required. It opens
   the UMP privacy-options form and then reconciles the banner on the next
   eligible route. It must work for a user who later subscribed but previously
   consented.

## Phase 5 — Automated verification

Use existing test styles and the smallest focused coverage that proves each
branch.

### Client tests

- `tests/ads.test.ts`:
  - subscribed never configures, initializes, loads, or shows;
  - explicit non-entitled adult runs UMP then initializes and shows;
  - refresh failure/unknown entitlement hides and fails closed;
  - teen and missing age use under-age + non-personalized and skip ATT;
  - adult iOS ATT denial uses non-personalized fallback;
  - excluded route, logout, and account switch hide/reset;
  - banner load height controls padding and failure clears it.
- Update `tests/appShell.test.ts` for route allowlisting, lifecycle teardown,
  and the mobile-nav offset handoff.
- Add focused feature coverage for `buff.ad_audience` and ensure
  `/subscription/refresh` still stores and returns the server's explicit
  `subscription.entitled` value without trusting request input.
- Add/update the native plugin contract test for registration, manual init,
  and both platform manifests.

### Server tests

- RevenueCat refresh recognizes only `buff_plus`.
- `UserResource` exposes the correct `subscription.entitled` projection.
- Active Buff+ still unlocks AI meal analysis and MCP paths.
- Inactive Buff+ errors mention Buff+, not an internal entitlement key.
- Webhook refresh fixtures use `buff_plus`.

Run after each affected test edit, then run:

```sh
# Client
php artisan test --compact tests/Feature/SubscriptionTest.php tests/Feature/AdAudienceTest.php tests/Unit/SubscriptionsPluginTest.php tests/Unit/AdmobPluginTest.php
pnpm run test:frontend
pnpm run type-check
pnpm run build
vendor/bin/pint --dirty --format agent

# Server — run from /Users/mason/Sites/buff-server
php artisan test --compact tests/Feature/SubscriptionTest.php tests/Feature/RevenueCatWebhookTest.php tests/Feature/MealAnalysisTest.php tests/Feature/McpDraftAndMediaToolsTest.php
vendor/bin/pint --dirty --format agent
```

Adjust a listed filename if the existing suite uses a different focused test
file; do not create an empty umbrella test just to satisfy the command. Once
focused tests pass, ask the user to run `php artisan test --compact` in both
repositories.

## Phase 6 — Device QA and release

The executor must not run NativePHP build commands. The operator runs these in
their terminal after automated checks pass:

```sh
php artisan native:run ios
php artisan native:run android
```

Use test mode and demo ads for the full matrix on real devices/simulators:

- iOS and Android;
- subscribed, explicitly unsubscribed, refresh failure, and offline;
- adult UMP required/not required, ATT allowed, ATT denied/restricted;
- teen and missing age, confirming no ATT prompt and non-personalized requests;
- Home, Goals, Progress, every excluded route, rapid navigation, logout, and
  account switch;
- portrait/landscape, phone/tablet, gesture/navigation bars, large text, and
  bottom-of-page scrolling;
- app background/foreground, consent change, purchase, and restore.

Acceptance evidence for each platform:

1. A cold start with a Buff+ account generates no AdMob
   initialization/load/show call; a user who subscribes mid-session has the
   existing banner hidden and generates no further load/show calls.
2. An unknown or failed subscription check displays no banner.
3. Only the three allowed routes display a test banner.
4. The banner is above app/system navigation and never covers content.
5. Teen/missing-age sessions make no ATT request and use non-personalized ads.
6. ATT denial still shows a non-personalized test banner.
7. Privacy choices can be reopened when UMP requires the entry point.
8. No live ad ID appears in development logs or screenshots.

Release order:

1. Complete RevenueCat `buff_plus`, AdMob apps/units/messages, privacy policy,
   store disclosures, and `app-ads.txt`.
2. Deploy the server entitlement change and verify sandbox/Test Store refresh.
   This cutover is safe only because the product owner confirmed there are no
   current subscribers on `ai_meal_analysis`.
3. Produce iOS and Android release candidates with ads enabled but test mode
   still on; complete the matrix.
4. Switch only the signed production builds to real unit IDs and
   `ADMOB_TEST_MODE=false`. Recheck subscribed and failure states before upload.
5. Release through staged App Store/Play rollout and watch AdMob policy center,
   consent errors, crash reporting, and Buff+ support reports. Pause the staged
   rollout if any gate fails; if an affected build is already live, ship an
   `ADMOB_ENABLED=false` hotfix.

## STOP conditions

Stop and report if any of these occurs:

- The pinned free fork does not compile against NativePHP 4.3 on both iOS and
  Android without a broader rewrite.
- Google Mobile Ads initializes before an explicit non-entitled result and
  completed consent flow.
- UMP cannot refresh each launch, report `canRequestAds`, or reopen privacy
  options where required.
- RevenueCat still has active users attached only to `ai_meal_analysis`.
- The app cannot distinguish `entitled === false` from unavailable/unknown.
- A subscribed, signed-out, web, excluded-route, teen-personalized, or
  refresh-failure case can load/show an ad.
- A banner overlaps the app bottom nav, system chrome, or scrollable content.
- A development/QA build can request a live ad unit.
- Real platform app IDs, unit IDs, privacy messages, store disclosures,
  privacy policy, or verified `app-ads.txt` are unavailable for release.

Switching to the paid marketplace plugin, changing ad formats, adding
mediation, or weakening fail-closed behavior requires a new product decision;
it is not an implementation workaround.

## Definition of done

- `buff_plus` is the sole RevenueCat entitlement used by server and native
  purchase code, with no active-user migration required.
- AdMob is manually initialized only for an explicitly non-entitled account
  after age policy and consent resolve.
- One adaptive banner appears only on Home, Goals, and Progress, above the
  mobile nav, with no covered content.
- Buff+ users and every unknown/error state see no ads.
- Teen/missing-age and ATT-denied behavior is non-personalized as specified.
- UMP privacy choices, test IDs, kill switch, store disclosures, and
  `app-ads.txt` are complete.
- Focused automated tests, type-check, frontend build, formatting, both native
  device matrices, and staged release checks pass.
