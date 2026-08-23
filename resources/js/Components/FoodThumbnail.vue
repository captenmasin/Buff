<script setup lang="ts">
import { Barcode, Utensils } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { cn } from '../lib/utils';

const props = withDefaults(defineProps<{
    src?: string | null;
    icon?: 'utensils' | 'barcode';
    class?: string;
    iconSize?: number;
}>(), {
    src: null,
    icon: 'utensils',
    class: 'h-12 w-12',
    iconSize: 20,
});

const failed = ref(false);
const loaded = ref(false);

watch(() => props.src, () => {
    failed.value = false;
    loaded.value = false;
});

const showImage = computed(() => Boolean(props.src) && !failed.value);
</script>

<template>
    <span class="relative inline-grid shrink-0">
        <img
            v-if="showImage"
            :src="src ?? ''"
            alt=""
            :class="cn('col-start-1 row-start-1 rounded-xl object-cover', props.class, loaded ? '' : 'opacity-0')"
            @load="loaded = true"
            @error="failed = true"
        >
        <span
            v-if="!showImage || !loaded"
            :class="cn('col-start-1 row-start-1 grid place-items-center rounded-xl bg-muted text-muted-foreground', props.class)"
        >
            <Utensils v-if="icon === 'utensils'" :size="iconSize" />
            <Barcode v-else :size="iconSize" />
        </span>
    </span>
</template>
