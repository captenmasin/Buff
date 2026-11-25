export function nativeRuntimeAvailable(): boolean {
    return typeof document !== 'undefined' && document.querySelector('meta[name="buff-native-runtime"]') !== null;
}
