<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Home, Plus, Scale, Settings, Target } from '@lucide/vue';
import Button from '../Components/ui/button/Button.vue';

const page = usePage<{
    summary?: { date: string };
    flash?: { message?: string };
    buff: {
        needs_sign_in: boolean;
    };
}>();
const fallbackToast = ref('');
const toastTimer = ref<number | null>(null);
let removeFlashToastListener: (() => void) | null = null;
let syncInProgress = false;

const navItems = [
    { href: '/', label: 'Home', icon: Home, match: '/' },
    { href: '/goals', label: 'Goals', icon: Target, match: '/goals' },
    { href: '/progress', label: 'Progress', icon: Scale, match: '/progress' },
    { href: '/settings', label: 'Settings', icon: Settings, match: '/settings' },
];

const path = computed(() => new URL(page.url, window.location.origin).pathname);
const isAddActive = computed(() => path.value === '/add');
const addHref = computed(() => page.props.summary?.date ? `/add?date=${encodeURIComponent(page.props.summary.date)}` : '/add');

function isActive(match: string) {
    return path.value === match;
}

function handleNativeAndroidBack() {
    if (window.history.length > 1) {
        window.history.back();

        return true;
    }

    return false;
}

function clearFallbackToast() {
    if (toastTimer.value) {
        window.clearTimeout(toastTimer.value);
        toastTimer.value = null;
    }

    fallbackToast.value = '';
}

function showFallbackToast(message: string) {
    clearFallbackToast();

    fallbackToast.value = message;
    toastTimer.value = window.setTimeout(() => {
        fallbackToast.value = '';
        toastTimer.value = null;
    }, 4000);
}

function handleToast(event: Event) {
    showFlashToast((event as CustomEvent<string>).detail);
}

async function syncOnResume() {
    if (page.props.buff.needs_sign_in || !navigator.onLine || syncInProgress) {
        return;
    }

    syncInProgress = true;

    try {
        await axios.post('/sync/resume');
        router.reload();
    } catch {
        router.reload({only: ['buff']});
    } finally {
        syncInProgress = false;
    }
}

function handleVisibilityChange() {
    if (document.visibilityState === 'visible') {
        syncOnResume();
    }
}

async function showFlashToast(message?: string) {
    if (!message) {
        return;
    }

    try {
        const native = await import('#nativephp');
        await native.Dialog.toast(message, 'long');
    } catch {
        showFallbackToast(message);
    }
}

onMounted(() => {
    window.__buffHandleAndroidBack = handleNativeAndroidBack;
    syncOnResume();
    window.addEventListener('focus', syncOnResume);
    window.addEventListener('online', syncOnResume);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('buff:toast', handleToast);

    showFlashToast(page.props.flash?.message);

    removeFlashToastListener = router.on('success', (event) => {
        const flash = event.detail.page.props.flash as { message?: string } | undefined;

        showFlashToast(flash?.message);
    });
});

onUnmounted(() => {
    window.removeEventListener('focus', syncOnResume);
    window.removeEventListener('online', syncOnResume);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('buff:toast', handleToast);
    clearFallbackToast();

    if (window.__buffHandleAndroidBack === handleNativeAndroidBack) {
        delete window.__buffHandleAndroidBack;
    }

    if (removeFlashToastListener) {
        removeFlashToastListener();
        removeFlashToastListener = null;
    }
});
</script>

<template>
    <div class="app-shell flex bg-background sm:pl-64">
        <aside class="app-sidebar fixed inset-y-0 left-0 z-20 hidden w-64 border-r border-border bg-card/80 px-4 py-5 backdrop-blur sm:flex sm:flex-col">
            <div class="mb-6 px-2">
                <p class="text-sm text-muted-foreground">Buff</p>
                <p class="text-2xl font-semibold text-foreground">Daily log</p>
            </div>

            <nav class="grid gap-1" aria-label="Primary">
                <Button
                    v-for="item in navItems"
                    :key="item.href"
                    :as="Link"
                    :href="item.href"
                    :variant="isActive(item.match) ? 'secondary' : 'ghost'"
                    class="h-11 justify-start px-3 text-sm"
                >
                    <component :is="item.icon" :size="19" stroke-width="2.2" />
                    <span>{{ item.label }}</span>
                </Button>

                <Button
                    :as="Link"
                    :href="addHref"
                    :variant="isAddActive ? 'default' : 'surface'"
                    class="mt-3 h-11 justify-start px-3 text-sm"
                    aria-label="Add"
                >
                    <Plus :size="19" stroke-width="2.4" />
                    <span>Add</span>
                </Button>
            </nav>
        </aside>

        <main class="app-main mx-auto w-full max-w-md flex-1 px-4 sm:max-w-3xl sm:px-6 lg:max-w-5xl lg:px-8">
            <slot />
        </main>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-3 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-3 opacity-0"
        >
            <div
                v-if="fallbackToast"
                class="fixed inset-x-4 top-[calc(env(safe-area-inset-top,0px)+1rem)] z-50 mx-auto max-w-sm rounded-md bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground shadow-xl"
                role="status"
                aria-live="polite"
            >
                {{ fallbackToast }}
            </div>
        </Transition>

        <nav class="bottom-nav fixed inset-x-0 bottom-0 z-20 border-t border-border bg-card/95 shadow-lg backdrop-blur sm:hidden">
            <div class="mx-auto grid max-w-md grid-cols-5 gap-1 px-3 pt-2">
                <Button
                    :as="Link"
                    :href="navItems[0].href"
                    size="nav"
                    :variant="isActive(navItems[0].match) ? 'secondary' : 'ghost'"
                    class="flex"
                >
                    <component :is="navItems[0].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[0].label }}</span>
                </Button>

                <Button
                    :as="Link"
                    :href="navItems[1].href"
                    size="nav"
                    :variant="isActive(navItems[1].match) ? 'secondary' : 'ghost'"
                    class="flex"
                >
                    <component :is="navItems[1].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[1].label }}</span>
                </Button>

                <Button
                    :as="Link"
                    :href="addHref"
                    size="icon"
                    :variant="isAddActive ? 'default' : 'default'"
                    class="relative z-10 mx-auto -mt-5 h-16 w-16 rounded-full border-4 border-card shadow-none active:translate-y-0.5 active:bg-primary"
                    aria-label="Add"
                >
                    <Plus :size="28" stroke-width="2.4" />
                </Button>

                <Button
                    :as="Link"
                    :href="navItems[2].href"
                    size="nav"
                    :variant="isActive(navItems[2].match) ? 'secondary' : 'ghost'"
                    class="flex"
                >
                    <component :is="navItems[2].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[2].label }}</span>
                </Button>

                <Button
                    :as="Link"
                    :href="navItems[3].href"
                    size="nav"
                    :variant="isActive(navItems[3].match) ? 'secondary' : 'ghost'"
                    class="flex"
                >
                    <component :is="navItems[3].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[3].label }}</span>
                </Button>
            </div>
        </nav>
    </div>
</template>
