type NativeBridge = {
    Device?: {
        vibrate?: () => Promise<unknown>;
    };
    Haptics?: {
        vibrate?: () => Promise<unknown>;
    };
};

export async function hapticImpact(duration = 25): Promise<void> {
    if (navigator.vibrate) {
        navigator.vibrate(duration);
    }

    try {
        const native = await import('#nativephp') as NativeBridge;

        await (native.Device?.vibrate?.() ?? native.Haptics?.vibrate?.());
    } catch {
        // Browser vibration is enough when the native bridge is unavailable.
    }
}
