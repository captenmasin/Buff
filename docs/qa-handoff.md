# Buff full-feature QA handoff

Last updated: 2026-09-06  
Repository: `/Users/mason/Sites/Buff`  
Branch / starting commit: `master` at `9e9dcf1`, with a large pre-existing dirty worktree  
Overall state: **incomplete; current QA pass wrapped at the user's “Alright let's finish this up” instruction**

## Read this first

The task is to inventory every app behavior from the code, record a user story and expected behavior for each feature in one canonical spreadsheet, test every applicable story on iOS and Android emulators, document every failure, fix every reproducible logistical or UX defect, and retest every story after the fixes.

The feature inventory is complete, but the testing/fixing/retest matrix is not. Do not report the task as finished. The final RUN-0439 checkpoint has 261 stories, 439 runs, 101 defects, 23 fixed defects, 78 unresolved defects, and 0 fully verified stories. Zero fully verified means no story yet has its entire required platform and retest matrix complete.

The earlier stop/pause was honored and the user explicitly resumed work, then asked to finish the current pass. The standing instruction is **stop when usage reaches 75% used**. The live account tool last reported 69% used on 6 September while wrapping build23 search QA. No usage reset has been authorized or redeemed. If resumed, continue checking at meaningful checkpoints and stop at75%.

## Canonical source of truth

There is exactly one canonical workbook:

`/Users/mason/Sites/Buff/outputs/01a05def-1c0d-76d1-abc6-07c63a443582/Buff-feature-verification.xlsx`

Do not create a second tracker, copy, replacement workbook, CSV, or Google Sheet. Update this file in place. Its main tabs are:

- `Summary`: workflow gates and live formula-derived totals.
- `User Stories`: 261 stable story IDs, code references, expected behavior, platform applicability, and latest status.
- `Coverage Map`: feature/source coverage.
- `Defects`: 101 defect records and their current state.
- `Test Runs`: immutable historical run rows. Append new runs; never rewrite history.
- `Environment`: emulator, build, and verification notes.

The last complete workbook verification is:

`/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/inventory/search-query-b23-checkpoint-verification.json`

That verification confirms:

- 261 stories, 439 runs, 101 defects, 23 fixed, 0 fully verified.
- Old test runs were preserved.
- Story formulas were preserved.
- IDs were unique.
- Failed runs referenced known defects.
- All 6 evidence paths used by that checkpoint existed.
- No formula errors were detected.
- Latest changed-code checks:44 focused frontend tests, TypeScript and `git diff --check` passed. Both native23 builds passed. The new shared-input regression and three selection-race cases failed before their respective fixes and pass afterward. Earlier PHP409/3,252 and native hook8/138 results remain historical; no PHP changed in the search fixes.

The workbook's formulas and row data are authoritative if a number here differs from a later workbook state.

## What has been completed

### 1. Code-derived inventory

The repository was surveyed and 261 user stories were written from observable code behavior. The inventory covers routes, pages, shared components, account/authentication and onboarding, nutrition goals, meals, custom foods, barcode/photo flows, recipes, workouts, progress, weekly reporting, settings, subscriptions/ads, synchronization, Health Connect, background tasks, and native/plugin integrations.

Each story records expected behavior based on the source rather than an invented product specification. The `User Stories` and `Coverage Map` sheets contain the detailed result. Inventory is the only workflow gate currently marked complete.

### 2. Broad automated verification and fixes

There are extensive pre-existing uncommitted changes across the application and tests. Preserve them. The latest resumed round additionally changes the existing native-refresh install hook and its regression test for the iOS toast, plus this handoff and the canonical workbook. Do not reset, checkout, stash, or overwrite the dirty tree.

Earlier broad test baseline (not a fresh full-suite run after the toast patch):

- Full PHP suite: 409 passing tests, 3,252 assertions.
- `tests/Feature/MealEntryTest.php`: 42 passing tests, 369 assertions.
- Frontend Node tests: 219 passing.
- Focused recipe frontend tests: 14 passing.
- TypeScript: passed.
- Laravel Pint: passed.
- `git diff --check`: passed.
- Android web build: passed.
- Android native build 17: passed and its APK was verified before an explicit in-place install.

Do not infer emulator/user-story completion from automated tests. They are supporting evidence, not substitutes for the required native-platform behavior checks.

### 3. Fixed defects

The fixed defect IDs at the latest checkpoint are:

`DEF-004`, `DEF-007`, `DEF-017`, `DEF-022`, `DEF-025`, `DEF-032`, `DEF-033`, `DEF-035`, `DEF-036`, `DEF-037`, `DEF-087`, `DEF-092`, `DEF-094`, `DEF-096`, `DEF-097`, `DEF-098`, and `DEF-099`.

The workbook contains the exact descriptions, source changes, run references, evidence, and remaining story-level gaps for each. Do not mark additional defects fixed merely because source tests pass.

Notable completed rounds include:

- Recipe editing was exercised on both platforms on build 16. Recipe identity was preserved, yield 2 calculations were checked, ingredients could be added/removed, and cancellation reset the draft. This closed `DEF-037` and `DEF-087`. Broader stories `NUT-043` and `NUT-056` remain Blocked because their complete case sets are not finished.
- Android custom-food validation was expanded with 13 backend cases: 11 invalid boundary cases and two accepted boundary cases.
- Android build 17 narrowly verified the `DEF-097` scroll behavior fix for custom-food validation.

### 4. Earlier source fix: DEF-097

The relevant production change is in:

`/Users/mason/Sites/Buff/resources/js/Pages/Add.vue` around line 732

The custom meal submission now uses:

