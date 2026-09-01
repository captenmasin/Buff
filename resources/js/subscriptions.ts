import subscriptionsBridge from '../../native-plugins/in-app-purchases/resources/js/in-app-purchases.js';

export type SubscriptionPlatform = 'ios' | 'android' | 'unsupported';
export type SubscriptionPackageKind = 'monthly' | 'annual';

export interface SubscriptionAccount {
    id?: string | number;
    revenuecat_app_user_id?: string | null;
    subscription?: {
        entitled?: boolean;
        expires_at?: string | null;
        management_url?: string | null;
        product_id?: string | null;
    } | null;
}

export interface SubscriptionPackage {
    kind: SubscriptionPackageKind;
    packageIdentifier: string;
    productIdentifier: string;
    localizedPrice: string;
    localizedPeriod: string;
    introductoryOffer: null | {
        localizedPrice: string;
        localizedPeriod: string;
        periodCount: number;
        paymentMode: string;
        isFreeTrial: boolean;
    };
}

export interface NativeSubscriptionEvent {
    app_user_id?: string;
    category?: string;
    entitled?: boolean;
    message?: string;
    package_identifier?: string;
    product_identifier?: string;
    packages?: unknown[];
}

export const subscriptionEvents = {
    configurationCompleted: 'Buff\\InAppPurchases\\Events\\ConfigurationCompleted',
    configurationFailed: 'Buff\\InAppPurchases\\Events\\ConfigurationFailed',
    offeringLoaded: 'Buff\\InAppPurchases\\Events\\OfferingLoaded',
    offeringFailed: 'Buff\\InAppPurchases\\Events\\OfferingFailed',
    purchaseCompleted: 'Buff\\InAppPurchases\\Events\\PurchaseCompleted',
    purchaseCancelled: 'Buff\\InAppPurchases\\Events\\PurchaseCancelled',
    purchasePending: 'Buff\\InAppPurchases\\Events\\PurchasePending',
    purchaseFailed: 'Buff\\InAppPurchases\\Events\\PurchaseFailed',
    restoreCompleted: 'Buff\\InAppPurchases\\Events\\RestoreCompleted',
    restoreFailed: 'Buff\\InAppPurchases\\Events\\RestoreFailed',
} as const;

const fallbackManagementUrls: Record<Exclude<SubscriptionPlatform, 'unsupported'>, string> = {
    ios: 'https://apps.apple.com/account/subscriptions',
    android: 'https://play.google.com/store/account/subscriptions',
};

export function isSubscriptionActive(expiresAt?: string | null, now = Date.now()): boolean {
    const expiry = expiresAt ? Date.parse(expiresAt) : Number.NaN;

    return Number.isFinite(expiry) && expiry > now;
}

export function subscriptionPackageButtonLabel(
    subscriptionPackage: Pick<SubscriptionPackage, 'kind' | 'productIdentifier'>,
    active: boolean,
    activeProductId?: string | null,
): string {
    if (!active) {
        return `Choose ${subscriptionPackage.kind}`;
    }

    return subscriptionPackage.productIdentifier === activeProductId
        ? 'Current plan active'
        : 'Manage subscription to change plan';
}

export function managementUrl(platform: SubscriptionPlatform, providerUrl?: string | null): string | null {
    if (providerUrl?.trim()) {
        return providerUrl;
    }

    return platform === 'unsupported' ? null : fallbackManagementUrls[platform];
}

export function normalizeNativePayload(payload: unknown): NativeSubscriptionEvent {
    if (typeof payload === 'string') {
        try {
            return normalizeNativePayload(JSON.parse(payload));
        } catch {
            return {};
        }
    }

    return payload !== null && typeof payload === 'object' ? payload as NativeSubscriptionEvent : {};
}

