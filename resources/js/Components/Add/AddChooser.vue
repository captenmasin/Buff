<script setup lang="ts">
import type { Component } from 'vue';
import { Camera, Dumbbell, Pencil, ScanBarcode, Search, UtensilsCrossed } from '@lucide/vue';
import Button from '../ui/button/Button.vue';

export type AddChoiceMode = 'food' | 'custom' | 'workout' | 'photo' | 'recipe';

interface AddChoice {
    mode: AddChoiceMode;
    extra?: Record<string, string>;
    label: string;
    description: string;
    icon: Component;
    tone: 'acid' | 'violet' | 'night';
}

const toneClasses: Record<AddChoice['tone'], string> = {
    acid: 'bg-brand-acid text-brand-night',
    violet: 'bg-brand-violet text-brand-white',
    night: 'bg-brand-night text-brand-white',
};

const tiles: AddChoice[] = [
    {
        mode: 'food',
        label: 'Search',
        description: 'Packaged food and previous items',
        icon: Search,
        tone: 'acid',
    },
    {
        mode: 'food',
        extra: { scan: '1' },
        label: 'Scan',
        description: 'Look up a barcode',
        icon: ScanBarcode,
        tone: 'acid',
    },
    {
        mode: 'photo',
        label: 'Photo',
        description: 'Estimate editable macros',
        icon: Camera,
        tone: 'acid',
    },
    {
        mode: 'custom',
        label: 'Custom',
        description: 'Enter name and macros',
        icon: Pencil,
        tone: 'acid',
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
                variant="ghost"
                class="h-auto min-h-28 w-full flex-col gap-2 rounded-2xl bg-secondary/70 px-3 py-4 hover:bg-secondary"
                :aria-label="`${choice.label}. ${choice.description}`"
                @click="emit('select', choice.mode, choice.extra)"
            >
                <span
                    class="grid h-12 w-12 place-items-center rounded-xl"
                    :class="toneClasses[choice.tone]"
                >
                    <component :is="choice.icon" :size="22" />
                </span>
                <span class="text-sm font-semibold">{{ choice.label }}</span>
            </Button>
        </div>

        <Button
            v-for="choice in rows"
            :key="choice.label"
            type="button"
            variant="ghost"
            class="h-auto min-h-20 w-full justify-start gap-3 rounded-2xl bg-secondary/70 px-3 py-3 text-left hover:bg-secondary"
            :aria-label="`${choice.label}. ${choice.description}`"
            @click="emit('select', choice.mode, choice.extra)"
        >
            <span
                class="grid h-12 w-12 shrink-0 place-items-center rounded-xl"
                :class="toneClasses[choice.tone]"
            >
                <component :is="choice.icon" :size="22" />
            </span>
            <span class="min-w-0">
                <span class="block font-semibold">{{ choice.label }}</span>
                <span class="block text-sm text-muted-foreground">{{ choice.description }}</span>
            </span>
        </Button>
    </div>
</template>
