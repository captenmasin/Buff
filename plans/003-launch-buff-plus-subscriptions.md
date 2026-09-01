# Plan 003: Launch Buff+ subscriptions safely on iOS and Android

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. Store secrets only in the deployment platform, store consoles, or
> an approved secret manager; never paste their values into source, this plan,
> logs, screenshots, or issue comments. If anything in the "STOP conditions"
> section occurs, stop and report — do not improvise. When done, update the
> status rows for Plans 001 and 003 in `plans/README.md`.
>
> Native packaging, device runs, console changes, uploads, and purchases are
> operator actions. Agents must not run NativePHP build/run/package/open/watch/
> install commands or make purchases.
>
> **Drift check (run first)**:
>
> ```sh
> # Client
> git diff --stat 7fb4a31..HEAD -- config/nativephp.php resources/js/subscriptions.ts resources/js/Pages/Settings/Subscription.vue native-plugins/in-app-purchases tests plans
> git diff --stat -- config/nativephp.php resources/js/subscriptions.ts resources/js/Pages/Settings/Subscription.vue native-plugins/in-app-purchases tests plans
>
> # API
> git -C ../buff-server diff --stat 1ab03ea..HEAD -- app/Services/RevenueCatService.php app/Http/Controllers/Api/V1/RevenueCatWebhookController.php app/Jobs/RefreshRevenueCatEntitlement.php app/Providers/AppServiceProvider.php config/buff.php config/services.php routes/api.php tests
> git -C ../buff-server diff --stat -- app/Services/RevenueCatService.php app/Http/Controllers/Api/V1/RevenueCatWebhookController.php app/Jobs/RefreshRevenueCatEntitlement.php app/Providers/AppServiceProvider.php config/buff.php config/services.php routes/api.php tests
> ```
>
> The source state below is reconciled through client `7fb4a31` and API
> `1ab03ea`, plus the current uncommitted webhook no-throttle fix. Any later
> material subscription-flow change is a STOP condition until this plan is
> reconciled again.

## Status

- **Status**: IN PROGRESS — source prerequisites complete; operator launch work remains
- **Priority**: P1
- **Effort**: M — approximately 2–4 operator days plus store review time
- **Risk**: HIGH — real money, store review, server-side access control
- **Depends on**: `plans/001-production-ios-android-subscriptions.md`
- **Category**: direction
- **Planned at**: client commit `3eafa2f`, API commit `989de27`, 2026-08-30
- **Reconciled at**: client commit `7fb4a31`, API commit `1ab03ea`, 2026-09-01

## Why this matters

The purchase, restore, RevenueCat refresh, webhook, and AI entitlement paths are
implemented and covered by automated tests, but they are not launch-ready until
the real store products, credentials, offerings, webhooks, signed builds, and
review flows exist. The release must also support Apple review transactions,
which can be sandbox receipts from a production-signed build. A blanket
production rejection of all sandbox receipts would reproduce the earlier
"purchase is still being confirmed" failure during review.

This plan is the remaining path to launch. It keeps `buff_plus` as the only
RevenueCat entitlement, limits review/test access to named Buff App User IDs,
keeps AI enforcement off until both apps are live, and provides a rollback that
does not alter subscription data.

## Current state

### Client: `/Users/mason/Sites/Buff`

- `config/nativephp.php` reads the public version and monotonically increasing
  version code from `NATIVEPHP_APP_VERSION` and
  `NATIVEPHP_APP_VERSION_CODE`.
- `.env.example` fixes the app identifier as `com.spacemancodes.buff`; the
  operator's current ignored `.env` uses release version `1.0.0` and code `12`.
  Code 12 must be increased if either store has already consumed it.
- `native-plugins/in-app-purchases/nativephp.json` declares RevenueCat for iOS
  and Android, the iOS In-App Purchase capability, Android Billing permission,
  and purchase/restore/customer-info bridge functions.
