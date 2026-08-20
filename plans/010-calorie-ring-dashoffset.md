# 010 — Ease the calorie ring dashoffset

- **Status**: DONE
- **Commit**: 9e6c09b
- **Severity**: LOW
- **Category**: Missed opportunity
- **Estimated scope**: 1 file (`resources/js/Components/CalorieRing.vue`)

## Problem

On Today, consumed calories are the hero number. The SVG ring’s `stroke-dashoffset` jumps whenever `consumed` / `goal` change (page load, Inertia reload after logging). That is a state change with no spatial continuity.

`resources/js/Components/CalorieRing.vue:36-46` — current:

```vue
                <circle
                    cx="56"
                    cy="56"
                    :r="radius"
                    fill="none"
                    class="stroke-success"
                    stroke-width="8"
                    stroke-linecap="round"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="dashOffset"
                />
```

`dashOffset` is already a computed `circumference * (1 - progress)`. No CSS transition.

`stroke-dashoffset` is paint, not compositor. One circle on Today is acceptable; do **not** animate `r`, `width`, or a second overlay. Do not animate the numeral (that would be extra scope).

## Target

Progress arc:

- `transition: stroke-dashoffset 300ms cubic-bezier(0.23, 1, 0.32, 1)` (300ms is the UI cap; this is a morph of an on-screen value)
- Reduced motion: no dashoffset tween (`motion-reduce:transition-none`)
- Keep `stroke-linecap="round"` and `-rotate-90` on the svg

Tailwind on the progress circle:

```
class="stroke-success transition-[stroke-dashoffset] duration-300 ease-out motion-reduce:transition-none"
```

`ease-out` must be `cubic-bezier(0.23, 1, 0.32, 1)` (plan 001 token). If that token is missing, add it to `@theme inline` first.

Do not add `transition-all`.

## Repo conventions to follow

- `ViewConfigurationTest` locks `role="img"` on this component. Keep the wrapper and `:aria-label`.
- Exemplar of `motion-reduce:transition-none` (until plan 007 deletes it): `Goals.vue` bar. Prefer the same utility rather than a new CSS block.

## Steps

1. On the **progress** circle only (the one with `:stroke-dashoffset="dashOffset"`), add `transition-[stroke-dashoffset] duration-300 ease-out motion-reduce:transition-none` to `class` next to `stroke-success`.

2. Leave the muted track circle unchanged.

3. Do not wrap in Vue `<Transition>`. Do not animate `consumed` text.

## Boundaries

- Do NOT change Progress.vue linear bars (plan 009).
- Do NOT add JS `requestAnimationFrame` loops.
- Do NOT introduce a chart library.

## Verification

- **Mechanical**: `php artisan test --compact tests/Feature/ViewConfigurationTest.php` (ring `role="img"`). `pnpm run type-check`.
- **Feel check**:
  - Open Today with a goal and some food. On first paint the arc may run 0 → current (OK). Logging a meal and returning to Today, the arc should grow/shrink over ~300ms, not jump.
  - If consumed is 0, the arc stays empty (offset = circumference); no flicker.
  - Emulate `prefers-reduced-motion: reduce`: dashoffset snaps. After plan 006, global reduce may already kill this; the `motion-reduce:` class is still required.
  - DevTools 10%: only `stroke-dashoffset` interpolates; the numeral is instant.
- **Done when**: the success circle has the transition utilities above; the number does not tween.
