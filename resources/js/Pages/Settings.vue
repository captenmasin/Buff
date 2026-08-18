<script setup lang="ts">
import {Head, Link, router, useForm, usePage} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import {Link2, Moon, RefreshCw, Smartphone, Sun, X} from '@lucide/vue';
import {applyAppearance, saveAppearance, storedAppearance, type Appearance} from '../appearance';
import AppSheet from '../Components/AppSheet.vue';
import Card from '../Components/Card.vue';
import PageHeader from '../Components/PageHeader.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import Select from '../Components/ui/select/Select.vue';
import SelectContent from '../Components/ui/select/SelectContent.vue';
import SelectItem from '../Components/ui/select/SelectItem.vue';
import SelectTrigger from '../Components/ui/select/SelectTrigger.vue';
import SelectValue from '../Components/ui/select/SelectValue.vue';
import Switch from '../Components/ui/switch/Switch.vue';
import {type HeightUnit, type WeightUnit} from '../bodyUnits';

type MealType = 'breakfast' | 'lunch' | 'dinner';

type MealReminders = Record<MealType, {
    enabled: boolean;
    time: string;
}>;

const props = defineProps<{
    preferences: {
        weight_unit: WeightUnit;
        height_unit: HeightUnit;
    };
    mealReminders: MealReminders;
    healthConnect: HealthConnectState;
    timezones: string[];
}>();

interface HealthConnectState {
    is_android: boolean;
    available: boolean | null;
    supported: boolean;
    status?: string | null;
    last_successful_sync_at?: string | null;
    last_synced_at?: string | null;
    last_status?: string | null;
    synced_records?: number | null;
    last_error?: string | null;
    message?: string | null;
    has_permissions?: boolean | null;
    foreground_granted?: boolean | null;
    background_granted?: boolean | null;
}

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
        needs_sign_in: boolean;
        has_local_account: boolean;
        sync: {last_succeeded_at: string | null; last_error: string | null; pending: number} | null;
    };
}>();

const unitForm = useForm({
    weight_unit: props.preferences.weight_unit,
    height_unit: props.preferences.height_unit,
});

const mealReminderForm = useForm<MealReminders>({
    breakfast: {...props.mealReminders.breakfast},
    lunch: {...props.mealReminders.lunch},
    dinner: {...props.mealReminders.dinner},
});

const accountForm = useForm({
    name: page.props.buff.account?.name ?? '',
    email: page.props.buff.account?.email ?? '',
    timezone: page.props.buff.account?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC',
});
const logoutForm = useForm({});
const deleteAccountForm = useForm({password: ''});

const appearance = ref<Appearance>(storedAppearance());
const healthConnectState = ref({...props.healthConnect});
const healthConnectLoading = ref(false);
const healthConnectRefreshTimer = ref<number | null>(null);
const deleteAccountOpen = ref(false);
const syncLoading = ref(false);
const syncMessage = ref('');

const appearanceOptions: Array<{ value: Appearance; label: string; icon: typeof Sun }> = [
    {value: 'system', label: 'System', icon: Smartphone},
    {value: 'light', label: 'Light', icon: Sun},
    {value: 'dark', label: 'Dark', icon: Moon},
];
const mealReminderOptions: Array<{ id: MealType; label: string }> = [
    {id: 'breakfast', label: 'Breakfast'},
    {id: 'lunch', label: 'Lunch'},
    {id: 'dinner', label: 'Dinner'},
];
const timezoneOptions = computed(() => {
    const timezones = [...props.timezones];

    if (accountForm.timezone && !timezones.includes(accountForm.timezone)) {
        timezones.unshift(accountForm.timezone);
    }

    return timezones;
});

