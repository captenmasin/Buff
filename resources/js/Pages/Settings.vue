<script setup lang="ts">
import {Head, Link, useForm, usePage} from '@inertiajs/vue3';
import {computed, onMounted, ref, watch} from 'vue';
import {X} from '@lucide/vue';
import {storedAppearance, type Appearance} from '../appearance';
import AppSheet from '../Components/AppSheet.vue';
import PageHeader from '../Components/PageHeader.vue';
import SettingsGroup from '../Components/SettingsGroup.vue';
import SettingsRow from '../Components/SettingsRow.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import {type HeightUnit, type WeightUnit} from '../bodyUnits';

type MealType = 'breakfast' | 'lunch' | 'dinner';
type EatBack = 'all' | 'half' | 'none';

type MealReminders = Record<MealType, {
    enabled: boolean;
    time: string;
}>;

const props = defineProps<{
    preferences: {
        weight_unit: WeightUnit;
        height_unit: HeightUnit;
        eat_back: EatBack;
    };
    mealReminders: MealReminders;
    healthConnect: {
        is_android: boolean;
        status?: string | null;
    };
    appleHealth: {
        is_ios: boolean;
        status?: string | null;
    };
}>();

interface BuffAccount {
    id: string;
    name: string;
    email: string;
    timezone: string;
    email_verified: boolean;
}

const page = usePage<{
    buff: {
        account: BuffAccount | null;
        has_local_account: boolean;
    };
}>();

const logoutForm = useForm({});
const deleteAccountForm = useForm({password: ''});
const deleteAccountOpen = ref(false);
const appearance = ref<Appearance>(storedAppearance());

const appearanceLabels: Record<Appearance, string> = {
    system: 'System',
    light: 'Light',
    dark: 'Dark',
};
const eatBackLabels: Record<EatBack, string> = {
    all: 'Eat all back',
    half: 'Eat half back',
    none: "Don't eat back",
};
const passwordResetUrl = computed(() => {
    const email = page.props.buff.account?.email;

    return email
        ? `/account/forgot-password?${new URLSearchParams({email}).toString()}`
        : '/account/forgot-password';
});
const healthName = computed(() => {
    if (props.appleHealth.is_ios === true) {
        return 'Apple Health';
    }

    if (props.healthConnect.is_android === true) {
        return 'Health Connect';
    }

    return null;
});
const unitsDetail = computed(() => {
    const weight = props.preferences.weight_unit === 'lb' ? 'lb' : 'kg';
    const height = props.preferences.height_unit === 'in' ? 'ft/in' : 'cm';

    return `${weight}, ${height}`;
});
const reminderDetail = computed(() => {
    const enabled = (['breakfast', 'lunch', 'dinner'] as const)
        .filter((meal) => props.mealReminders[meal].enabled);

    return enabled.length === 0 ? 'Off' : `${enabled.length} on`;
});

function openDeleteAccountModal() {
    deleteAccountForm.reset();
    deleteAccountForm.clearErrors();
    deleteAccountOpen.value = true;
}

function closeDeleteAccountModal() {
    if (deleteAccountForm.processing) {
        return;
    }

    deleteAccountForm.reset();
    deleteAccountForm.clearErrors();
    deleteAccountOpen.value = false;
}

function submitDeleteAccount() {
    deleteAccountForm.delete('/account', {
        preserveScroll: true,
        onError: () => {
            deleteAccountOpen.value = true;
        },
        onSuccess: () => closeDeleteAccountModal(),
    });
}

watch(() => deleteAccountForm.errors.password, (error) => {
    if (error) {
        deleteAccountOpen.value = true;
    }
});

onMounted(() => {
    if (deleteAccountForm.errors.password) {
        deleteAccountOpen.value = true;
    }
});
</script>

<template>
    <Head title="Settings"/>

    <section class="space-y-6">
        <PageHeader>Settings</PageHeader>

        <SettingsGroup title="My Account">
            <template v-if="page.props.buff.account">
                <SettingsRow href="/settings/account" :supporting="page.props.buff.account.email">
                    Edit profile
                </SettingsRow>
                <SettingsRow href="/settings/password">Change password</SettingsRow>
            </template>
            <template v-else>
                <SettingsRow href="/account/login">Sign in</SettingsRow>
            </template>
        </SettingsGroup>

        <SettingsGroup title="Preferences">
            <SettingsRow href="/settings/appearance" :detail="appearanceLabels[appearance]">Appearance</SettingsRow>
            <SettingsRow href="/settings/reminders" :detail="reminderDetail">Meal reminders</SettingsRow>
            <SettingsRow href="/settings/body-profile">Body profile</SettingsRow>
            <SettingsRow href="/settings/units" :detail="unitsDetail">Units</SettingsRow>
            <SettingsRow href="/settings/exercise" :detail="eatBackLabels[preferences.eat_back]">Exercise calories</SettingsRow>
        </SettingsGroup>

        <SettingsGroup v-if="healthName || page.props.buff.account" title="Apps & devices">
            <SettingsRow v-if="healthName" href="/settings/health">{{ healthName }}</SettingsRow>
            <SettingsRow v-if="page.props.buff.account" href="/settings/connected-assistants">Connected assistants</SettingsRow>
        </SettingsGroup>

        <SettingsGroup v-if="page.props.buff.account || page.props.buff.has_local_account">
            <template v-if="page.props.buff.account">
                <SettingsRow href="/account/logout" method="post">Log out</SettingsRow>
                <SettingsRow destructive @click="openDeleteAccountModal">Delete account</SettingsRow>
            </template>
            <SettingsRow
                v-else
                @click="logoutForm.post('/account/logout')"
            >
                Remove local account data
            </SettingsRow>
        </SettingsGroup>

        <AppSheet :open="deleteAccountOpen" labelled-by="delete-account-title" @close="closeDeleteAccountModal">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="delete-account-title" class="text-xl font-semibold">Delete account</h2>
                    <p class="mt-1 text-sm text-muted-foreground">This permanently deletes your Buff account and all synced data. Enter your password to confirm.</p>
                </div>
                <Button type="button" variant="ghost" size="icon" aria-label="Close delete account dialog" :disabled="deleteAccountForm.processing" @click="closeDeleteAccountModal">
                    <X :size="20" />
                </Button>
            </div>

            <form class="mt-4 space-y-3" @submit.prevent="submitDeleteAccount">
                <p class="text-sm text-muted-foreground">
                    Signed in with Google or Apple, or don't know your password?
                    <Link :href="passwordResetUrl" class="text-link">Set or reset it by email first.</Link>
                </p>
                <label for="delete-account-password" class="block field-label">Password</label>
                <Input
                    id="delete-account-password"
                    v-model="deleteAccountForm.password"
                    type="password"
                    autocomplete="current-password"
                    placeholder="Current password"
                    required
                    autofocus
                    :disabled="deleteAccountForm.processing"
                />
                <p v-if="deleteAccountForm.errors.password" class="text-sm text-destructive" role="alert">{{ deleteAccountForm.errors.password }}</p>
                <div class="grid grid-cols-2 gap-2">
                    <Button type="button" variant="surface" :disabled="deleteAccountForm.processing" @click="closeDeleteAccountModal">
                        Cancel
                    </Button>
                    <Button variant="destructive" :disabled="deleteAccountForm.processing">
                        Delete account
                    </Button>
                </div>
            </form>
        </AppSheet>
    </section>
</template>
