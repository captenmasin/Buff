# Plans

Each file is self-contained. Execute in a fresh agent with only that markdown file.

## Product plans

Gaps in the calorie-tracker / progress loop (starting weight, charts, recipes, eat-back, weekly insights). Personality: honest tracker, not a bigger diary. Do not add TDEE, micros, water, or health-app weight import unless a plan says so.

### Status

| # | Plan | Severity | Status |
| --- | --- | --- | --- |
| 013 | Starting weight on day one | HIGH | TODO |
| 014 | Date-true charts, longer history | HIGH | TODO |
| 015 | Smoothed weight trend | HIGH | TODO |
| 016 | Recipes / copy yesterday | HIGH | TODO |
| 017 | Eat-back preference | MEDIUM | TODO |
| 018 | Weekly insights | MEDIUM | TODO |

### Recommended order

1. **013** (onboarding; independent).
2. **014** then **015** (015’s trend line needs 014’s date axis).
3. **016** (logging; independent of progress).
4. **017** then **018** (insights should use eat-back targets).

### Dependencies

- **015** → **014**. If 014 is skipped, copy its X-by-date chart math into 015 before drawing the trend polyline.
- **018** → **017** recommended, not required. Without 017, insights use the current eat-all-back `effective_target`.
- **013, 016, 017** have no product-plan dependency.

### Execute

Run one plan at a time, e.g. any agent with only `plans/013-starting-weight-day-one.md`.

---

# Animation plans

Plans from the improve-animations audit of Buff (commit `9e6c09b`). Each file is self-contained. Execute in a fresh agent with no audit context.

Personality: crisp native-style tracker. Prefer deleting high-frequency motion over adding bounce.

## Status

| # | Plan | Severity | Status |
| --- | --- | --- | --- |
| 001 | Button press `scale(0.97)` + drop `transition-all` (introduces easing tokens) | HIGH | DONE |
| 002 | Remove Today week-strip `transition` | HIGH | DONE |
| 003 | Bottom sheets travel `translateY(100%)` with `--ease-drawer` | HIGH | DONE |
| 004 | Dialogs `duration-200 ease-out` (feel-check centering) | HIGH | DONE |
| 005 | Fallback toast leave `ease-out` | MEDIUM | DONE |
| 006 | `prefers-reduced-motion` keeps opacity/color | MEDIUM | DONE |
| 007 | Goals split bar: no `width` tween | MEDIUM | DONE |
| 008 | Gate `hover:` with `(pointer: fine)` | MEDIUM | DONE |
| 009 | Progress / Switch / Badge: drop `transition-all` | LOW | DONE |
| 010 | Calorie ring `stroke-dashoffset` 300ms | LOW | DONE |
| 011 | Meal details ↔ edit opacity crossfade | LOW | DONE |
| 012 | Onboarding step fade | LOW | DONE |

Finding 9 (shared tokens as its own cleanup) was **not** selected. Tokens are still introduced in **001** because every later ease-out/drawer plan needs `cubic-bezier(0.23, 1, 0.32, 1)` and `cubic-bezier(0.32, 0.72, 0, 1)` in `@theme inline`. Finding m4 (meal row → bottom sheet instead of centered modal) was **not** selected — 011 only crossfades inner content.

## Recommended order

1. **001** first (tokens + every tap).
2. **009** (same `transition-all` cleanup, other primitives).
3. **002** (independent, high-frequency).
4. **003** then **004** (overlays; 003 needs `--ease-drawer` from 001).
5. **005** (toast easing; needs strong `ease-out` from 001).
6. **006** after 001/003/004 so reduce rules match the new transform/opacity motion.
7. **007**, **008** (independent).
8. **010**, **011**, **012** (additive; order among them does not matter).

## Dependencies

- **003, 004, 005, 009, 010, 011, 012** → **001** tokens (`--ease-out`, `--ease-drawer` in `resources/css/app.css` `@theme inline`). If 001 is skipped, copy those two exact beziers into `@theme inline` before using `ease-out` / `ease-drawer`.
- **006** → **001, 003, 004** (button scale + sheet/dialog slots).
- **002, 007, 008** have no motion-token dependency.

## Execute

Run one plan at a time, e.g. `improve-animations execute 001-button-press-no-transition-all` or any agent with only that markdown file.
