# 002 — Remove the week-strip transition on Today

- **Status**: TODO
- **Commit**: 9e6c09b
- **Severity**: HIGH
- **Category**: Purpose & frequency
- **Estimated scope**: 1 file (`resources/js/Pages/Today.vue`)

## Problem

The seven-day strip on Today is a high-frequency control (date switching many times per session). It currently uses Tailwind `transition`, which interpolates background/color (and other default transition properties) on every selected-state change. High-frequency UI should not animate.

`resources/js/Pages/Today.vue:583` — current:

```vue
class="relative flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-sm font-semibold transition"
```

The selected class on the next line (`day.is_selected ? 'bg-secondary text-foreground' : 'text-muted-foreground active:bg-muted'`) is the state change that gets interpolated.

## Target

Instant selected-state swap. No `transition`, `transition-*`, `duration-*`, or `animate-*` on the week-strip `Link`.

Keep `:active` feedback as a fill only (`active:bg-muted` already on the unselected branch). Do not add scale or opacity animation.

Resulting class string:

```
relative flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-sm font-semibold
```

## Repo conventions to follow

- Today chrome is locked by `tests/Feature/ViewConfigurationTest.php` (`rounded-2xl bg-card p-1.5 shadow-card` on the parent `<nav>`). Do not change the parent nav classes.
- Day status dots stay as they are (`dayStatusClass`). Do not animate those either.
- Exemplar of an instantaneous state change already in this file: meal macro tiles use `active:bg-muted` with no transition (`Today.vue` around the macros `Link`).

## Steps

1. In `resources/js/Pages/Today.vue`, on the week-strip `Link` (`v-for="day in week"`), delete the `transition` token from the class string. Leave every other class and the `:class` binding unchanged.

2. Optional test lock in `tests/Feature/ViewConfigurationTest.php` (same file already reads Today.vue). Add to an existing Today assertion or a tiny new example:

```php
it('does not animate the today week strip', function (): void {
    $today = file_get_contents(resource_path('js/Pages/Today.vue'));

    expect($today)
        ->toContain('aria-label="Week"')
        ->toContain('relative flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-sm font-semibold')
        ->not->toContain('flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl text-sm font-semibold transition');
});
```

If you add this test, run it. If you skip the test, still run ViewConfigurationTest to prove you did not disturb locked strings.

## Boundaries

- Do NOT change weekly page date UI in `resources/js/Pages/Weekly.vue`.
- Do NOT add a fade, scale, or underline animation as a “replacement.”
- Do NOT change `active:bg-muted`.
- Do NOT touch Button CVA.

## Verification

- **Mechanical**: `php artisan test --compact tests/Feature/ViewConfigurationTest.php`.
- **Feel check**:
  - On Today, tap several week cells quickly. The selected background must appear on the new cell with no fade or slide. The old cell must go muted instantly.
  - At 10% animation playback there should be **no** animation recorded for those links.
  - Keyboard / VoiceOver: if the strip is focusable via the `Link`, focus rings (browser default / existing) must still appear; we only removed the color transition.
- **Done when**: the week `Link` class has no `transition`; tapping dates feels like a toggle, not a crossfade.