- Both native implementations now decide access using only:

  ```text
  customerInfo.entitlements.active["buff_plus"]
  ```

- `resources/js/subscriptions.ts` identifies the active package by the exact
  server `product_id`, preventing monthly and annual from both appearing active.
- RevenueCat account changes wait for matching completion/failure events,
  serialize concurrent switches, and time out safely.
- The release build must use the platform-specific `appl_...` and `goog_...`
  RevenueCat public SDK keys. A Test Store key must never enter either submitted
  binary.
- Store screenshot assets are committed under `artifacts/store-screenshots`;
  console upload remains unverified.

### API: `/Users/mason/Sites/buff-server`

- `config/buff.php` sets the entitlement to `buff_plus`, defaults enforcement
  and sandbox acceptance to false, and intentionally retains the AI-named
  database column and rollout switch.
- `POST /api/v1/webhooks/revenuecat` requires the exact configured
  Authorization value plus RevenueCat's timestamped HMAC signature, then queues
  an authoritative subscriber refresh. The route has no application throttle,
  so every valid signed delivery queues and returns HTTP 200.
- Webhook `environment` may be absent or null; entitlement truth still comes
  from the queued authoritative subscriber fetch.
- `RevenueCatService` accepts sandbox projection only when
  `REVENUECAT_ALLOW_SANDBOX_ENTITLEMENTS=true`, including in production. The
  default remains false and both production flag states are covered by tests.
  RevenueCat's App User ID allowlist is the trust boundary during review; after
  launch the API flag returns to false.
- A later valid RevenueCat grace-period expiry extends projected access.
- The production webhook remains production-events-only throughout review.
  Review purchases are confirmed by the authenticated refresh endpoint; they
  do not require sandbox webhook delivery into production.
- The 2026-09-01 verification checkpoint passed:
  - API: 188 tests, 1,453 assertions.
  - Client: 277 tests, 2,183 assertions.
  - Frontend: 127 tests.
  - Type checking, Vite build, Pint, diff checks, and NativePHP plugin
    validation passed.

### Outstanding operator work

1. Restrict RevenueCat Sandbox Testing Access to named QA/review App User IDs.
2. Create/activate the Apple and Google products and annual trial.
3. Connect both stores to RevenueCat and configure `buff_plus`, offerings,
   credentials, webhooks, restore behavior, and Google RTDN.
4. Deploy a non-production API for platform-sandbox acceptance.
5. Package signed iOS/Android builds and complete the manual matrix.
6. Submit both apps, pass review, release, then enable enforcement.

## Commands you will need

| Repository | Purpose | Command | Expected on success |
|---|---|---|---|
| `Buff` | Full PHP tests | `composer run test` | exit 0; all pass |
| `Buff` | Frontend tests | `pnpm test:frontend` | exit 0; 127 or more pass |
| `Buff` | Type check | `pnpm type-check` | exit 0, no errors |
| `Buff` | Plugin validation | `php artisan native:plugin:validate --no-interaction` | exit 0; in-app-purchases valid |
| `buff-server` | Focused billing tests | `php artisan test --compact tests/Feature/SubscriptionTest.php tests/Feature/RevenueCatWebhookTest.php tests/Feature/MealAnalysisTest.php tests/Feature/McpDraftAndMediaToolsTest.php` | all pass |
| `buff-server` | Full API tests | `composer run test` | exit 0; all pass |
| `buff-server` | Format PHP | `vendor/bin/pint --dirty --format agent` | exit 0 |
| production host | Inspect subscription config | `php artisan config:show buff.subscriptions` | phase-appropriate values shown |

The following are **operator-only** packaging commands. Run them manually only
after the platform named by the command is ready:

```sh
# Android signed bundle
pnpm run build:android
php artisan native:package android --build-type=bundle

# iOS App Store package
pnpm run build:ios
php artisan native:package ios --export-method=app-store
```