const showHealthConnect = computed(() => healthConnectState.value.is_android === true);
const canSyncHealthConnect = computed(() => ['connected', 'sync_queued'].includes(healthConnectState.value.status ?? ''));
const healthConnectLabel = computed(() => {
    if (healthConnectState.value.available === false) return 'Unavailable';
    if (healthConnectState.value.status === 'connected') return 'Connected';
    if (healthConnectState.value.status === 'background_permission_required') return 'Background access needed';
    if (healthConnectState.value.status === 'sync_queued') return 'Sync queued';
    return 'Permission needed';
});
const healthConnectDetail = computed(() => {
    if (healthConnectState.value.message) {
        return healthConnectState.value.message;
    }

    if (healthConnectState.value.last_successful_sync_at) {
        return `Last synced ${new Date(healthConnectState.value.last_successful_sync_at).toLocaleString([], {dateStyle: 'short', timeStyle: 'short'})}`;
    }

    if (healthConnectState.value.last_error) {
        return healthConnectState.value.last_error;
    }

    return 'Connect Health Connect to import workout calories automatically.';
});
const healthConnectButtonLabel = computed(() => {
    if (healthConnectLoading.value) return canSyncHealthConnect.value ? 'Syncing...' : 'Opening...';
    if (healthConnectState.value.status === 'sync_queued') return 'Sync queued';
    if (canSyncHealthConnect.value) return 'Sync now';
    return 'Connect Health Connect';
});
const syncDetail = computed(() => {
    if (syncMessage.value) return syncMessage.value;
    if (page.props.buff.sync?.last_error) return page.props.buff.sync.last_error;
    if (page.props.buff.sync?.last_succeeded_at) {
        return `Last synced ${new Date(page.props.buff.sync.last_succeeded_at).toLocaleString([], {dateStyle: 'short', timeStyle: 'short'})}`;
    }

    return `${page.props.buff.sync?.pending ?? 0} changes waiting to sync`;
});

function saveUnits() {
    if (unitForm.processing) {
        return;
    }

    unitForm.put('/settings/units', {preserveScroll: true});
}

function saveMealReminders() {
    if (mealReminderForm.processing) {
        return;
    }

    mealReminderForm.put('/settings/meal-reminders', {preserveScroll: true});
}

function saveAccount() {
    accountForm.patch('/account', {preserveScroll: true});
}

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

async function syncNow() {
    syncLoading.value = true;
    syncMessage.value = '';

    try {
        await axios.post('/sync');
        syncMessage.value = 'Sync complete.';
        router.reload();
    } catch (error) {
        syncMessage.value = axios.isAxiosError(error)
            ? error.response?.data?.message || 'Could not sync Buff.'
            : 'Could not sync Buff.';
    } finally {
        syncLoading.value = false;
    }
}

function mealReminderError(meal: MealType, field: 'enabled' | 'time') {
    return mealReminderForm.errors[`${meal}.${field}`];
}

function selectAppearance(value: Appearance) {
    appearance.value = value;
    saveAppearance(value);
    applyAppearance(value);
}

async function refreshHealthConnectStatus() {
    try {
        const {data} = await axios.get('/health-connect/status');
        healthConnectState.value = {...healthConnectState.value, ...data};
    } catch {
        healthConnectState.value = {...healthConnectState.value, last_error: 'Could not check Health Connect.'};
    }
}

async function connectHealthConnect() {
    healthConnectLoading.value = true;

    try {
        const endpoint = canSyncHealthConnect.value ? '/health-connect/sync' : '/health-connect/connect';
        const {data} = await axios.post(endpoint);
        healthConnectState.value = {...healthConnectState.value, ...data, ...(data.native || {})};

        if (healthConnectState.value.status === 'permission_requested') {
            scheduleHealthConnectStatusRefresh();
        }
    } finally {
        healthConnectLoading.value = false;
    }
}

function clearHealthConnectStatusRefresh() {
    if (healthConnectRefreshTimer.value === null) {
        return;
    }

    window.clearTimeout(healthConnectRefreshTimer.value);
    healthConnectRefreshTimer.value = null;
}

