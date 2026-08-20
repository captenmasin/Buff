# 011 — Crossfade meal details and the edit form

- **Status**: DONE
- **Commit**: 9e6c09b
- **Severity**: LOW
- **Category**: Missed opportunity
- **Estimated scope**: 1 file (`resources/js/Pages/Today.vue`)

## Problem

Tapping Edit on a meal swaps `mealSheetMode` from `'details'` to edit with a `v-if` / `v-else` pair. The overlay stays mounted (`AppSheet`); only the inner body teleports. That is a jarring content swap inside an already-open panel.

`resources/js/Pages/Today.vue:742-828` — current structure:

```vue
        <AppSheet :open="Boolean(mealSheetMode && selectedMeal)" labelled-by="meal-sheet-title" @close="closeMeal">
                <template v-if="mealSheetMode === 'details'">
                <div class="flex items-start justify-between gap-3">
                    ...
                </div>
                ...
                </template>
                <template v-else>
                <div class="mb-4 flex items-center justify-between gap-3">
                    ...
                </div>
                <form class="space-y-4" @submit.prevent="saveMealEdit">
                    ...
                </form>
                </template>
        </AppSheet>
```

Vue `<Transition>` is already used for the toast in `AppShell.vue` (compiler built-in, no import).

## Target

Opacity crossfade, **200ms**, `ease-out` = `cubic-bezier(0.23, 1, 0.32, 1)`. `mode="out-in"` so the two states never overlap (avoids double-exposure). Optional `blur(2px)` during the fade is allowed (audit max 20px; use **2px** only). Prefer **opacity only** unless the fade still feels like a hard cut at 10% playback; then add `blur-[2px]` on the from/to classes.

Do not translate the body (plan 006 / reduced motion: keep opacity, drop movement). Do not use `@keyframes`.

```vue
        <AppSheet :open="Boolean(mealSheetMode && selectedMeal)" labelled-by="meal-sheet-title" @close="closeMeal">
            <Transition
                mode="out-in"
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-200 ease-out"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="mealSheetMode === 'details'" key="details">
                    <!-- existing details markup, unchanged -->
                </div>
                <div v-else-if="mealSheetMode === 'edit' || mealSheetMode" key="edit">
                    <!-- existing edit markup, unchanged -->
                </div>
            </Transition>
        </AppSheet>
```

Use `key="details"` / `key="edit"` so Transition sees a real swap. The `v-else-if` must still cover the current `v-else` (edit mode). If `mealSheetMode` is only `'details' | 'edit' | null`, `v-else` with `key="edit"` is enough.

If plan 001 has not landed, add `--ease-out: cubic-bezier(0.23, 1, 0.32, 1)` to `@theme inline` so `ease-out` is the strong curve.

## Repo conventions to follow

- `ViewConfigurationTest` `highlights meal rows...` locks meal **row** classes (`rounded-none px-5 py-2.5 text-left`). Do not change those.
- `labelled-by="meal-sheet-title"` must remain; both titles already use `id="meal-sheet-title"`.
- Exemplar: `AppShell.vue` toast `<Transition>` class names (duration + ease-out + opacity). Match that style.

## Steps

1. Replace the two `<template v-if>` / `<template v-else>` wrappers with a `<Transition mode="out-in">` and two keyed `<div>`s. Move existing inner nodes unchanged into those divs.

2. Use the exact enter/leave classes in Target (opacity only).

3. Do not import `Transition`.

4. Do not change `startEditingMeal`, `closeMeal`, or `AppSheet` props.

## Boundaries

- Do NOT convert the meal overlay from modal to a bottom sheet (not selected).
- Do NOT animate the AppSheet open/close here (plans 003/004).
- Do NOT stagger children. Stagger must never block Edit/Save.
- Do NOT add a dependency.

## Verification

- **Mechanical**: `php artisan test --compact tests/Feature/ViewConfigurationTest.php` (meal row classes + AppSheet). `pnpm run type-check`.
- **Feel check**:
  - Open a meal, tap Edit. Details fade out, then the form fades in (~200ms each because `out-in`). No stacked double image. Save/Cancel still work.
  - Spam Edit: because `out-in` waits, a second tap during fade should not show both UIs. If `mealSheetMode` cannot toggle back without a close, that is fine.
  - DevTools 10%: opacity only (and blur 2px only if you added it).
  - `prefers-reduced-motion`: after plan 006, movement is already gone; opacity fade may remain — correct.
- **Done when**: details ↔ edit uses Vue Transition opacity 200ms ease-out; row highlight tests still pass.