```ts
.post('/meals/custom', { preserveScroll: 'errors' });
```

This preserves position for validation errors but allows a successful save to navigate normally. Its focused regression test is:

`/Users/mason/Sites/Buff/tests/recipeDesign.test.ts` around lines 73-101

The test invokes the actual extracted callback with a real Inertia `useForm`, checks both ordinary custom-food and photo-review-style payloads, verifies the transformed numeric payload, confirms `preserveScroll: 'errors'`, and proves rejected input remains intact.

Android build 17 native results:

- Blank name: scroll `559.619` before and after; input retained; relevant error visible.
- 121-character name: scroll `535.619` before and after; input retained.
- Portion quantity `10001`: scroll `535.619` before and after; input retained.
- Macro value `1001`: scroll `535.619` before and after; input retained.
- Correcting macro fields reduced errors from 3 to 2 to 1 to 0.
- One corrected submission saved exactly once and returned to the dated Today screen at scroll 0.

This is enough to keep `DEF-097` Fixed on Android. It is not enough to complete `NUT-040`, which remains Blocked pending the rest of the story's boundary/unit coverage and iOS/native coverage. It also does not prove the photo-review UI path on iOS.

Evidence bundle:

`/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/inventory/android-custom-scroll-b17-evidence.json`

Build/install provenance:

`/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/inventory/android-build17-install-provenance.json`

The already-imported checkpoint payload and verification are:

- `/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/inventory/custom-scroll-b17-checkpoint.json`
- `/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/inventory/custom-scroll-b17-checkpoint-verification.json`

**Do not import that checkpoint again.** It already produced runs through `RUN-0406`; rerunning it would duplicate history.

### 5. Latest native fix and retests: DEF-098 and DEF-022

The shared iOS ToastManager positioned confirmations above a fixed bottom offset, where the native banner could obscure them. The existing native-refresh hook now patches generated `Toast.swift` to `safeAreaInsets.top + 16` and an opaque native `.darkGray` background. No vendor source or Android toast implementation was edited. Source changes are in `native-plugins/native-refresh/src/Commands/InstallNativeShellIntegrationsCommand.php` and `tests/Feature/NativePluginHooksTest.php`.

The regression test runs the real install hook against the installed vendor template, checks the position/background, and reapplies it to verify stable output. Eight tests / 138 assertions, Pint and diff checks pass. Build 18's position-only retest failed because the heading showed through the translucent background; that failure is preserved in RUN-0412. Build 19 includes both corrections and passes two native confirmations with banners present (RUN-0413).

Build 19 saved `QA b19 grams` on 20 August, Breakfast, 350 g, 488 kcal, P45/C50/F12, and `QA b19 ml`, Lunch, 200 ml, 410 kcal, P20/C60/F10. Their combined 898 kcal, P65/C110/F22, IDs and other checked record hashes survived cold relaunch exactly. Native screenshots show the same dated totals and rows. DEF-098 is Fixed; this does not complete the whole platform matrix.

The subsequent iOS macro validation round submitted all three macros at 1001. All three messages appeared without manually scrolling; the rejected draft created no meal. Correcting protein, carbs and fat cleared their errors independently, then one valid save produced `QA b19 validation`, Lunch on 19 August, 350 g, 488 kcal, P45/C50/F12. UUID: `01a07616-2683-70bd-b2ed-0ad7db4dbd76`. RUN-0414 closes the missing iOS DEF-022 retest; its Android retest already passed. NUT-040 remains Blocked for the other native boundaries and unit cases.

### 6. Shared field-feedback fix: DEF-099, Fixed

RUN-0415 found stale name and portion errors in the shared custom/photo-review form. Blank/overlength names and portion 10001 are rejected correctly; portion 0 gets a clear native minimum popup. But editing these fields leaves the obsolete server message, so 0 still shows the old maximum-10000 error. No invalid meal was saved; 18 August remained empty and the total meal count stayed 24.

`resources/js/Pages/Add.vue` now applies the same field-specific `clearErrors` model-update pattern as macros to name, portion quantity and unit. A new test in `tests/recipeDesign.test.ts` executes the actual template callbacks with real Inertia forms and checks that only the edited error clears without changing input values. It failed before the fix; all 15 focused tests, TypeScript and diff check now pass. No backend or dependency change.

iOS build 20 compiles this fix, verifies the source plus all three compiled handlers, and is installed in place on QA Pro. The pre-install private backup is recorded in `inventory/ios-build20-before.json`. Its post-install proof is `inventory/ios-build20-after.json`. RUN-0416 passed independent name/portion feedback, blank and overlength rejection, quantity minimum and all three negative-macro popups. Rejected submissions created no meal. `QA b20 min` saved Breakfast on 18 August, 0.1 g and blank macros stored as zero (ID `01a07634-0512-7298-ab4c-7e484c909e04`); `QA b20 max` saved Lunch, 10000 ml and P1000/C1000/F1000, 17000 kcal (ID `01a07637-e555-71f4-8993-ec6c078f576c`). Both confirmations and dated rows were native-visible. Reports: `inventory/ios-boundaries-b20-rejected.json`, `ios-boundaries-b20-minimum.json`, and `ios-boundaries-b20-saved.json`. Together with unchanged macro maximum cases from RUN-0414, NUT-040's iOS retest passes. DEF-099 stays In Progress until Android passes. Android still runs build 17; its build 20 web bundle passed and guarded native compilation is underway.

## Current emulator and account state

