# 007 — Stop animating width on the Goals split bar

- **Status**: TODO
- **Commit**: 9e6c09b
- **Severity**: MEDIUM
- **Category**: Performance
- **Estimated scope**: 1 file (`resources/js/Pages/Goals.vue`)

## Problem

The protein/carbs/fat summary bar interpolates `width`. Width is layout, not compositor. The percents only change when the user picks a different split (occasional), so the strongest fix is to **delete** the animation, not invent a `scaleX` stack.

`resources/js/Pages/Goals.vue:196-202` — current:

```vue
                    <div
                        v-for="macro in macros"
                        :key="macro.key"
                        class="h-full transition-[width] duration-200 ease-out motion-reduce:transition-none"
                        :class="macro.color"
                        :style="{ width: `${macro.percent}%` }"
                    />
```

`ease-out` here is fine; the property is not.

The preset chips below this bar do **not** animate their mini bars (static `:style` widths). Match that.

## Target

Same markup, no transition:

```vue
                    <div
                        v-for="macro in macros"
                        :key="macro.key"
                        class="h-full"
                        :class="macro.color"
                        :style="{ width: `${macro.percent}%` }"
                    />
```

Widths still update immediately when `macro.percent` changes.

## Repo conventions to follow

- `tests/Feature/ViewConfigurationTest.php` `presents goals as a calorie target and named macro split` locks `bg-protein`, `bg-carbs`, `bg-fat`, `role="radiogroup"`. Do not remove those.
- Mini bars on preset buttons (same file, ~254–256) already omit transitions — copy that.

## Steps

1. On the three summary-bar segments (`v-for="macro in macros"` inside the `role="img"` track), replace `class="h-full transition-[width] duration-200 ease-out motion-reduce:transition-none"` with `class="h-full"`.

2. Leave `:style="{ width: `${macro.percent}%` }"` and `:class="macro.color"` unchanged.

3. Do not add `transform: scaleX(...)`.

## Boundaries

- Do NOT change the custom macro wheel (`macro-wheel` / `snap-y`) in this file.
- Do NOT change `Progress.vue` (plan 009).
- Do NOT animate `flex-grow` as a substitute (still layout).

## Verification

- **Mechanical**: `php artisan test --compact tests/Feature/ViewConfigurationTest.php --filter='presents goals'`.
- **Feel check**:
  - On Goals, switch High protein → Balanced → Higher fat. The three colors must jump to the new percentages with **no** width tween. Grams text may update in the same frame.
  - Nudge calories ±50: split percents stay the same, so the bar should not move at all.
  - DevTools 10%: no width animation on those nodes.
- **Done when**: `transition-[width]` is gone from Goals.vue; preset switching snaps.
