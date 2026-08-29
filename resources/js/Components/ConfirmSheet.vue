<script setup lang="ts">
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
    confirmLabel?: string
    processing?: boolean
    processingLabel?: string
    error?: string
}>(), {
    confirmLabel: 'Delete',
    processingLabel: 'Deleting…',
    error: '',
})

const emit = defineEmits<{
    cancel: []
    confirm: []
}>()

function onOpenChange(open: boolean) {
    if (!open && !props.processing) {
        emit('cancel')
    }
}
</script>

<template>
    <AlertDialog :open="open" @update:open="onOpenChange">
        <AlertDialogContent size="sm">
            <AlertDialogHeader class="place-items-start text-left">
                <AlertDialogTitle id="confirm-sheet-title">{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>{{ message }}</AlertDialogDescription>
            </AlertDialogHeader>
            <p v-if="error" class="text-sm text-destructive" role="alert">{{ error }}</p>
            <AlertDialogFooter>
                <AlertDialogCancel variant="surface" :disabled="processing" @click="emit('cancel')">Cancel</AlertDialogCancel>
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
