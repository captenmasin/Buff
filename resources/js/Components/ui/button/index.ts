import type { VariantProps } from 'class-variance-authority'
import { cva } from 'class-variance-authority'

export { default as Button } from './Button.vue'

export const buttonVariants = cva(
  'focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:aria-invalid:border-destructive/50 rounded-lg border border-transparent bg-clip-padding text-sm font-medium aria-invalid:ring-3 group/button inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap outline-none select-none disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
  {
    variants: {
      variant: {
        default: 'bg-primary text-primary-foreground [a]:hover:bg-primary/80',
        outline: 'border-border bg-card hover:bg-muted hover:text-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 aria-expanded:bg-muted aria-expanded:text-foreground',
        secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80 aria-expanded:bg-secondary aria-expanded:text-secondary-foreground',
        ghost: 'hover:bg-muted hover:text-foreground dark:hover:bg-muted/50 aria-expanded:bg-muted aria-expanded:text-foreground',
        destructive: 'bg-destructive/10 hover:bg-destructive/20 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/20 text-destructive focus-visible:border-destructive/40 dark:hover:bg-destructive/30',
        'destructive-solid': 'bg-destructive text-destructive-foreground hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40',
        link: 'text-link underline-offset-4 hover:underline',
        /** Camera and scanner chrome sitting on a dark photo surface. */
        inverse: 'bg-card text-foreground hover:bg-muted',
        surface: 'border-border/80 bg-muted/80 text-foreground hover:bg-muted',
      },
      size: {
        'default': 'h-12 px-4 py-3 has-data-[icon=inline-end]:pr-3 has-data-[icon=inline-start]:pl-3',
        'xs': 'h-6 gap-1 rounded-[min(var(--radius-md),10px)] px-2 text-xs in-data-[slot=button-group]:rounded-lg has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5',
        'sm': 'h-9 gap-1.5 rounded-lg px-3 text-sm in-data-[slot=button-group]:rounded-lg',
        'lg': 'h-14 gap-1.5 px-4 text-base',
        'icon': 'size-11',
        'icon-xs': 'size-6 rounded-[min(var(--radius-md),10px)] in-data-[slot=button-group]:rounded-lg',
        'icon-sm': 'size-8 rounded-lg in-data-[slot=button-group]:rounded-lg',
        'icon-lg': 'size-11',
        'nav': 'min-h-12 flex-col gap-0.5 px-1 py-1.5 text-xs font-semibold tracking-wide',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
