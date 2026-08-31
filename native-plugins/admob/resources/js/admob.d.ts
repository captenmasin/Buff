export type BannerPosition = 'bottom' | 'top';
export type ConsentStatus = 'required' | 'obtained' | 'not_required' | 'unknown';
export type PrivacyOptionsStatus = 'required' | 'not_required' | 'unknown';
export type TrackingStatus = 'authorized' | 'denied' | 'restricted' | 'notDetermined' | 'unsupported';

export interface CallResult {
    ok: boolean;
    error?: string | null;
}

export interface Policy {
    underAgeOfConsent: boolean;
    nonPersonalized: boolean;
    maxContentRating: 'T';
}

export interface ConsentResult extends CallResult {
    status?: ConsentStatus;
    canRequestAds?: boolean;
    privacyOptionsRequired?: boolean;
}

export interface BannerResult extends CallResult {
    height?: number;
}

export interface AdmobApi {
    enabled(): Promise<boolean>;
    configurePolicy(policy: Policy): Promise<CallResult>;
    initialize(): Promise<CallResult>;
    banner(slot: string): {
        load(): Promise<BannerResult>;
        show(position?: BannerPosition, offset?: number | null): Promise<CallResult>;
        hide(): Promise<CallResult>;
    };
    ump: {
        requestInfo(): Promise<ConsentResult>;
        showForm(): Promise<ConsentResult>;
        canRequestAds(): Promise<boolean>;
        status(): Promise<ConsentStatus>;
        privacyOptionsStatus(): Promise<PrivacyOptionsStatus>;
        showPrivacyOptionsForm(): Promise<ConsentResult>;
    };
    att: {
        request(): Promise<CallResult & {status?: TrackingStatus}>;
        status(): Promise<TrackingStatus>;
    };
}

export const Admob: AdmobApi;
export default Admob;
export function setEndpoint(url: string): void;
export const Events: Readonly<Record<string, string>>;
