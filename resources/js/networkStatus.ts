type NetworkNavigator = {
    onLine?: boolean;
};

export function isNavigatorOnline(nav: NetworkNavigator | undefined = globalThis.navigator): boolean {
    return nav?.onLine !== false;
}

export function subscribeToNetworkStatus(listener: (online: boolean) => void): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    const handleOnline = (): void => {
        listener(true);
    };
    const handleOffline = (): void => {
        listener(false);
    };
    const handleVisibility = (): void => {
        if (document.visibilityState === 'visible') {
            listener(isNavigatorOnline());
        }
    };

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    document.addEventListener('visibilitychange', handleVisibility);

    return () => {
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
        document.removeEventListener('visibilitychange', handleVisibility);
    };
}
