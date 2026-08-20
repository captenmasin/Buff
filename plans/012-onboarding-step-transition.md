# 012 — Fade onboarding steps instead of swapping cards

- **Status**: DONE
- **Commit**: 9e6c09b
- **Severity**: LOW
- **Category**: Missed opportunity
- **Estimated scope**: 1 file (`resources/js/Pages/Onboarding.vue`)

## Problem

Onboarding is rare (first-run), so a short bit of motion is allowed. Today the two steps (`Daily Targets` vs `Body & Units`) toggle with `v-if` on separate `Card`s. The form body teleports. The step chips at the top also snap with no motion — leave those chips instant (high-frequency-relative to the two taps; they are status, not a panel).

`resources/js/Pages/Onboarding.vue:122-204` — three `Card v-if` blocks:

- `currentStep === 'Body & Units'` — units selects
- `currentStep === 'Daily Targets'` — calorie/macros
- `currentStep === 'Body & Units'` — body profile (a **second** card)

Back/Next sit outside those cards (`grid-cols-2` at line 206). Do not animate the buttons.

`defineOptions({ layout: null })` and safe-area classes are locked by `ViewConfigurationTest`. Keep them. Do not add `kicker`.

## Target

Wrap **only the step cards** in one keyed container and fade with `mode="out-in"`:

- Duration: **200ms**
- Easing: `ease-out` = `cubic-bezier(0.23, 1, 0.32, 1)`
- Opacity only (no `translateX` page-curl; Buff is crisp, and reduced motion should keep this fade)
- Do not use `scale(0)`
- Stagger: none. Next/Back must not wait on staggered children. `out-in`’s 200ms wait is acceptable for two rare steps.

```vue
        <Transition
            mode="out-in"
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-out"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div :key="currentStep" class="space-y-5">
                <Card v-if="currentStep === 'Body & Units'">
                    <!-- existing units card, unchanged -->
                </Card>

                <Card v-if="currentStep === 'Daily Targets'">
                    <!-- existing targets card, unchanged -->
                </Card>

                <Card v-if="currentStep === 'Body & Units'">
                    <!-- existing profile card, unchanged -->
                </Card>
            </div>
        </Transition>
```

Keep the inner `v-if`s so each step still renders the same cards. The `:key="currentStep"` is what Transition needs.

If plan 001 tokens are missing, add `--ease-out: cubic-bezier(0.23, 1, 0.32, 1)` to `@theme inline`.

## Repo conventions to follow

- Onboarding is a layout-less page (`layout: null`). Do not attach `AppShell`.
- Exemplar: `AppShell.vue` toast `<Transition>` (no import).
- Tests: `keeps account and onboarding outside the app shell` (safe-area + `layout: null`); `uses page kickers only for dates` (`$onboarding->not->toContain('kicker')`).

## Steps

1. Wrap the three step `Card`s in the `<Transition>` + keyed `<div class="space-y-5">` from Target. Preserve each card’s inner markup exactly.

2. Leave the step-indicator grid (`grid-cols-2` labels) and the Back/Next row **outside** the Transition.

3. Do not change `nextStep` / `previousStep` / `finish` logic.

## Boundaries

- Do NOT add a progress bar animation or confetti (delight budget is the fade only).
- Do NOT animate validation error appearance.
- Do NOT use `ease-in`.
- Do NOT add a dependency.

## Verification

- **Mechanical**: `php artisan test --compact tests/Feature/ViewConfigurationTest.php --filter=onboarding`. `pnpm run type-check`.
- **Feel check**:
  - Fill Daily Targets (macros matching calories), tap Next. The targets card fades out, then both Body & Units cards fade in together (~200ms + 200ms). Back reverses.
  - Next stays clickable after the fade; do not disable it for animation.
  - DevTools 10%: opacity only; no horizontal slide.
  - Reduced motion (plan 006 does not apply this page — it is not `.app-shell`). If you want reduce here, add `motion-reduce:transition-none` on the Transition classes via a wrapper class, e.g. enter-active `transition duration-200 ease-out motion-reduce:transition-none`. **Do add that** so first-run respects OS settings even without AppShell.
- **Done when**: step changes fade 200ms ease-out; tests for `layout: null` / no kicker still pass; reduced-motion kills the fade on this page via `motion-reduce:transition-none`.
