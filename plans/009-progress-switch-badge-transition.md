# 009 — Replace remaining `transition-all` on Progress, Switch, and Badge

- **Status**: DONE
- **Commit**: 9e6c09b
- **Severity**: LOW
- **Category**: Performance
- **Estimated scope**: 3 files (`Progress.vue`, `Switch.vue`, `badge/index.ts`)

## Problem

`transition-all` is always a finding. Plan 001 removes it from Button. These three still have it.

`resources/js/Components/ui/progress/Progress.vue:34` — current:

```vue
      :class="cn('size-full flex-1 bg-primary transition-all', props.indicatorClass)"
      :style="`transform: translateX(-${100 - (props.modelValue ?? 0)}%);`"
```

The indicator already moves with **transform**. `transition-all` is unnecessary; it can tween layout leftovers.

`resources/js/Components/ui/switch/Switch.vue:33` — current root:

```
... peer group/switch relative inline-flex items-center transition-all outline-none after:absolute ...
```

The thumb (line 41) already has `transition-transform` (good). The root only needs color.

`resources/js/Components/ui/badge/index.ts:7` — current:

```
'h-5 gap-1 rounded-4xl border border-transparent px-2 py-0.5 text-xs font-medium transition-all ...
```

Badges are not a high-frequency control here; still drop `all`.

## Target

- Progress indicator: `transition-transform duration-200 ease-out`  
  Easing is `cubic-bezier(0.23, 1, 0.32, 1)` via `ease-out` after plan 001 tokens. Duration 200ms (progress is on-screen morph → under 300ms).
- Switch root: `transition-colors duration-150 ease` (color/hover change uses `ease`, 150ms).
- Switch thumb: keep `transition-transform`; add `duration-200 ease-out` so it is explicit. Do not use keyframes.
- Badge: `transition-colors` only (or delete the transition entirely if badges never change color in place). Prefer `transition-colors duration-150 ease`.

Do not animate `width`/`flex` on Progress. Keep the existing `transform: translateX(...)`.

## Repo conventions to follow

- UI primitives use CVA / `cn()` like Button. After plan 001, `ease-out` is the strong curve.
- Switch thumb already uses `data-checked:translate-x-*` — that is the motion to keep.
- If plan 001 tokens are missing, add `--ease-out: cubic-bezier(0.23, 1, 0.32, 1)` to `@theme inline` first.

## Steps

1. `Progress.vue`: replace `transition-all` with `transition-transform duration-200 ease-out` on `ProgressIndicator`.

2. `Switch.vue`: on `SwitchRoot`, replace `transition-all` with `transition-colors duration-150 ease`. On `SwitchThumb`, change `transition-transform` to `transition-transform duration-200 ease-out`.

3. `badge/index.ts`: replace `transition-all` with `transition-colors duration-150 ease`.

4. grep the repo for remaining `transition-all` in `resources/`. If any are left in app code (not node_modules), stop and report them; do not expand scope unless they are one-line copies of this fix in the same PR.

## Boundaries

- Do NOT change Button (plan 001) or Goals width (plan 007).
- Do NOT add springs.
- Do NOT change Switch sizes / translate distances (`translate-x-6` etc.).

## Verification

- **Mechanical**: `rg 'transition-all' resources` — zero hits. `pnpm run type-check`. `php artisan test --compact tests/Feature/ViewConfigurationTest.php`.
- **Feel check**:
  - Today macros: Progress bars should ease their fill via `translateX` (~200ms) when navigating days, not resize the track.
  - Settings meal-reminder switches: thumb slides with a 200ms ease-out; track color changes in ~150ms. Spam-toggle must reverse mid-slide (transition, not keyframes).
  - DevTools: Progress/Switch thumb interpolating `transform` only.
- **Done when**: no `transition-all` under `resources/`; Progress uses `transition-transform`; Switch root uses `transition-colors`.