Use the `ANDROID_*` values and Apple signing/API credentials from the operator's
secret store. Do not place credentials on the command line or commit them.

## Suggested executor toolkit

- Invoke `nativephp-mobile`, `laravel-best-practices`, and
  `testing-best-practices` before changing source or tests.
- RevenueCat:
  - <https://www.revenuecat.com/docs/projects/configuring-products>
  - <https://www.revenuecat.com/docs/projects/restore-behavior>
  - <https://www.revenuecat.com/docs/integrations/webhooks>
  - <https://www.revenuecat.com/docs/projects/sandbox-access>
  - <https://www.revenuecat.com/docs/test-and-launch/sandbox>
  - <https://www.revenuecat.com/docs/test-and-launch/app-store-rejections>
- Apple:
  - <https://developer.apple.com/help/app-store-connect/manage-subscriptions/offer-auto-renewable-subscriptions/>
  - <https://developer.apple.com/help/app-store-connect/manage-subscriptions/set-up-introductory-offers-for-auto-renewable-subscriptions/>
  - <https://developer.apple.com/help/app-store-connect/test-in-app-purchases/overview-of-testing-in-sandbox/>
  - <https://developer.apple.com/help/app-store-connect/test-a-beta-version/testing-subscriptions-and-in-app-purchases-in-testflight/>
- Google:
  - <https://support.google.com/googleplay/android-developer/answer/140504?hl=en>
  - <https://support.google.com/googleplay/android-developer/answer/6062777?hl=en>
  - <https://developer.android.com/google/play/billing/test>

## Scope

**In scope**:

- RevenueCat project, app, product, entitlement, offering, restore, sandbox,
  credential, and webhook configuration.
- App Store Connect subscription, sandbox, TestFlight, review, and release
  configuration.
- Google Play subscription, offer, internal testing, license testing, RTDN,
  review, and release configuration.
- Staging and production deployment configuration for `buff-server`.
- Signed release configuration for the existing NativePHP client.
- The minimal review-safe sandbox-policy change in:
  - `../buff-server/app/Services/RevenueCatService.php`
  - `../buff-server/tests/Feature/SubscriptionTest.php`
- `plans/001-production-ios-android-subscriptions.md`
- `plans/003-launch-buff-plus-subscriptions.md`
- `plans/README.md`

**Out of scope**:

- AdMob or ad-removal implementation; that remains Plan 002.
- New subscription tiers, weekly/lifetime products, price changes, coupons,
  promotions, web checkout, or family sharing.
- Direct Apple/Google receipt verification or a replacement for RevenueCat.
- Renaming `ai_meal_analysis_entitled_until` or the AI enforcement flag.
- Accepting a native purchase result as authority without API refresh.
- A second operations guide or committed credentials.

## Git workflow

- Commit the existing client and API subscription changes separately before
  producing release artifacts. Do not mix the two repositories in one commit.
- Use descriptive subjects; do not commit secrets, generated native build
  directories, signed artifacts, receipts, or store-account information.
- Do not push, upload, submit, or release unless the operator explicitly owns
  that action.

## Steps

### Step 1: Finish Apple review sandbox controls

Apple development/TestFlight purchases use sandbox, and a production-signed app
may receive a test receipt during App Review. Preserve the default-deny policy
while allowing only the operator-controlled review/test accounts:

Source checkpoint:

- [x] `RevenueCatService` accepts sandbox only when
  `config('buff.subscriptions.allow_sandbox_entitlements')` is true, including
  production.
- [x] Feature tests cover rejection and acceptance for both production flag
  states.

Remaining operator steps:

1. In RevenueCat **Project settings → General → Sandbox Testing Access**, select
   **Allowed App User IDs only**.
2. Create dedicated Buff QA and App Review accounts. Record their server-issued
   `revenuecat_app_user_id` UUIDs in the secret manager and add only those UUIDs
   to RevenueCat's allowlist. Put review login credentials only in App Store
   Connect's private App Review Information.
