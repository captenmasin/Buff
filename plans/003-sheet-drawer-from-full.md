# 003 — Slide sheets from off-screen with the drawer curve

- **Status**: DONE
- **Commit**: 9e6c09b
- **Severity**: HIGH
- **Category**: Easing & duration + Physicality + Interruptibility
- **Estimated scope**: 2–3 files (`resources/js/Components/ui/sheet/SheetContent.vue`, `resources/js/Components/ui/sheet/SheetOverlay.vue`; tokens in `resources/css/app.css` if plan 001 has not landed)

## Problem

The Add drawer (and every Reka `SheetContent`) enters with `animate-in` **keyframes** (not interruptible), default `ease`, a redundant `transition duration-200 ease-in-out`, and `slide-in-from-bottom-10`.

`slide-in-from-bottom-10` sets `--tw-enter-translate-y` to `10 * var(--spacing)` = **2.5rem**, not the sheet’s own height. The panel fades in while hopping 40px. Nothing in the real world does that. Bottom sheets should travel `translateY(100%)`.

Entering/exiting UI must use a strong ease-out; drawers specifically use `--ease-drawer: cubic-bezier(0.32, 0.72, 0, 1)`. Duration budget for drawers: **200–500ms**.

`resources/js/Components/ui/sheet/SheetContent.vue:45` — current class (single string):

```
bg-popover text-popover-foreground fixed z-50 flex flex-col gap-4 bg-clip-padding text-sm shadow-lg transition duration-200 ease-in-out data-[side=bottom]:inset-x-0 data-[side=bottom]:bottom-0 data-[side=bottom]:h-auto data-[side=bottom]:border-t data-[side=left]:inset-y-0 data-[side=left]:left-0 data-[side=left]:h-full data-[side=left]:w-3/4 data-[side=left]:border-r data-[side=right]:inset-y-0 data-[side=right]:right-0 data-[side=right]:h-full data-[side=right]:w-3/4 data-[side=right]:border-l data-[side=top]:inset-x-0 data-[side=top]:top-0 data-[side=top]:h-auto data-[side=top]:border-b data-[side=left]:sm:max-w-sm data-[side=right]:sm:max-w-sm data-open:animate-in data-open:fade-in-0 data-[side=bottom]:data-open:slide-in-from-bottom-10 data-[side=left]:data-open:slide-in-from-left-10 data-[side=right]:data-open:slide-in-from-right-10 data-[side=top]:data-open:slide-in-from-top-10 data-closed:animate-out data-closed:fade-out-0 data-[side=bottom]:data-closed:slide-out-to-bottom-10 data-[side=left]:data-closed:slide-out-to-left-10 data-[side=right]:data-closed:slide-out-to-right-10 data-[side=top]:data-closed:slide-out-to-top-10
```

`resources/js/Components/ui/sheet/SheetOverlay.vue:16` — current:

```
bg-black/10 supports-backdrop-filter:backdrop-blur-xs fixed inset-0 z-50 duration-100 data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0
```

`tw-animate-css` implements `animate-in` as `@keyframes enter` (see `node_modules/tw-animate-css/dist/tw-animate.css`). Keyframes restart from zero if the drawer is toggled mid-motion. Reka presence already listens for `transitionend`, so CSS **transitions** are the correct primitive.

Bare `slide-in-from-bottom` (no `-10`) already exists in tw-animate and sets `--tw-enter-translate-y: 100%`. This plan does **not** use that: it leaves keyframes entirely.

## Target

Panel (all four `side` values):

- Properties: `transform` and `opacity` only
- Duration: `320ms`
- Easing: `cubic-bezier(0.32, 0.72, 0, 1)` via the `ease-drawer` utility (from plan 001 tokens)
- Bottom: closed/start `translateY(100%)`; open `translateY(0)`
- Left: closed/start `translateX(-100%)`; open `translateX(0)`
- Right: closed/start `translateX(100%)`; open `translateX(0)`
- Top: closed/start `translateY(-100%)`; open `translateY(0)`
- Closed opacity `0`, open opacity `1`
- First paint: `@starting-style` matches the closed transform/opacity so entry interpolates (Tailwind v4.3 `starting:` variant is available)