RUN-0417 supersedes the pending Android notes above. Android build 20 passes independent name/portion correction, blank/121-character rejection, quantity minimum, and all three negative macro native popups. Two accepted meals saved once on 17 August: `QA b20 Android min`, Breakfast, 0.1 g with blank macros stored as zero (ID `01a0764e-9bfa-7161-a593-ec2148abf169`), and `QA b20 Android max`, Lunch, 10000 ml/P1000/C1000/F1000, 17000 kcal (ID `01a07650-7793-73ac-b82d-b0297a5d5724`). Native confirmations/rows and UI state match; pending sync 0. The maximum draft used the visible previous-custom-food card and was renamed; the unit picker was changed ml→g→ml. Unchanged macro maximum/clearing cases reuse build 17 evidence. DEF-099 is Fixed; NUT-040 retests both Passed but its initial iOS gap remains. No direct photo-review native test is claimed.

### Android

- Device: `emulator-5554`.
- AVD: Medium Phone, Android 17 / API 37.
- Package: `com.spacemancodes.buff`.
- QA Android user: user 10.
- Installed build: `1.0.0b21`.
- Last known QA process: PID 28381 on 6 September; current CDP forward 9222 targets that QA WebView. Recheck before using a PID.
- An Owner 0 process appeared automatically during earlier QA launches. Do not claim Owner 0 was unaffected or manually launched.
- Android shares the installed APK across users. Build 20 replacement stopped QA PID6036 and Owner0 PID13317; only QA user10 was explicitly launched. Owner0 PID20945 later appeared automatically. No user data was read from Owner0.
- No uninstall, package clear, emulator wipe, logout, account deletion, or database reset was performed.

The APK and embedded bundle were hash-verified against the local build, the signer matched build 16, the package/version were verified, and the packaged `Add.vue`/compiled Add module contained the `preserveScroll: 'errors'` change. See the build 17 provenance JSON above for hashes and limitations.

Current build20 proof: `inventory/android-build20-before.json` and `inventory/android-build20-install-provenance.json`. Installed APK SHA256 `f0d5bfe807bb7a81b47554aa6f55a1e07afea360214fccb2c5a8b8e0040b39bf`; signer unchanged, embedded bundle/current source/80 assets/three compiled error handlers verified. Build-only guard blocked NativePHP's automatic install; explicit `adb install -r --user 10` succeeded. No private Android database was queried/copied. Aug23 meal IDs and all summary data except the time-dependent streak matched the stale pre-update page. Streak changed1→0 across the calendar-day refresh; it is excluded from equality. Initial sync failure cleared after one Retry sync; pending0. Emulator lock-screen timeout caused black pre-install/launch captures; these are not app rendering evidence. Unlocking promptly restored a clean native viewport.

QA Android saved data that must be preserved:

- 2026-08-23 breakfast: ID `01a06d2c-c329-73e5-a0f4-9256c2b3e16b`, “QA Android custom validation 2026-09-04”, 350g, 488 kcal, P45/C50/F12.
- 2026-08-23 dinner: ID `01a06d41-3e76-7341-8fa2-5941c74551ed`, “QA Android scroll regression 2026-09-04”, 100g, 165 kcal, P10/C20/F5.
- Exact 2026-08-23 total: 653 kcal, P55/C70/F17, sync pending 0.
- Existing 2026-09-04 meal total: 500 kcal.
- Existing 2026-09-04 workouts: 124 + 123 = 247 kcal.
- Historical meal count at the build 17 Android save was 17. iOS has since saved additional QA fixtures; do not assume this is the current cross-device count.

The app uses the production API endpoint `https://api.usebuff.app/api/v1` with a dedicated QA account. Do not touch real user data or perform broad/destructive operations.

### iOS

- QA device: iPhone 17 Pro simulator, iOS 26.5.
- UDID: `B5DFD1FE-B554-4A7D-996B-BF8F952B2C33`.
- Package: `com.spacemancodes.buff`.
- Installed and extracted build: `1.0.0b20`; local `.env` version code is 21 and iOS build21 compilation is underway.
- Last known state: Today on 13 August, no unsaved draft, PID52031. Native Settings was inspected read-only for offline controls, then Buff was brought back to the foreground.
- Last checked iOS records: 35 meals, 9 recipes, 7 workouts, 2 body metrics, 1 profile, 1 goal, 1 preferences row, outbox0. The seven Android fixtures synced after cold launch, and two newer iOS reuse fixtures were added. Recheck before calling these current counts. Account binding is the dedicated QA account (ID6).
- The separate iPhone 17 simulator beginning `FF9D5978` remained on build 15 and was intentionally left untouched.

iOS testing resumed with native UI, video/screenshots and authorized read-only QA database checks. Do not attach WebKit/JavaScriptCore remote inspection again.

Two simulator crashes were associated with active remote inspection:

1. `SIGABRT` / `NSInvalidArgumentException`, nil dictionary key in `Inspector::RemoteInspector::pushListingsNow()` immediately after a successful remote-inspector read. Diagnostic summary:
   `/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/inventory/ios-build16-remote-inspector-crash.json`
2. `EXC_BAD_ACCESS` / `SIGILL` / Bus error 10 on the same JavaScriptCore remote-inspector queue, with recursive binary-plist serialization under `pushListingsNow()`. Raw crash report:
   `/Users/mason/Library/Logs/DiagnosticReports/NativePHP-simulator-2026-09-04-164406.ips`

These prove real foreground crashes associated with the inspection path. They do **not** prove a deterministic recipe/PHP defect or a common release-build crash. Native-only recreation had passed. Do not patch app source based solely on these stacks.

### QA credentials

The temporary QA account record is private and mode `0600`:

`/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/inventory/qa-account-ios-retest-private.json`

Do not copy its contents into logs, prompts, screenshots, shell output, documentation, or the workbook. Read it only when login is necessary and keep the account scoped to QA data.

