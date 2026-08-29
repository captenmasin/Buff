/// <reference types="vite/client" />

declare module '#nativephp' {
    export const Browser: {
        auth(url: string): Promise<boolean>;
        inApp(url: string): Promise<boolean>;
        open(url: string): Promise<boolean>;
    };

    export const System: {
        isAndroid(): Promise<boolean>;
        isIos(): Promise<boolean>;
        isMobile(): Promise<boolean>;
    };

    export const Dialog: {
        toast(message: string, duration?: string): Promise<{ success: boolean }>;
    };

    export const Scanner: {
        scan(): {
            prompt(message: string): ReturnType<typeof Scanner.scan>;
            formats(formats: string[]): ReturnType<typeof Scanner.scan>;
            id(id: string): Promise<void>;
        };
    };

    export function On(eventName: string, callback: (payload: unknown, eventName: string) => void): void;

    export function Off(eventName: string, callback: (payload: unknown, eventName: string) => void): void;

    export const Events: {
        Scanner: {
            CodeScanned: string;
        };
    };
}

interface ImportMetaEnv {
    readonly VITE_REVENUECAT_IOS_PUBLIC_SDK_KEY?: string;
    readonly VITE_REVENUECAT_ANDROID_PUBLIC_SDK_KEY?: string;
}

interface Window {
    __buffHandleAndroidBack?: () => boolean;
}
