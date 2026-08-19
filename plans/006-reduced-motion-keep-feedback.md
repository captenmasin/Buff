# 006 — Keep opacity and color under `prefers-reduced-motion`

- **Status**: TODO
- **Commit**: 9e6c09b
- **Severity**: MEDIUM
- **Category**: Accessibility
- **Estimated scope**: 1 file (`resources/css/app.css`)

## Problem

Reduced motion must drop **movement**, not all feedback. The current rule sets `animation: none` and `transition: none` on almost the entire shell, including button color, overlay fades, and switch tracks. Loaders (`animate-spin`) also die.

`resources/css/app.css:328-346` — current:

```css
@media (prefers-reduced-motion: reduce) {
    html {
        scroll-behavior: auto;
    }

    .app-shell *,
    .bottom-drawer,
    .sheet-scrim,
    .sheet-scrim *,
    [data-slot='sheet-overlay'],
    [data-slot='sheet-content'],
    [data-slot='dialog-overlay'],
    [data-slot='dialog-content'],
    [data-slot='alert-dialog-overlay'],
    [data-slot='alert-dialog-content'] {
        animation: none !important;
        transition: none !important;
    }
}
```

`tests/Feature/ViewConfigurationTest.php` only asserts the file **contains** the string `prefers-reduced-motion`. Keep that substring.

Run this **after** plans 001, 003, and 004 so the remaining motion is transform/opacity you can target.

## Target

```css
@media (prefers-reduced-motion: reduce) {
    html {
        scroll-behavior: auto;
    }

    .app-shell *:not(.animate-spin),
    .bottom-drawer,
    [data-slot='sheet-overlay'],
    [data-slot='sheet-content'],
    [data-slot='dialog-overlay'],
    [data-slot='dialog-content'],
    [data-slot='alert-dialog-overlay'],
    [data-slot='alert-dialog-content'] {
        animation: none !important;
        transition-property: color, background-color, border-color, box-shadow, opacity !important;
    }

    [data-slot='sheet-content'],
    [data-slot='dialog-content'],
    [data-slot='alert-dialog-content'],
    [data-slot='button'] {
        transform: none !important;
        translate: none !important;
        scale: none !important;
    }
}
```

Notes:

- Do **not** use `transition: none`. Color and opacity may still change (≤200ms is fine; you do not need to restate duration).
- Drop movement: `transform` / `translate` / `scale` none on overlays and buttons.
- Keep `.animate-spin` (LoaderCircle / RefreshCw) spinning — it is status, not decoration.
- `.sheet-scrim` / `.sheet-scrim *` are unused by current Vue (overlays use Reka slots). You may drop those selectors; do not keep dead rules “just in case.”
- Vue toast in AppShell lives inside `.app-shell *`, so `translate-y-3` will be killed by `transform: none` if you add toast to the transform list. Toast is not `data-slot`. Either add `[role='status']` inside the shell to the transform-none list, or accept that the toast will still fade (opacity) without the 12px move — **preferred**. To get that, put `transform: none` on `.app-shell *:not(.animate-spin)` as well, **or** only on the slot list plus `[data-slot='button']`. Prefer **only slots + button** so we do not flatten every icon. The toast will still translate 12px unless you include it. Include this extra selector so the toast loses movement but keeps fade:

```css
.app-shell [role='status'] {
    transform: none !important;
    translate: none !important;
}
```

## Repo conventions to follow

- Motion a11y already lives in `resources/css/app.css` next to `prefers-reduced-transparency` and `prefers-contrast`. Stay there.
- `ViewConfigurationTest` locks `prefers-reduced-motion` as a substring. Do not rename the media query.
- Exemplar of a non-nuclear reduce already in the repo: `Goals.vue` uses `motion-reduce:transition-none` on one bar (plan 007 will remove that bar transition entirely).

## Steps

1. Replace the `prefers-reduced-motion` block in `resources/css/app.css` with the Target CSS (including the toast `[role='status']` rule). Keep `html { scroll-behavior: auto; }`.

2. Do not add JS `useReducedMotion()`. This app has no Motion library.

3. Optional: in `ViewConfigurationTest.php` `exposes focus, caption, dark domain, and motion tokens`, add:

```php
->and($css)->toContain('transition-property: color, background-color, border-color, box-shadow, opacity')
->and($css)->not->toContain('transition: none !important');
```

Only add the `not->toContain` if no other `transition: none !important` remains in the file.

## Boundaries

- Do NOT disable `animate-spin`.
- Do NOT zero all animation durations globally (`animation-duration: 0.01ms` on `*`).
- Do NOT edit Vue components in this plan.
- Do NOT remove the media query.

## Verification

- **Mechanical**: `php artisan test --compact tests/Feature/ViewConfigurationTest.php`.
- **Feel check** (Chrome Rendering → “Emulate CSS prefers-reduced-motion: reduce”):
  - Add drawer: no slide; scrim may still fade. Content appears in place.
  - Dialog / delete confirm: no zoom; fade OK.
  - Buttons: no scale; background/opacity change on press/disabled still allowed.
  - Photo-analysis / search `LoaderCircle` still spins.
  - Fallback toast (if shown): fades without dropping 12px.
- **Done when**: no `transition: none !important` in the reduce block; spinners still animate; overlays do not translate/scale.