## Completed Android cold-launch result

The last substantive action cold-launched Android build 17 and returned to 2026-08-23. The correct before/after evidence shows the full day summary survived exactly, including both meals, IDs, portions, macros, totals, goal, empty workouts, streak, and pending sync count. The only expected difference is transient flash/sync timing metadata.

Correct evidence:

- Before cold launch: `/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/emulator-runs/retest/android/NUT-039-b17-cold-before.json`
- After cold launch on the correct date: `/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/emulator-runs/retest/android/NUT-039-b17-cold-aug23.json`
- Native screenshot: `/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/emulator-runs/retest/android/NUT-039-b17-cold-aug23.png`

The independent deep comparison of both complete `value.summary` objects passed after resumption. It was imported as RUN-0407 and verified. Do not import it again.

There are misleading intermediate artifacts captured on the wrong selected date:

- `NUT-039-b17-cold-after.json`
- `NUT-039-b17-cold-settled.json`
- their matching screenshots

**Do not use those wrong-date files as persistence proof.** Only the `cold-before.json` versus `cold-aug23.json` pair is valid.

The historical snapshot taken before that completed import is:

`/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/inventory/custom-cold-b17-before.json`

## Android renderer limitation: DEF-091

`DEF-091` remains Open with a verified local emulator workaround. Rendering corruption reproduced on builds 15, 16 and 17 while page state and calculations remained coherent. The controlled build 17 comparison then held the APK, WebView 152.0.7977.65 and complete QA summary constant: lavapipe failed, SwiftShader failed, host GPU passed, returning to lavapipe failed, and a cold launch using saved host mode passed again.

The historical failed screenshot is:

`/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/emulator-runs/retest/android/NUT-039-b17-cold-aug23.png`

The only AVD configuration change was `/Users/mason/.android/avd/Medium_Phone.avd/config.ini`: `hw.gpu.mode=auto` became `hw.gpu.mode=host`. The old config is preserved as `inventory/def091-avd-config-before.ini`. No app CSS or rendering workaround was shipped. Host mode uses the Apple M4 Pro renderer; the failing software modes reported ANGLE/SwiftShader. Exact comparison evidence and hashes are in `inventory/def091-renderer-comparison-b17.json` under the QA artifact root. RUN-0408 and RUN-0409 record the experiment. Do not repeat the five-way comparison without new evidence requiring it.

Native date/ring, scrolled groups, meal detail and repeated cold launch were clean in host mode. RUN-0418 completes NUT-039 Android's fresh g/ml save/toast and cold-persistence cases under host mode, so its latest retest is Passed. The old Failed runs remain historical. The underlying emulator/software-renderer issue remains Open because a general app-level fix was not established.

## First action after this checkpoint

Check the latest workbook verification, then continue from its unresolved story/platform rows. The import backlog is complete through RUN-0423. Its saved verification confirms all 422 older runs and story formulas are preserved, IDs are unique, all 3 evidence files exist, and formula errors are zero. Do not rerun any of those checkpoint updaters.

The completed checkpoint sequence is:

- `custom-cold-b17`: RUN-0407.
- `renderer-b17`: RUN-0408–0409.
- `ios-custom-b16`: RUN-0410–0411, new DEF-098.
- `toast-b18`: RUN-0412, failed position-only toast fix.
- `toast-b19`: RUN-0413, passed native toast and cold persistence; DEF-098 Fixed.
- `validation-b19`: RUN-0414, passed narrow macro checks; DEF-022 Fixed, broader NUT-040 still Blocked.
- `boundaries-b19`: RUN-0415, failed stale field feedback; new DEF-099 In Progress, source checks passed.
- `boundaries-b20`: RUN-0416, passed iOS boundary and field-feedback retest; DEF-099 remains In Progress for Android.
- `android-boundaries-b20`: RUN-0417, passed Android boundary/field-feedback retest; DEF-099 Fixed, both NUT-040 retests Passed.
- `android-custom-reuse-b20`: RUN-0418–0419, passed Android manual g/ml entry with cold persistence and recent-food reuse/deduplication/editing.
- `android-offline-b20`: RUN-0420, passed Android offline custom save, exact persistence across an offline cold restart, and automatic sync on reconnection.
- `ios-recent-b20`: RUN-0421, first iOS NUT041 coverage recorded as Initial Passed; separate Retest still Not Run.
- `ios-offline-b20`: RUN-0422, iOS NUT042 Initial Blocked at network-loss precondition; no app failure or offline save claimed.
- `recipe-guidance-b20`: RUN-0423, DEF096 reproduced on Android20; minimal source fix passes tests, fresh native21 retests pending.

RUN-0421 saved two meals on13August: Breakfast `QA b20 Android ml`,200ml/410kcal/P20/C60/F10,ID `01a0766d-a216-72cc-859a-3339957006c9`; Lunch `QA b20 iOS reuse`,200ml/414kcal/P20/C61/F10,ID `01a0766f-d50b-73c4-990d-197a3d06f511`. Native prefill clears three errors,copy/edit calculations pass,identical copies retain one recent card,and ten unique cards remain. Exact prior Breakfast and seven synced Android fixtures are unchanged; total824/P40/C121/F20,outbox0. Data reports are `inventory/ios-reuse-b20-before.json`, `ios-reuse-b20-duplicate.json`, and `ios-reuse-b20-saved.json`.

RUN-0422: iOS26.5 native Settings lacks airplane/wifi/cellular controls. Developer settings were traversed completely; Performance exposes L4S but no Network Link Conditioner. simctl/Features likewise provide no isolated network-loss control. No host network/firewall or simulator settings changed. The offline precondition is blocked, not passed or a product failure. Details and official Apple references are in `inventory/ios-offline-b20-environment-observation.json`.

