<script setup lang="ts">
import Button from './ui/button/Button.vue';

defineProps<{
    appleLoginAvailable: boolean;
}>();

const emit = defineEmits<{
    signIn: [provider: 'google' | 'apple'];
}>();
</script>

<template>
    <div class="space-y-2 border-t pt-4">
        <Button
            type="button"
            variant="surface"
            class="w-full border-[#747775] bg-white text-[#1f1f1f] hover:bg-[#f8fafd] hover:text-[#1f1f1f]"
            @click="emit('signIn', 'google')"
        >
            <svg aria-hidden="true" class="size-5" viewBox="0 0 18 18">
                <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62Z" />
                <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.83.86-3.04.86a5.25 5.25 0 0 1-4.93-3.71H1.05v2.33A9 9 0 0 0 9 18Z" />
                <path fill="#FBBC05" d="M4.07 10.71A5.4 5.4 0 0 1 3.78 9c0-.59.1-1.17.29-1.71V4.96H1.05A9 9 0 0 0 0 9c0 1.45.35 2.83 1.05 4.04l3.02-2.33Z" />
                <path fill="#EA4335" d="M9 3.58c1.32 0 2.51.45 3.44 1.35l2.58-2.59A8.66 8.66 0 0 0 9 0a9 9 0 0 0-7.95 4.96l3.02 2.33A5.25 5.25 0 0 1 9 3.58Z" />
            </svg>
            Continue with Google
        </Button>
        <Button
            v-if="appleLoginAvailable"
            type="button"
            variant="surface"
            class="w-full border-black bg-black text-white hover:bg-[#1c1c1e] hover:text-white dark:border-white/30"
            @click="emit('signIn', 'apple')"
        >
            <svg aria-hidden="true" class="size-5 fill-current" viewBox="0 0 24 24">
                <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.32-.07 2.25.72 3.05.78 1.26-.26 2.46-1.04 3.81-.94 1.62.13 2.84.77 3.65 1.93-3.34 2-2.55 6.39.52 7.62-.61 1.58-1.41 3.15-3.03 4.58ZM12.15 5.68c-.15-2.35 1.75-4.29 3.94-4.48.3 2.72-2.47 4.75-3.94 4.48Z" />
            </svg>
            Continue with Apple
        </Button>
    </div>
</template>
