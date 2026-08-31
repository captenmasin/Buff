import axios from 'axios';
import type {AdmobApi, Policy} from '../../native-plugins/admob/resources/js/admob.js';
import {subscriptionPlatform, type SubscriptionAccount, type SubscriptionPlatform} from './subscriptions.ts';

export type AdAudience = 'adult' | 'teen';

export interface AdReconciliation {
    account?: SubscriptionAccount | null;
    url: string;
    audience: AdAudience;
    bottomOffset: number | null;
}

export interface AdCoordinatorDependencies {
    platform?: () => Promise<SubscriptionPlatform>;
    loadBridge?: () => Promise<AdmobApi>;
    refreshSubscription?: () => Promise<unknown>;
    setBannerHeight: (height: number) => void;
}

const adRoutes = new Set(['/', '/goals', '/progress']);
let nativeOperationTail = Promise.resolve();

function serializeNativeOperation<T>(operation: () => Promise<T>): Promise<T> {
    const result = nativeOperationTail.catch(() => undefined).then(operation);

    nativeOperationTail = result.then(() => undefined, () => undefined);

    return result;
}

export function isAdRoute(url: string): boolean {
    try {
        return adRoutes.has(new URL(url, 'https://buff.local').pathname);
    } catch {
        return false;
    }
}

async function loadAdmob(): Promise<AdmobApi> {
    const {Admob} = await import('../../native-plugins/admob/resources/js/admob.js');

    return Admob;
}

export async function hideAppShellBanner(
    dependencies: Pick<AdCoordinatorDependencies, 'platform' | 'loadBridge'> = {},
): Promise<void> {
    const platform = dependencies.platform ?? subscriptionPlatform;
    const bridgeLoader = dependencies.loadBridge ?? loadAdmob;

    try {
        if (await platform() === 'unsupported') {
            return;
        }

        await serializeNativeOperation(async () => {
            await (await bridgeLoader()).banner('app_shell').hide();
        });
    } catch {
        // Auth screens remain usable if the native bridge is unavailable.
    }
}

async function refreshSubscription(): Promise<unknown> {
    const response = await axios.post('/subscription/refresh', {}, {timeout: 10_000});

    return response.data;
}

function accountId(account?: SubscriptionAccount | null): string | null {
    const id = account?.id ?? account?.revenuecat_app_user_id;

    return id === undefined || id === null || id === '' ? null : String(id);
}

function isExplicitlyNonEntitled(result: unknown): boolean {
    if (result === null || typeof result !== 'object') {
        return false;
    }

    const data = (result as {data?: unknown}).data;

    return data !== null
        && typeof data === 'object'
        && (data as {subscription?: {entitled?: unknown}}).subscription?.entitled === false;
}