RUN-0423: Android20 native0.15 recipe servings again reopens the keyboard over the nearest-valid-values guidance0.1/0.2. No log request; cancel preserves14August39kcal. `RecipeMode.vue` now sends the native validationMessage to its existing inline servings error via `@invalid.prevent`; model updates clear only servings errors. Required/min/max/step remain unchanged. A behavioral test using actual template callbacks,Vue modifiers and real Inertia forms failed before the change,then all16focused frontend tests,TypeScript and diffcheck passed. DEF096 is In Progress pending native retests.

Android21 compiled with the existing build-only guard. APK SHA256 `40e278eaa000dc7df1bb04b67b1923693bc16610d5c764a1c5d22edc4b9fe603`,unchanged signer,current source and80assets verified;explicit install-r--user10 succeeded. Shared replacement stopped QA24710 and newly observed Owner0 26738;only QA10 relaunched,PID28381. Complete14August summary equals pre-install exactly,pending0. Proof: `inventory/android-build21-apk-verified.json` and `android-build21-install-provenance.json`. No private Android DB access. iOS21 web build passed and native build is underway; perform the private backup/provenance steps before in-place installation.

RUN-0420 saved `QA b20 Android offline` once on 14 August, Snacks100g/P1/C2/F3,39kcal; ID `01a07663-ed89-7383-8437-1a0753c13800`. Airplane mode, no active default network and native offline banner were verified. Native confirmation/row appeared, pending1. The complete summary and queued item survived QA-only cold restart. Disabling airplane mode restored wifi/mobile and automatically synced pending0/errornull at11:06:03UTC, with the same complete summary. QA PID24710, Owner0PID20945 unchanged. Current Android Today14August is scrolled to that row, no draft. No Android database access. iOS native recent-food reuse is next; QA Pro cold-launched with PID52031, no remote inspection. Usage last checked36% used.

Do not reimport a checkpoint already represented in the workbook. RUN-0418 saved `QA b20 Android g` (Breakfast350g/P45/C50/F12,488kcal) and `QA b20 Android ml` (Lunch200ml/P20/C60/F10,410kcal) on 16 August. Both confirmations were native-visible; the complete 898kcal summary matched exactly after QA-only cold restart, with pending sync0. RUN-0419 on 15 August proved a recent card clears three existing errors and copies every value. Saving an identical410kcal copy retained one recent card; editing carbs60→61 and the name created `QA b20 Android reuse`, Lunch414kcal, while the earlier Breakfast stayed410. The day total is824kcal/P40/C121/F20, pending0. Ten unique recent cards remained. Evidence is in the imported checkpoint payload. No Android private database or banner-present toast proof is claimed. Continue NUT-042 offline save, then outstanding iOS reuse/offline coverage. Preserve every QA fixture.

## Workbook tooling and authoring rules

Before editing the XLSX, load and follow the current spreadsheet skill and its required references:

`/Users/mason/.codex/plugins/cache/openai-primary-runtime/spreadsheets/26.904.11930/skills/spreadsheets/SKILL.md`

Use the workspace dependency loader to obtain the bundled Node/runtime paths. Follow the skill's marker requirement exactly once per authoring session. Preserve styles, formulas, validations, frozen panes, filters, and existing row history.

The main workbook scripts are:

- Builder: `/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/build_feature_tracker.mjs`
- General updater: `/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/update-feature-tracker.mjs`
- Snapshot helper: `/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/inventory/snapshot-recipe-editor-b16.mjs`
- Checkpoint verifier/import helper: `/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/inventory/verify-build16-checkpoint.mjs`

The verifier writes run evidence column `J` from the new payload and checks dynamic counts, unchanged old runs, formulas, linked defects and evidence paths. Run it with the new checkpoint prefix, for example `validation-b19`. It also saves formatting before its final verification. Review an existing payload for shape; never blindly rerun a completed payload-driven script. The spreadsheet authoring marker already succeeded once during this continuous authoring session; compaction alone does not start another session.

## Useful evidence and manifests

All QA artifacts live under:

`/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582`

Key files:

- Build 16 Android provenance: `inventory/android-build16-install-provenance.json`
- Build 16 iOS QA Pro provenance: `inventory/ios-pro-build16-install-provenance.json`
- Build 17 Android provenance: `inventory/android-build17-install-provenance.json`
- Build 16 Android custom-food evidence: `inventory/android-custom-food-b16-evidence.json`
- Build 17 Android custom-scroll evidence: `inventory/android-custom-scroll-b17-evidence.json`
- Build 16 Android recipe-edit evidence: `inventory/android-recipe-edit-b16-evidence.json`
- Build 16 iOS recipe-edit case manifest: `emulator-runs/retest/ios/batch-016-case-manifest.json`
- Build 16 iOS recipe-edit observations: `emulator-runs/retest/ios/batch-016-observations.json`
- iOS remote-inspector crash diagnosis: `inventory/ios-build16-remote-inspector-crash.json`
- Latest toast workbook verification: `inventory/toast-b19-checkpoint-verification.json`
- Latest validation checkpoint: `inventory/validation-b19-checkpoint.json` and `inventory/validation-b19-checkpoint-verification.json`, both finalized.
- Controlled Android renderer evidence: `inventory/def091-renderer-comparison-b17.json`
- iOS build 18 and 19 install preservation: `inventory/ios-build18-before.json`, `inventory/ios-build18-after.json`, `inventory/ios-build19-before.json`, `inventory/ios-build19-after.json`
- iOS build 19 exact cold persistence: `inventory/ios-toast-b19-cold-before.json`, `inventory/ios-toast-b19-cold-after.json`
- iOS build 19 macro rejection/corrected save: `inventory/ios-validation-b19-rejected.json`, `inventory/ios-validation-b19-saved.json`
- Android CDP harness: `emulator-harness/android-cdp.mjs`