3. Use these server phases:

   | Phase | `APP_ENV` | Sandbox flag | AI enforcement |
   |---|---|---:|---:|
   | Staging/device QA | `staging` | `true` | `true` |
   | Production during TestFlight/App Review | `production` | `true` | `false` |
   | Production after both apps are live | `production` | `false` | `true` |

4. Keep the production RevenueCat webhook subscribed to production events only.
   The review purchase becomes visible through authenticated API refresh.

**Verify**:

```sh
cd ../buff-server
php artisan test --compact tests/Feature/SubscriptionTest.php
vendor/bin/pint --dirty --format agent
```

Expected: both flag states are tested, all tests pass, and Pint exits 0. In a
pre-submission production smoke test using the allowlisted review account, an
Apple sandbox purchase must become active after `/subscription/refresh`; a
non-allowlisted sandbox account must remain inactive.

### Step 2: Create and activate the store products

Record the four permanent product identifiers outside source control. Do not
rename an identifier after it has been used by a store build.

#### Apple

1. Activate the Paid Apps Agreement, banking, and tax setup.
2. Under the Buff app's **Monetization → Subscriptions**, create one group named
   **Buff+**.
3. Add monthly and annual auto-renewable subscriptions:
   - monthly: one month, £4.99 UK price, no introductory offer;
   - annual: one year, £24.99 UK price, seven-day free introductory offer.
4. Add every required localization, availability, tax category, review note,
   and review screenshot. Both products must reach **Ready to Submit**.
5. Add both subscriptions to the first app-version submission.

#### Google

1. Package a signed AAB and upload it to an Internal testing release so Play
   Console recognizes the billing-enabled package.
2. Under **Monetize with Play → Products → Subscriptions**, create the approved
   monthly and annual products/base plans with the same commercial terms.
3. Add a seven-day free-trial offer to annual only, restricted to customers who
   have never held a Buff subscription. Do not put a trial on monthly.
4. Activate both base plans and the annual offer in the intended countries.
5. Add the QA Google accounts to both Internal testing and License testing,
   accept the opt-in link, and install from Play rather than sideloading.

**Verify**: App Store Connect shows both products **Ready to Submit** with the
annual-only trial. Play Console shows both base plans and the annual offer
**Active**, and the Internal testing build is available from its opt-in link.

### Step 3: Finish RevenueCat and server-notification configuration

RevenueCat products are mappings to products that already exist in App Store
Connect or Play Console; creating a RevenueCat row does not create a billable
store product. At this checkpoint, the Product Catalog shows products only
under Test Store while **Buff (Play Store)** and **Buff (App Store)** are empty.
That is not production-ready.

1. Connect the App Store app using the exact bundle ID, In-App Purchase Key,
   shared secret/required App Store credentials, and recommended App Store
   Connect API key.
2. Connect the Play app using package `com.spacemancodes.buff` and a service
   account with RevenueCat's required view-financial-data, order/subscription,
   and app permissions. Upload its JSON only in RevenueCat.
3. Wait for Google credential propagation if RevenueCat does not validate it;
   propagation can take up to 36 hours.
4. In **Product catalog → Entitlements**, create exactly `buff_plus`.
5. After Step 2's store products exist, return to RevenueCat **Product
   Catalog** and use **Import** under each real store. Import monthly and annual
   from App Store and monthly and annual from Play Store, then attach all four
   to `buff_plus`. Prefer Import because it verifies the store connection and
   identifiers. If Import remains disabled, fix the store credentials/product
   state; do not treat **+ New** as a substitute for missing store-side
   products. Manual **+ New** is acceptable only to register an exact identifier
   for a product that already exists in that store.
6. Confirm no active user relies only on `ai_meal_analysis`; there is
   intentionally no dual-entitlement fallback.
