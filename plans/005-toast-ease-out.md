# 005 — Stop using `ease-in` on the fallback toast

- **Status**: TODO
- **Commit**: 9e6c09b
- **Severity**: MEDIUM
- **Category**: Easing & duration
- **Estimated scope**: 1 file (`resources/js/Layouts/AppShell.vue`)

## Problem

`ease-in` on UI is always wrong: it delays the first frame the user is watching. The web fallback toast (shown when native `Dialog.toast` throws) leaves with `ease-in`.

`resources/js/Layouts/AppShell.vue:239-245` — current:

```vue
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-3 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-3 opacity-0"
        >
```

Enter is already `ease-out` at 200ms (good duration for a small overlay). Leave is the bug. Vue `<Transition>` here uses CSS **transitions** (interruptible), which is correct — do not convert to `@keyframes`.

This toast is fallback-only (`showFallbackToast` after native toast fails) but the easing is still wrong.

## Target

Leave uses the same strong ease-out as enter:

- Easing: `cubic-bezier(0.23, 1, 0.32, 1)` via `ease-out` (plan 001 token)
- Leave duration: **150ms** (keep; small overlay, under 300ms)
- Enter duration: **200ms** (keep)
- Motion: keep the 12px (`translate-y-3`) + opacity. Do not use `scale(0)`.

```vue
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-3 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-out"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-3 opacity-0"
        >
```

If `--ease-out: cubic-bezier(0.23, 1, 0.32, 1)` is not in `@theme inline` yet, add it (plan 001) so `ease-out` is not Tailwind’s weak `cubic-bezier(0, 0, 0.2, 1)`.

## Repo conventions to follow

- AppShell already uses Vue `<Transition>` without importing it (compiler built-in). Keep that.
- Native toast remains the primary path in `showFlashToast`. Do not add a third toast system.
- Exemplar: this same block’s enter classes.

## Steps

1. In `resources/js/Layouts/AppShell.vue`, change `leave-active-class` from `transition duration-150 ease-in` to `transition duration-150 ease-out`.

2. Do not change enter classes, markup, timer (`4000`), or `role="status"`.

## Boundaries

- Do NOT restyle the toast.
- Do NOT switch to native-only (keep the fallback).
- Do NOT use `ease-in` anywhere else as a “pair.”
- Do NOT add a dependency.

## Verification

- **Mechanical**: `php artisan test --compact tests/Feature/ViewConfigurationTest.php` (locks `bottom-nav` / `openAddDrawer` strings in this file). `pnpm run type-check`.
- **Feel check**:
  - Force the fallback: in DevTools, block the native module or temporarily throw in the `try` of `showFlashToast`, then trigger a flash (save a meal). Toast should ease **out** on both appear and dismiss — motion starts immediately, no laggy fade-up.
  - DevTools 10% on leave: cubic-bezier is `0.23, 1, 0.32, 1` (or Tailwind `ease-out` after the token override), **not** `ease-in`.
  - If two toasts fire in a row (`clearFallbackToast` then set message), a remount is OK; do not rebuild this as a spring.
- **Done when**: `ease-in` is gone from AppShell.vue; leave is `ease-out`.