The artifact tree contains many earlier batch manifests, screenshots, workbook snapshots, and verification JSON files. Prefer a checkpoint's explicit evidence list and verification result over similarly named intermediate images.

## Build and device-safety notes

The user later allowed fresh builds and emulator use. That permission does not authorize destructive data operations.

iOS builds 18 and 19 used build-only `php artisan native:build --simulated --target=B5DFD1FE-B554-4A7D-996B-BF8F952B2C33 --no-tty --no-interaction`, then an explicit in-place install on QA Pro. Each install was preceded by a private 0700 full-container backup and consistent SQLite snapshot. Stable QA record hashes/account binding were compared before/after; installed executable, ZIP and all 80 extracted assets matched. Build 19 compiled the actual `Toast.swift` change. Provenance records contain the backup paths. Do not rerun completed before/after scripts against a later data state.

NativePHP's ordinary Android run path can install with replacement flags and may fall back to uninstalling. Do not invoke it blindly against the QA emulator. For build 17, a temporary build-only `adb` guard was used so Gradle could finish while every device operation was blocked; the APK was then verified and installed explicitly in place for user 10.

The previous temporary guard paths were:

- Build-only guard: `/private/tmp/buff-qa-build-only.fb0jZq/adb` — allowed only `adb version`, blocked all device operations with exit 64.
- Older uninstall/clear guard: `/private/tmp/buff-qa-adb-guard.Zq0Rsz/adb` — insufficient for user-10 isolation because it only blocked uninstall/clear.

Temporary paths may disappear after reboot/model switch. Do not assume either guard still exists or is correct. Revalidate or recreate the stricter build-only guard before any fresh Android native build. Verify the output APK's package, version, signer, embedded bundle, and compiled fix before a deliberate explicit in-place install.

Never uninstall, clear package data, wipe an emulator, reset the database, delete the QA account, log out, purchase, subscribe, or incur charges without the user's explicit action-time confirmation. Preserve the QA fixtures and other Android users.

The protected Android private-database export/comparison remains pending permission. A prior attempt was safety-blocked; do not retry through a bypass. Therefore byte-for-byte local database equality before/after build 17 is **not proven**. Public package metadata and UI-visible persisted values are not a substitute for that proof.

## Testing constraints and evidence quality

- Use both iOS and Android emulators for the requested final matrix.
- Native screenshots/UI observations are required for visible behavior. DOM/CDP evidence can support state and calculations but cannot prove correct native rendering.
- Do not use WebKit Remote Inspector on iOS; it was associated with two crashes.
- Avoid Android `/json/protocol` inspection; earlier probing was crash-prone. The existing narrow harness and native screenshots are safer.
- Use unique QA fixture names and IDs so every save can be distinguished from a duplicate.
- For every run, record build, platform, story, phase, expected result, actual result, evidence paths, and defect ID when failed.
- Failed runs remain historical after a fix. Add a new Retest row; do not convert the old failure into a pass.
- A defect becomes Fixed only after the root-cause change, an automated regression check, a fresh build where required, and narrow native verification. A story becomes fully verified only after all applicable cases and platforms pass post-fix.
- Keep external/testability limitations explicit rather than manufacturing a pass.

## Remaining work, in order

1. If resumed, continue after the verified `search-query-b23` import through RUN-0439; next run440. Do not duplicate any completed checkpoint.
2. DEF014, DEF015, DEF031, DEF072, DEF096, DEF100 and DEF101 are Fixed. Both platforms run installed/extracted23. NUT020/045 still have explicit native matrix gaps (especially precise iOS timing/pending Cancel), and NUT021/023/024 still need composition, locale/cache and offline coverage. Follow the remaining workbook rows.
3. iOS NUT041 Initial passes but its separate retest remains. NUT042 iOS is blocked by unavailable isolated network-loss controls. Use iOS native-only inspection and Android's saved host-GPU configuration. Recheck runtime PIDs before relying on them.
4. Work through every `Not Run`, `Blocked`, and `Failed` story/platform cell in the canonical workbook. Use the workbook ordering and prerequisites rather than starting a second checklist.
5. For each reproducible logistical or UX defect: isolate the shared root cause, make the minimum change following repository conventions, add/update the narrowest useful test, run affected tests, then run the relevant broader checks.
6. Build and retest the exact native behavior on both applicable emulators. Preserve immutable failed run history and append post-fix Retest rows.
7. Complete the protected Android database equality check only if the user grants explicit permission for that specific export/comparison action; do not bypass the existing block.
8. Reverify the workbook after each import: formulas, unique IDs, run count, defect count, evidence existence, and unchanged historical rows.
9. Finish only when every applicable story has been tested and post-fix retested, every observed error is documented, each reproducible UX/logistical defect is fixed or has an explicit external blocker, and there are no untracked failures.

## Repository conventions to re-read before editing code

The project rules are in:

- `/Users/mason/Sites/Buff/AGENTS.md` or the injected project instructions for this workspace.
- `/Users/mason/Sites/Buff/.ai/rules/index.md`
- `/Users/mason/Sites/Buff/.ai/rules/`