Overlay:

- Opacity only, `200ms`, `ease-out` = `cubic-bezier(0.23, 1, 0.32, 1)`
- No slide, no keyframes

## Repo conventions to follow

- Overlay components are Reka wrappers with `data-slot="sheet-content"` / `data-slot="sheet-overlay"`. Keep those slots; plan 006 targets them.
- `AppSheet.vue` passes `side="bottom"` and must keep working with no class changes required. Do not edit `AppSheet.vue` unless a class merge forces it.
- Tokens: if `@theme inline` does not yet contain `--ease-drawer: cubic-bezier(0.32, 0.72, 0, 1)`, add it (same three tokens as plan 001). Do not invent a fourth curve.
- Exemplar of slot + `cn()`: `SheetOverlay.vue`.

## Steps

1. Confirm `resources/css/app.css` `@theme inline` has `--ease-drawer: cubic-bezier(0.32, 0.72, 0, 1)` and `--ease-out: cubic-bezier(0.23, 1, 0.32, 1)`. If missing, add them (plan 001).

2. Replace the motion-related utilities on `SheetContent.vue` `DialogContent` `:class`. Keep every **layout** utility (`fixed`, side insets, widths, borders, `bg-popover`, etc.). Remove:

- `transition duration-200 ease-in-out`
- `data-open:animate-in` `data-open:fade-in-0` `data-closed:animate-out` `data-closed:fade-out-0`
- all `slide-in-from-*-10` and `slide-out-to-*-10`

Add (one line, same `cn()` call):

```
transition-[transform,opacity] duration-[320ms] ease-drawer
data-[side=bottom]:data-closed:translate-y-full data-[side=bottom]:starting:translate-y-full
data-[side=left]:data-closed:-translate-x-full data-[side=left]:starting:-translate-x-full
data-[side=right]:data-closed:translate-x-full data-[side=right]:starting:translate-x-full
data-[side=top]:data-closed:-translate-y-full data-[side=top]:starting:-translate-y-full
data-closed:opacity-0 starting:opacity-0 data-open:opacity-100
```

Open state is the default placed position (no extra `translate-y-0` needed once closed/starting translates unapply).

3. Replace overlay classes in `SheetOverlay.vue`. Keep `bg-black/10 supports-backdrop-filter:backdrop-blur-xs fixed inset-0 z-50`. Remove `duration-100 data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0`. Add:

```
transition-opacity duration-200 ease-out data-closed:opacity-0 starting:opacity-0 data-open:opacity-100
```

4. Do not change `Sheet.vue`, `SheetHeader.vue`, or page call sites.

## Boundaries

- Do NOT change Dialog / AlertDialog (plan 004).
- Do NOT add drag-to-dismiss or springs.
- Do NOT use `slide-in-from-bottom` keyframes even with `-full`.
- Do NOT animate `height`, `top`, or `padding`.
- Do NOT add a new dependency.

## Verification

- **Mechanical**: `php artisan test --compact tests/Feature/ViewConfigurationTest.php` (locks `AppSheet` / `bottom-drawer`). `pnpm run type-check`.
- **Feel check**:
  - Tap the Add FAB. The sheet must rise from **below the screen**, not fade/hop 40px. Duration ~320ms. It should start moving immediately (drawer curve, not ease-in).
  - Close via X, scrim, and Android back (`AppShell` popstate). Exit uses the same 320ms curve, travelling fully off-screen.
  - Open, then immediately close (interrupt). Motion must reverse from the current position, not jump to 100% and restart. If it jumps, you still have keyframes — remove remaining `animate-in` / `animate-out`.
  - DevTools 10%: transform `translateY` from `100%` of **self height** to `0`. Opacity may fade; it must not be the only motion.
  - Overlay: opacity only, ~200ms, no blur animation (static `backdrop-blur-xs` is fine).
- **Done when**: no `animate-in`/`slide-in-from-*-10` on SheetContent/Overlay; bottom sheet travels 100% of its height; spam open/close is interruptible.
