<script setup lang="ts">
import { onBeforeUnmount, onMounted, useId } from 'vue'
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from './ui/alert-dialog'
import Button from './ui/button/Button.vue'

const props = withDefaults(defineProps<{
    open: boolean
    title: string
    message: string
    cancelLabel?: string
    confirmLabel?: string
    processing?: boolean
    processingLabel?: string
    error?: string
}>(), {
    confirmLabel: 'Delete',
    cancelLabel: 'Cancel',
    processingLabel: 'Deleting…',
    error: '',
})

const emit = defineEmits<{
    cancel: []
    confirm: []
}>()

const sheetId = useId()

function onOpenChange(open: boolean) {
    if (!open && !props.processing) {
        emit('cancel')
    }
}

function handleNativeAndroidBack(event: Event) {
    if (!props.open || event.defaultPrevented) {
        return
    }

    const openSheets = document.querySelectorAll<HTMLElement>('[data-app-sheet][data-state="open"]')

    if (openSheets.item(openSheets.length - 1)?.dataset.appSheet !== sheetId) {
        return
    }

    event.preventDefault()
    onOpenChange(false)
}

onMounted(() => window.addEventListener('buff:android-back', handleNativeAndroidBack))
onBeforeUnmount(() => window.removeEventListener('buff:android-back', handleNativeAndroidBack))
</script>

<template>
    <AlertDialog :open="open" @update:open="onOpenChange">
        <AlertDialogContent size="sm" :data-app-sheet="sheetId">
            <AlertDialogHeader class="place-items-start text-left">
                <AlertDialogTitle id="confirm-sheet-title">{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>{{ message }}</AlertDialogDescription>
            </AlertDialogHeader>
            <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
            <AlertDialogFooter>
                <AlertDialogCancel variant="surface" :disabled="processing" @click="emit('cancel')">{{ cancelLabel }}</AlertDialogCancel>
                <Button
                    type="button"
                    variant="destructive-solid"
                    :loading="processing"
                    :loading-label="processingLabel"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