Before changing a code path, read the index, all matching rule files, and search `.ai/rules` for the relevant domain keyword. NativePHP work requires the project `nativephp-mobile` skill; Laravel/PHP work requires the Laravel conventions; Inertia/Vue work requires the Inertia Vue skill; tests require `testing-best-practices`. Confirm installed package versions before relying on version-specific APIs. Use `apply_patch` for source edits and preserve unrelated dirty changes.

## Current completion assessment

The inventory is complete, the tracker is healthy, and native retests have closed additional defects. The full two-platform matrix and universal post-fix retest remain incomplete. The workbook determines completion; historical automated passes do not replace current native evidence.

When resuming, verify the latest checkpoint and let the workbook's unresolved rows drive subsequent work. RUN-0407 is already imported.


## Build 21 recipe feedback checkpoint: RUN-0424–0425

Both QA platforms now run installed/extracted 1.0.0b21. iOS QA Pro PID 65277 was installed in place after the completed private backup in `inventory/ios-build21-before.json`; `ios-build21-after.json` verifies all prior checked records, executable/ZIP and 80 assets. The first after-check ran before extraction finished and failed before writing a report; the completed check then passed. No reinstall retry or data reset. Android PID 28381 and build21 provenance remain as recorded above.

DEF-096 is Fixed. Android blank, 0, 100.1 and 0.15 show complete inline messages without reopening the keyboard or sending a meal request. IME submission of 0.25 also keeps nearest values 0.2/0.3 fully visible above the open keyboard. Editing clears the previous error. iOS passes the same boundaries and correction behavior; its native step message is the platform-provided “Enter a valid value.” Read-only iOS QA data confirms no rejected meals.

A corrected 0.2-serving recipe A saved exactly once per platform: Android Lunch on 14 August, ID `01a07688-5b7b-71a6-b2b1-45103dae1726`; iOS Lunch on 12 August, ID `01a0768e-773d-7048-8716-cf74e07ab870`. Each is 17 kcal/P1/C2/F0.5. Android retained the existing 39-kcal Snacks entry, totaling 56 kcal/P2/C4/F3.5. iOS 12 August totals 17 kcal and the total meal count is 37. Both sync queues are empty. Native confirmation and dated rows are captured. iOS toast settled frame is `DEF-096-b21-confirmation-settled.png`; the first extracted frame was during fade-in.

The canonical workbook checkpoint is `inventory/recipe-guidance-b21-checkpoint-verification.json`: 425 immutable runs, 99 defects, 17 fixed, 82 unresolved, zero fully verified, 35 evidence paths, no formula errors. NUT-052 remains Blocked for stale recipe/date/type and server-error reset cases. Initial iOS coverage is not backfilled from these retests. Do not reimport this checkpoint.

Next work: recipe ingredient search DEF-031 / NUT-045 and remaining cancellation races. Android is at Add food on 14 August; iOS is Today on 12 August. No unsaved recipe draft is active at this checkpoint. Usage last checked 45%; stop at 75% used.


## Search ordering defect: RUN-0426 / DEF-100

User interrupted, then explicitly said “just continue”. Usage last checked 55%; stop at 75% used. Native Android build21 evidence shows `Thai peanut` sending `q=Thai+peanu`; replacing it with `Ab` sends no request. The shared Input forwarded `@input` before its own `updateValue` published the model. Both All foods and recipe search read the prior value. DEF-100 is distinct from debounce/rate-limit DEF-031 and stale-response DEF-015. It links NUT-020 and NUT-045.

The minimal fix moves `@input="updateValue"` before `v-bind="attrs"` in `resources/js/Components/ui/input/Input.vue`. The new actual compiled-template event test in `tests/foodRequests.test.ts` failed before the change and passes afterward for text, clearing, numeric zero and decimal. All 38 focused tests, TypeScript and diff check pass. No dependency or PHP changes. The verified canonical checkpoint `inventory/search-order-b21-checkpoint-verification.json` has 426 runs, 100 defects, 17 fixed, zero fully verified, four evidence paths, no formula errors. Do not reimport it. DEF-100 remains In Progress until native22 checks.

Local `.env` version code is now22. Android web and guarded native22 builds passed. `inventory/android-build22-apk-verified.json` verifies package22, unchanged signer, exact current Input/RecipeMode/Add sources and 80 archived asset hashes. APK SHA256 `3426bbc44e296767f242aa519dce75e0c6156535e0cdf50e378dbbd81f3dc76e`. Compiled Input emits its local `onInput` before forwarded attrs. Android22 is NOT installed yet. Device was asleep/locked after the user interruption; pending native key/screenshot commands stalled and were canceled. A device-scoped adb reconnect was attempted; transport then reported offline. No reset, wipe, uninstall, database access or emulator restart.

iOS web/native22 builds passed. Before backup `inventory/ios-build22-before.json` captures all37 meals/9 recipes/outbox0 and other preserved QA records. In-place22 install and launch completed; post-extraction provenance and native search checks pending. Latest launch PID must be read from tool output. No saved recipe draft was created. Android retains an unnamed New recipe search draft with no ingredients; no save occurred.

## Build22 search verification: RUN-0427–0430

This checkpoint supersedes the preceding pending-install notes. Android transport recovered through the scoped reconnect/wake/unlock, with no restart or reset. Explicit `adb install -r --user 10` succeeded; installed public APK SHA matches the build22 APK above, signer unchanged, QA PID4901. CDP9222 targets `webview_devtools_remote_4901`. The dated Aug14 summary is unchanged at two meals/56kcal/P2/C4/F3.5, outbox0. Private Android database equality remains unproven. iOS PID51318 runs22; `ios-build22-after.json` verifies records, executable/ZIP and80 assets. `ios-search-b22-records.json` confirms all checked records still unchanged after searches, including37 meals/9 recipes/outbox0.

