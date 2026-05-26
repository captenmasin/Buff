<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Moon, Smartphone, Sun } from '@lucide/vue';
import { applyAppearance, saveAppearance, storedAppearance, type Appearance } from '../appearance';
import Card from '../Components/Card.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';

const props = defineProps<{
    settings: {
        height_cm: number | null;
        target_weight_kg: number | null;
        target_body_fat_percent: number | null;
    };
}>();

const bodyTargetForm = useForm({
    target_weight_kg: props.settings.target_weight_kg ?? '',
    target_body_fat_percent: props.settings.target_body_fat_percent ?? '',
});

const heightForm = useForm({
    height_cm: props.settings.height_cm ?? '',
});

const appearance = ref<Appearance>(storedAppearance());

const appearanceOptions: Array<{ value: Appearance; label: string; icon: typeof Sun }> = [
    { value: 'system', label: 'System', icon: Smartphone },
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
];

function saveBodyTargets() {
    bodyTargetForm.put('/settings/body-targets', { preserveScroll: true });
}

function saveHeight() {
    heightForm.put('/settings/height', { preserveScroll: true });
}

function selectAppearance(value: Appearance) {
    appearance.value = value;
    saveAppearance(value);
    applyAppearance(value);
}
</script>

<template>
    <Head title="Settings" />

    <section class="space-y-5">
        <header>
            <p class="text-sm text-muted-foreground">Preferences</p>
            <h1 class="text-3xl font-semibold tracking-normal text-foreground">Settings</h1>
        </header>

        <Card>
            <h2 class="font-semibold">Appearance</h2>
            <div class="mt-3 grid grid-cols-3 gap-2">
                <Button
                    v-for="option in appearanceOptions"
                    :key="option.value"
                    type="button"
                    class="flex-col px-2 text-sm"
                    :variant="appearance === option.value ? 'default' : 'surface'"
                    @click="selectAppearance(option.value)"
                >
                    {{ option.label }}
                </Button>
            </div>
        </Card>

        <Card>
            <form class="space-y-3" @submit.prevent="saveBodyTargets">
                <h2 class="font-semibold">Body targets</h2>

                <div class="grid grid-cols-2 gap-3">
                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Weight kg</span>
                        <Input
                            v-model.number="bodyTargetForm.target_weight_kg"
                            type="number"
                            min="1"
                            max="1000"
                            step="0.1"
                            class="mt-1"
                        />
                        <span v-if="bodyTargetForm.errors.target_weight_kg" class="mt-1 block text-sm text-destructive">{{ bodyTargetForm.errors.target_weight_kg }}</span>
                    </label>

                    <label>
                        <span class="text-xs font-semibold uppercase text-muted-foreground">Body fat %</span>
                        <Input
                            v-model.number="bodyTargetForm.target_body_fat_percent"
                            type="number"
                            min="1"
                            max="80"
                            step="0.1"
                            class="mt-1"
                        />
                        <span v-if="bodyTargetForm.errors.target_body_fat_percent" class="mt-1 block text-sm text-destructive">{{ bodyTargetForm.errors.target_body_fat_percent }}</span>
                    </label>
                </div>

                <Button class="w-full" :disabled="bodyTargetForm.processing">
                    Save targets
                </Button>
            </form>
        </Card>

        <Card>
            <form class="space-y-3" @submit.prevent="saveHeight">
                <h2 class="font-semibold">Height</h2>
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-muted-foreground">Height cm</span>
                    <Input
                        v-model.number="heightForm.height_cm"
                        type="number"
                        min="50"
                        max="260"
                        step="0.1"
                        class="mt-1"
                    />
                    <span v-if="heightForm.errors.height_cm" class="mt-1 block text-sm text-destructive">{{ heightForm.errors.height_cm }}</span>
                </label>
                <Button class="w-full" :disabled="heightForm.processing">
                    Save height
                </Button>
            </form>
        </Card>
    </section>
</template>
