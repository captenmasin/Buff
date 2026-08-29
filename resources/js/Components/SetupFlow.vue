<script setup lang="ts">
import { computed } from 'vue';
import { ArrowLeft } from '@lucide/vue';
import { publicAssetUrl } from '../publicAssetUrl';
import Button from './ui/button/Button.vue';

const props = withDefaults(defineProps<{
    phase: string;
    progress: number;
    nextLabel?: string;
    nextDisabled?: boolean;
    backDisabled?: boolean;
    processing?: boolean;
    showNext?: boolean;
}>(), {
    nextLabel: 'Next',
    nextDisabled: false,
    backDisabled: false,
    processing: false,
    showNext: true,
});

const emit = defineEmits<{
    back: [];
    next: [];
}>();

const progressWidth = computed(() => `${Math.min(100, Math.max(0, props.progress))}%`);
</script>

<template>
    <main class="flex h-dvh flex-col overflow-hidden bg-background text-foreground">
        <header class="mx-auto w-full max-w-md pb-4 pt-[calc(env(safe-area-inset-top,0px)+1.25rem)] sm:max-w-lg">
            <div class="flex items-center justify-between gap-4">
                <img :src="publicAssetUrl('/logo.svg')" alt="Buff" class="h-auto w-24 dark:hidden" />
                <img :src="publicAssetUrl('/logo-dark.svg')" alt="Buff" class="hidden h-auto w-24 dark:block" />
                <p class="text-sm font-semibold">{{ phase }}</p>
            </div>
            <div
                class="mt-5 h-1.5 overflow-hidden rounded-full bg-secondary"
                role="progressbar"
                aria-label="Setup progress"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-valuenow="Math.round(progress)"
            >
                <div class="h-full rounded-full bg-brand-violet transition-[width] duration-300 ease-out motion-reduce:transition-none" :style="{ width: progressWidth }" />
            </div>
        </header>

        <section class="min-h-0 flex-1 overflow-y-auto px-5 py-8">
            <div class="mx-auto w-full max-w-md sm:max-w-lg">
                <slot />
            </div>
        </section>

        <footer class="border-t border-border/70 bg-background px-5 pb-[calc(env(safe-area-inset-bottom,0px)+1rem)] pt-4">
            <div
                class="mx-auto w-full max-w-md gap-3 sm:max-w-lg"
                :class="showNext ? 'grid grid-cols-[3.5rem_1fr]' : 'flex'"
            >
                <Button
                    type="button"
                    variant="surface"
                    size="icon"
                    class="size-14 rounded-full"
                    aria-label="Go back"
                    :disabled="backDisabled"
                    @click="emit('back')"
                >
                    <ArrowLeft :size="24" />
                </Button>
                <Button
                    v-if="showNext"
                    type="button"
                    size="lg"
                    class="h-14 rounded-full text-lg font-semibold"
                    :disabled="nextDisabled"
                    :loading="processing"
                    loading-label="Please wait…"
                    @click="emit('next')"
                >
                    {{ nextLabel }}
                </Button>
            </div>
        </footer>
    </main>
</template>
