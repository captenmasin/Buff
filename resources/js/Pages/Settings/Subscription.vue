<script setup lang="ts">
import {Head, Link, usePage} from '@inertiajs/vue3';
import axios from 'axios';
import {computed, onMounted, onUnmounted, ref} from 'vue';
import {Check, Crown, ExternalLink} from '@lucide/vue';
import Card from '../../Components/Card.vue';
import SettingsPageHeader from '../../Components/SettingsPageHeader.vue';
import Button from '../../Components/ui/button/Button.vue';
import {
    configureSubscriptions,
    isSubscriptionActive,
    listenForSubscriptionEvents,
    managementUrl,
    nativeError,
    normalizeOffering,
    subscriptionPackageButtonLabel,
    subscriptionNative,
    type SubscriptionAccount,
    type SubscriptionPackage,
    type SubscriptionPlatform,
} from '../../subscriptions';

interface BuffAccount extends SubscriptionAccount {
    name?: string;
    email?: string;
}

const page = usePage<{buff: {account: BuffAccount | null}}>();
const account = ref<BuffAccount | null>(page.props.buff.account);
const platform = ref<SubscriptionPlatform>('unsupported');
const platformResolved = ref(false);
const packages = ref<SubscriptionPackage[]>([]);
const busy = ref<string | null>(null);
const refreshing = ref(false);
const statusMessage = ref('');
const errorMessage = ref('');
let removeNativeListeners: (() => void) | null = null;

const active = computed(() => isSubscriptionActive(account.value?.subscription?.expires_at));
const manageUrl = computed(() => managementUrl(platform.value, account.value?.subscription?.management_url));
const expiryLabel = computed(() => {
    const expiresAt = account.value?.subscription?.expires_at;

    if (!expiresAt || !Number.isFinite(Date.parse(expiresAt))) {
        return null;
    }

    return new Intl.DateTimeFormat(undefined, {dateStyle: 'medium'}).format(new Date(expiresAt));
});

async function refreshServer(silent = false): Promise<boolean | null> {
    refreshing.value = true;

    if (!silent) {
        errorMessage.value = '';
    }

    try {
        const response = await axios.post('/subscription/refresh');
        const refreshed = response.data?.data;

        if (refreshed && typeof refreshed === 'object') {
            account.value = refreshed;
        }

        return isSubscriptionActive(account.value?.subscription?.expires_at);
    } catch (error) {
        if (!silent) {
            errorMessage.value = axios.isAxiosError(error)
                ? error.response?.data?.message || 'Subscription status could not be refreshed.'
                : 'Subscription status could not be refreshed.';
        }

        return null;
    } finally {
        refreshing.value = false;
    }
}

async function confirmWithServer(action: 'purchase' | 'restore'): Promise<void> {
    busy.value = 'refresh';
    const entitled = await refreshServer();
    busy.value = null;

    if (entitled === true) {
        statusMessage.value = action === 'purchase' ? 'Buff+ is active.' : 'Purchases restored. Buff+ is active.';
    } else if (entitled === false) {
        statusMessage.value = action === 'restore'
            ? 'No active Buff+ subscription was found for this store account.'
            : 'Your purchase is still being confirmed. Refresh again in a moment.';
    }
}

async function startNativeSubscriptions(): Promise<void> {
    try {
        const configuration = await configureSubscriptions(account.value);
        platform.value = configuration.platform;
        platformResolved.value = true;

        if (!configuration.configured) {
            if (configuration.reason === 'missing_key') {
                errorMessage.value = 'Subscriptions are not configured in this build.';
            }

            return;
        }

        removeNativeListeners = await listenForSubscriptionEvents({
            offeringLoaded: (payload) => {
                packages.value = normalizeOffering(payload);
                busy.value = null;
            },
            offeringFailed: (payload) => {
                errorMessage.value = nativeError(payload, 'Subscription options are temporarily unavailable.').message;
                busy.value = null;
            },
            purchaseCompleted: () => void confirmWithServer('purchase'),
            purchaseCancelled: () => {
                busy.value = null;
                statusMessage.value = 'Purchase cancelled.';
            },
            purchasePending: () => {
                busy.value = null;
                statusMessage.value = 'Payment is pending. Buff+ will unlock after the store confirms it.';
            },
            purchaseFailed: (payload) => {
                errorMessage.value = nativeError(payload, 'The purchase could not be completed.').message;
                busy.value = null;
            },
            restoreCompleted: () => void confirmWithServer('restore'),
            restoreFailed: (payload) => {
                errorMessage.value = nativeError(payload, 'Purchases could not be restored.').message;
                busy.value = null;
            },
        });
        busy.value = 'offering';
        await subscriptionNative.loadOffering();
    } catch {
        platformResolved.value = true;
        busy.value = null;
        errorMessage.value = 'Subscriptions are temporarily unavailable.';
    }
}

async function purchase(subscriptionPackage: SubscriptionPackage): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = subscriptionPackage.packageIdentifier;
    errorMessage.value = '';
    statusMessage.value = '';

    try {
        await subscriptionNative.purchase(subscriptionPackage.packageIdentifier);
    } catch {
        busy.value = null;
        errorMessage.value = 'The purchase could not be started.';
    }
}

async function restorePurchases(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = 'restore';
    errorMessage.value = '';
    statusMessage.value = '';

    try {
        await subscriptionNative.restore();
    } catch {
        busy.value = null;
        errorMessage.value = 'Restore could not be started.';
    }
}

