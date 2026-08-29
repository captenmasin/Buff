import subscriptionsBridge from '../../native-plugins/in-app-purchases/resources/js/in-app-purchases.js';

export type SubscriptionPlatform = 'ios' | 'android' | 'unsupported';
export type SubscriptionPackageKind = 'monthly' | 'annual';

export interface SubscriptionAccount {
    revenuecat_app_user_id?: string | null;
    subscription?: {
        expires_at?: string | null;
        management_url?: string | null;
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
    category?: string;
    entitled?: boolean;
    message?: string;
    package_identifier?: string;
    product_identifier?: string;
    packages?: unknown[];
}

export const subscriptionEvents = {
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

    await subscriptionsBridge.configure(apiKey, appUserId);

    return {configured: true, platform};
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