function scheduleHealthConnectStatusRefresh(attemptsRemaining = 20) {
    clearHealthConnectStatusRefresh();

    if (attemptsRemaining < 1) {
        return;
    }

    healthConnectRefreshTimer.value = window.setTimeout(async () => {
        await refreshHealthConnectStatus();

        if (healthConnectState.value.status === 'permission_requested') {
            scheduleHealthConnectStatusRefresh(attemptsRemaining - 1);
        }
    }, 1000);
}

function handleHealthConnectResume() {
    refreshHealthConnectStatus();
}

function handleVisibilityChange() {
    if (document.visibilityState === 'visible') {
        refreshHealthConnectStatus();
    }
}

watch(() => deleteAccountForm.errors.password, (error) => {
    if (error) {
        deleteAccountOpen.value = true;
    }
});

watch(
    () => [unitForm.weight_unit, unitForm.height_unit] as const,
    () => {
        saveUnits();
    },
);

onMounted(() => {
    refreshHealthConnectStatus();
    window.addEventListener('focus', handleHealthConnectResume);
    document.addEventListener('visibilitychange', handleVisibilityChange);

    if (deleteAccountForm.errors.password) {
        deleteAccountOpen.value = true;
    }
});

onBeforeUnmount(() => {
    clearHealthConnectStatusRefresh();
    window.removeEventListener('focus', handleHealthConnectResume);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});

</script>