7. Create one current offering with a monthly package and an annual package.
   The app loads only the current offering and recognizes those package kinds.
8. Set restore behavior to **Transfer to new App User ID**.
9. Configure Google RTDN, send its test notification, and confirm RevenueCat's
   **Last received** timestamp updates.
10. Create the production webhook:
   - URL: `https://api.usebuff.app/api/v1/webhooks/revenuecat`
   - production events only;
   - exact Authorization value matching the production server setting;
   - HMAC signing enabled, with its secret matching the server setting.
11. Create a separate sandbox-events webhook for staging using different
    Authorization and HMAC values.

**Verify**: RevenueCat's product catalog shows one `buff_plus` entitlement,
four attached store products, and one current monthly/annual offering. Store
credentials are valid, RTDN is received, and each webhook test receives HTTP
200 and completes its queued refresh without a failed job.

### Step 4: Deploy staging and package platform-sandbox builds

1. Deploy `buff-server` to a non-production HTTPS origin with a separate
   database, queue, cache, and secrets. Configure:

   ```dotenv
   APP_ENV=staging
   REVENUECAT_ALLOW_SANDBOX_ENTITLEMENTS=true
   SUBSCRIPTIONS_ENFORCE_AI_MEAL_ANALYSIS=true
   ```

2. Point QA client builds at the staging `/api/v1` URL while retaining the
   platform-specific RevenueCat public SDK key.
3. Set `NATIVEPHP_APP_VERSION=1.0.0` and a build/version code that is greater
   than every value already uploaded. Code `11` is valid only if unused.
4. Have the operator package Android and iOS using the commands above. Never use
   a RevenueCat Test Store key in these builds.
5. Upload Android to Internal testing and iOS to TestFlight. Install both from
   their store-controlled distribution links on physical devices.

**Verify**: each installed build reports the intended version/build number,
loads localized products from the real platform sandbox, calls the staging API,
and identifies the RevenueCat customer with the Buff UUID rather than an
anonymous `$RCAnonymousID`.

### Step 5: Complete the real-store sandbox matrix

Keep a dated result with platform, build number, scenario, Buff test-account
label, RevenueCat event/customer link or event ID, API result, and pass/fail.
Never record passwords, receipts, purchase tokens, or payment details.

Required on both platforms unless marked Android-only:

1. Monthly purchase succeeds; the page identifies monthly as the current plan,
   shows a future access date, and AI analysis unlocks only after API refresh.
2. Annual purchase succeeds; an eligible new customer sees a seven-day trial,
   annual becomes the sole current plan, and the server product ID is annual.
3. A non-eligible customer does not receive another introductory trial.
4. Cancelling the purchase sheet leaves the previous entitlement unchanged.
5. Android pending/slow approval stays locked while pending and unlocks only
   after completion; slow decline never unlocks.
6. Restore succeeds after reinstall and after clearing local app data.
7. Cancellation keeps access through the paid period; accelerated expiry
   removes it.
8. Billing retry/grace period, refund, and revocation project the RevenueCat
   state correctly.
9. Transfer from Buff account A to B gives access to B and removes it from A.
10. The same Buff account sees the same server entitlement after switching
    between iOS and Android.
11. RevenueCat/API failure never grants a new entitlement and preserves only a
    still-valid last-known server projection.
12. Manage Subscription opens the correct platform page.
13. Manual/barcode logging and existing meal/history access remain free while
    AI analysis and follow-up return `subscription_required` when inactive.

After every purchase/restore, check all three layers:

- RevenueCat customer history has `buff_plus`, the expected store/product, and
  the Buff UUID;
- the API refresh returns the expected `entitled`, `product_id`, `store`, and
  expiry values;
- the page shows only the matching package as **Current plan active** and never
  remains on "purchase is still being confirmed" after a successful refresh.

**Verify**: every row is PASS on physical iOS and Android devices. Any failed or
untested row blocks submission.

