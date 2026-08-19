# 001 — Give buttons a 160ms scale press and stop `transition-all`

- **Status**: TODO
- **Commit**: 9e6c09b
- **Severity**: HIGH
- **Category**: Performance + Physicality
- **Estimated scope**: 2 files (`resources/css/app.css`, `resources/js/Components/ui/button/index.ts`) plus a small assertion in `tests/Feature/ViewConfigurationTest.php`

## Problem

Every pressable control in Buff inherits `transition-all` and a 1px downward nudge. `transition-all` animates unintended properties off-GPU. A 1px `translateY` is not press feedback; the target is a subtle scale.

`resources/js/Components/ui/button/index.ts:7` — current:

```ts
'focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:aria-invalid:border-destructive/50 rounded-lg border border-transparent bg-clip-padding text-sm font-medium aria-invalid:ring-3 active:not-aria-[haspopup]:translate-y-px group/button inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap transition-all outline-none select-none disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
```

`resources/js/Layouts/AppShell.vue:344` already adds `active:scale-95` on the Add FAB. Leave that override. Do not change the FAB in this plan.

There are no motion tokens yet. Color/duration tokens live in `resources/css/app.css` `:root` / `@theme inline`. This plan introduces easing tokens there so later plans can use `ease-out` and `ease-drawer` instead of inventing parallel curves.

## Target

Pressable buttons (except elements with `aria-haspopup`, which the current `:active` selector already skips):

- Rest: `transform: none` (scale 1)
- `:active`: `transform: scale(0.97)`
- Transition: `transform 160ms` with `--ease-out: cubic-bezier(0.23, 1, 0.32, 1)`
- Asymmetric timing: **press** uses 160ms; **release** uses 100ms (system snaps back). Implement with a longer `transition-duration` on `:active` than on the rest state.
- Color / background / border / shadow / opacity: `150ms ease` (hover/color change uses `ease`, not the strong enter curve)
- **Never** `transition-all`
- **Never** `translate-y-px` as press feedback

Exact token values to add (copy, do not approximate):

```css
--ease-out: cubic-bezier(0.23, 1, 0.32, 1);
--ease-in-out: cubic-bezier(0.77, 0, 0.175, 1);
--ease-drawer: cubic-bezier(0.32, 0.72, 0, 1);
```

## Repo conventions to follow

- Design tokens live in `resources/css/app.css` (`:root` for raw values, `@theme inline` so Tailwind utilities can see them). Exemplar: `--radius: 0.5rem` in `:root` and `--radius-lg` in `@theme inline`.
- Button chrome is CVA in `resources/js/Components/ui/button/index.ts`. Do not move variants into CSS.
- Keep `focus-visible:ring-2` and `text-xs` on the nav size. `tests/Feature/ViewConfigurationTest.php` locks both.
- `tailwind-merge` is used via `cn()`. Prefer utilities that merge cleanly (`scale-[0.97]` vs `translate-y-px`).

## Steps

1. In `resources/css/app.css`, inside `:root` (after `--radius` is fine), add the three easing custom properties listed in Target.

2. In the same file, inside `@theme inline`, add:

```css
--ease-out: cubic-bezier(0.23, 1, 0.32, 1);
--ease-in-out: cubic-bezier(0.77, 0, 0.175, 1);
--ease-drawer: cubic-bezier(0.32, 0.72, 0, 1);
```

This overrides Tailwind’s default `--ease-out: cubic-bezier(0, 0, 0.2, 1)` so the `ease-out` utility becomes the strong UI curve. That is intentional. Also creates an `ease-drawer` utility for plan 003.

3. In `resources/js/Components/ui/button/index.ts`, in the base CVA string, **remove** `transition-all` and **remove** `active:not-aria-[haspopup]:translate-y-px`. Do not replace them with other `transition-*` or `active:scale-*` utilities (tailwind-merge would fight a two-duration transition). Leave the rest of the string intact.

4. In `resources/css/app.css`, after the existing `button, input, select, textarea { font: inherit; }` rule, add:

```css
[data-slot='button'] {
    transition:
        color 150ms ease,
        background-color 150ms ease,
        border-color 150ms ease,
        box-shadow 150ms ease,
        opacity 150ms ease,
        transform 100ms var(--ease-out);
}

[data-slot='button']:active:not([aria-haspopup]) {
    transform: scale(0.97);
    transition-duration: 160ms;
    transition-property: color, background-color, border-color, box-shadow, opacity, transform;
}
```

`Button.vue` already sets `data-slot="button"`. Color uses `ease`; transform uses `var(--ease-out)` which is `cubic-bezier(0.23, 1, 0.32, 1)`. Press is 160ms; release is 100ms.

5. In `tests/Feature/ViewConfigurationTest.php`, inside `it('exposes focus, caption, dark domain, and motion tokens'...)`, extend the existing `$css` / `$button` expectations:

```php
->and($css)->toContain('--ease-out: cubic-bezier(0.23, 1, 0.32, 1)')
->and($css)->toContain('--ease-drawer: cubic-bezier(0.32, 0.72, 0, 1)')
->and($button)->not->toContain('transition-all')
->and($button)->not->toContain('translate-y-px');
```

Keep the existing `focus-visible:ring-2` and `text-xs` assertions.

## Boundaries

- Do NOT change `resources/js/Layouts/AppShell.vue` FAB `active:scale-95`.
- Do NOT change meal-row `active:translate-y-0` in `Today.vue` (that override exists to cancel the old 1px nudge on list rows). After this plan, that class is a no-op; leave it for a later cleanup, do not touch Today.vue here.
- Do NOT change Switch, Progress, or Badge (plan 009).
- Do NOT add Framer Motion, GSAP, or new dependencies.
- Do NOT change markup in `Button.vue`.

## Verification

- **Mechanical**: `vendor/bin/pint --dirty --format agent` if PHP tests changed; `php artisan test --compact tests/Feature/ViewConfigurationTest.php`. Expect pass. `pnpm run type-check` should still pass (no TS API changes).
- **Feel check**:
  - Tap any primary button on Today. Finger-down should reach scale 0.97 in ~160ms; finger-up should return faster (~100ms). It must not dip, bounce, or slide down 1px.
  - In DevTools Animations panel at 10% playback, confirm only `transform` plus color/background change; width/padding/margin must not interpolate.
  - Spam-tap: the scale must retarget from the current value (CSS transition), never restart from 1.
  - Toggle `prefers-reduced-motion` after plan 006; until then, existing global reduce still kills the press. Do not implement reduced-motion in this plan.
  - Confirm the Add FAB still scales to 0.95, not 0.97.
- **Done when**: no `transition-all` or `translate-y-px` in `button/index.ts`; tokens exist; press is `scale(0.97)`; ViewConfigurationTest passes.
