<script setup lang="ts">
import type { Component } from 'vue';
import { Camera, Dumbbell, LockKeyhole, Pencil, ScanBarcode, Search, UtensilsCrossed } from '@lucide/vue';
import Button from '../ui/button/Button.vue';

const props = withDefaults(defineProps<{
    subscriptionActive?: boolean;
}>(), {
    subscriptionActive: false,
});

export type AddChoiceMode = 'food' | 'custom' | 'workout' | 'photo' | 'recipe';

interface AddChoice {
    mode: AddChoiceMode;
    extra?: Record<string, string>;
    label: string;
    description: string;
    icon: Component;
    tone: 'acid' | 'violet' | 'night' | 'muted';
}

const toneClasses: Record<AddChoice['tone'], string> = {
    acid: 'bg-brand-acid text-brand-night',
    violet: 'bg-brand-violet text-brand-white',
    night: 'bg-primary-container text-primary-container-foreground',
    muted: 'bg-muted text-foreground',
};

const tiles: AddChoice[] = [
    {
        mode: 'food',
        label: 'Search',
        description: 'Food & history',
        icon: Search,
        tone: 'acid',
    },
    {
        mode: 'food',
        extra: { scan: '1' },
        label: 'Scan',
        description: 'Barcode',
        icon: ScanBarcode,
        tone: 'violet',
    },
    {
        mode: 'photo',
        label: 'Photo',
        description: 'Macro estimate',
        icon: Camera,
        tone: 'night',
    },
    {
        mode: 'custom',
        label: 'Custom',
        description: 'Manual entry',
        icon: Pencil,
        tone: 'muted',
    },
];

const rows: AddChoice[] = [
    {
        mode: 'recipe',
        label: 'Recipe',
        description: 'Saved multi-ingredient meal',
        icon: UtensilsCrossed,
        tone: 'violet',
    },
    {
        mode: 'workout',
        label: 'Workout',
        description: 'Log calories burned',
        icon: Dumbbell,
        tone: 'night',
    },
];

const emit = defineEmits<{
    select: [mode: AddChoiceMode, extra?: Record<string, string>];
}>();
</script>

<template>
    <div class="grid gap-3" role="group" aria-label="Add">
        <div class="grid grid-cols-2 gap-2">
            <Button
                v-for="choice in tiles"
                :key="choice.label"
                type="button"
                variant="outline"
                class="h-auto min-h-20 w-full justify-start gap-3 whitespace-normal rounded-2xl px-3 py-3 text-left"
                :aria-label="`${choice.label}. ${choice.description}${choice.mode === 'photo' && !props.subscriptionActive ? '. Buff+ required' : ''}`"
                @click="emit('select', choice.mode, choice.extra)"
            >
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl"
                    :class="toneClasses[choice.tone]"
                >
                    <component :is="choice.icon" :size="20" />
                </span>
                <span class="min-w-0">
                    <span class="flex items-center gap-1.5 text-sm font-semibold">
                        {{ choice.label }}
                        <span v-if="choice.mode === 'photo' && !props.subscriptionActive" class="inline-flex items-center gap-1 rounded-full bg-primary-container px-1.5 py-0.5 text-[0.625rem] uppercase tracking-wide text-primary-container-foreground">
                            <LockKeyhole :size="11" aria-hidden="true" /> Buff+
                        </span>
                    </span>
                    <span class="block truncate text-xs leading-snug text-muted-foreground">{{ choice.description }}</span>
                </span>
            </Button>
        </div>

        <Button
            v-for="choice in rows"
            :key="choice.label"
            type="button"
            variant="outline"
            class="h-auto min-h-18 w-full justify-start gap-3 whitespace-normal rounded-2xl px-3 py-3 text-left"
            :aria-label="`${choice.label}. ${choice.description}`"
            @click="emit('select', choice.mode, choice.extra)"
        >
            <span
                class="grid size-10 shrink-0 place-items-center rounded-xl"
                :class="toneClasses[choice.tone]"
            >
                <component :is="choice.icon" :size="20" />
            </span>
            <span class="min-w-0">
                <span class="block font-semibold">{{ choice.label }}</span>
                <span class="block text-xs leading-snug text-muted-foreground">{{ choice.description }}</span>
            </span>
        </Button>
    </div>
</template>
