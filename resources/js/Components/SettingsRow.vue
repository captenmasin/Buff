<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import {ChevronRight} from '@lucide/vue';
import {computed} from 'vue';

const props = withDefaults(defineProps<{
    href?: string;
    method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
    supporting?: string;
    detail?: string;
    destructive?: boolean;
    disclose?: boolean;
}>(), {
    href: '',
    method: 'get',
    supporting: '',
    detail: '',
    destructive: false,
    disclose: undefined,
});

const showChevron = computed(() => props.disclose ?? (props.href !== '' && props.method === 'get'));
const rowClass = computed(() => [
    'flex min-h-12 w-full items-center gap-3 bg-transparent px-5 py-3 text-left text-[1rem] leading-snug no-underline active:bg-muted/80',
    props.destructive ? 'text-destructive' : 'text-foreground',
]);
</script>

<template>
    <Link
        v-if="href && method === 'get'"
        :href="href"
        prefetch
        :class="rowClass"
    >
        <span class="min-w-0 flex-1">
            <span class="block font-medium"><slot /></span>
            <span v-if="supporting" class="mt-0.5 block text-sm font-medium text-muted-foreground">{{ supporting }}</span>
        </span>
        <span v-if="detail" class="shrink-0 text-sm text-muted-foreground">{{ detail }}</span>
        <ChevronRight v-if="showChevron" class="shrink-0 text-muted-foreground" :size="18" stroke-width="2.2" />
    </Link>
    <Link
        v-else-if="href"
        :href="href"
        :method="method"
        as="button"
        :class="rowClass"
    >
        <span class="min-w-0 flex-1">
            <span class="block font-medium"><slot /></span>
            <span v-if="supporting" class="mt-0.5 block text-sm font-medium text-muted-foreground">{{ supporting }}</span>
        </span>
        <span v-if="detail" class="shrink-0 text-sm text-muted-foreground">{{ detail }}</span>
        <ChevronRight v-if="showChevron" class="shrink-0 text-muted-foreground" :size="18" stroke-width="2.2" />
    </Link>
    <button
        v-else
        type="button"
        :class="rowClass"
    >
        <span class="min-w-0 flex-1">
            <span class="block font-medium"><slot /></span>
            <span v-if="supporting" class="mt-0.5 block text-sm font-medium text-muted-foreground">{{ supporting }}</span>
        </span>
        <span v-if="detail" class="shrink-0 text-sm text-muted-foreground">{{ detail }}</span>
        <ChevronRight v-if="showChevron" class="shrink-0 text-muted-foreground" :size="18" stroke-width="2.2" />
    </button>
</template>
