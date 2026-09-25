<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
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
let activePointerId: number | null = null

const drawerStyle = computed(() => {
    if (props.variant !== 'drawer') {
        return undefined
    }

    const style: Record<string, string> = {}

    if (dragY.value > 0) {
        // SheetContent animates the CSS `translate` property — keep drag on the same axis.
        style.translate = `0 ${dragY.value}px`
    }

    if (dragging.value) {
        style.transitionDuration = '0ms'
        style.transitionProperty = 'none'
    }

    return Object.keys(style).length > 0 ? style : undefined
})

function prefersReducedMotion(): boolean {
    return document.documentElement.hasAttribute('data-reduce-motion')
        || window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

function isDesktopDrawer(): boolean {
    return window.matchMedia('(width >= 40rem)').matches
}

function canDrag(): boolean {
    return props.variant === 'drawer'
        && props.dismissible
        && props.open
        && !prefersReducedMotion()
        && !isDesktopDrawer()
}

function onOpenChange(open: boolean) {
    if (!open) {
        if (!props.dismissible) {
            return
        }

        resetDrag()
        emit('close')
    }
}

function preventDismiss(event: Event) {
    if (!props.dismissible) {
        event.preventDefault()
    }
}

function handleNativeAndroidBack(event: Event) {
    if (!props.open || event.defaultPrevented) {
        return
    }

    const openSheets = document.querySelectorAll<HTMLElement>('[data-app-sheet][data-state="open"]')

    if (openSheets.item(openSheets.length - 1)?.dataset.appSheet !== props.labelledBy) {
        return
    }

    event.preventDefault()

    if (props.dismissible) {
        emit('close')
    }
}

function beginDrag(clientY: number, timeStamp: number) {
    dragging.value = true
    pointerStartY = clientY
    lastY = clientY
    lastT = timeStamp
    velocity = 0
}

function moveDrag(clientY: number, timeStamp: number) {
    if (!dragging.value) {
        return
    }

    dragY.value = Math.max(0, clientY - pointerStartY)
    const elapsed = timeStamp - lastT

    if (elapsed > 0) {
        velocity = (clientY - lastY) / elapsed
    }

    lastY = clientY
    lastT = timeStamp
}

function endDrag() {
    if (!dragging.value) {
        return
    }

    const shouldClose = dragY.value > 96 || velocity > 0.45
    resetDrag()

    if (shouldClose) {
        emit('close')
    }
}

function resetDrag() {
    dragging.value = false
    dragY.value = 0
    activePointerId = null
    velocity = 0
}

function onHandlePointerDown(event: PointerEvent) {
    if (!canDrag() || event.button > 0) {
        return
    }

    activePointerId = event.pointerId
    beginDrag(event.clientY, event.timeStamp)

    if (event.currentTarget instanceof HTMLElement) {
        event.currentTarget.setPointerCapture(event.pointerId)
    }
}

function onHandlePointerMove(event: PointerEvent) {
    if (!dragging.value || (activePointerId !== null && event.pointerId !== activePointerId)) {
        return
    }

    event.preventDefault()
    moveDrag(event.clientY, event.timeStamp)
}

function onHandlePointerUp(event: PointerEvent) {
    if (activePointerId !== null && event.pointerId !== activePointerId) {
        return
    }

    endDrag()
}

function onHandleTouchStart(event: TouchEvent) {
    if (!canDrag() || event.touches.length !== 1 || activePointerId !== null) {
        return
    }

    const touch = event.touches.item(0)

    if (!touch) {
        return
    }

    beginDrag(touch.clientY, event.timeStamp)
}

function onHandleTouchMove(event: TouchEvent) {
    if (!dragging.value || activePointerId !== null || event.touches.length !== 1) {
        return
    }

    const touch = event.touches.item(0)

    if (!touch) {
        return
    }

    event.preventDefault()
    moveDrag(touch.clientY, event.timeStamp)
}

function onHandleTouchEnd() {
    if (activePointerId !== null) {
        return
    }

    endDrag()
}

onMounted(() => window.addEventListener('buff:android-back', handleNativeAndroidBack))
onBeforeUnmount(() => {
    window.removeEventListener('buff:android-back', handleNativeAndroidBack)
    resetDrag()
})
</script>

<template>
    <Sheet v-if="variant === 'drawer'" :open="open" @update:open="onOpenChange">
        <SheetContent
            side="bottom"
            :show-close-button="false"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="labelledBy"
            :data-app-sheet="labelledBy"
            overlay-class="sm:left-64"
            :class="cn('bottom-drawer max-h-[88dvh] gap-0 overflow-y-auto overscroll-contain rounded-t-3xl border-border/70 p-4 sm:left-64 sm:max-w-lg', dragging && 'select-none', props.class)"
            :style="drawerStyle"
            @pointer-down-outside="preventDismiss"
            @interact-outside="preventDismiss"
            @escape-key-down="preventDismiss"
        >
            <DialogTitle class="sr-only">{{ title }}</DialogTitle>
            <DialogDescription class="sr-only">{{ description }}</DialogDescription>
            <div
                class="flex cursor-grab touch-none justify-center pb-2 pt-1 active:cursor-grabbing sm:hidden"
                data-drawer-handle
                @pointerdown="onHandlePointerDown"
                @pointermove="onHandlePointerMove"
                @pointerup="onHandlePointerUp"
                @pointercancel="onHandlePointerUp"
                @touchstart="onHandleTouchStart"
                @touchmove="onHandleTouchMove"
                @touchend="onHandleTouchEnd"
                @touchcancel="onHandleTouchEnd"
            >
                <span class="h-1.5 w-12 rounded-full bg-muted-foreground/40" aria-hidden="true" />
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
            :data-app-sheet="labelledBy"
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
