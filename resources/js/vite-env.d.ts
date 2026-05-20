/// <reference types="vite/client" />

declare module '#nativephp' {
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

interface Window {
    __buffHandleAndroidBack?: () => boolean;
}
