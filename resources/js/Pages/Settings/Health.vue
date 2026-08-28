<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, onBeforeUnmount, onMounted, ref} from 'vue';
import {Link2} from '@lucide/vue';
import Card from '../../Components/Card.vue';
import SettingsPageHeader from '../../Components/SettingsPageHeader.vue';
import Button from '../../Components/ui/button/Button.vue';

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

interface AppleHealthState {
    is_ios: boolean;
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

const props = defineProps<{
    healthConnect: HealthConnectState;
    appleHealth: AppleHealthState;
}>();

const healthConnectState = ref({...props.healthConnect});
const appleHealthState = ref({...props.appleHealth});
const healthConnectLoading = ref(false);
const healthConnectDisconnecting = ref(false);
const healthConnectRefreshTimer = ref<number | null>(null);

const healthImport = computed(() => {
    if (appleHealthState.value.is_ios === true) {
        return {name: 'Apple Health', prefix: '/apple-health', state: appleHealthState.value};
    }

    if (healthConnectState.value.is_android === true) {
        return {name: 'Health Connect', prefix: '/health-connect', state: healthConnectState.value};
    }

    return null;
});
const canSyncHealthConnect = computed(() => ['connected', 'sync_queued'].includes(healthImport.value?.state.status ?? ''));
const canDisconnectHealthConnect = computed(() => (
    healthImport.value?.prefix === '/health-connect' && (
        healthImport.value.state.foreground_granted === true || healthImport.value.state.background_granted === true
    )
));
const healthConnectLabel = computed(() => {
    const state = healthImport.value?.state;

    if (state?.available === false) return 'Unavailable';
    if (state?.status === 'connected') return 'Connected';
    if (state?.status === 'background_permission_required') return 'Background access needed';
    if (state?.status === 'sync_queued') return 'Sync queued';
    return 'Permission needed';
});
const healthConnectDetail = computed(() => {
    const state = healthImport.value?.state;

    if (state?.message) {
        return state.message;
    }

    if (state?.last_successful_sync_at) {
        return `Last synced ${new Date(state.last_successful_sync_at).toLocaleString([], {dateStyle: 'short', timeStyle: 'short'})}`;
    }

    if (state?.last_error) {
        return state.last_error;
    }

    return `Connect ${healthImport.value?.name ?? 'health data'} to import workout calories automatically.`;
});
const healthConnectButtonLabel = computed(() => {
    if (healthConnectLoading.value) return canSyncHealthConnect.value ? 'Syncing...' : 'Opening...';
    if (healthImport.value?.state.status === 'sync_queued') return 'Sync queued';
    if (canSyncHealthConnect.value) return 'Sync now';
    return `Connect ${healthImport.value?.name ?? 'health data'}`;
});

function applyHealthImportState(data: {native?: Record<string, unknown>} & Record<string, unknown>) {
    const nextState = {message: null, ...data, ...(data.native || {})};

    if (healthImport.value?.prefix === '/apple-health') {
        appleHealthState.value = {...appleHealthState.value, ...nextState};
        return;
    }

    healthConnectState.value = {...healthConnectState.value, ...nextState};
}

async function refreshHealthConnectStatus() {
    const prefix = healthImport.value?.prefix;

    if (!prefix) {
        return;
    }

    try {
        const {data} = await axios.get(`${prefix}/status`);
        applyHealthImportState(data);
    } catch {
        applyHealthImportState({last_error: `Could not check ${healthImport.value?.name ?? 'health data'}.`});
    }
}

async function connectHealthConnect() {
    const prefix = healthImport.value?.prefix;

    if (!prefix) {
        return;
    }

    healthConnectLoading.value = true;

    try {
        const endpoint = canSyncHealthConnect.value ? `${prefix}/sync` : `${prefix}/connect`;
        const {data} = await axios.post(endpoint);
        applyHealthImportState(data);

        if (healthImport.value?.state.status === 'permission_requested') {
            scheduleHealthConnectStatusRefresh();
        }
    } finally {
        healthConnectLoading.value = false;
    }
}

async function disconnectHealthConnect() {
    if (!canDisconnectHealthConnect.value) {
        return;
    }

    healthConnectDisconnecting.value = true;

    try {
        const {data} = await axios.delete('/health-connect');
        applyHealthImportState(data);
    } finally {
        healthConnectDisconnecting.value = false;
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

        if (healthImport.value?.state.status === 'permission_requested') {
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

onMounted(() => {
    refreshHealthConnectStatus();
    window.addEventListener('focus', handleHealthConnectResume);
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
    clearHealthConnectStatusRefresh();
    window.removeEventListener('focus', handleHealthConnectResume);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <Head :title="healthImport?.name ?? 'Health'"/>

    <section class="space-y-5">
        <SettingsPageHeader>{{ healthImport?.name ?? 'Health' }}</SettingsPageHeader>

        <Card>
            <div class="flex items-start gap-3">
                <div class="grid h-10 w-10 flex-none place-items-center rounded-xl bg-primary text-primary-foreground">
                    <Link2 :size="18"/>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-muted-foreground">{{ healthConnectLabel }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ healthConnectDetail }}</p>
                </div>
            </div>
            <Button
                type="button"
                class="mt-4 w-full"
                :disabled="healthConnectLoading || healthConnectDisconnecting || healthImport?.state.available === false"
                @click="connectHealthConnect"
            >
                {{ healthConnectButtonLabel }}
            </Button>
            <Button
                v-if="canDisconnectHealthConnect"
                type="button"
                variant="destructive"
                class="mt-2 w-full"
                :disabled="healthConnectLoading || healthConnectDisconnecting"
                @click="disconnectHealthConnect"
            >
                {{ healthConnectDisconnecting ? 'Disconnecting...' : 'Disconnect Health Connect' }}
            </Button>
        </Card>
    </section>
</template>
