<script setup lang="ts">
import { WifiOff } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import { isNavigatorOnline, subscribeToNetworkStatus } from '../networkStatus';

const online = ref(true);
let unsubscribe: (() => void) | null = null;

function setOnline(value: boolean): void {
    online.value = value;

    if (value) {
        delete document.documentElement.dataset.offline;

        return;
    }

    document.documentElement.dataset.offline = '';
}

onMounted(() => {
    setOnline(isNavigatorOnline());
    unsubscribe = subscribeToNetworkStatus(setOnline);
});

onUnmounted(() => {
    unsubscribe?.();
    unsubscribe = null;
    delete document.documentElement.dataset.offline;
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
        enter-from-class="-translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-out motion-reduce:transition-none"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="-translate-y-2 opacity-0"
    >
        <div
            v-if="!online"
            class="offline-banner pointer-events-none fixed inset-x-0 top-0 z-40 sm:hidden"
            role="status"
            aria-live="polite"
        >
            <div class="flex items-center justify-center gap-1.5 bg-warning-soft px-3 pb-1.5 pt-[calc(env(safe-area-inset-top,0px)+0.35rem)] text-[0.8125rem] font-medium text-warning-soft-foreground">
                <WifiOff :size="14" stroke-width="2.4" aria-hidden="true" />
                <span>No internet connection</span>
            </div>
        </div>
    </Transition>
</template>
