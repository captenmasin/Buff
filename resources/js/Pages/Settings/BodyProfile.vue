<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import BodyProfileEditor from '../../Components/BodyProfileEditor.vue';
import Card from '../../Components/Card.vue';
import SettingsPageHeader from '../../Components/SettingsPageHeader.vue';
import Button from '../../Components/ui/button/Button.vue';
import {type ActivityLevel, type Sex} from '../../bodyProfile';
import {heightFromCm, heightToCm, type HeightUnit, type WeightUnit} from '../../bodyUnits';

const props = defineProps<{
    preferences: {
        weight_unit: WeightUnit;
        height_unit: HeightUnit;
    };
    bodyProfile: {
        height_cm: number | null;
        age: number | null;
        sex: Sex | null;
        activity_level: ActivityLevel | null;
    };
}>();

const profileForm = useForm({
    height_cm: heightFromCm(props.bodyProfile.height_cm, props.preferences.height_unit) ?? '',
    age: props.bodyProfile.age ?? '',
    sex: props.bodyProfile.sex ?? '',
    activity_level: props.bodyProfile.activity_level ?? '',
});

function saveBodyProfile() {
    profileForm.transform((data) => ({
        ...data,
        height_cm: heightToCm(data.height_cm, props.preferences.height_unit),
    })).put('/settings/body-profile', {preserveScroll: true});
}
</script>

<template>
    <Head title="Body profile"/>

    <section class="space-y-5">
        <SettingsPageHeader>Body profile</SettingsPageHeader>

        <Card>
            <div class="space-y-3">
                <div>
                    <h2 class="card-title">Body profile</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Used for BMI and calorie estimates. Weight and body-fat goals live under Goals.</p>
                </div>
                <form class="space-y-3" @submit.prevent="saveBodyProfile">
                    <BodyProfileEditor
                        v-model:age="profileForm.age"
                        v-model:sex="profileForm.sex"
                        v-model:height="profileForm.height_cm"
                        v-model:activity_level="profileForm.activity_level"
                        :height-unit="preferences.height_unit"
                        :errors="profileForm.errors"
                    />
                    <Button class="w-full" :disabled="profileForm.processing">Save profile</Button>
                </form>
            </div>
        </Card>
    </section>
</template>