DEF100 is Fixed. Android recipe and All foods both send exact `q=Ab`; rapid recipe `Thai peanut` sends one complete query 250.4ms after final native input. iOS All foods Ab returns products; 121-character queries in both consumers reach the server's120-character boundary, confirming current full value. Both platforms show one-character guidance and restore previous foods when cleared. Android recipe failure/Retry/recovery is supported by request traces; iOS visible error/Retry/recovery, oat-milk selection and Cancel/reopen pass. No recipe or meal was saved in this round.

Canonical `search-native-b22-checkpoint-verification.json` verifies430 immutable runs,100 defects,18 Fixed,82 unresolved,0 fully verified,27 evidence paths, and no formula errors. NUT020 and NUT045 aggregate retests remain Blocked for deliberately delayed responses and pending cancellation races; native iOS debounce timing is not measured. DEF031 remains In Progress. DEF014 native hint checks pass both platforms, with targeted source regression review still pending before closure. No initial iOS result was backfilled. Next run431; no pending workbook import. Both apps currently show empty All foods search/history (Android Aug14; iOS Sept6). Usage last60%; stop at75% used.

## Product-selection race fixed: RUN-0431–0434

DEF101 reproduced on both native22 platforms: append to a query with displayed products, then select one before the replacement search settles. The ingredient is added and the input clears, but queued search results return underneath it. Android event/request traces prove the request started23.3ms after selection and returned1840ms later; native screenshots show the empty query and late products. iOS milk + chocolate and immediate selection reproduces it too. `selection-race-b22` records both immutable failures,432 runs/101 defects.

The fix replaces two partial clear assignments in `RecipeMode.addProduct` with the existing `resetFoodSearch()` call. Three actual-source tests failed before and pass after for a queued request, late success and late failure.41 focused tests, TypeScript and diff check passed. No new dependency or PHP change.

Both web/native23 builds passed. Android build-only adb guard blocked automatic installation; deliberate in-place QA user10 install succeeded. APK SHA256 `14cd180d8c0f6d817b2fffcee6ee2ca0cfcf13e378c7d03299bbd9204b8a9d69`; unchanged signer, exact sources and80 archived assets matched the Android host build. Public installed APK hash matches. QA PID7874, CDP9222 forwards to `webview_devtools_remote_7874`. Owner0 PID7619 was also observed after install and was not targeted. The initial launch used an incorrect relative activity name and failed without launching; package resolution gave `com.nativephp.mobile.ui.MainActivity`, then explicit user10 launch succeeded. Aug14 full summary remains unchanged, outbox0. Private Android database equality is still not proven.

iOS QA Pro PID90995 runs installed/extracted23. `ios-build23-before.json` and `ios-build23-after.json` verify private backup, all37 meals/9 recipes and other checked records, executable/ZIP and80 assets. Native23 exact selection reproductions now stay cleared on both platforms. Android additionally proves a successful in-flight response completing after selection cannot restore results; each of two deliberate taps adds one100g/143kcal draft ingredient. Both drafts were cancelled without saving. DEF101 is Fixed. Canonical `selection-race-b23-checkpoint-verification.json` verifies434 runs/101 defects/19 Fixed/0 fully verified,9 evidence paths, unchanged history/formulas and no errors. Next run435; no pending import.

Further unimported checks: Android `NUT-045-b23-pending-cancel-reopen.{json,png}` proves Cancel during a3912.8ms request and a clean reopened draft. iOS `NUT-045-b23-clear-search.mov` and `...-settled.png` show clearing while Searching is visible and no late results. The earlier iOS `...-cancel-search.mov` attempt did NOT establish pending cancellation; results arrived before Cancel could be reached. Do not count it as a pass. All recordings are stopped. New tests in `foodRequests.test.ts` cover debounce, stale replies before the next request, short/empty queries, history filtering and Retry for both consumers; these two cases passed in43 focused tests. A further compiled hint test passes (foodRequests now10 tests). Latest usage67%; stop at75% used. NUT020/045 remain incomplete until their actual matrix gaps are closed.

## Final wrap: RUN-0435–0439

The preceding unimported checks are now recorded by `search-races-b23` through438. All foods pending-clear passes both native platforms; Android traces show clearing339ms after request start and ignoring its200 response that took5393.1ms. iOS native video shows Searching before clearing and retained previous-food history afterward. A replacement-query Android check ends with current milk results, but it does not by itself prove the exact inter-request debounce window. Deterministic tests execute the actual functions to resolve old success during that window and reject old failure after clearing, while checking full trimmed query,250ms debounce, Retry and recipe history filtering. A compiled hint test verifies blank/space/one/two-character behavior.44 focused tests and TypeScript pass. DEF014/015/031 are Fixed at their narrow defect scope; aggregate story gaps remain explicitly Blocked, and no Initial iOS results were backfilled.

Final `search-query-b23` RUN439 closes Android DEF072: both native typing and keyboard Search send `q=peanut&locale=en-US`, return200 and show catalogue cards. The broader NUT021 history/composition/review selection, NUT023 locale-change/cache and NUT024 offline cases were not completed by this check. The user asked to finish up, so no new feature work was started. Final workbook439 runs/101 defects/23 Fixed/78 unresolved/0 fully verified; failed run history and formulas preserved. No pending import, build, recording or test process remains. Both apps run23; Android is All foods with peanut results on Aug14, iOS is empty All foods/history on Sept6. No unsaved recipe draft remains, and no recipe or meal was saved during this search pass. Read-only iOS counts remain37 meals/9 recipes/outbox0. Android private database equality remains unproven under the standing safety block. Last usage69%, below the75% limit.