async function openManagement(): Promise<void> {
    const url = manageUrl.value;

    if (!url) {
        return;
    }

    try {
        const {Browser} = await import('#nativephp');

        if (await Browser.open(url)) {
            return;
        }
    } catch {
        // Fall through to the ordinary browser for web builds.
    }

    window.location.assign(url);
}

onMounted(() => {
    void refreshServer(true);
    void startNativeSubscriptions();
});

onUnmounted(() => removeNativeListeners?.());
</script>

<template>
    <Head title="Subscription" />

    <section class="space-y-5 pb-8">
        <SettingsPageHeader>Subscription</SettingsPageHeader>

        <Card v-if="!account" class="gap-4">
            <h2 class="card-title">Sign in to subscribe</h2>
            <p class="text-sm text-muted-foreground">Buff+ is linked to your Buff account so purchases can be restored across devices.</p>
            <Button :as="Link" href="/account/login">Sign in</Button>
        </Card>

        <template v-else>
            <Card class="gap-3">
                <div class="flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary text-primary-foreground">
                        <Crown :size="22" aria-hidden="true" />
                    </span>
                    <div class="grid gap-2">
                        <h2 class="card-title">Buff+</h2>
                        <ul class="grid gap-2 text-sm text-muted-foreground">
                            <li class="flex items-center gap-2"><Check :size="17" aria-hidden="true" /> AI meal analysis and follow-ups</li>
                            <li class="flex items-center gap-2"><Check :size="17" aria-hidden="true" /> No ads</li>
                        </ul>
                    </div>
                </div>
            </Card>
            <p v-if="active" class="flex items-center gap-2 text-sm font-semibold text-success-soft-foreground">
                <Check :size="17" aria-hidden="true" />
                Buff+ active<span v-if="expiryLabel"> · access through {{ expiryLabel }}</span>
            </p>

            <div v-if="packages.length" class="grid gap-3">
                <Card v-for="subscriptionPackage in packages" :key="subscriptionPackage.packageIdentifier" class="gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold capitalize">{{ subscriptionPackage.kind }}</h2>
                            <p class="text-sm text-muted-foreground">{{ subscriptionPackage.localizedPrice }} per {{ subscriptionPackage.localizedPeriod }}</p>
                        </div>
                        <span v-if="subscriptionPackage.kind === 'annual'" class="rounded-full bg-primary-container px-2.5 py-1 text-xs font-semibold text-primary-container-foreground">Best value</span>
                    </div>
                    <p v-if="subscriptionPackage.kind === 'annual' && subscriptionPackage.introductoryOffer?.isFreeTrial" class="text-sm font-medium">
                        Eligible customers get {{ subscriptionPackage.introductoryOffer.localizedPeriod }} free, then {{ subscriptionPackage.localizedPrice }} per {{ subscriptionPackage.localizedPeriod }}.
                    </p>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        Auto-renews for {{ subscriptionPackage.localizedPeriod }} at {{ subscriptionPackage.localizedPrice }} unless cancelled. Payment is charged to your {{ platform === 'ios' ? 'Apple' : 'Google Play' }} account. Cancel in your store subscription settings before renewal.
                    </p>
                    <Button
                        class="w-full"
                        :loading="busy === subscriptionPackage.packageIdentifier"
                        loading-label="Opening store…"
                        :disabled="busy !== null || active"
                        @click="purchase(subscriptionPackage)"
                    >
                        {{ subscriptionPackageButtonLabel(subscriptionPackage, active, account?.subscription?.product_id) }}
                    </Button>
                </Card>
            </div>

            <Card v-else-if="platformResolved && platform === 'unsupported'" class="gap-2">
                <h2 class="card-title">Use the Buff mobile app</h2>
                <p class="text-sm text-muted-foreground">Purchases and restore are available in Buff for iOS and Android.</p>
            </Card>

            <p v-if="refreshing || statusMessage" class="rounded-xl bg-muted p-3 text-sm" role="status" aria-live="polite">
                {{ refreshing ? 'Checking subscription status…' : statusMessage }}
            </p>
            <p v-if="errorMessage" class="rounded-xl bg-danger-soft p-3 text-sm text-danger-soft-foreground" role="alert" aria-live="assertive">{{ errorMessage }}</p>

            <div class="grid gap-2">
                <Button v-if="platformResolved && platform !== 'unsupported'" variant="surface" :disabled="busy !== null" :loading="busy === 'restore'" loading-label="Restoring…" @click="restorePurchases">
                    Restore purchases
                </Button>
                <Button v-if="manageUrl" variant="surface" @click="openManagement">
                    Manage subscription
                    <ExternalLink :size="17" aria-hidden="true" />
                </Button>
                <Button variant="ghost" :disabled="refreshing" @click="refreshServer()">Refresh status</Button>
            </div>

            <p class="text-xs leading-relaxed text-muted-foreground">
                By subscribing you agree to the <a href="https://usebuff.app/terms/" target="_blank" rel="noopener noreferrer" class="text-link">Terms / EULA</a> and acknowledge the <a href="https://usebuff.app/privacy/" target="_blank" rel="noopener noreferrer" class="text-link">Privacy Policy</a>. Need help? Visit <a href="https://usebuff.app/support/" target="_blank" rel="noopener noreferrer" class="text-link">Buff Support</a>.
            </p>
        </template>
    </section>
</template>
