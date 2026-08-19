# 004 — Give dialogs 200ms ease-out scale, not 100ms `ease`

- **Status**: TODO
- **Commit**: 9e6c09b
- **Severity**: HIGH
- **Category**: Easing & duration + Physicality
- **Estimated scope**: 3 files (`DialogContent.vue`, `DialogOverlay.vue`, `AlertDialogContent.vue`)

## Problem

Centered dialogs (meal details via `AppSheet` default `variant="modal"`, delete confirms, Progress/Settings/Add modals) open with `duration-100` and tw-animate’s default `ease` (`cubic-bezier(0.25, 0.1, 0.25, 1)` via `var(--tw-ease, ease)`). Modal budget is **200–500ms**. Enter/exit must use **ease-out**, not `ease`. `zoom-in-95` is the correct scale floor (`0.95`, not `scale(0)`). `transform-origin: center` is correct for modals — do not change origin.

`resources/js/Components/ui/dialog/DialogContent.vue:37` — current:

```
bg-popover text-popover-foreground data-open:animate-in data-closed:animate-out data-closed:fade-out-0 data-open:fade-in-0 data-closed:zoom-out-95 data-open:zoom-in-95 ring-foreground/10 grid max-w-[calc(100%-2rem)] gap-4 rounded-xl p-4 text-sm ring-1 duration-100 sm:max-w-sm fixed top-1/2 left-1/2 z-50 w-full -translate-x-1/2 -translate-y-1/2 outline-none
```

`resources/js/Components/ui/dialog/DialogOverlay.vue:17` — current:

```
data-open:animate-in data-closed:animate-out data-closed:fade-out-0 data-open:fade-in-0 bg-black/10 duration-100 supports-backdrop-filter:backdrop-blur-xs fixed inset-0 isolate z-50
```

`resources/js/Components/ui/alert-dialog/AlertDialogContent.vue:37` overlay + `:45` panel — same `duration-100` + `zoom-in-95` pattern.

Centering uses Tailwind v4’s independent `translate` property (`-translate-x-1/2 -translate-y-1/2`), while `animate-in` keyframes animate `transform` (scale). Those **should compose**. Do not wrap/restructure unless the feel-check shows the panel sliding from the corner.

## Target

Panel (`DialogContent` and `AlertDialogContent` inner content, not the overlay):

- Keep `zoom-in-95` / `zoom-out-95` (scale 0.95 → 1) and fade
- Duration: **200ms** (low end of the modal range; Buff is a crisp utility app, not a marketing page)
- Easing: `ease-out` = `cubic-bezier(0.23, 1, 0.32, 1)` (plan 001 token). Force it on the animation with `ease-out` in the class list so `--tw-ease` is not the default `ease`.
- Keep `fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2`
- Do not use `scale-0` / `zoom-in-0`

Overlay:

- Fade only, **200ms**, `ease-out`
- No zoom

## Repo conventions to follow

- Dialogs are Reka + `cn()` like sheets. Keep `data-slot="dialog-content"` / `alert-dialog-content` / overlays (plan 006 uses them).
- If `--ease-out: cubic-bezier(0.23, 1, 0.32, 1)` is missing from `@theme inline` in `resources/css/app.css`, add it first (plan 001). Then `ease-out` is the strong curve.
- Select and Popover also use `duration-100` + zoom; that is correct for 125–200ms popovers. **Do not change them.**

## Steps

1. `DialogContent.vue`: replace `duration-100` with `duration-200 ease-out`. Leave `data-open:animate-in data-closed:animate-out data-closed:fade-out-0 data-open:fade-in-0 data-closed:zoom-out-95 data-open:zoom-in-95` and the centering utilities.

2. `DialogOverlay.vue`: replace `duration-100` with `duration-200 ease-out`. Keep fade-only `animate-in` / `fade-in-0` / `fade-out-0`. Do not add zoom.

3. `AlertDialogContent.vue`: on **both** the overlay (line 37) and the content (line 45), replace `duration-100` with `duration-200 ease-out`. Do not drop `zoom-in-95` on the content.

4. Only if feel-check fails (panel visibly slides from the top-left while scaling): split positioning. Outer wrapper keeps `fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2`; inner panel gets the fade/zoom classes. Do **not** do this unless the corner-slide is observed. Tailwind v4 `translate` + keyframe `transform` is expected to compose.

## Boundaries

- Do NOT change Select, Popover, or Sheet.
- Do NOT change `AppSheet.vue` variant logic (modal vs drawer).
- Do NOT add `@starting-style` unless you also drop keyframes (out of scope).
- Do NOT introduce a new dependency.

## Verification

- **Mechanical**: `php artisan test --compact tests/Feature/ViewConfigurationTest.php` (`routes overlays through AppSheet`). `pnpm run type-check`.
- **Feel check**:
  - Open a meal from Today (centered `AppSheet` modal). The card should stay **centered** while scaling 0.95 → 1 and fading, ~200ms, no slow start (not `ease-in` / default `ease`).
  - If it drifts from a corner, stop and apply step 4.
  - Open delete confirm (`ConfirmSheet` / AlertDialog). Same 200ms ease-out zoom. Overlay fades only.
  - DevTools 10%: scale from 0.95 not 0.0; transform-origin center is correct.
  - Spam open/close is less critical than sheets; still watch for a jump to 0.95 on interrupt (keyframes). Acceptable for occasional modals; do not rewrite to transitions in this plan.
- **Done when**: all three files use `duration-200 ease-out`; zoom remains 95; no corner drift (or wrapper fix applied).
