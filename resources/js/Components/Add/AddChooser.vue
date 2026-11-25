<script setup lang="ts">
import type { Component } from 'vue';
import { Camera, ChevronRight, Dumbbell, LockKeyhole, Pencil, ScanBarcode, Search, UtensilsCrossed } from '@lucide/vue';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import Button from '../ui/button/Button.vue';

const props = withDefaults(defineProps<{
    subscriptionActive?: boolean;
}>(), {
    subscriptionActive: false,
});

type AddChoiceMode = 'food' | 'custom' | 'photo' | 'recipe';

interface AddChoice {
    mode: AddChoiceMode;
    extra?: Record<string, string>;
    label: string;
    icon: Component;
}

const shortcuts: AddChoice[] = [
    { mode: 'food', label: 'Search', icon: Search },
    { mode: 'food', extra: { scan: '1' }, label: 'Scan', icon: ScanBarcode },
    { mode: 'photo', label: 'Photo', icon: Camera },
];

const manualChoices: Array<AddChoice & { description: string; iconClass: string }> = [
    { mode: 'custom', label: 'Custom', description: 'Enter nutrition manually', icon: Pencil, iconClass: 'bg-muted text-foreground' },
    { mode: 'recipe', label: 'Recipe', description: 'Combine ingredients', icon: UtensilsCrossed, iconClass: 'bg-brand-violet/12 text-link' },
];

const tabClass = 'flex min-h-11 items-center justify-center gap-2 rounded-full px-2 text-sm font-semibold text-muted-foreground outline-offset-2 focus-visible:outline-2 focus-visible:outline-ring aria-selected:bg-card aria-selected:text-foreground aria-selected:shadow-sm forced-colors:aria-selected:outline-1 forced-colors:aria-selected:-outline-offset-2';

const emit = defineEmits<{
    select: [mode: AddChoiceMode, extra?: Record<string, string>];
}>();
</script>

<template>
    <TabsRoot default-value="food" :unmount-on-hide="false">
        <TabsList aria-label="Entry type" class="grid grid-cols-2 gap-1 rounded-full bg-muted p-1">
            <TabsTrigger value="food" :class="tabClass">
                <UtensilsCrossed :size="16" aria-hidden="true" />
                Food
            </TabsTrigger>
            <TabsTrigger value="workout" :class="tabClass">
                <Dumbbell :size="16" aria-hidden="true" />
                Workout
            </TabsTrigger>
        </TabsList>

        <TabsContent value="food" tabindex="-1" class="mt-3">
            <div class="grid grid-cols-3 rounded-3xl bg-brand-violet/12">
                <Button
                    v-for="choice in shortcuts"
                    :key="choice.label"
                    type="button"
                    variant="ghost"
                    class="relative h-20 min-w-0 flex-col gap-1.5 rounded-none px-1 text-foreground before:pointer-events-none before:absolute before:inset-y-4 before:left-0 before:w-px before:bg-link/20 first:rounded-l-3xl first:before:hidden last:rounded-r-3xl hover:bg-brand-violet/10 dark:hover:bg-brand-violet/15"
                    @click="emit('select', choice.mode, choice.extra)"
                >
                    <component :is="choice.icon" :size="23" class="text-link" aria-hidden="true" />
                    <span class="flex items-center gap-1 font-semibold">
                        {{ choice.label }}
                        <template v-if="choice.mode === 'photo' && !props.subscriptionActive">
                            <LockKeyhole :size="12" class="text-muted-foreground" aria-hidden="true" />
                            <span class="sr-only">Buff+ required</span>
                        </template>
                    </span>
                </Button>
            </div>

            <div class="mt-2 divide-y divide-border">
                <Button
                    v-for="choice in manualChoices"
                    :key="choice.mode"
                    type="button"
                    variant="ghost"
                    class="h-auto min-h-14 w-full justify-start gap-3 whitespace-normal rounded-none px-1 py-2 text-left first:rounded-t-xl last:rounded-b-xl"
                    @click="emit('select', choice.mode, choice.extra)"
                >
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg" :class="choice.iconClass">
                        <component :is="choice.icon" :size="19" aria-hidden="true" />
                    </span>
                    <span>
                        <span class="block font-semibold">{{ choice.label }}</span>
                        <span class="block text-xs leading-snug text-muted-foreground">{{ choice.description }}</span>
                    </span>
                    <ChevronRight :size="16" class="ml-auto text-muted-foreground" aria-hidden="true" />
                </Button>
            </div>
        </TabsContent>

        <TabsContent value="workout" tabindex="-1" class="mt-3">
            <div class="rounded-3xl bg-background p-3.5">
                <slot name="workout" />
            </div>
        </TabsContent>
    </TabsRoot>
</template>