export function normalizeOffering(payload: unknown): SubscriptionPackage[] {
    const packages = normalizeNativePayload(payload).packages;

    if (!Array.isArray(packages)) {
        return [];
    }

    return packages.flatMap((candidate): SubscriptionPackage[] => {
        if (candidate === null || typeof candidate !== 'object') {
            return [];
        }

        const value = candidate as Record<string, unknown>;
        const packageIdentifier = stringValue(value.package_identifier);
        const productIdentifier = stringValue(value.product_identifier);
        const localizedPrice = stringValue(value.localized_price);
        const localizedPeriod = stringValue(value.localized_period);
        const kind = packageKind(packageIdentifier);

        if (!kind || !packageIdentifier || !productIdentifier || !localizedPrice || !localizedPeriod) {
            return [];
        }

        const introductory = value.introductory_offer;
        const introductoryOffer = introductory !== null && typeof introductory === 'object'
            ? {
                localizedPrice: stringValue((introductory as Record<string, unknown>).localized_price),
                localizedPeriod: stringValue((introductory as Record<string, unknown>).localized_period),
                periodCount: numberValue((introductory as Record<string, unknown>).period_count, 1),
                paymentMode: stringValue((introductory as Record<string, unknown>).payment_mode),
                isFreeTrial: (introductory as Record<string, unknown>).is_free_trial === true,
            }
            : null;

        return [{kind, packageIdentifier, productIdentifier, localizedPrice, localizedPeriod, introductoryOffer}];
    }).sort((left, right) => left.kind === right.kind ? 0 : left.kind === 'monthly' ? -1 : 1);
}

export function nativeError(payload: unknown, fallback: string): {category: string; message: string} {
    const event = normalizeNativePayload(payload);

    return {
        category: stringValue(event.category) || 'unknown',
        message: stringValue(event.message) || fallback,
    };
}

export async function subscriptionPlatform(): Promise<SubscriptionPlatform> {
    const mode = (import.meta as ImportMeta & {env?: ImportMetaEnv & {MODE?: string}}).env?.MODE;

    if (mode === 'ios' || mode === 'android') {
        return mode;
    }

    try {
        const {System} = await import('#nativephp');

        if (await System.isIos()) {
            return 'ios';
        }

        return await System.isAndroid() ? 'android' : 'unsupported';
    } catch {
        return 'unsupported';
    }
}

export async function configureSubscriptions(account?: SubscriptionAccount | null): Promise<{
    configured: boolean;
    platform: SubscriptionPlatform;
    reason?: 'missing_account' | 'missing_key' | 'unsupported';
}> {
    const platform = await subscriptionPlatform();
    const appUserId = account?.revenuecat_app_user_id;

    if (!appUserId) {
        return {configured: false, platform, reason: 'missing_account'};
    }

    if (platform === 'unsupported') {
        return {configured: false, platform, reason: 'unsupported'};
    }

    const env = (import.meta as ImportMeta & {env?: ImportMetaEnv}).env;
    const apiKey = platform === 'ios'
        ? env?.VITE_REVENUECAT_IOS_PUBLIC_SDK_KEY
        : env?.VITE_REVENUECAT_ANDROID_PUBLIC_SDK_KEY;

    if (!apiKey) {
        return {configured: false, platform, reason: 'missing_key'};
    }

    const nativeEvents = await import('#nativephp');
    await completeSubscriptionConfiguration(
        appUserId,
        () => subscriptionsBridge.configure(apiKey, appUserId),
        nativeEvents,
    );

    return {configured: true, platform};
}

interface NativeConfigurationResult {
    switching_account?: boolean;
}

interface NativeEventBridge {
    On(eventName: string, callback: (payload: unknown, eventName: string) => void): void;
    Off(eventName: string, callback: (payload: unknown, eventName: string) => void): void;
}

let subscriptionConfigurationTail: Promise<void> = Promise.resolve();

export function completeSubscriptionConfiguration(
    appUserId: string,
    configure: () => Promise<unknown>,
    nativeEvents: NativeEventBridge,
    timeoutMs = 30_000,
): Promise<void> {
    const configuration = subscriptionConfigurationTail
        .then(() => performSubscriptionConfiguration(appUserId, configure, nativeEvents, timeoutMs));

    subscriptionConfigurationTail = configuration.catch(() => undefined);

    return configuration;
}

