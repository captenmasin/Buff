<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import {Moon, Smartphone, Sun} from '@lucide/vue';
import {ref} from 'vue';
import {applyAppearance, saveAppearance, storedAppearance, type Appearance} from '../../appearance';
import Card from '../../Components/Card.vue';
import SettingsPageHeader from '../../Components/SettingsPageHeader.vue';
import Button from '../../Components/ui/button/Button.vue';

const appearance = ref<Appearance>(storedAppearance());
const appearanceOptions: Array<{ value: Appearance; label: string; icon: typeof Sun }> = [
    {value: 'system', label: 'System', icon: Smartphone},
    {value: 'light', label: 'Light', icon: Sun},
    {value: 'dark', label: 'Dark', icon: Moon},
];

function selectAppearance(value: Appearance) {
    appearance.value = value;
    saveAppearance(value);
    applyAppearance(value);
}
</script>

<template>
    <Head title="Appearance"/>

    <section class="space-y-5">
        <SettingsPageHeader>Appearance</SettingsPageHeader>

        <Card>
            <div class="mt-1 rounded-xl bg-brand-night p-1">
                <div class="grid grid-cols-3 gap-1" role="group" aria-label="Appearance">
                    <Button
                        v-for="option in appearanceOptions"
                        :key="option.value"
                        type="button"
                        size="sm"
                        class="h-10 w-full gap-1.5 rounded-lg px-2 focus-visible:border-brand-white focus-visible:ring-brand-white"
                        :class="appearance === option.value ? '' : 'text-brand-white hover:bg-brand-white/10 hover:text-brand-white'"
                        :variant="appearance === option.value ? 'default' : 'ghost'"
                        :aria-pressed="appearance === option.value"
                        @click="selectAppearance(option.value)"
                    >
                        <component :is="option.icon" :size="16" stroke-width="2.2" />
                        <span>{{ option.label }}</span>
                    </Button>
                </div>
            </div>
        </Card>
    </section>
</template>