### Step 6: Deploy production safely and submit both apps

1. Commit and deploy the API code first with production credentials, shared
   cache, queue worker, scheduler, and:

   ```dotenv
   APP_ENV=production
   REVENUECAT_ALLOW_SANDBOX_ENTITLEMENTS=true
   SUBSCRIPTIONS_ENFORCE_AI_MEAL_ANALYSIS=false
   ```

   Sandbox acceptance is temporary and safe only while RevenueCat remains on
   **Allowed App User IDs only**.
2. Clear/rebuild configuration cache or restart the deployment, then run
   `php artisan config:show buff.subscriptions`. Confirm `buff_plus`, sandbox
   true, enforcement false.
3. Confirm the production webhook returns HTTP 200 and the queue processes its
   refresh job.
4. Package production builds using `https://api.usebuff.app/api/v1`, the
   platform-specific public SDK keys, and unique version/build numbers.
5. Before submission, install the exact iOS build through TestFlight and use
   the allowlisted App Review account against the production API. Its sandbox
   purchase must confirm and display the correct current plan.
6. In App Store Connect's private review information, provide the dedicated
   Buff review login, explain where Buff+ purchase/restore/manage controls live,
   and attach both subscription products to the submission.
7. Submit iOS and promote the Android build through review using staged rollout
   controls. Do not enable AI enforcement merely because builds were uploaded
   or approved; both must be publicly obtainable first.

**Verify**: both store dashboards show the intended signed build and products
in review/approved state, Apple review can confirm a sandbox purchase through
production API, and production AI remains ungated while either app is not live.

### Step 7: Release, enable enforcement, and monitor

1. Make both approved builds publicly available through small staged rollouts.
2. Perform one real production monthly purchase per store with an operator-owned
   account, verify purchase/restore/manage and server projection, then cancel
   or request refund according to the store's normal process.
3. Once both production smoke tests pass, set:

   ```dotenv
   REVENUECAT_ALLOW_SANDBOX_ENTITLEMENTS=false
   SUBSCRIPTIONS_ENFORCE_AI_MEAL_ANALYSIS=true
   ```

4. Reload production configuration and verify the two effective values using
   `php artisan config:show buff.subscriptions`.
5. Confirm the allowlisted sandbox review account no longer gains/retains a
   server entitlement after refresh, while the real production subscribers do.
6. Monitor for at least 48 hours:
   - webhook authentication failures and delivery retries;
   - failed/slow entitlement refresh jobs;
   - `subscription_status_unavailable` and `subscription_required` rates;
   - active-customer/product counts in RevenueCat;
   - purchase-confirmation support reports;
   - AI request volume and cost.
7. If the billing path is unhealthy, set only
   `SUBSCRIPTIONS_ENFORCE_AI_MEAL_ANALYSIS=false` and reload configuration.
   Never delete or overwrite subscription projection data as a rollback.

**Verify**: production sandbox is false, enforcement is true, both real store
transactions remain active, both store rollouts are healthy, and the queue has
no failed subscription refresh job.

### Step 8: Close the plans

After the 48-hour monitoring gate passes:

1. Mark Plan 001 `DONE` because its source and launch conditions are complete.
2. Mark Plan 003 `DONE`.
3. Leave Plan 002 `IN PROGRESS` until its external AdMob/device/release gates
   pass.

**Verify**:

```sh
git diff --check
git status --short
git -C ../buff-server diff --check
git -C ../buff-server status --short
```

Expected: no whitespace errors, no secrets or signed artifacts, and only the
intentional plan/source changes remain before their commits.

## Test plan

### Automated

- API subscription tests prove sandbox default-deny plus explicit flag-enable
  behavior in production, authoritative refresh, refund/expiry clearing, and
  last-known projection preservation.
- Webhook tests prove exact Authorization/HMAC validation, UUID filtering,
  transfer identities, idempotent jobs, and immediate HTTP 200 acknowledgement.
