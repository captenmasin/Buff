<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import {Moon, Smartphone, Sun} from '@lucide/vue';
import {ref} from 'vue';
import {
    saveAppearance,
    saveReducedMotion,
    storedAppearance,
    storedReducedMotion,
    type Appearance,
} from '../../appearance';
import Card from '../../Components/Card.vue';
import SettingsPageHeader from '../../Components/SettingsPageHeader.vue';
import Button from '../../Components/ui/button/Button.vue';
import Switch from '../../Components/ui/switch/Switch.vue';

const appearance = ref<Appearance>(storedAppearance());
const reduceMotion = ref(storedReducedMotion());
const appearanceOptions: Array<{ value: Appearance; label: string; icon: typeof Sun }> = [
    {value: 'system', label: 'System', icon: Smartphone},
    {value: 'light', label: 'Light', icon: Sun},
    {value: 'dark', label: 'Dark', icon: Moon},
];

function selectAppearance(value: Appearance) {
    appearance.value = value;
    saveAppearance(value);
}

function updateReducedMotion() {
    saveReducedMotion(reduceMotion.value);
}
</script>

<template>
    <Head title="Appearance"/>

    <section class="space-y-5">
        <SettingsPageHeader>Appearance</SettingsPageHeader>

        <Card class="gap-4">
            <div>
                <h2 class="card-title">Theme</h2>
                <p class="mt-1 text-sm text-muted-foreground">Choose how Buff looks on this device.</p>
            </div>

            <div class="mt-1 rounded-xl bg-muted p-1 dark:bg-secondary">
                <div class="grid grid-cols-3 gap-1" role="group" aria-label="Appearance">
                    <Button
                        v-for="option in appearanceOptions"
                        :key="option.value"
                        type="button"
                        size="sm"
                        class="h-10 w-full gap-1.5 rounded-lg px-2"
                        :class="appearance === option.value
                            ? 'bg-primary-container text-primary-container-foreground hover:bg-primary-container dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary'
                            : 'text-foreground hover:bg-foreground/8'"
                        :variant="appearance === option.value ? 'default' : 'ghost'"
                        :aria-pressed="appearance === option.value"
                        @click="selectAppearance(option.value)"
                    >
                        <component :is="option.icon" :size="16" stroke-width="2.2" />
                        <span>{{ option.label }}</span>
                    </Button>
                </div>
            </div>

            <div class="border-t border-border/70 pt-4" aria-label="Appearance preview">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Preview</p>
                        <p class="mt-0.5 page-title-compact page-title">Today</p>
                    </div>
                    <span class="rounded-full bg-success-soft px-2.5 py-1 text-xs font-semibold text-success-soft-foreground">On track</span>
                </div>

                <div class="mt-3">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium text-muted-foreground">Calories</p>
                            <p class="mt-0.5 text-xl font-semibold">1,420</p>
                        </div>
                        <p class="text-xs font-medium text-muted-foreground">of 2,100 kcal</p>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full w-2/3 rounded-full bg-success transition-[width] duration-500"></div>
                    </div>
                </div>
            </div>
        </Card>

        <Card>
            <div class="flex items-center gap-4">
                <label for="reduce-motion" class="min-w-0 flex-1">
                    <span class="card-title block">Reduce motion</span>
                    <span class="mt-1 block text-sm text-muted-foreground">
                        Limit animations and page transitions. Your device’s accessibility setting is always respected.
                    </span>
                </label>
                <Switch
                    id="reduce-motion"
                    v-model="reduceMotion"
                    aria-label="Reduce motion"
                    class="shrink-0"
                    @change="updateReducedMotion"
                />
            </div>
        </Card>
    </section>
</template>
