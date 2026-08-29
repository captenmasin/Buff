<script setup lang="ts">
import { computed, ref } from 'vue'
import Card from './Card.vue'
import { Dialog, DialogContent, DialogDescription, DialogTitle } from './ui/dialog'
import { Sheet, SheetContent } from './ui/sheet'
import { cn } from '../lib/utils'

const props = withDefaults(defineProps<{
    open: boolean
    labelledBy: string
    title: string
    description: string
    variant?: 'modal' | 'drawer'
    class?: string
    /** When false, outside click and Escape will not dismiss the sheet. */
    dismissible?: boolean
}>(), {
    variant: 'modal',
    class: '',
    dismissible: true,
})

const emit = defineEmits<{
    close: []
}>()

const dragY = ref(0)
const dragging = ref(false)
let pointerStartY = 0
let lastY = 0
let lastT = 0
let velocity = 0

const drawerStyle = computed(() => {
    if (props.variant !== 'drawer' || dragY.value === 0) {
        return undefined
    }

    return { transform: `translateY(${dragY.value}px)` }
})

function prefersReducedMotion(): boolean {
    return document.documentElement.hasAttribute('data-reduce-motion')
        || window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

function isDesktopDrawer(): boolean {
    return window.matchMedia('(width >= 40rem)').matches
}

function onOpenChange(open: boolean) {
    if (!open) {
        if (!props.dismissible) {
            return
        }

        emit('close')
    }
}

function preventDismiss(event: Event) {
    if (!props.dismissible) {
        event.preventDefault()
    }
}

function onHandlePointerDown(event: PointerEvent) {
    if (props.variant !== 'drawer' || !props.dismissible || prefersReducedMotion() || isDesktopDrawer()) {
        return
    }

    dragging.value = true
    pointerStartY = event.clientY
    lastY = event.clientY
    lastT = event.timeStamp
    velocity = 0
    if (event.currentTarget instanceof HTMLElement) {
        event.currentTarget.setPointerCapture(event.pointerId)
    }
}

function onHandlePointerMove(event: PointerEvent) {
    if (!dragging.value) {
        return
    }

    dragY.value = Math.max(0, event.clientY - pointerStartY)
    const elapsed = event.timeStamp - lastT

    if (elapsed > 0) {
        velocity = (event.clientY - lastY) / elapsed
    }

    lastY = event.clientY
    lastT = event.timeStamp
}

function onHandlePointerUp() {
    if (!dragging.value) {
        return
    }

    dragging.value = false
    const shouldClose = dragY.value > 96 || velocity > 0.45
    dragY.value = 0

    if (shouldClose) {
        emit('close')
    }
}
</script>

<template>
    <Sheet v-if="variant === 'drawer'" :open="open" @update:open="onOpenChange">
        <SheetContent
            side="bottom"
            :show-close-button="false"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="labelledBy"
            overlay-class="sm:left-64"
            :class="cn('bottom-drawer max-h-[88dvh] gap-0 overflow-y-auto overscroll-contain rounded-t-3xl border-border/70 p-4 sm:left-64 sm:max-w-lg', props.class)"
            :style="drawerStyle"
            @pointer-down-outside="preventDismiss"
            @interact-outside="preventDismiss"
            @escape-key-down="preventDismiss"
        >
            <DialogTitle class="sr-only">{{ title }}</DialogTitle>
            <DialogDescription class="sr-only">{{ description }}</DialogDescription>
            <div
                class="flex cursor-grab touch-none justify-center py-1 active:cursor-grabbing sm:hidden"
                @pointerdown="onHandlePointerDown"
                @pointermove="onHandlePointerMove"
                @pointerup="onHandlePointerUp"
                @pointercancel="onHandlePointerUp"
            >
                <span class="h-1.5 w-10 rounded-full bg-muted-foreground/40" aria-hidden="true" />
                <span class="sr-only">Drag down to close</span>
            </div>
            <slot />
        </SheetContent>
    </Sheet>

    <Dialog v-else :open="open" @update:open="onOpenChange">
        <DialogContent
            :show-close-button="false"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="labelledBy"
            :class="cn('max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md gap-0 overflow-x-hidden overflow-y-auto overscroll-contain p-0 sm:max-w-lg', props.class)"
            @pointer-down-outside="preventDismiss"
            @interact-outside="preventDismiss"
            @escape-key-down="preventDismiss"
        >
            <DialogTitle class="sr-only">{{ title }}</DialogTitle>
            <DialogDescription class="sr-only">{{ description }}</DialogDescription>
            <Card class="border-0 shadow-none ring-0">
                <slot />
            </Card>
        </DialogContent>
    </Dialog>
</template>