- Client tests prove exact product matching, monthly/annual button labels,
  localized offering mapping, pending/cancel behavior, and server-only unlock.
- Run every command in "Commands you will need" before packaging and again
  before enabling production enforcement.

### Manual

- Step 5 is the required platform-sandbox matrix.
- Step 6 adds the Apple review-account path using the submitted build and
  production API.
- Step 7 adds one real production transaction per store.
- Evidence may use private console links/event IDs, but never secrets, receipts,
  tokens, or personal payment information.

## Done criteria

All must hold:

- [ ] Client and API implementation changes are committed and all automated
  checks pass.
- [ ] `buff_plus` is the only RevenueCat entitlement used by source and the
  product catalog; no active customer relies only on `ai_meal_analysis`.
- [ ] Apple and Google each have active monthly and annual products at the fixed
  UK prices, with a seven-day introductory trial on annual only.
- [ ] The RevenueCat current offering maps both packages on both platforms.
- [ ] Restore behavior is Transfer to new App User ID.
- [ ] Apple/Google credentials, Google RTDN, the production webhook, and the
  staging webhook validate successfully.
- [ ] RevenueCat Sandbox Testing Access is restricted to named App User IDs
  during QA/review; no general sandbox user can gain Buff+.
- [ ] The full physical-device sandbox matrix passes on iOS and Android.
- [ ] The exact submitted Apple build confirms the allowlisted review account's
  sandbox purchase against production API.
- [ ] Both submitted builds use platform-specific public SDK keys, never the
  Test Store key, and have unique release/build numbers.
- [ ] Both apps are approved, publicly available, and pass real production
  purchase/restore/manage smoke tests.
- [ ] Production ends with sandbox acceptance false and AI enforcement true.
- [ ] The first 48 hours complete without an unresolved billing incident.
- [ ] Plans 001 and 003 are marked DONE; Plan 002 remains independently IN
  PROGRESS until its external gates pass.

## STOP conditions

Stop and report; do not improvise if:

- Either store's agreement, tax, banking, package/bundle identity, permanent
  product IDs, privacy URL, terms/EULA, support URL, or review account is not
  available.
- The fixed £4.99 monthly / £24.99 annual / annual-only seven-day trial terms
  cannot be configured.
- RevenueCat does not offer App User ID-restricted sandbox access. Do not enable
  global production sandbox access for a public app; design a server-side UUID
  allowlist before continuing.
- Any active customer relies only on `ai_meal_analysis`.
- A submitted build contains a Test Store key, wrong API origin, reused build
  number, unsigned artifact, or mismatched store product identifier.
- Apple review/TestFlight sandbox purchase cannot be confirmed against the
  production API using the restricted review account.
- Product fetching, purchase, pending, restore, transfer, cancellation,
  expiry, refund, or revocation fails on either physical platform.
- A purchase unlocks from native CustomerInfo before authoritative API refresh,
  or a pending/declined purchase unlocks at all.
- Production webhook authentication, HMAC, queue processing, or Google RTDN
  cannot be verified.
- Either store build is unavailable when enforcement would be enabled.
- A verification fails twice after one reasonable correction, or completing a
  step requires a source file outside this plan's scope.

## Maintenance notes

- For future App Store submissions where review may exercise subscriptions,
  temporarily use the same restricted review-account procedure, then return the
  API sandbox flag to false. Never leave it enabled based only on memory; verify
  effective config after every deployment.
- Every Android upload and iOS build requires a greater build/version code.
- New products must attach to `buff_plus` and the current offering before any
  binary referencing them is submitted.
- Keep production and sandbox webhooks separate. Production entitlement truth
  remains a fresh RevenueCat subscriber fetch, not webhook event fields.
- AdMob remains disabled until Plan 002's separate privacy and device matrix is
  complete; it does not block the Buff+ subscription launch.