function performSubscriptionConfiguration(
    appUserId: string,
    configure: () => Promise<unknown>,
    nativeEvents: NativeEventBridge,
    timeoutMs: number,
): Promise<void> {
    return new Promise((resolve, reject) => {
        let bridgeCompleted = false;
        let switchingAccount = false;
        let accountSwitchOutcome: Error | true | null = null;
        let settled = false;
        let timeout: ReturnType<typeof setTimeout> | null = null;

        const cleanup = () => {
            if (timeout !== null) {
                globalThis.clearTimeout(timeout);
            }

            nativeEvents.Off(subscriptionEvents.configurationCompleted, configurationCompleted);
            nativeEvents.Off(subscriptionEvents.configurationFailed, configurationFailed);
        };
        const fail = (error: unknown) => {
            if (settled) {
                return;
            }

            settled = true;
            cleanup();
            reject(error);
        };
        const finish = () => {
            if (settled || !bridgeCompleted) {
                return;
            }

            if (!switchingAccount) {
                settled = true;
                cleanup();
                resolve();
                return;
            }

            if (accountSwitchOutcome === null) {
                return;
            }

            if (accountSwitchOutcome instanceof Error) {
                fail(accountSwitchOutcome);
                return;
            }

            settled = true;
            cleanup();
            resolve();
        };
        const isExpectedAccount = (payload: unknown): boolean =>
            normalizeNativePayload(payload).app_user_id === appUserId;
        function configurationCompleted(payload: unknown): void {
            if (!isExpectedAccount(payload) || accountSwitchOutcome !== null) {
                return;
            }

            accountSwitchOutcome = true;
            finish();
        }
        function configurationFailed(payload: unknown): void {
            if (!isExpectedAccount(payload) || accountSwitchOutcome !== null) {
                return;
            }

            accountSwitchOutcome = new Error(nativeError(payload, 'Subscriptions could not switch accounts.').message);
            finish();
        }

        nativeEvents.On(subscriptionEvents.configurationCompleted, configurationCompleted);
        nativeEvents.On(subscriptionEvents.configurationFailed, configurationFailed);
        timeout = globalThis.setTimeout(
            () => fail(new Error('Subscriptions timed out while switching accounts.')),
            timeoutMs,
        );

        void Promise.resolve()
            .then(configure)
            .then((result) => {
                bridgeCompleted = true;
                switchingAccount = (result as NativeConfigurationResult | null)?.switching_account === true;
                finish();
            }, fail);
    });
}

export async function listenForSubscriptionEvents(
    handlers: Partial<Record<keyof typeof subscriptionEvents, (payload: NativeSubscriptionEvent) => void>>,
): Promise<() => void> {
    const {On, Off} = await import('#nativephp');
    const listeners = Object.entries(handlers).map(([name, handler]) => {
        const event = subscriptionEvents[name as keyof typeof subscriptionEvents];
        const listener = (payload: unknown) => handler?.(normalizeNativePayload(payload));

        On(event, listener);

        return {event, listener};
    });

    return () => listeners.forEach(({event, listener}) => Off(event, listener));
}

export const subscriptionNative = {
    loadOffering: subscriptionsBridge.loadOffering,
    purchase: subscriptionsBridge.purchase,
    restore: subscriptionsBridge.restore,
};

function packageKind(identifier: string): SubscriptionPackageKind | null {
    const normalized = identifier.toLowerCase();

    if (normalized.includes('monthly')) {
        return 'monthly';
    }

    return normalized.includes('annual') || normalized.includes('yearly') ? 'annual' : null;
}

function stringValue(value: unknown): string {
    return typeof value === 'string' ? value.trim() : '';
}

function numberValue(value: unknown, fallback: number): number {
    return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
}
