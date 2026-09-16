# Focused release-blocker QA plan

## Objective

Get Buff ready for release by resolving crashes, data loss, and failures that prevent core actions. Pause the exhaustive 261-feature QA matrix. Keep its existing evidence and history, but let release impact determine the next task.

## Priorities

1. **Crashes and startup failures:** pending-photo crashes, broken release bundles, incorrect platform assets, and release version mismatches.
2. **Data loss and sync failures:** offline photos, onboarding recovery, and locally accepted records that cloud sync rejects.
3. **Blocked core actions:** failures that prevent saving meals, goals, or progress, including overlays that cover required controls.

Cosmetic polish, minor edge cases, and broad coverage expansion are deferred unless they expose a release blocker.

## Full issue list and locations

All recorded issues, including those not named in this plan, are in the canonical spreadsheet:

**[Buff-feature-verification.xlsx](/Users/mason/Sites/Buff/outputs/01a05def-1c0d-76d1-abc6-07c63a443582/Buff-feature-verification.xlsx)**

Absolute path: `/Users/mason/Sites/Buff/outputs/01a05def-1c0d-76d1-abc6-07c63a443582/Buff-feature-verification.xlsx`

- **Defects:** find the `DEF-xxx` ID for severity, affected platform, reproduction steps, expected/actual behavior, evidence paths, root cause, source/test locations, proposed fix, and retest status.
- **User Stories:** follow the defect's linked story IDs for the app workflow, expected behavior, code references, and platform coverage.
- **Test Runs:** read the latest relevant runs for actual results, installed build, remaining gaps, and evidence. Historical failures remain recorded after a fix.
- **Environment:** check emulator details and testing constraints before native verification.

Start by filtering unresolved Critical and High defects, then assess them against the priorities above. Check current code and recent runs: an In Progress entry may already have a source fix awaiting verification. Use the workbook's current entries rather than treating this plan as a frozen list of outstanding bugs. Resolve source locations by function name if recorded line numbers have moved.

## First target

Start with **DEF-067 / HLT-023: Android crashes when previewing an offline progress photo** (Critical).

- **Where in the app:** Progress → save a new body metric with a JPEG while offline → display the locally staged photo.
- **Recorded failure:** the JPEG response crossed NativePHP's JNI string bridge. `NewStringUTF` rejected binary byte `0xff` as invalid Modified UTF-8, and Android terminated with `SIGABRT`.
- **Application code:** [BodyMetricPhotoUploader.php:74](/Users/mason/Sites/Buff/app/Services/BodyMetricPhotoUploader.php:74), `pendingPhotosFor()`, now returns base64 data URLs at line93. The binary-response method `pendingPhotoResponse()` still exists at line106; check callers before assuming every preview path is safe.
- **Native bridge:** [php_bridge.c](/Users/mason/Sites/Buff/vendor/nativephp/mobile/resources/androidstudio/app/src/main/cpp/php_bridge.c), where response bytes are passed to `NewStringUTF`. This is dependency code for tracing the cause, not the first place to patch.
- **Existing regression:** [ProgressPhotoTest.php:234](/Users/mason/Sites/Buff/tests/Feature/ProgressPhotoTest.php:234), `lists and serves staged pending photos when cloud has none yet`, checks the data URL and decoded image bytes.
- **Original crash evidence:** [HLT-023-pending-photo-native-crash.json](/Users/mason/.codex/visualizations/2026/09/01/01a05def-1c0d-76d1-abc6-07c63a443582/emulator-runs/initial/android/HLT-023-pending-photo-native-crash.json). The canonical workbook's Defects row DEF-067 contains the reproduction and status.

The source fix already exists; verify it before changing code. Run the focused pending-photo regression, then retest on Android: stage a JPEG offline, confirm the preview renders without a crash, relaunch and confirm it survives, then reconnect and confirm upload succeeds. Preserve existing QA data. Close DEF-067 only after the native crash retest passes.

Then assess offline photo persistence and onboarding recovery. Treat tracker severity and status as leads, not proof that a defect still exists.

## Workflow for each blocker

1. Read its recorded reproduction, relevant code, existing fix, and tests. Reuse sufficient evidence rather than repeating completed checks.
2. If still broken, trace the shared cause and make the smallest necessary fix. Follow repository rules and preserve unrelated work.
3. Run one focused regression check covering the failure. Reuse or update an existing test where possible.
4. Perform one relevant native retest of the exact behavior on the affected platform. Test the other platform only when the change or defect affects it too.
5. Record the result and move on. Broaden testing only after a failure, a new change, or a concrete unresolved risk.

Build only when the installed app does not contain the change needed for the retest. Preserve app data and existing fixtures; destructive actions still require explicit permission.

## Reporting and tracking

Update the canonical workbook linked above in place. Append results without rewriting historical failures. Keep all issue details there; do not create a second tracker or copy the full defect list into this plan.

Update the workbook once per completed blocker or stopping point. Keep updates brief: blocker, fix, verification result, and any remaining release risk. Avoid repeated inventories, redundant screenshots, and running totals that do not change the next decision.

## Completion and stopping rules

A blocker is resolved when its fix passes the focused regression check and applicable native retest. If verification is blocked, record the specific reason and move to the next actionable blocker.

The pass is complete when no identified release blocker remains unresolved or unassessed. Report explicit external blockers separately; do not claim the exhaustive feature matrix is complete.

Check live account usage at meaningful checkpoints and stop work at **75% used**, or earlier if the user pauses. Do not redeem a usage reset without authorization.