export function createAdCoordinator(dependencies: AdCoordinatorDependencies) {
    const platform = dependencies.platform ?? subscriptionPlatform;
    const bridgeLoader = dependencies.loadBridge ?? loadAdmob;
    const refresh = dependencies.refreshSubscription ?? refreshSubscription;
    let bridge: AdmobApi | null = null;
    let initialized = false;
    let activeAccountId: string | null = null;
    let generation = 0;
    let inFlight: {key: string; promise: Promise<void>} | null = null;
    let operationTail = Promise.resolve();
    let destroyed = false;

    async function hide(): Promise<void> {
        dependencies.setBannerHeight(0);

        if (bridge) {
            await bridge.banner('app_shell').hide().catch(() => undefined);
        }
    }

    function isCurrent(expectedGeneration: number, expectedAccountId: string): boolean {
        return generation === expectedGeneration && activeAccountId === expectedAccountId;
    }

    async function showEligibleBanner(
        input: AdReconciliation,
        nativePlatform: Exclude<SubscriptionPlatform, 'unsupported'>,
        expectedGeneration: number,
        expectedAccountId: string,
    ): Promise<void> {
        bridge ??= await bridgeLoader();

        if (!isCurrent(expectedGeneration, expectedAccountId)) {
            await hide();
            return;
        }

        if (!await bridge.enabled()) {
            await hide();
            return;
        }

        const underAge = input.audience !== 'adult';
        let policy: Policy = {
            underAgeOfConsent: underAge,
            nonPersonalized: underAge,
            maxContentRating: 'T',
        };

        if (!(await bridge.configurePolicy(policy)).ok) {
            await hide();
            return;
        }

        let consent = await bridge.ump.requestInfo();

        if (consent.ok && consent.status === 'required') {
            consent = await bridge.ump.showForm();
        }

        if (
            !isCurrent(expectedGeneration, expectedAccountId)
            || !consent.ok
            || consent.canRequestAds !== true
            || (consent.status !== 'obtained' && consent.status !== 'not_required')
        ) {
            await hide();
            return;
        }

        if (!underAge && nativePlatform === 'ios') {
            let trackingStatus = 'unsupported';

            try {
                trackingStatus = await bridge.att.status();

                if (trackingStatus === 'notDetermined') {
                    const tracking = await bridge.att.request();
                    trackingStatus = tracking.status ?? 'unsupported';
                }
            } catch {
                trackingStatus = 'unsupported';
            }

            policy = {...policy, nonPersonalized: trackingStatus !== 'authorized'};
        }

        if (!isCurrent(expectedGeneration, expectedAccountId) || !(await bridge.configurePolicy(policy)).ok) {
            await hide();
            return;
        }

        if (!initialized) {
            const initialization = await bridge.initialize();

            if (!initialization.ok) {
                await hide();
                return;
            }

            initialized = true;
        }

        if (!isCurrent(expectedGeneration, expectedAccountId)) {
            await hide();
            return;
        }

        const banner = bridge.banner('app_shell');
        const loaded = await banner.load();
        const height = Number(loaded.height);

        if (!loaded.ok || !Number.isFinite(height) || height <= 0 || !isCurrent(expectedGeneration, expectedAccountId)) {
            await hide();
            return;
        }

        dependencies.setBannerHeight(Math.ceil(height));

        if (!(await banner.show('bottom', input.bottomOffset)).ok || !isCurrent(expectedGeneration, expectedAccountId)) {
            await hide();
        }
    }

    async function run(
        input: AdReconciliation,
        nativePlatform: Exclude<SubscriptionPlatform, 'unsupported'>,
        expectedGeneration: number,
        expectedAccountId: string,
    ): Promise<void> {
        try {
            await hide();
            const refreshed = await refresh();

            if (!isCurrent(expectedGeneration, expectedAccountId) || !isExplicitlyNonEntitled(refreshed)) {
                await hide();
                return;
            }

            await serializeNativeOperation(async () => {
                if (!isCurrent(expectedGeneration, expectedAccountId)) {
                    await hide();
                    return;
                }

                await showEligibleBanner(input, nativePlatform, expectedGeneration, expectedAccountId);
            });
        } catch {
            await hide();
        }
    }

    async function reconcile(input: AdReconciliation): Promise<void> {
        if (destroyed) {
            return;
        }

        const nextAccountId = accountId(input.account);

        if (!nextAccountId || !isAdRoute(input.url)) {
            generation++;
            activeAccountId = nextAccountId;
            inFlight = null;
            await hide();
            return;
        }

        const nativePlatform = await platform();

        if (nativePlatform === 'unsupported') {
            generation++;
            activeAccountId = nextAccountId;
            inFlight = null;
            await hide();
            return;
        }

        const accountChanged = activeAccountId !== nextAccountId;
        const reconciliationKey = JSON.stringify([nextAccountId, input.audience, input.bottomOffset]);

        if (accountChanged) {
            generation++;
            activeAccountId = nextAccountId;
            void hide();
        }

        if (inFlight?.key === reconciliationKey) {
            return inFlight.promise;
        }

        const expectedGeneration = ++generation;
        const previousOperation = operationTail;
        const promise = previousOperation.catch(() => undefined).then(async () => {
            if (isCurrent(expectedGeneration, nextAccountId)) {
                await run(input, nativePlatform, expectedGeneration, nextAccountId);
            }
        });
        operationTail = promise;
        inFlight = {key: reconciliationKey, promise};

        try {
            await promise;
        } finally {
            if (inFlight?.promise === promise) {
                inFlight = null;
            }
        }
    }

    async function beforeNavigation(url: string): Promise<void> {
        if (!destroyed && !isAdRoute(url)) {
            generation++;
            inFlight = null;
            await hide();
        }
    }

    async function destroy(): Promise<void> {
        destroyed = true;
        generation++;
        activeAccountId = null;
        inFlight = null;
        await hide();
    }

    return {reconcile, beforeNavigation, destroy};
}

export async function adPrivacyOptionsRequired(audience: AdAudience): Promise<boolean> {
    try {
        if (await subscriptionPlatform() === 'unsupported') {
            return false;
        }

        return await serializeNativeOperation(async () => {
            const bridge = await loadAdmob();
            const underAge = audience !== 'adult';
            const configured = await bridge.configurePolicy({
                underAgeOfConsent: underAge,
                nonPersonalized: underAge,
                maxContentRating: 'T',
            });

            if (!configured.ok) {
                return false;
            }

            const consent = await bridge.ump.requestInfo();

            return consent.ok && consent.privacyOptionsRequired === true;
        });
    } catch {
        return false;
    }
}

export async function showAdPrivacyOptions(): Promise<boolean> {
    try {
        if (await subscriptionPlatform() === 'unsupported') {
            return false;
        }

        return await serializeNativeOperation(async () => (
            await (await loadAdmob()).ump.showPrivacyOptionsForm()
        ).ok);
    } catch {
        return false;
    }
}
