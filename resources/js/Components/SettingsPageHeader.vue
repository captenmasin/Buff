<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import {ChevronLeft} from '@lucide/vue';

withDefaults(defineProps<{
    backHref?: string;
    backLabel?: string;
}>(), {
    backHref: '/settings',
    backLabel: 'Back to settings',
});
</script>

<template>
    <header class="flex min-h-11 items-center gap-1">
        <Link
            :href="backHref"
            class="-ml-2 grid size-11 flex-none place-items-center rounded-full text-foreground"
            :aria-label="backLabel"
        >
            <ChevronLeft :size="26" stroke-width="2.2" />
        </Link>
        <h1 class="page-title page-title-compact">
            <slot />
        </h1>
    </header>
</template>
