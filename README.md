# Buff mobile app

Buff is the offline-first Laravel, Inertia, Vue, and NativePHP client. Health logs, sync state, encrypted credentials, the sync outbox, and pending progress photos live locally on the device. The separate private [`buff-server`](https://github.com/captenmasin/buff-server) application owns remote accounts and synced data.

This README is also the release handbook for subscriptions, ads, the API, App Store Connect, and Google Play. Do not treat a successful native build as proof that the store, RevenueCat, or server configuration is complete; all of them must agree on the same app and product identifiers.

## Contents

- [Start here](#start-here)
- [Current release status](#current-release-status)
- [Accounts and dashboards](#accounts-and-dashboards)
- [Setup](#setup)
- [Native shell](#native-shell)
- [Secret handling](#secret-handling)
- [RevenueCat subscriptions](#revenuecat-subscriptions)
- [AdMob](#admob)
- [API server](#api-server)
- [Store submission material](#store-submission-material)
- [iOS and App Store publishing](#ios-and-app-store-publishing)
- [Android and Google Play publishing](#android-and-google-play-publishing)
- [Complete release inventory](#complete-release-inventory)
- [Recommended release order](#recommended-release-order)
- [Data and sync](#data-and-sync)
- [Troubleshooting](#troubleshooting)

## Start here

There are two private repositories and two independently deployed applications:

| Part | Location | Responsibility |
| --- | --- | --- |
| Mobile client | This repository, normally `Buff/` | Laravel/Inertia/Vue UI, on-device SQLite data, native iOS/Android shells, RevenueCat purchases, AdMob display, and sync client |
| API server | [`buff-server` on GitHub](https://github.com/captenmasin/buff-server), normally [`../buff-server`](../buff-server) | Remote accounts, synced data, OAuth callbacks, photos, AI meal analysis, subscription authority, RevenueCat webhooks, and optional MCP access |

Request access to both private GitHub repositories before onboarding; their links return `404` anonymously. Authorised local users can use the relative server links in this README.

Common source locations in this repository are:

- `resources/js/` — the Inertia/Vue application bundled into the native web view.
- `app/`, `routes/`, and `database/` — local Laravel application behaviour and the on-device database.
- `native-plugins/` — the Swift/Kotlin bridges for purchases, ads, health, camera, notifications, and other native capabilities.
- `config/nativephp.php` — bundle/package identity and platform build configuration.
- `artifacts/store-screenshots/` — store screenshot sources, exports, and provenance.
- `plans/` — implementation and release evidence; plans are not proof that an external dashboard was configured.

Choose the path that matches the work:

1. **Local web development:** complete [Setup](#setup), choose the API deliberately, then use `composer run dev`.
2. **Native device QA:** complete Setup, configure a staging API and platform build values, then follow [Native shell](#native-shell).
3. **First production release:** read the entire handbook, fill the non-secret identifier register, assign every release-inventory item, and follow the [recommended release order](#recommended-release-order).

Terminology used below:

- A **store product ID** permanently identifies a subscription in Apple or Google. Google places a **base plan** and optional **offer** below a subscription product.
- A RevenueCat **entitlement** is the access granted to a customer (`buff_plus`); an **offering** is the paywall catalogue; a **package** maps a standard duration such as `$rc_monthly` to a store product.
- A RevenueCat **App User ID** is the server-generated `revenuecat_app_user_id` UUID returned by the account API. It is not the account primary `id`, email address, or an anonymous RevenueCat ID.
- RevenueCat public SDK keys and AdMob IDs are embedded identifiers. Secret API keys, webhook secrets, private keys, service-account JSON, and signing passwords are server/release secrets.
- An `.xcarchive` is the Xcode archive inspected before export; an `.ipa` is the signed distributable iOS package. AAB means Android App Bundle; ATT is App Tracking Transparency; UMP is Google's User Messaging Platform; RTDN is Google Play Real-time developer notifications.

Current registered plugins require iOS 18.2 or later on iPhone in portrait orientation, and Android API 33 or later in portrait orientation. Android currently compiles and targets API 36.

## Current release status

The repository contains the client/server integrations and most listing artwork, but Git cannot prove that Apple, Google, RevenueCat, AdMob, DNS, or production-host dashboards are configured. Treat every external step without dated evidence as incomplete. The source checkpoint in [`plans/003-launch-buff-plus-subscriptions.md`](plans/003-launch-buff-plus-subscriptions.md) still requires store configuration, credentials, physical-device tests, submission, and rollout evidence.

Known repository-side release blockers at the 1 September 2026 audit are:

- Create or record the permanent Apple and Play subscription identifiers; do not invent them during upload.
- Export a store-compliant icon without transparency, create the separate 512×512 Play listing icon, and flatten the Buff+ review screenshot.
- Publish and verify an explicit web account-deletion request action before completing the Play declaration; the current support page only provides general support guidance.
- Remove the current `NSHealthUpdateUsageDescription` declaration or implement/disclose genuine HealthKit writes; the release behaviour is currently read-only.
- Prove the external store, RevenueCat, AdMob, signing, staging, review-account, and device-test state in the release register.
- Recheck the resolved Google billing/ads SDKs and 16 KB native-library compatibility before submission.

Human owners must decide and record the launch territories, non-UK prices, category/tags, target age groups, seller/legal name, Apple SKU, copyright, localisations, tax category, release mode, staged-rollout percentage, support/reviewer contacts, and rollout/rollback owners. These values cannot safely be inferred from code.

## Accounts and dashboards

| Service | Dashboard | Used for |
| --- | --- | --- |
| Apple Developer | [Certificates, Identifiers & Profiles](https://developer.apple.com/account/resources/) | App ID, capabilities, certificates, provisioning profiles, and Sign in with Apple keys |
| App Store Connect | [Apps](https://appstoreconnect.apple.com/apps) | Store listing, subscriptions, TestFlight, App Review, banking, and tax |
| Google Play | [Play Console](https://play.google.com/console/) | Store listing, subscriptions, testing tracks, policy declarations, and production releases |
| Google Cloud | [Cloud Console](https://console.cloud.google.com/) | Service accounts, APIs, OAuth clients, and Pub/Sub for purchase notifications |
| RevenueCat | [RevenueCat dashboard](https://app.revenuecat.com/) | Store product mapping, `buff_plus`, offerings, customer history, and webhooks |
| Google AdMob | [AdMob dashboard](https://admob.google.com/) | iOS/Android app IDs, banner units, consent messages, and test devices |
| Google Payments | [Payments Centre](https://payments.google.com/) | Merchant, payment, tax, and payout details where Google requests them |

Use organisation-owned accounts and give each operator their own login. Keep at least two trusted administrators on Apple, Google Play, RevenueCat, AdMob, the production host, DNS, and the company email domain.

## Setup

The supported project toolchain is:

| Tool | Requirement |
| --- | --- |
| PHP | 8.4 or later, with Composer |
| Node.js | `^20.19.0` or `>=22.12.0` |
| pnpm | 10.12.1, as declared by `packageManager` |
| Local database | SQLite |
| iOS builds | macOS, the current App Store-supported Xcode/iOS SDK, and the [NativePHP iOS prerequisites](https://nativephp.com/docs/mobile/4/getting-started/environment-setup) |
| Android builds | Android Studio/JDK toolchain with Android SDK 36 and the [NativePHP Android prerequisites](https://nativephp.com/docs/mobile/4/getting-started/environment-setup) |

NativePHP does not support building the native shell from WSL. On a fresh checkout, create the ignored SQLite file before the setup script runs its migrations:

```sh
touch database/database.sqlite
composer run setup
```

PowerShell equivalent:

```powershell
New-Item database/database.sqlite -ItemType File -Force
composer run setup
```

`composer run setup` installs PHP/JavaScript dependencies, creates `.env` from `.env.example` when needed, generates `APP_KEY`, migrates SQLite, and builds the web assets.

**The checked-in `.env.example` points `BUFF_API_URL` at the production API.** Before creating accounts or test data, deliberately choose one of:

- production: `https://api.usebuff.app/api/v1`;
- an isolated HTTPS staging API with its own database, cache, queue, storage, OAuth credentials, and RevenueCat webhook; or
- the local sibling server, normally `https://buff-server.test/api/v1`, for host-browser development.

Do not invent a staging URL in the app: deploy it, record its owner, and verify it first. A phone or simulator cannot normally reach the host's `.test` address without additional networking. Keep `BUFF_ALLOW_REMOTE_HTTP=false` except for controlled local development, and never submit a build with it enabled.

For web development:

```sh
composer run dev
```

Laravel Herd also exposes the client at `https://buff.test` and the sibling API at `https://buff-server.test`. When using Herd's PHP server, run `pnpm run dev` for Vite and start a queue worker separately when testing queued behaviour. `composer run dev` is the optional all-in-one PHP server, queue, log, and Vite process group with its own development URL.

Stable verification commands are:

```sh
composer run test
pnpm test:frontend
pnpm type-check
```

The **release environment** is the ignored `.env` or injected CI environment read while assets and native packages are compiled. It is not a store-console setting. `VITE_*` values are embedded into JavaScript and AdMob app IDs are substituted into native manifests, so changing them requires rebuilding and repackaging.

## Native shell

The app's bundle ID and Android application ID are both `com.spacemancodes.buff`. Changing that value creates a different store app and disconnects existing installs, subscriptions, associated domains, and signing configuration.

Native configuration uses `NATIVEPHP_APP_ID`, `NATIVEPHP_APP_VERSION`, `NATIVEPHP_APP_VERSION_CODE`, and `NATIVEPHP_DEEPLINK_SCHEME`. Set `NATIVEPHP_APP_ID=com.spacemancodes.buff` before the first native installation. Never archive or submit while `NATIVEPHP_APP_VERSION=DEBUG`; use a semantic release version such as `1.0.0` and a unique, increasing build number/version code.

After choosing a platform and completing its environment values, the release operator—not an automated agent—runs the applicable installation command:

```sh
php artisan native:install ios
# or
php artisan native:install android

php artisan native:plugin:validate --no-interaction
php artisan native:plugin:list --no-interaction
```

The current validator reports warnings for the hook-only `camera-permissions` and `native-refresh` plugins because they intentionally expose no bridge functions; both must still appear in the registered-plugin list. Investigate any new warning or error instead of assuming it is harmless.

Use `php artisan native:run ios --watch --vite` or `php artisan native:run android --watch --vite` for device QA; Vite is opt-in in NativePHP v4 and this app uses web-view assets. Before packaging a release, increment the human-readable version once with `php artisan native:release patch` (or the intended `minor`/`major` change), then test a release-mode build on a physical device. Store build numbers/version codes only need to increase within their own platform and may differ.

When `GOOGLE_SERVICE_ACCOUNT_KEY` or `--google-service-key` is present, Android bundle packaging queries Play and may update `NATIVEPHP_APP_VERSION_CODE` in `.env`. Re-read `.env`, inspect the AAB, and record the final platform-specific code. Do not rebuild an already-tested iOS artifact merely to make the numbers match; any rebuild caused by a source/configuration change must be tested again.

This application uses web-view assets, so `pnpm run build:ios` or `pnpm run build:android` is required before the corresponding native package. Retain the Git commit, version/build, IPA/AAB, signing-certificate fingerprints, symbols/mapping files, and store submission ID. Promote the tested artifact instead of rebuilding after QA.

NativePHP build, run, package, and IDE commands are manual and platform-specific. The official references are [installation](https://nativephp.com/docs/mobile/4/getting-started/installation), [publishing](https://nativephp.com/docs/mobile/4/publishing/introduction), [iOS publishing](https://nativephp.com/docs/mobile/4/publishing/ios), and [Android publishing](https://nativephp.com/docs/mobile/4/publishing/android).

## Secret handling

- Never commit `.env`, `.p8`, `.p12`, `.cer`, `.mobileprovision`, `.jks`, keystore passwords, service-account JSON, OAuth secrets, Passport private keys, RevenueCat secret keys, or webhook secrets.
- Store the only recoverable copies in the company password manager or secrets vault, with access and recovery owners recorded. CI secrets should contain release-time copies, not become the only source of truth.
- `/credentials/`, `/nativephp`, and `.env` are ignored by Git. Ignoring a file prevents a normal commit; it does not make an exposed credential safe.
- RevenueCat public mobile SDK keys (`appl_…` and `goog_…`) and AdMob app/ad-unit IDs are identifiers embedded in the app, not server secrets. They still belong in environment configuration so test and production apps cannot be mixed accidentally.
- Use separate least-privilege service accounts and API keys for CI upload, RevenueCat, and human administration. Revoke and rotate credentials when a maintainer leaves or a secret is exposed.
- Before sharing logs or screenshots, remove bearer tokens, signed URLs, customer UUIDs, private keys, email addresses, and store financial information.

## RevenueCat subscriptions

### Buff subscription contract

The application and API recognise exactly one entitlement: `buff_plus`. The current offering must expose one monthly package and one annual package. The UK launch configuration is:

| Plan | Store duration | UK launch terms |
| --- | --- | --- |
| Monthly | One month | £4.99, no introductory trial |
| Annual | One year | £24.99, seven-day free introductory trial |

Prices shown by the app must come from RevenueCat/store localisation, not hard-coded strings. Product identifiers are permanent store identifiers: choose them once, record them in the release register, and use the exact same values in the store and RevenueCat. New products must be attached to `buff_plus` and the current offering before they are made available.

The API is authoritative for Buff+ access. The mobile app logs into RevenueCat with the authenticated account's server-issued `revenuecat_app_user_id`, purchases or restores through the native store, then refreshes the API subscription state. RevenueCat restore behaviour must be **Transfer to new App User ID**. Restrict RevenueCat sandbox testing access to the explicit App User IDs used by QA and App Review; do not allow arbitrary production accounts to receive sandbox entitlements.

### Store credentials required by RevenueCat

| Store | Credential | Where to create it | Handling |
| --- | --- | --- | --- |
| Apple | In-App Purchase key (`.p8`), Key ID, and Issuer ID | [App Store Connect → Users and Access → Integrations → In-App Purchase](https://developer.apple.com/help/app-store-connect/configure-in-app-purchase-settings/generate-keys-for-in-app-purchases) | Required for the StoreKit 2 connection; upload to RevenueCat and vault the original because Apple does not let it be downloaded again |
| Apple | RevenueCat App Store Connect API key (`.p8`), Key ID, Issuer ID, and Vendor Number | App Store Connect → Users and Access → Integrations, plus Payments and Financial Reports for the Vendor Number | Recommended for importing products and checking configuration; grant at least App Manager and keep it separate from the NativePHP upload key |
| Apple | App-specific shared secret | App Store Connect subscription settings | Only needed for legacy StoreKit 1 receipt validation/compatibility; this StoreKit 2 integration should not depend on it |
| Google | Google Play service-account JSON | Google Cloud IAM, then grant access in Play Console | Required for RevenueCat's Play connection; keep it separate from the optional build-upload account |
| Google | Pub/Sub topic and Real-time developer notifications | Google Cloud Pub/Sub and Play Console monetisation setup | Required for prompt renewals, cancellations, billing issues, and refunds |

Follow RevenueCat's current permission list when granting the Play service account; Google occasionally renames console permissions. At this audit it needs **View app information and download bulk reports**, **View financial data, orders, and cancellation survey responses**, **Manage orders and subscriptions**, and **Manage store presence**. A new credential can take up to 36 hours to validate. See [RevenueCat's Apple credentials guide](https://www.revenuecat.com/docs/store-configuration/app-store/service-credentials-index), [App Store Connect API-key configuration](https://www.revenuecat.com/docs/service-credentials/itunesconnect-app-specific-shared-secret/app-store-connect-api-key-configuration), and [Play service credentials guide](https://www.revenuecat.com/docs/service-credentials/creating-play-service-credentials).

### RevenueCat setup

1. Create the App Store Connect app and Apple subscriptions first. For Google, create the Play app and upload a signed, billing-enabled AAB to Internal testing **before** creating/importing Play subscriptions; Play must recognise the uploaded package and billing integration. Store products cannot be invented only in RevenueCat.
2. In [RevenueCat](https://app.revenuecat.com/), create the Buff project and add an iOS app and Android app using `com.spacemancodes.buff`.
3. Connect Apple and Google using the credentials above. Confirm both connections pass RevenueCat's credential checks.
4. Import the monthly and annual products from each store. Importing verifies that the store connection and product identifiers work.
5. Under **Product catalog → Entitlements**, create exactly `buff_plus` and attach every monthly/annual store product that grants Buff+.
6. Create one current offering. Add only `$rc_monthly` and `$rc_annual` packages and map them to the correct platform products.
7. Set **Project settings → Restore behaviour** to **Transfer to new App User ID**. Configure [sandbox testing access](https://www.revenuecat.com/docs/projects/sandbox-access) for named QA/reviewer `revenuecat_app_user_id` values only.
8. Copy the iOS public SDK key into `VITE_REVENUECAT_IOS_PUBLIC_SDK_KEY` and the Android public SDK key into `VITE_REVENUECAT_ANDROID_PUBLIC_SDK_KEY`. Never use a RevenueCat Test Store key in a release build.
9. Add a production webhook targeting `https://api.usebuff.app/api/v1/webhooks/revenuecat`. Configure both the shared `Authorization` value and HMAC signing secret, and put the exact corresponding values in the API environment. Use a separate endpoint and secrets for staging. [RevenueCat webhooks](https://www.revenuecat.com/docs/integrations/webhooks) require a Pro plan.
10. Send and verify a test webhook, then complete a sandbox purchase, renewal/cancellation simulation, restore, refund/revocation, and cross-device sign-in test on both stores.

Create the server-only RevenueCat secret API key under the project's API-key settings and grant only the access the API needs; see [RevenueCat authentication](https://www.revenuecat.com/docs/projects/authentication). Never put it in a `VITE_*` value or the mobile repository.

### Store notification routing

The event path is **Apple/Google → RevenueCat → Buff API**. Do not point either store directly at the Buff RevenueCat webhook.

- **Apple:** copy the dashboard-generated RevenueCat App Store Server Notifications URL into both Production and Sandbox URL fields in App Store Connect and select V2. Production and sandbox URLs may differ. Verify RevenueCat's last-received status after a purchase. Only enable RevenueCat's server-notification purchase tracking when Buff's UUID is supplied as Apple's `appAccountToken`. See [RevenueCat Apple notifications](https://www.revenuecat.com/docs/platform-resources/server-notifications/apple-server-notifications) and [Apple server URLs](https://developer.apple.com/help/app-store-connect/configure-in-app-purchase-settings/enter-server-urls-for-app-store-server-notifications).
- **Google:** create a Pub/Sub topic, grant `google-play-developer-notifications@system.gserviceaccount.com` the Pub/Sub Publisher role, enter the full `projects/{project_id}/topics/{topic_name}` value under Play Console → Monetize → Monetisation setup, enable RTDN, and send a console test notification. Confirm RevenueCat records it. See [Google Play billing backend setup](https://developer.android.com/google/play/billing/getting-ready).

Server-only RevenueCat variables live in `buff-server`, never this mobile app:

```dotenv
REVENUECAT_SECRET_API_KEY=
REVENUECAT_WEBHOOK_AUTHORIZATION=
REVENUECAT_WEBHOOK_SIGNING_SECRET=
REVENUECAT_API_URL=https://api.revenuecat.com/v1
```

Set the remaining flags by launch phase, not by copying one permanent example:

| Phase | `APP_ENV` | `REVENUECAT_ALLOW_SANDBOX_ENTITLEMENTS` | `SUBSCRIPTIONS_ENFORCE_AI_MEAL_ANALYSIS` |
| --- | --- | --- | --- |
| Isolated staging/device QA | `staging` | `true` | `true` |
| Production during TestFlight/App Review | `production` | `true` | `false` |
| Production after both stores are live | `production` | `false` | `true` |

TestFlight and App Review purchases use Apple's sandbox. During review, production sandbox acceptance is safe only while RevenueCat **Sandbox Testing Access** is set to **Allowed App User IDs only** and contains the `revenuecat_app_user_id` values of the non-expiring reviewer/QA accounts. Keep the production RevenueCat webhook filtered to production events; the authenticated subscription refresh retrieves the allowlisted review transaction. Restore the final row after both stores are live.

Use RevenueCat's [product configuration](https://www.revenuecat.com/docs/projects/configuring-products), [webhook](https://www.revenuecat.com/docs/integrations/webhooks), [restore behaviour](https://www.revenuecat.com/docs/projects/restore-behavior), [identity](https://www.revenuecat.com/docs/customers/identifying-customers), and [launch checklist](https://www.revenuecat.com/docs/test-and-launch/launch-checklist) documentation as the live source of truth.

## AdMob

Buff currently uses one banner placement named `app_shell`. Ads are for accounts without `buff_plus`; subscribers must not see the banner. AdMob is disabled by default and test mode is enabled by default.

Do not confuse the identifiers:

- Publisher ID: `pub-…`; this is the public account record used by `app-ads.txt`.
- App ID: `ca-app-pub-…~…`; one per platform and required in the native manifest/plist.
- Ad-unit ID: `ca-app-pub-…/…`; one per banner placement and platform.

The NativePHP compile hook requires a valid platform app ID even when `ADMOB_ENABLED=false`. For development only, use Google's sample app IDs—Android `ca-app-pub-3940256099942544~3347511713` or iOS `ca-app-pub-3940256099942544~1458002511`—and keep `ADMOB_TEST_MODE=true`. See the [Android quick start](https://developers.google.com/admob/android/quick-start) and [iOS quick start](https://developers.google.com/admob/ios/quick-start). Never submit a sample ID or test ad-unit ID.

| Build | `ADMOB_ENABLED` | `ADMOB_TEST_MODE` | Identifier rule |
| --- | --- | --- | --- |
| Development without banners | `false` | `true` | A platform app ID is still required to compile; Google's sample app ID is allowed only here |
| Ad QA/reviewer rehearsal | `true` | `true` | Use test ads and registered physical test devices; never click a live ad |
| First release before AdMob readiness | `false` | `true` | Use the production app ID but keep ads disabled; do not block subscription launch on AdMob review |
| Live-ad release | `true` | `false` | Use the verified production app and banner IDs only after app-ads.txt and app-readiness are approved |

### AdMob setup

1. Complete the AdMob account's identity, phone/address, payments profile, tax/bank, and mailed address-PIN steps when requested. Record the account type and publisher ID; account type is not normally changeable later. See [AdMob signup](https://support.google.com/admob/answer/7356219?hl=en), [account types](https://support.google.com/admob/answer/12835021?hl=en), and [payment verification](https://support.google.com/admob/answer/6149211?hl=en).
2. In [AdMob](https://admob.google.com/), create separate iOS and Android apps. They may begin as unpublished; after each public release, link its exact App Store/Play listing. Do not reuse an app ID across platforms.
3. Create one banner ad unit for `app_shell` in each AdMob app. Do not swap app and ad-unit IDs.
4. Set the platform-specific identifiers in the release environment:

   ```dotenv
   ADMOB_ENABLED=true
   ADMOB_TEST_MODE=false
   ADMOB_APP_ID_ANDROID=ca-app-pub-…~…
   ADMOB_APP_ID_IOS=ca-app-pub-…~…
   ADMOB_BANNER_APP_SHELL_ANDROID=ca-app-pub-…/…
   ADMOB_BANNER_APP_SHELL_IOS=ca-app-pub-…/…
   ADMOB_UMP_ENABLED=true
   ADMOB_ATT_ENABLED=true
   ADMOB_UMP_DEBUG_GEOGRAPHY=DISABLED
   ```

5. Configure User Messaging Platform consent messages for every served region. On every launch, refresh consent information, show a form when required, request ads only when `canRequestAds` permits it, and expose privacy options whenever UMP requires them. Test grant, denial, withdrawal, under-age handling, and consent-unavailable behaviour. See [UMP for iOS](https://developers.google.com/admob/ios/privacy) and [UMP for Android](https://developers.google.com/admob/android/privacy).
6. Configure App Tracking Transparency on iOS, including the AdMob IDFA message and `NSUserTrackingUsageDescription`. Let UMP sequence its explainer before ATT; denial must retain a safe non-IDFA path. Maintain the current [SKAdNetwork configuration](https://developers.google.com/admob/ios/privacy/strategies) and follow [AdMob's IDFA guidance](https://developers.google.com/admob/ios/privacy/idfa).
7. Add physical QA devices under AdMob **Settings → Test devices**. Keep `ADMOB_TEST_MODE=true` and use Google's test ads in every local, simulator, emulator, CI, and reviewer rehearsal build. Never click live ads. See [iOS test ads](https://developers.google.com/admob/ios/test-ads) and [Android test ads](https://developers.google.com/admob/android/test-ads).
8. Set the developer website in both store listings to `https://usebuff.app/`. Verify `https://usebuff.app/app-ads.txt` returns `200`, contains the exact publisher ID, then use AdMob **Verify app → Check for updates**. See [app-ads.txt verification](https://support.google.com/admob/answer/14538460?hl=en-GB) and the [app-ads.txt guide](https://support.google.com/admob/answer/9363762?hl=en).
9. Wait for both app-ads.txt **Verified** and app-readiness **Ready**, and clear **Policy centre**, before enabling live ads. Readiness depends on a public store listing, so a first release can safely ship with ads disabled. See [app setup](https://support.google.com/admob/answer/9989980?hl=en-GB), [app/ad-unit IDs](https://support.google.com/admob/answer/7356431?hl=en), and [app readiness](https://support.google.com/admob/answer/10564477?hl=en).

At the 1 September 2026 audit, the local fork declares Android Google Mobile Ads 24.0.0/UMP 3.0.0 and iOS Google Mobile Ads `~> 13.4`/UMP `~> 2.7`; current upstream lines have moved. Before submission, inspect the resolved Gradle dependencies and `Podfile.lock` against the [Android release notes](https://developers.google.com/admob/android/rel-notes), [iOS release notes](https://developers.google.com/admob/ios/rel-notes), and [iOS UMP releases](https://developers.google.com/admob/ios/privacy/download). Upgrade deprecated child/teen treatment APIs and regression-test consent, ATT, privacy options, layout, subscriber suppression, and the [Google Mobile Ads Data safety disclosure](https://developers.google.com/admob/android/privacy/play-data-disclosure) in a dedicated change—not during production upload.

## API server

The API is deployed separately from this repository. Source and full setup are in the private [`buff-server` repository](https://github.com/captenmasin/buff-server), its [local server README](../buff-server/README.md), and [local environment template](../buff-server/.env.example). The [GitHub environment template](https://github.com/captenmasin/buff-server/blob/master/.env.example) requires repository access. A local checkout is normally at `../buff-server`.

### Production URLs

| Purpose | URL |
| --- | --- |
| API base | `https://api.usebuff.app/api/v1` |
| Health check | `https://api.usebuff.app/up` |
| MCP endpoint | `https://api.usebuff.app/mcp/buff` |
| RevenueCat webhook | `https://api.usebuff.app/api/v1/webhooks/revenuecat` |
| Google OAuth callback | `https://api.usebuff.app/api/v1/auth/google/callback` |
| Apple OAuth callback | `https://api.usebuff.app/api/v1/auth/apple/callback` |

`APP_URL` is the origin `https://api.usebuff.app`, without `/api/v1`. The client uses `BUFF_API_URL=https://api.usebuff.app/api/v1` and `BUFF_MCP_URL=https://api.usebuff.app/mcp/buff`. Production must use valid HTTPS and `BUFF_ALLOW_REMOTE_HTTP=false`.

Mobile `/api/v1` account requests use Sanctum personal access tokens. The stable Passport key pair supports the optional MCP OAuth server; `MCP_ENABLED` defaults to `false`, so MCP is not a store-release prerequisite unless Connected Assistants is enabled or advertised.

The social-auth chain is Google `GET` callback or Apple `POST` callback → API account exchange → `buff://account/social/callback`. Password recovery returns to `buff://reset-password`. Register the exact HTTPS provider callbacks above and keep these custom-scheme return values aligned with `SOCIAL_AUTH_CALLBACK_URL`, `BUFF_RESET_PASSWORD_URL`, and the native deep-link scheme.

### Runtime requirements

- A web process serving Laravel with `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, trusted proxies, and the correct origin.
- A production database with migrations run during deployment and backups tested for restoration.
- One or more continuously supervised `queue:work` processes. Restart workers after each deployment so they load new code.
- A shared atomic cache/lock backend, such as Redis, used by every web and worker instance. Do not use per-process array/file caches for distributed sync locks.
- Durable private object storage for progress photos, correct CORS/signed URL configuration, lifecycle policy, and backups as appropriate.
- Laravel's scheduler invoked every minute by cron or the hosting platform.
- Stable Laravel Passport signing keys. Generate once, vault and back them up; replacing them invalidates issued tokens. Deploy either `storage/oauth-private.key` and `storage/oauth-public.key` or stable `PASSPORT_PRIVATE_KEY`/`PASSPORT_PUBLIC_KEY` values.
- Transactional email delivery for account verification, password recovery, and operational mail, with SPF/DKIM/DMARC configured for the sending domain.
- Centralised logs, uptime monitoring for `/up`, queue-failure alerts, storage alerts, database alerts, and webhook-failure monitoring.

`/up` proves only that Laravel can return an HTTP response. Monitor database, cache, queue, object storage, email, OAuth, and webhook dependencies separately.

See Laravel's current guidance for [deployment](https://laravel.com/docs/13.x/deployment), [queue workers](https://laravel.com/docs/13.x/queues#running-the-queue-worker), [the scheduler](https://laravel.com/docs/13.x/scheduling#running-the-scheduler), and [Passport deployment](https://laravel.com/docs/13.x/passport#deploying-passport).

### API environment and credentials

Copy `buff-server/.env.example` and provide production values for these exact groups. The template is authoritative if a name changes:

| Group | Required material |
| --- | --- |
| Laravel/runtime | `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `LOG_*`, `SANCTUM_EXPIRATION`, `BUFF_SYNC_MAX_FUTURE_SECONDS` |
| Database/cache/queue | `DB_*`, `CACHE_STORE`, `QUEUE_CONNECTION`, `REDIS_*`; use shared production services and supervised workers |
| Passport/MCP OAuth | Stable `PASSPORT_PRIVATE_KEY`/`PASSPORT_PUBLIC_KEY` or `storage/oauth-*.key`; `MCP_ENABLED`, `MCP_REDIRECT_DOMAINS`, `MCP_CUSTOM_SCHEMES`, `MCP_APP_APPROVAL_URL`, and the `MCP_*_TTL` values |
| Storage | `FILESYSTEM_DISK`, `MEAL_PHOTO_DISK`, `BODY_METRIC_PHOTO_DISK`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_DEFAULT_REGION`, endpoint/path-style settings where used |
| Mail | `MAIL_*`, verified sender address/domain, and support/reply routing |
| Google OAuth | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`; register the exact production callback using [Google's OAuth web-server guide](https://developers.google.com/identity/protocols/oauth2/web-server) |
| Sign in with Apple | `APPLE_TEAM_ID`, `APPLE_CLIENT_ID` (Services ID), `APPLE_KEY_ID`, `APPLE_PRIVATE_KEY`, `APPLE_REDIRECT_URI`; configure the domain/return URL using [Apple's web guide](https://developer.apple.com/help/account/capabilities/configure-sign-in-with-apple-for-the-web/) |
| App return URLs | `SOCIAL_AUTH_CALLBACK_URL`, `BUFF_RESET_PASSWORD_URL`; these normally use the `buff://` scheme described above |
| RevenueCat | `REVENUECAT_SECRET_API_KEY`, `REVENUECAT_API_URL`, `REVENUECAT_WEBHOOK_AUTHORIZATION`, `REVENUECAT_WEBHOOK_SIGNING_SECRET`, `REVENUECAT_ALLOW_SANDBOX_ENTITLEMENTS`, `SUBSCRIPTIONS_ENFORCE_AI_MEAL_ANALYSIS` |
| AI and food data | `AI_PROVIDER`, `AI_MODEL`, `AI_TIMEOUT`, `OPENAI_API_KEY`, `MEAL_ANALYSIS_DAILY_QUOTA`, `OPEN_FOOD_FACTS_URL`, `OPEN_FOOD_FACTS_USER_AGENT` |

The RevenueCat webhook is authenticated and signature-verified but intentionally not protected by Laravel's public API rate limiter; legitimate delivery bursts must not receive `429` responses. Protect it with its secrets, request validation, HTTPS, replay/idempotency handling, logs, and infrastructure-level abuse controls.

### API release verification

Before either store submission:

- Deploy the API and run migrations before uploading a client that depends on the new schema or endpoints.
- Confirm `/up` succeeds externally, not only from the host.
- Test sign-up, sign-in, password recovery, Google OAuth, Sign in with Apple, account deletion, sync conflict handling, photo upload/download, and sign-out data removal using a production-like build.
- Deliver a signed RevenueCat test webhook and confirm the event is accepted once, persisted once, and updates the expected Buff account.
- Confirm queues drain, scheduled jobs run, storage survives a redeploy, backups can be restored, and Passport keys survive a redeploy/scale event.
- Confirm the privacy policy accurately describes API, health, subscription, advertising, analytics, support, deletion, and retention behaviour.

Run the server's billing checks, complete test suite, and scheduler inventory from the sibling repository:

```sh
cd ../buff-server
php artisan test --compact tests/Feature/RevenueCatWebhookTest.php
php artisan test --compact tests/Feature/SubscriptionTest.php
php artisan test --compact
php artisan schedule:list
```

## Store submission material

The following public pages must be live before submission and remain available after release:

- [Privacy policy](https://usebuff.app/privacy/)
- [Terms of service](https://usebuff.app/terms/)
- [Support](https://usebuff.app/support/)
- [AdMob app-ads.txt](https://usebuff.app/app-ads.txt)

The live support page currently mentions account deletion but does not expose a prominent deletion-specific request action. Before Play submission, either prove the Console accepts that mechanism or publish a dedicated deletion URL/form/anchor that names Buff and the developer, explains identity verification, what is deleted or retained and for how long, expected timing, and how subscription cancellation differs from account deletion. Put that exact URL in Play Console.

Store answers must describe actual behaviour, not intended future behaviour. Keep a dated, non-secret release register containing the app version/build, Git commit, product IDs, RevenueCat offering, public SDK-key suffixes, AdMob app/ad-unit suffixes, API release, privacy-policy version, store submission IDs, rollout state, and rollback owner.

### Permanent identifier register

Record these values before creating dependent records. Apple product IDs, the bundle/package ID, and uploaded Play package identity cannot be casually renamed or reused. If a required permanent value is blank, stop instead of inventing one during upload.

| Identifier | Value to record |
| --- | --- |
| Bundle/package ID | `com.spacemancodes.buff` |
| Apple numeric app ID and SKU | **Record before release** |
| Apple subscription group ID | **Record before release** |
| Apple monthly product ID | **Record before release** |
| Apple annual product ID | **Record before release** |
| Play app URL/application ID | **Record after app creation** |
| Play monthly subscription ID / base-plan ID | **Record before RevenueCat import** |
| Play annual subscription ID / base-plan ID / trial-offer ID | **Record before RevenueCat import** |
| RevenueCat project, iOS-app, and Android-app IDs | **Record after project creation** |
| RevenueCat entitlement | `buff_plus` |
| RevenueCat current offering ID | **Record before device QA** |
| RevenueCat packages | `$rc_monthly`, `$rc_annual` |
| QA/reviewer RevenueCat App User IDs | Server-issued `revenuecat_app_user_id` UUIDs; store privately, never account IDs/emails/passwords here |
| AdMob publisher, iOS/Android app, and banner-unit IDs | **Record after account/app creation** |
| Google Cloud project, Pub/Sub topic, and service-account email | **Record before RTDN testing** |
| Staging origin and owner | **Record after isolated staging deployment** |

### Privacy data map

Maintain one dated source of truth for both Apple App Privacy and Play Data safety. For every row record the exact fields, purpose, whether linked to identity, collection/sharing/processor, retention, deletion, security, optionality, and policy/listing disclosure.

| Data area | Systems and processors to cover |
| --- | --- |
| Account/authentication | Buff device/API, email, Google OAuth, Sign in with Apple, tokens and security logs |
| Nutrition, workouts, body metrics, and health | Device SQLite, API, HealthKit, Health Connect, sync history; never use health data for advertising |
| Meal and progress photos | Device pending files, private object storage, signed URLs, deletion and backup lifecycle |
| Purchases/subscriptions | Apple, Google Play, RevenueCat, Buff API entitlement records |
| Advertising/consent | AdMob/Google Mobile Ads, UMP, ATT/IDFA, Android advertising ID, IP-derived approximate location, diagnostics and interaction data actually emitted by the final SDK/configuration |
| AI and food lookup | OpenAI and Open Food Facts requests, redaction, retention, user-agent/contact obligations |
| Support and operations | Support messages, crash/diagnostic data, webhook/log records, abuse/fraud evidence, retention and access controls |

### Asset readiness

| Asset | Repository path | Status | Required action |
| --- | --- | --- | --- |
| Primary icon source | [`public/icon.png`](public/icon.png) | **Blocked:** 1024×1024 with alpha | Export and verify an accepted opaque Apple icon; do not assume the source is store-ready |
| Apple Icon Composer source | [`public/icon.icon/`](public/icon.icon/) | Source only | Verify the final archive's generated icons, sizes, appearance, and lack of forbidden transparency |
| Play listing icon | — | **Missing** | Create a separate opaque 512×512 PNG; the 1024×1024 source is not the Play listing export |
| Apple screenshots | [`artifacts/store-screenshots/exports/apple/`](artifacts/store-screenshots/exports/apple/) | Ready-sized | Four 1320×2868 opaque RGB screenshots; recheck against the final release UI |
| Play screenshots | [`artifacts/store-screenshots/exports/google-play/`](artifacts/store-screenshots/exports/google-play/) | Ready-sized | Four 1080×1920 opaque screenshots; recheck against the final release UI |
| Play feature graphic | [`feature-graphic-1024x500.png`](artifacts/store-screenshots/exports/google-play/feature-graphic-1024x500.png) | Ready-sized | Confirm final copy/branding before upload |
| Buff+ review screenshot | [`buff-plus-subscriptions.png`](artifacts/store-screenshots/review/buff-plus-subscriptions.png) | **Blocked:** contains alpha | Flatten to an opaque RGB PNG before App Store Connect upload |
| Asset manifest/provenance | [`manifest.json`](artifacts/store-screenshots/manifest.json), [`provenance.json`](artifacts/store-screenshots/provenance.json) | Present | Update dimensions/hashes if any final export changes |
| iOS privacy manifest source | [`PrivacyInfo.xcprivacy`](native-plugins/native-refresh/resources/ios/PrivacyInfo.xcprivacy) | Partial evidence | Confirm the final archive includes every app/SDK privacy manifest and audit collected data, tracking domains, and required-reason APIs |

Screenshots must reflect the release build, show no personal/health data belonging to a real person, and match the device class and language selected in the store. Apple screenshot specifications are [here](https://developer.apple.com/help/app-store-connect/reference/app-information/screenshot-specifications/); Play graphic requirements are [here](https://support.google.com/googleplay/android-developer/answer/9866151?hl=en).

Prepare and keep outside Git:

- App name, subtitle/short description, full description, keywords where supported, category, copyright, promotional text, release notes, support contact, marketing URL, and privacy-policy URL.
- Reviewer notes explaining offline-first storage, account creation/deletion, Buff+, restore purchases, ads for non-subscribers, HealthKit/Health Connect, camera/photo use, notifications, and any feature that is region-, account-, or permission-dependent.
- A dedicated, non-privileged, non-expiring review account with representative fictional data and exact test instructions. Keep its credentials only in the private store-review field/password manager, record its `revenuecat_app_user_id` for the RevenueCat sandbox allowlist, and rotate or disable it after review. Never give reviewers a staff/admin or personal account.
- Privacy inventory/data map, retention/deletion policy, processor list, consent records, and the exact answers submitted to Apple App Privacy and Google Data safety.
- Evidence of rights to the name, icon, screenshots, copy, fonts, imagery, and any third-party content.
- Encryption/export-compliance answers, age/content-rating questionnaire answers, advertising declarations, health declarations, and account-deletion URL/flow evidence.

## iOS and App Store publishing

### Apple account and legal prerequisites

- An active [Apple Developer Program](https://developer.apple.com/programs/enroll/) membership. Organisation enrollment normally needs the legal entity name, registered address, D‑U‑N‑S number, public website/domain email, a person with legal authority, an Apple Account with two-factor authentication, and any identity/business evidence Apple requests.
- Assign organisation-owned access using [App Store Connect roles](https://developer.apple.com/help/app-store-connect/reference/role-permissions/). Accept the latest Paid Applications agreement and complete [agreements, tax, and banking](https://developer.apple.com/help/app-store-connect/manage-agreements/overview-of-agreements-tax-and-banking/) before configuring paid subscriptions.
- Complete the [EU Digital Services Act trader declaration/contact verification](https://developer.apple.com/help/app-store-connect/manage-compliance-information/manage-european-union-digital-services-act-trader-requirements/) for EU distribution.
- An App Store Connect app record for `com.spacemancodes.buff`. Follow [Add a new app](https://developer.apple.com/help/app-store-connect/create-an-app-record/add-a-new-app/); record the Apple ID and SKU in the release register.
- An explicit App ID whose final provisioning profile contains In-App Purchase, HealthKit, and HealthKit background delivery. Buff offers Google authentication on iOS, so configure Sign in with Apple and its browser/Services-ID flow to satisfy Guideline 4.8. The app uses camera access, local notifications, and the `buff` URL scheme; do not add Push Notifications or Associated Domains unless the final application actually uses them. Regenerate profiles after any capability change. See [enable app capabilities](https://developer.apple.com/help/account/identifiers/enable-app-capabilities/) and the [App Review Guidelines](https://developer.apple.com/app-store/review/guidelines/).
- Recheck Apple's live [upcoming submission requirements](https://developer.apple.com/news/upcoming-requirements/) before every archive. At the 1 September 2026 audit, uploads require Xcode 26 or later and the iOS 26 SDK.

### Apple signing and upload credentials

| Item | Environment/file | Purpose |
| --- | --- | --- |
| Developer Team ID | `NATIVEPHP_DEVELOPMENT_TEAM`, `IOS_TEAM_ID` | Selects the Apple team and signing assets |
| NativePHP upload API key | `APP_STORE_API_KEY_PATH`, `APP_STORE_API_KEY_ID`, `APP_STORE_API_ISSUER_ID` | App Store Connect upload automation; NativePHP documents the Developer role and the path points to a private `.p8` |
| Apple Distribution certificate | `IOS_DISTRIBUTION_CERTIFICATE_PATH`, `IOS_DISTRIBUTION_CERTIFICATE_PASSWORD` | Signs the App Store archive; export the certificate/private key as a password-protected `.p12` if the packaging workflow requires it |
| App Store provisioning profile | `IOS_DISTRIBUTION_PROVISIONING_PROFILE_PATH` | App Store distribution profile for the exact bundle ID/capabilities |
| Certificate request material | `credentials/ios-certificate-request.csr`, `credentials/ios-private-key.key` | Used when issuing a certificate; secret/private-key material stays outside Git and must be backed up |

There are four active Apple `.p8` purposes: NativePHP upload, RevenueCat's App Store Connect import/configuration key (App Manager or higher plus Vendor Number), RevenueCat's In-App Purchase key, and the Sign in with Apple Services-ID key used by the API. Do not reuse or confuse them; in particular, never use the Sign in with Apple key for upload or RevenueCat. Apple `.p8` files are usually downloadable only once. Record each Key ID, Issuer ID, role, owner, creation date, expiry/rotation plan, and vault location.

For a new signing identity, the operator can run `php artisan native:credentials ios`, upload the generated CSR in Apple Developer, download the distribution certificate and App Store profile, and store/export the certificate plus private key as the password-protected format required by the packaging workflow. Do not overwrite an established release identity without a documented rotation/revocation plan.

### App Store Connect preparation

1. Create one Buff+ subscription group and put monthly and annual at the **same subscription level** because both unlock the same service. Configure the UK prices above, localisations, tax category, availability, subscription descriptions, and the annual offer as **Free Trial → 1 Week**. A customer can receive only one introductory offer per subscription group. See [auto-renewable subscriptions](https://developer.apple.com/help/app-store-connect/manage-subscriptions/offer-auto-renewable-subscriptions/) and [introductory offers](https://developer.apple.com/help/app-store-connect/manage-subscriptions/set-up-introductory-offers-for-auto-renewable-subscriptions/).
2. Upload the opaque Buff+ review screenshot and add precise review notes. Apple requires the first auto-renewable subscription/group to be submitted with a new app version; add both monthly and annual products to that first version submission. See [submit an in-app purchase](https://developer.apple.com/help/app-store-connect/manage-submissions-to-app-review/submit-an-in-app-purchase/).
3. Configure App Store Server Notifications V2 for both production and sandbox using RevenueCat's URLs as described in [Store notification routing](#store-notification-routing).
4. Complete the app listing, screenshots, content rights, support/marketing/privacy URLs, App Review contact, the post-January-2026 age-rating questionnaire, and [Accessibility Nutrition Labels](https://developer.apple.com/help/app-store-connect/manage-app-accessibility/manage-accessibility-nutrition-labels/). Accessibility labels are currently voluntary but omission is shown as not indicated and the requirement may change.
5. Complete [App Privacy](https://developer.apple.com/help/app-store-connect/manage-app-information/manage-app-privacy/) from the current data map. Include data handled by the API, RevenueCat, AdMob/Google Mobile Ads, OAuth providers, support, and health/photo features, even where a third-party SDK performs collection.
6. Answer [export compliance](https://developer.apple.com/help/app-store-connect/manage-app-information/overview-of-export-compliance). Record the answer and any exemption/document identifier so each build is answered consistently.
7. Explain that Buff reads workouts/exercise calories, may sync them to the Buff account/API, writes nothing to HealthKit, never uses health data for advertising, and remains usable when HealthKit permission is refused. `NSHealthShareUsageDescription` describes read access. The current plugin manifest still declares `NSHealthUpdateUsageDescription`; remove that write-purpose declaration before release or implement and disclose genuine write behaviour. Review [HealthKit privacy](https://developer.apple.com/documentation/healthkit/protecting-user-privacy).
8. Make in-app account deletion easy to find. Deleting a Buff account does **not** cancel an App Store subscription, so also expose the system subscription-management path and explain that distinction to the reviewer. See [Apple's account-deletion requirement](https://developer.apple.com/support/offering-account-deletion-in-your-app).
9. Ensure the ATT, camera/photo, HealthKit, and notification purpose strings and every app/SDK privacy manifest in the final archive match actual behaviour. Inspect Xcode's final privacy report for collected-data declarations, tracking domains, and [required-reason APIs](https://developer.apple.com/documentation/bundleresources/describing-use-of-required-reason-api), not only the checked-in manifest.
10. Because Buff displays third-party ads, verify the release includes a way to report inappropriate or age-inappropriate ads as required by App Review Guideline 2.5.18.

The paywall and store description must disclose the localised price, billing period, automatic renewal, cancellation route, annual trial and post-trial terms, Terms/EULA, privacy policy, restore purchases, and manage-subscription access.

### Build, test, and submit iOS

The release operator runs these commands manually on a Mac with the release environment and Apple toolchain:

```sh
pnpm run build:ios
php artisan native:run ios --build=release
php artisan native:package ios --export-method=app-store
```

Inspect the generated Xcode archive before upload: release bundle ID, version/build, Release configuration, distribution profile, entitlements, privacy manifest, icons, launch assets, API URL, RevenueCat iOS public key, production AdMob iOS IDs, and absence of development/test credentials. Follow Apple's [upload-build guidance](https://developer.apple.com/help/app-store-connect/manage-builds/upload-builds/) using Xcode Organizer/Transporter or, after validating the automation credentials, NativePHP's `--upload-to-app-store` option.

Then:

1. Process the build in App Store Connect and complete any export-compliance prompt.
2. Add it to an internal TestFlight group. Test fresh install, update, account creation/deletion, permissions denied/granted, background health delivery, offline sync, Google/Apple sign-in, Buff+ monthly/annual purchase, annual trial eligibility, restore, cancellation/expiry, subscriber ad suppression, and non-subscriber consent/banner behaviour. TestFlight purchases are sandbox and subscriptions renew daily, for up to six renewals within one week; a [Sandbox Apple Account](https://developer.apple.com/help/app-store-connect/test-in-app-purchases/create-a-sandbox-apple-account/) can exercise billing-retry scenarios. See [TestFlight subscription testing](https://developer.apple.com/help/app-store-connect/test-a-beta-version/testing-subscriptions-and-in-app-purchases-in-testflight/).
3. Add [external TestFlight testers](https://developer.apple.com/help/app-store-connect/test-a-beta-version/invite-external-testers/) if required. External testers require TestFlight App Review and the first build receives a full review. Resolve crashes/store-product issues and repeat on a physical iPhone.
4. Select the build and both subscriptions, add the dedicated non-expiring review account/instructions, allowlist its `revenuecat_app_user_id` in RevenueCat, answer privacy/export/content questions, choose manual or phased release, and [submit the app for review](https://developer.apple.com/help/app-store-connect/manage-submissions-to-app-review/submit-an-app/).
5. After approval, release deliberately, verify the live listing and purchase flow, monitor API/RevenueCat/AdMob/crash dashboards, and keep the previous known-good release details available.

## Android and Google Play publishing

### Google account and legal prerequisites

- A verified [Google Play developer account](https://play.google.com/console/). Because Buff is a health app, use an organisation account unless Google explicitly approves another type. Registration currently has a US$25 one-time fee and may require the legal entity, D‑U‑N‑S number, government/business evidence, matching Payments profile, public phone/email, and organisation website/domain verification. See [choose an account type](https://support.google.com/googleplay/android-developer/answer/13634885?hl=en-EN), [registration](https://support.google.com/googleplay/android-developer/answer/6112435?hl=en), and [identity verification](https://support.google.com/googleplay/android-developer/answer/10841920?hl=en).
- A merchant/payments profile with required bank and tax information for subscriptions.
- A Play app created for `com.spacemancodes.buff`. Follow [Create and set up your app](https://support.google.com/googleplay/android-developer/answer/9859152?hl=en). The package name cannot be reused or changed after first upload.
- Play App Signing enabled. Keep Google's app-signing key distinct from Buff's upload key and understand the recovery path before production. See [Android app signing](https://developer.android.com/studio/publish/app-signing) and [Play App Signing](https://support.google.com/googleplay/android-developer/answer/9842756?hl=en-EN).
- Complete [Android developer verification](https://developer.android.com/developer-verification/guides/google-play-console), confirm `com.spacemancodes.buff` is registered to the account, and resolve any signing-key ownership request by the 30 September 2026 package-registration deadline.

### Android signing and upload credentials

| Item | Environment/file | Purpose |
| --- | --- | --- |
| Upload keystore | `ANDROID_KEYSTORE_FILE` (currently expected under `credentials/`, for example `credentials/app-release-key.jks`) | Signs the AAB uploaded to Play; back it up securely |
| Keystore password | `ANDROID_KEYSTORE_PASSWORD` | Opens the keystore |
| Upload-key alias | `ANDROID_KEY_ALIAS` | Selects the upload key |
| Upload-key password | `ANDROID_KEY_PASSWORD` | Unlocks the selected key |
| Optional Play upload account | `GOOGLE_SERVICE_ACCOUNT_KEY` | Path to a service-account JSON used only for direct Play API upload/build-number lookup |
| RevenueCat Play account | Separate service-account JSON | RevenueCat purchase/subscription access; do not expose it to the app or reuse it casually for CI |

`google-services.json` is not currently required: Buff does not use Firebase. A RevenueCat or Play Publishing service-account JSON is a different secret and must never be renamed to or bundled as `google-services.json`.

For a never-released app, the operator can use `php artisan native:credentials android` to create a keystore under the project-root ignored `credentials/` directory. Never regenerate signing material for an existing Play app: its current `.jks`, alias, and passwords are the upload identity. Vault the keystore, upload-certificate PEM, SHA-1/SHA-256 fingerprints, and every applicable app-signing fingerprint shown under **App integrity**.

For optional automated uploads, create a Google Cloud project, enable the Google Play Developer API, create a dedicated service account, and invite it in Play Console. Grant app-level read access and **Release apps to testing tracks** for internal automation; grant production release permission only if production automation is intended. See [Google Play Developer API setup](https://developers.google.com/android-publisher/getting_started) and [Play permissions](https://support.google.com/googleplay/android-developer/answer/9844686?hl=en-GB). Keep this account separate from RevenueCat's billing account.

### Play Console preparation

1. Create the Play app, enable Play App Signing, build a signed billing-enabled AAB, and upload it to **Internal testing**. This bootstrap upload must happen before Play subscription setup and RevenueCat import.
2. Create and activate monthly and annual subscription products/base plans, set regional prices/availability, and give only the annual plan a seven-day trial offer. The paywall must disclose trial duration, post-trial localised price, billing period, automatic renewal, and cancellation method. See [Play subscriptions](https://support.google.com/googleplay/android-developer/answer/140504?hl=en) and [subscription policy](https://support.google.com/googleplay/android-developer/answer/9900533?hl=en).
3. Add licence testers, publish the Internal track, accept its opt-in link with each tester account, and install through Play. Licence testers do not count toward the separate 12-person closed-test requirement.
4. Connect the dedicated billing service account, configure Pub/Sub RTDN as described above, import the active products/base plans into RevenueCat, and test the same internal AAB. A `PENDING` purchase must not grant Buff+ until it becomes `PURCHASED`.
5. Complete the main store listing, app countries/regions, short/full descriptions, category/tags, contact/developer website, privacy policy, opaque 512×512 icon, phone screenshots, feature graphic, and release notes.
6. Complete every **App content** declaration: ads, app access/reviewer account, target audience, content rating, privacy policy, account deletion, Data safety, health apps, permissions, and any other declarations the Console presents. See [prepare for review](https://support.google.com/googleplay/android-developer/answer/9859455?hl=en).
7. Complete [Data safety](https://support.google.com/googleplay/android-developer/answer/10787469?hl=en) from the same data map used for Apple, including third-party SDK collection, sharing, encryption in transit, deletion, optional data, and health data. Reconcile the answer with the final merged manifest and resolved SDKs.
8. Declare the app's Health Connect use and provide clear justifications for only these permissions:
   - `android.permission.health.READ_EXERCISE`
   - `android.permission.health.READ_TOTAL_CALORIES_BURNED`
   - `android.permission.health.READ_ACTIVE_CALORIES_BURNED`
   - `android.permission.health.READ_HEALTH_DATA_IN_BACKGROUND`

   State in the listing that Buff is not a medical device and does not diagnose, treat, cure, or prevent any medical condition, and that users should consult a healthcare professional for medical advice, diagnosis, or treatment. Keep the rationale, listing, privacy policy, and in-app behaviour consistent. See [publish a Health Connect app](https://developer.android.com/health-and-fitness/health-connect/publish) and [Google Play health policy](https://support.google.com/googleplay/android-developer/answer/16679511?hl=en).
9. Declare that the app contains ads. Health Connect data must never be transferred, sold, or used for serving ads—including personalisation, targeting, measurement, request metadata, or custom parameters. Ads cannot obstruct content, permissions, purchase, restore, subscription management, or deletion controls. See the [Health Connect permissions policy](https://support.google.com/googleplay/android-developer/answer/16558241?hl=en).
10. Record the exact target age groups and reconcile them with Buff's 13+ onboarding/terms, marketing, UMP handling, and ad behaviour. Ages 13–17 may be children in some locations; NPA/TFUA flags alone do not satisfy the Families policy if a selected audience includes children. See [Target audience](https://support.google.com/googleplay/android-developer/answer/9867159?hl=en-EN) and [Families policy](https://support.google.com/googleplay/android-developer/answer/9893335?hl=en).
11. Provide both the in-app deletion flow and the prominent web deletion request described under [Store submission material](#store-submission-material). Also expose an easy Play subscription-management/cancellation link; deleting a Buff account does not cancel a Play subscription. See [account deletion requirements](https://support.google.com/googleplay/android-developer/answer/13327111?hl=en).

For personal Play developer accounts created after 13 November 2023, production access currently requires a closed test with at least 12 opted-in testers continuously for 14 days, followed by a production-access application. Re-check the requirement shown in the account before planning launch dates: [Google's testing requirements](https://support.google.com/googleplay/android-developer/answer/14151465?hl=en-GB).

### Build, test, and submit Android

Google Play requires an Android App Bundle for a new production app. The release operator runs:

```sh
pnpm run build:android
php artisan native:run android --build=release
php artisan native:package android --build-type=bundle
```

Inspect the final signed AAB before upload: package name, increasing version code, target/min SDK, release signing certificate, production API URL, RevenueCat Android public key, production AdMob Android IDs, Health Connect rationale activity, and absence of test/debug credentials. Compare Play declarations with the final merged manifest, including Camera, Post notifications, Receive boot completed, Internet/network state, Billing, the four Health Connect permissions, and `com.google.android.gms.permission.AD_ID` if merged by Google Mobile Ads.

The dated Android release gates are:

- Target API 36 or later for new apps/updates from 31 August 2026; this repository currently targets 36. Recheck [Play target API requirements](https://developer.android.com/google/play/requirements/target-sdk).
- Google Play Billing Library 8 or later unless a temporary extension is explicitly recorded; the normal PBL 7 deadline ended on 31 August 2026. Inspect the resolved Gradle dependency and `com.android.vending.billingclient.version`, rather than assuming the RevenueCat SDK version. See the [Billing deprecation FAQ](https://developer.android.com/google/play/billing/deprecation-faq) and [release notes](https://developer.android.com/google/play/billing/release-notes).
- Every native library must satisfy [64-bit requirements](https://developer.android.com/google/play/requirements/64-bit) and API 35+ 16 KB page-size support. Confirm `bundletool dump config` reports `PAGE_ALIGNMENT_16K`, inspect every `.so`, and test on a 16 KB device/emulator. Incompatible updates become blocked on 1 February 2027; follow the [16 KB guide](https://developer.android.com/guide/practices/page-sizes) and any stricter Console warning.

Then:

1. Upload the signed bootstrap/final-candidate AAB to **Internal testing** manually, or use NativePHP's `--upload-to-play-store --play-store-track=internal` only after validating the dedicated publishing service account.
2. Add licence testers and test monthly/annual purchase, trial, pending purchase, cancellation, grace period/account hold, refund/revocation, restore/cross-device access, and upgrade paths using [Play Billing test guidance](https://developer.android.com/google/play/billing/test).
3. Test fresh install/update, offline sync, Google/Apple sign-in as applicable, account deletion, camera/photos, notifications, all Health Connect permission combinations/background reads, UMP consent, subscriber ad suppression, and non-subscriber banners on physical devices.
4. Promote the same tested artifact through closed/open testing as required. Resolve pre-launch report, policy, signing, native-library, and subscription warnings.
5. Create the production release, choose a staged rollout, add release notes, and submit it for review. Follow [prepare and roll out a release](https://support.google.com/googleplay/android-developer/answer/9859348/prepare-and-roll-out-a-release?hl=en-GB).
6. After approval, expand the staged rollout only while crash, ANR, API, RevenueCat, billing, health sync, and AdMob metrics remain healthy. Halt the rollout rather than replacing the signing key or product IDs during an incident.

## Complete release inventory

Before declaring the app ready, assign an owner and vault/document location for every applicable row:

| Area | Credentials, documents, or files |
| --- | --- |
| Company/account verification | Legal entity details, registered address, company number, D‑U‑N‑S number, authorised representative, government ID/business evidence when requested, support phone/email, domain ownership, 2FA recovery plan |
| Apple membership/store | Apple Developer membership, App Store Connect roles, app record, App ID/capabilities, Paid Applications agreement, banking/tax forms, DSA trader status, SKU/Apple ID |
| Apple signing/upload | Distribution certificate/private key, password-protected `.p12` where used, App Store provisioning profile, Team ID, NativePHP upload `.p8`/Key ID/Issuer ID, fingerprints and recovery owner |
| Apple services | Sign in with Apple Services ID/key/private `.p8`, OAuth return URL/domain, separate RevenueCat IAP and App Store Connect keys, Vendor Number, subscription group/products/level, intro offer, server-notification URLs, IAP review screenshot |
| Google membership/store | Verified organisation Play developer account, merchant profile, bank/tax details, Play app record, developer/package verification, Play App Signing enrollment, production-access/testing approval where required |
| Android signing/upload | Upload `.jks`, passwords/alias, upload certificate PEM, SHA-1/SHA-256 and Play app-signing fingerprints, secure backups, optional Play Publishing service-account JSON |
| Google services | OAuth client ID/secret and callbacks, separate RevenueCat Play service-account JSON, Pub/Sub topic/Publisher grant/RTDN test, AdMob account/payment verification/apps/units, UMP messages, app-ads.txt verification/readiness |
| RevenueCat | Project/app records, iOS/Android public SDK keys, secret server API key, `buff_plus`, current offering, store credentials, restore/sandbox policy, webhook URL/Authorization/HMAC secret |
| API/hosting | Host/DNS/CDN access, TLS, `APP_KEY`, database/cache/queue credentials, Passport key pair, storage keys/bucket, mail credentials/domain records, OpenAI credentials, backup/recovery documents, monitors and incident contacts |
| Store metadata | Name/descriptions/keywords/categories, icon, screenshots, Play feature graphic, release notes, localisation, copyright, support/marketing/privacy URLs, reviewer contact/account/notes |
| Compliance | Privacy policy, terms, support/deletion page, data map, retention/deletion policy, processor list, Apple App Privacy, Play Data safety, ads/ATT/UMP declarations, HealthKit/Health Connect declarations, export compliance, age/content ratings, content-rights evidence |
| Release record | Version/build, Git commit, API release, product IDs, public-key/AdMob ID suffixes, test evidence, submission IDs, reviewer communications, approval date, rollout state, rollback owner |

## Recommended release order

1. Finalise legal identity, human launch decisions, store memberships, agreements, banking/tax, public policy/support/deletion pages, the privacy data map, compliant assets, and the permanent identifier register.
2. Deploy isolated staging and production API environments. Verify OAuth callbacks, Sanctum/MCP policy, Passport keys, workers, scheduler, shared cache, storage, email, backups, monitoring, and the correct RevenueCat phase flags.
3. Create both store app records and signing identities. Create the Apple subscription group/products. For Google, upload a signed billing-enabled bootstrap AAB to Internal testing **before** creating products.
4. Create/activate Play products, base plans, annual offer, licence testers, and internal opt-in. Connect both stores to RevenueCat; configure `buff_plus`, the current offering/packages, server-issued `revenuecat_app_user_id` identity, transfer restore, allowlisted sandbox access, and store notification routing.
5. Configure and test the RevenueCat → Buff webhooks and full Apple/Google purchase lifecycle. During TestFlight/App Review use the documented allowlisted sandbox phase; do not enable production enforcement early.
6. Configure AdMob apps, banner units, developer-website/app-ads.txt, UMP, ATT/SKAdNetwork, test devices, and policy declarations. Keep live ads off until the public listing, verification, readiness, and payments state allow them.
7. Freeze copy/assets/configuration, increment the version once, run client/server tests and plugin validation, produce signed release candidates, and complete the physical-device/reviewer rehearsal on both platforms.
8. Submit iOS and Android independently. A rejection or delay on one store must not cause identifiers, signing keys, products, or the already-tested artifact to be recreated.
9. Release gradually, monitor every external service, reconcile test/live purchases, restore the post-launch RevenueCat flags only after both stores are live, and record the final production state.

## Data and sync

Writes are local first and enter the sync outbox. The app sends them to `buff-server` when an authenticated connection is available. Pending progress photos remain on-device until upload succeeds or the related local/account data is explicitly removed.

Signing out or deleting an account removes user-owned local data. Signing into a different account requires confirmation and then replaces the prior account's local data; already-synced server data is unaffected.

Deleting a Buff account is separate from cancelling an Apple or Google subscription. The app, support/deletion page, reviewer notes, and both listings must explain and link both actions.

## Troubleshooting

- Confirm `BUFF_API_URL` is reachable from the simulator or device, not only from the host machine.
- If RevenueCat has no offering, verify the store product is active, available in the test region, attached to `buff_plus`, in the current offering, and requested with the correct platform public SDK key.
- If Play products cannot be created or imported, confirm a signed billing-enabled AAB has already reached Internal testing and allow time for Play/RevenueCat credential propagation.
- If a purchase succeeds in the store but Buff+ does not activate, compare the RevenueCat App User ID with the API account response's `revenuecat_app_user_id`, inspect customer history/webhook delivery, then refresh the API subscription endpoint.
- If a TestFlight/App Review purchase does not activate, confirm the review account's `revenuecat_app_user_id`—not its account ID or email—is allowlisted in RevenueCat and the production-review sandbox phase is active.
- If AdMob does not load, check UMP/ATT state, the platform app ID versus ad-unit ID, test-device configuration, app readiness, and subscriber suppression before assuming there is an SDK fault.
- If a native build fails while ads are disabled, check the platform AdMob app ID; the compile hook still requires one.
- If an archive/bundle works locally but fails store validation, inspect the actual submitted artifact rather than the generated project: signature, profile, entitlements/permissions, version/build, privacy manifest, target SDK/native libraries, and embedded environment values.
- PHPUnit forces Inertia SSR off so local `.env` settings cannot create test-only SSR requests.
- If frontend changes are missing, run the existing Vite development or build script; generated assets are not a substitute for source changes.
