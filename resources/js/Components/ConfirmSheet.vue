<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from './ui/alert-dialog'

withDefaults(defineProps<{
    open: boolean
    title: string
    message: string
    confirmLabel?: string
}>(), {
    confirmLabel: 'Delete',
})

const emit = defineEmits<{
    cancel: []
    confirm: []
}>()

function onOpenChange(open: boolean) {
    if (!open) {
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
            <AlertDialogFooter>
                <AlertDialogCancel variant="surface" @click="emit('cancel')">Cancel</AlertDialogCancel>
                <AlertDialogAction variant="destructive" @click="emit('confirm')">{{ confirmLabel }}</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
