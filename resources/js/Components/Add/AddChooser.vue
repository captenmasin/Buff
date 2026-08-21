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
    tone: 'food' | 'workout';
}

const tiles: AddChoice[] = [
    {
        mode: 'food',
        label: 'Search',
        description: 'Packaged food and previous items',
        icon: Search,
        tone: 'food',
    },
    {
        mode: 'food',
        extra: { scan: '1' },
        label: 'Scan',
        description: 'Look up a barcode',
        icon: ScanBarcode,
        tone: 'food',
    },
    {
        mode: 'photo',
        label: 'Photo',
        description: 'Estimate editable macros',
        icon: Camera,
        tone: 'food',
    },
    {
        mode: 'custom',
        label: 'Custom',
        description: 'Enter name and macros',
        icon: Pencil,
        tone: 'food',
    },
];

const rows: AddChoice[] = [
    {
        mode: 'recipe',
        label: 'Recipe',
        description: 'Saved multi-ingredient meal',
        icon: UtensilsCrossed,
        tone: 'food',
    },
    {
        mode: 'workout',
        label: 'Workout',
        description: 'Log calories burned',
        icon: Dumbbell,
        tone: 'workout',
    },
];

const emit = defineEmits<{
    select: [mode: AddChoiceMode, extra?: Record<string, string>];
}>();
</script>

<template>
    <div class="grid gap-3" role="group" aria-label="Add">
        <div class="rounded-xl bg-muted/80 p-1">
            <div class="grid grid-cols-2 gap-1">
                <Button
                    v-for="choice in tiles"
                    :key="choice.label"
                    type="button"
                    variant="ghost"
                    class="h-auto w-full flex-col gap-2 rounded-lg px-2 py-3"
                    :aria-label="`${choice.label}. ${choice.description}`"
                    @click="emit('select', choice.mode, choice.extra)"
                >
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-food text-primary-foreground">
                        <component :is="choice.icon" :size="20" />
                    </span>
                    <span class="text-sm font-semibold">{{ choice.label }}</span>
                </Button>
            </div>
        </div>

        <Button
            v-for="choice in rows"
            :key="choice.label"
            type="button"
            variant="ghost"
            class="h-auto w-full justify-start gap-3 rounded-xl bg-muted/80 px-3 py-3 text-left"
            :aria-label="`${choice.label}. ${choice.description}`"
            @click="emit('select', choice.mode, choice.extra)"
        >
            <span
                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-primary-foreground"
                :class="choice.tone === 'food' ? 'bg-food' : 'bg-workout'"
            >
                <component :is="choice.icon" :size="20" />
            </span>
            <span class="min-w-0">
                <span class="block font-semibold">{{ choice.label }}</span>
                <span class="block text-sm font-medium text-muted-foreground">{{ choice.description }}</span>
            </span>
        </Button>
    </div>
</template>
