# 008 — Require a fine pointer before `hover:` styles apply

- **Status**: DONE
- **Commit**: 9e6c09b
- **Severity**: MEDIUM
- **Category**: Accessibility
- **Estimated scope**: 1 file (`resources/css/app.css`)

## Problem

Buff is a NativePHP mobile app. Sticky `:hover` after tap is a WebView classic (ghost highlight on buttons, calendar cells, ghost/outline variants).

Tailwind **v4.3 already wraps** the `hover` variant in `@media (hover: hover)` (see `node_modules/tailwindcss/dist/lib.mjs`). That is not enough: some iOS/iPad WebViews still match `(hover: hover)` without a fine pointer. The audit target is:

```css
@media (hover: hover) and (pointer: fine) {
  .element:hover { ... }
}
```

There is no `transform: scale` on hover today (good). The remaining issue is **color** hover (`hover:bg-muted`, `[a]:hover:bg-primary/80`, calendar `hover:opacity-100`) sticking after a tap.

## Target

Redefine the `hover` variant globally in `resources/css/app.css` so every existing `hover:` and `dark:hover:` utility requires both hover capability **and** a fine pointer.

Place this **after** `@import 'tailwindcss';` (and the other imports) so it overrides the default variant. Tailwind v4 custom-variant form:

```css
@custom-variant hover {
    @media (hover: hover) and (pointer: fine) {
        &:hover {
            @slot;
        }
    }
}
```

Do not change Button CVA class names. They keep `hover:bg-muted`; the compiled CSS gains the extra media query.

`:active` styles (plan 001 scale, `active:bg-muted` on the week strip) stay available on touch. Do not gate `:active`.

## Repo conventions to follow

- Global CSS overrides already live at the top of `resources/css/app.css` (`@custom-variant dark (&:is(.dark *));` is the exemplar). Put `@custom-variant hover` next to that dark variant, not buried at the bottom.
- Do not introduce `pointer-fine:hover:` on every component.

## Steps

1. In `resources/css/app.css`, immediately after `@custom-variant dark (&:is(.dark *));`, add the `@custom-variant hover` block from Target.

2. If the build fails because `@slot` is invalid in this Tailwind version, use the single-argument form instead and stop if that also fails (report; do not invent `@variant`):

```css
@custom-variant hover (@media (hover: hover) and (pointer: fine) { &:hover });
```

3. Do not edit `button/index.ts`, calendar files, or Badge.

4. Optional test: in `ViewConfigurationTest.php` CSS assertions:

```php
->and($css)->toContain('(pointer: fine)')
```

## Boundaries

- Do NOT remove hover color styles; only gate them.
- Do NOT use `hover:hover` / `any-hover` variants on individual classes.
- Do NOT disable `:focus-visible` rings.
- Do NOT add a JS touch heuristic.

## Verification

- **Mechanical**: `pnpm run build` or the project’s usual Vite type-check (`pnpm run type-check`). If `@custom-variant hover` fails the CSS compiler, try the alternate syntax in step 2. `php artisan test --compact tests/Feature/ViewConfigurationTest.php`.
- **Feel check**:
  - **Phone / emulator (coarse pointer)**: tap a ghost button and a calendar day. After finger-up, the hover fill must **not** stick. `:active` / selected styles still appear while down or when selected.
  - **Desktop / trackpad (fine pointer + hover)**: Button `hover:bg-muted` still appears on mouse hover.
  - DevTools: emulate “any-pointer: coarse” if you lack a device; compiled CSS for `.hover\:bg-muted:hover` must sit inside `@media (hover: hover) and (pointer: fine)`.
- **Done when**: hover utilities compile inside both media queries; tap on a coarse pointer leaves no sticky hover fill.
