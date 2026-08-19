<script setup lang="ts">
import Card from './Card.vue'
import { Dialog, DialogContent } from './ui/dialog'
import { Sheet, SheetContent } from './ui/sheet'
import { cn } from '../lib/utils'

const props = withDefaults(defineProps<{
    open: boolean
    labelledBy: string
    variant?: 'modal' | 'drawer'
    class?: string
}>(), {
    variant: 'modal',
    class: '',
})

const emit = defineEmits<{
    close: []
}>()

function onOpenChange(open: boolean) {
    if (!open) {
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
            :class="cn('bottom-drawer max-h-[88vh] gap-0 rounded-t-3xl border-border/70 p-4 sm:left-64 sm:max-w-lg', props.class)"
        >
            <slot />
        </SheetContent>
    </Sheet>

    <Dialog v-else :open="open" @update:open="onOpenChange">
        <DialogContent
            :show-close-button="false"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="labelledBy"
            :class="cn('max-w-md gap-0 overflow-hidden p-0 sm:max-w-lg', props.class)"
        >
            <Card class="border-0 shadow-none ring-0">
                <slot />
            </Card>
        </DialogContent>
    </Dialog>
</template>