<template>
    <Head title="Settings"/>

    <section class="space-y-5">
        <PageHeader>Settings</PageHeader>

        <Card>
            <h2 class="font-semibold">Appearance</h2>
            <div class="mt-3 rounded-xl bg-muted/80 p-1">
                <div class="grid grid-cols-3 gap-1" role="group" aria-label="Appearance">
                    <Button
                        v-for="option in appearanceOptions"
                        :key="option.value"
                        type="button"
                        size="sm"
                        class="h-10 w-full gap-1.5 rounded-lg px-2"
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

        <Card>
            <template v-if="page.props.buff.account">
                <form class="space-y-3" @submit.prevent="saveAccount">
                    <div>
                        <h2 class="font-semibold">Your account</h2>
                        <p class="mt-1 text-sm text-muted-foreground">{{ syncDetail }}</p>
                    </div>
                    <label class="block">
                        <span class="field-label">Name</span>
                        <Input v-model="accountForm.name" autocomplete="name" required class="mt-1" />
                        <span v-if="accountForm.errors.name" class="mt-1 block text-sm text-destructive">{{ accountForm.errors.name }}</span>
                    </label>
                    <label class="block">
                        <span class="field-label">Email</span>
                        <Input v-model="accountForm.email" type="email" autocomplete="email" required class="mt-1" />
                        <span v-if="accountForm.errors.email" class="mt-1 block text-sm text-destructive">{{ accountForm.errors.email }}</span>
                    </label>
                    <label class="block">
                        <span class="field-label">Timezone</span>
                        <Select v-model="accountForm.timezone" class="mt-1">
                            <SelectTrigger>
                                <SelectValue placeholder="Select timezone" />
                            </SelectTrigger>
                            <SelectContent class="max-h-72">
                                <SelectItem v-for="timezone in timezoneOptions" :key="timezone" :value="timezone">
                                    {{ timezone.replaceAll('_', ' ') }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="accountForm.errors.timezone" class="mt-1 block text-sm text-destructive">{{ accountForm.errors.timezone }}</span>
                    </label>
                    <div class="grid gap-2">
                        <Button :disabled="accountForm.processing">Save account</Button>
                    </div>
                </form>
            </template>

            <div v-else class="space-y-3">
                <h2 class="font-semibold">Account sync paused</h2>
                <p class="text-sm text-muted-foreground">Your offline data remains on this device. Sign in before the next sync.</p>
                <Button :as="Link" href="/account/login" class="w-full">Sign in</Button>
                <form v-if="page.props.buff.has_local_account" @submit.prevent="logoutForm.post('/account/logout')">
                    <Button variant="surface" class="w-full" :disabled="logoutForm.processing">Remove local account data</Button>
                </form>
            </div>
        </Card>

        <Card v-if="showHealthConnect">
            <div class="flex items-start gap-3">
                <div class="grid h-10 w-10 flex-none place-items-center rounded-xl bg-primary text-primary-foreground">
                    <Link2 :size="18"/>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold">Health Connect</h2>
                    <p class="mt-1 text-sm text-muted-foreground">{{ healthConnectLabel }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ healthConnectDetail }}</p>
                </div>
            </div>
            <Button
                type="button"
                class="mt-4 w-full"
                :disabled="healthConnectLoading || healthConnectState.available === false"
                @click="connectHealthConnect"
            >
                {{ healthConnectButtonLabel }}
            </Button>
        </Card>

        <Card>
            <div class="space-y-3">
                <div>
                    <h2 class="font-semibold">Meal reminders</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Get a reminder to log each meal.</p>
                </div>

                <div class="divide-y divide-border/60">
                    <div v-for="meal in mealReminderOptions" :key="meal.id" class="py-3 first:pt-1 last:pb-1">
                        <div class="flex items-center gap-3">
                            <label :for="`${meal.id}-reminder-enabled`" class="min-w-0 flex-1 font-medium">
                                {{ meal.label }}
                            </label>
                            <Input
                                :id="`${meal.id}-reminder-time`"
                                v-model="mealReminderForm[meal.id].time"
                                type="time"
                                :aria-label="`${meal.label} reminder time`"
                                class="w-[7.5rem] shrink-0 border-0 bg-transparent px-0 py-1 text-right text-sm tabular-nums shadow-none focus:border-transparent focus:bg-transparent focus-visible:ring-2 focus-visible:ring-ring"
                                @change="saveMealReminders"
                            />
                            <Switch
                                :id="`${meal.id}-reminder-enabled`"
                                v-model="mealReminderForm[meal.id].enabled"
                                :aria-label="`Enable ${meal.label.toLowerCase()} reminder`"
                                class="shrink-0"
                                @change="saveMealReminders"
                            />
                        </div>

                        <span v-if="mealReminderError(meal.id, 'enabled')" class="mt-1 block text-sm text-destructive">
                            {{ mealReminderError(meal.id, 'enabled') }}
                        </span>
                        <span v-if="mealReminderError(meal.id, 'time')" class="mt-1 block text-sm text-destructive">
                            {{ mealReminderError(meal.id, 'time') }}
                        </span>
                    </div>
                </div>
            </div>
        </Card>

        <Card>
            <div class="space-y-3">
                <h2 class="font-semibold">Units</h2>

                <div class="grid grid-cols-2 gap-3">
                    <label>
                        <span class="field-label">Weight</span>
                        <Select v-model="unitForm.weight_unit" class="mt-1">
                            <SelectTrigger>
                                <SelectValue placeholder="Select weight unit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="kg">Kilograms</SelectItem>
                                <SelectItem value="lb">Pounds</SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="unitForm.errors.weight_unit" class="mt-1 block text-sm text-destructive">{{ unitForm.errors.weight_unit }}</span>
                    </label>

                    <label>
                        <span class="field-label">Height</span>
                        <Select v-model="unitForm.height_unit" class="mt-1">
                            <SelectTrigger>
                                <SelectValue placeholder="Select height unit" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cm">Centimeters</SelectItem>
                                <SelectItem value="in">Inches</SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="unitForm.errors.height_unit" class="mt-1 block text-sm text-destructive">{{ unitForm.errors.height_unit }}</span>
                    </label>
                </div>
            </div>
        </Card>

        <div v-if="page.props.buff.account" class="grid gap-2">
            <form @submit.prevent="logoutForm.post('/account/logout')">
                <Button variant="surface" class="w-full" :disabled="logoutForm.processing">Sign out and remove local data</Button>
            </form>
            <Button type="button" variant="destructive" class="w-full" @click="openDeleteAccountModal">
                Delete account
            </Button>
        </div>

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
