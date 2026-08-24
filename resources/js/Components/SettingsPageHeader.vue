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
    <header class="relative flex min-h-11 items-center">
        <Link
            :href="backHref"
            class="absolute left-0 -ml-2 grid size-11 place-items-center rounded-full text-foreground"
            :aria-label="backLabel"
        >
            <ChevronLeft :size="26" stroke-width="2.2" />
        </Link>
        <h1 class="page-title page-title-compact w-full text-center">
            <slot />
        </h1>
    </header>
</template>
