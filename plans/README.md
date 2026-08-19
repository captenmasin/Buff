# Animation plans

Plans from the improve-animations audit of Buff (commit `9e6c09b`). Each file is self-contained. Execute in a fresh agent with no audit context.

Personality: crisp native-style tracker. Prefer deleting high-frequency motion over adding bounce.

## Status

| # | Plan | Severity | Status |
| --- | --- | --- | --- |
| 001 | Button press `scale(0.97)` + drop `transition-all` (introduces easing tokens) | HIGH | TODO |
| 002 | Remove Today week-strip `transition` | HIGH | TODO |
| 003 | Bottom sheets travel `translateY(100%)` with `--ease-drawer` | HIGH | TODO |
| 004 | Dialogs `duration-200 ease-out` (feel-check centering) | HIGH | TODO |
| 005 | Fallback toast leave `ease-out` | MEDIUM | TODO |
| 006 | `prefers-reduced-motion` keeps opacity/color | MEDIUM | TODO |
| 007 | Goals split bar: no `width` tween | MEDIUM | TODO |
| 008 | Gate `hover:` with `(pointer: fine)` | MEDIUM | TODO |
| 009 | Progress / Switch / Badge: drop `transition-all` | LOW | TODO |
| 010 | Calorie ring `stroke-dashoffset` 300ms | LOW | TODO |
| 011 | Meal details ↔ edit opacity crossfade | LOW | TODO |
| 012 | Onboarding step fade | LOW | TODO |

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
