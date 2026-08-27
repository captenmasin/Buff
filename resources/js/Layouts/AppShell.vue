<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Home, Plus, Scale, Settings, Target, X } from '@lucide/vue';
import { hapticImpact } from '../haptics';
import AddChooser from '../Components/Add/AddChooser.vue';
import AppSheet from '../Components/AppSheet.vue';
import OfflineBanner from '../Components/OfflineBanner.vue';
import Button from '../Components/ui/button/Button.vue';
import { publicAssetUrl } from '../publicAssetUrl';

const page = usePage<{
    summary?: { date: string };
    flash?: { message?: string };
    buff: {
        needs_sign_in: boolean;
    };
}>();
const addDrawerOpen = ref(false);
const drawerHistoryActive = ref(false);
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

const isSettingsSubpage = computed(() => path.value.startsWith('/settings/'));

function isActive(match: string) {
    if (match === '/') {
        return path.value === '/';
    }

    return path.value === match || path.value.startsWith(`${match}/`);
}

function openAddDrawer(pushHistory = true) {
    if (addDrawerOpen.value) {
        return;
    }

    hapticImpact();

    if (pushHistory) {
        window.history.pushState({ ...(window.history.state || {}), buffAddDrawer: true }, '');
        drawerHistoryActive.value = true;
    }

    addDrawerOpen.value = true;
}

function closeDrawerImmediately() {
    addDrawerOpen.value = false;
    drawerHistoryActive.value = false;
}

function closeAddDrawer() {
    if (drawerHistoryActive.value) {
        closeDrawerImmediately();
        window.history.back();

        return;
    }

    closeDrawerImmediately();
}

function handlePopState() {
    if (!addDrawerOpen.value) {
        return;
    }

    closeDrawerImmediately();
}

function handleNativeAndroidBack() {
    const event = new Event('buff:android-back', { cancelable: true });
    window.dispatchEvent(event);

    if (event.defaultPrevented) {
        return true;
    }

    if (addDrawerOpen.value) {
        closeAddDrawer();

        return true;
    }

    if (isSettingsSubpage.value) {
        router.visit('/settings', { replace: true });

        return true;
    }

    if (window.history.length > 1) {
        window.history.back();

        return true;
    }

    return false;
}

function openAddMode(mode: string, extra?: Record<string, string>) {
    hapticImpact();
    closeDrawerImmediately();

    const params = new URLSearchParams({ mode, ...(extra ?? {}) });
    const selectedDate = page.props.summary?.date;

    if (selectedDate) {
        params.set('date', selectedDate);
    }

    router.visit(`/add?${params.toString()}`);
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
    window.addEventListener('popstate', handlePopState);
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
    window.removeEventListener('popstate', handlePopState);
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
    <div class="app-shell flex bg-background sm:pl-64" :class="{'settings-subpage': isSettingsSubpage}">
        <OfflineBanner />

        <aside class="app-sidebar fixed inset-y-0 left-0 z-20 hidden w-64 border-r border-border/70 bg-card/75 px-4 py-5 backdrop-blur-xl sm:flex sm:flex-col">
            <Link href="/" class="mb-8 px-2" aria-label="Buff home">
                    <img :src="publicAssetUrl('/logo.svg')" alt="Buff" class="h-auto w-32 dark:hidden" />
                    <img :src="publicAssetUrl('/logo-dark.svg')" alt="Buff" class="hidden h-auto w-32 dark:block" />
            </Link>

            <nav class="grid gap-1" aria-label="Primary">
                <Button
                    v-for="item in navItems"
                    :key="item.href"
                    :as="Link"
                    :href="item.href"
                    :variant="isActive(item.match) ? 'secondary' : 'ghost'"
                    class="h-11 justify-start rounded-xl px-3 text-sm"
                >
                    <component :is="item.icon" :size="19" stroke-width="2.2" />
                    <span>{{ item.label }}</span>
                </Button>

                <Button
                    variant="default"
                    class="mt-4 h-11 justify-start px-3 text-sm"
                    aria-label="Add"
                    @click="openAddDrawer()"
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
            leave-active-class="transition duration-150 ease-out"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-3 opacity-0"
        >
            <div
                v-if="fallbackToast"
                class="fallback-toast fixed inset-x-4 top-[calc(env(safe-area-inset-top,0px)+1rem)] z-50 mx-auto max-w-sm rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground shadow-card"
                role="status"
                aria-live="polite"
            >
                {{ fallbackToast }}
            </div>
        </Transition>

        <AppSheet
            :open="addDrawerOpen"
            labelled-by="add-drawer-title"
            variant="drawer"
            class="bottom-drawer"
            @close="closeAddDrawer"
        >
            <div class="mb-5 flex items-center justify-between">
                <h2 id="add-drawer-title" class="card-title">Add</h2>
                <Button variant="surface" size="icon" class="rounded-full" aria-label="Close add drawer" @click="closeAddDrawer">
                    <X :size="20" />
                </Button>
            </div>

            <AddChooser @select="openAddMode" />
        </AppSheet>

        <nav v-if="!isSettingsSubpage" class="bottom-nav fixed inset-x-0 bottom-0 z-20 border-t border-border/70 bg-card/50 shadow-card backdrop-blur-lg sm:hidden">
            <div class="mx-auto grid grid-cols-5 items-end gap-1 px-2 pt-1.5">
                <Button
                    :as="Link"
                    :href="navItems[0].href"
                    size="nav"
                    :variant="isActive(navItems[0].match) ? 'secondary' : 'ghost'"
                    :class="isActive(navItems[0].match) ? 'bg-primary dark:text-primary-foreground' : ''"
                    class="flex rounded-xl font-normal"
                >
                    <component :is="navItems[0].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[0].label }}</span>
                </Button>

                <Button
                    :as="Link"
                    :href="navItems[1].href"
                    size="nav"
                    :variant="isActive(navItems[1].match) ? 'secondary' : 'ghost'"
                    :class="isActive(navItems[1].match) ? 'bg-primary dark:text-primary-foreground' : ''"
                    class="flex rounded-xl font-normal"
                >
                    <component :is="navItems[1].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[1].label }}</span>
                </Button>

                <Button
                    size="icon"
                    variant="default"
                    class="relative z-10 mx-auto -translate-y-1 h-[3rem] w-[3rem] rounded-full border-0 border-card active:scale-95"
                    aria-label="Add"
                    @click="openAddDrawer()"
                >
                    <Plus :size="26" stroke-width="2.4" />
                </Button>

                <Button
                    :as="Link"
                    :href="navItems[2].href"
                    size="nav"
                    :variant="isActive(navItems[2].match) ? 'secondary' : 'ghost'"
                    :class="isActive(navItems[2].match) ? 'bg-primary dark:text-primary-foreground' : ''"
                    class="flex rounded-xl font-normal"
                >
                    <component :is="navItems[2].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[2].label }}</span>
                </Button>

                <Button
                    :as="Link"
                    :href="navItems[3].href"
                    size="nav"
                    :variant="isActive(navItems[3].match) ? 'secondary' : 'ghost'"
                    :class="isActive(navItems[3].match) ? 'bg-primary dark:text-primary-foreground' : ''"
                    class="flex rounded-xl font-normal"
                >
                    <component :is="navItems[3].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[3].label }}</span>
                </Button>
            </div>
        </nav>
    </div>
</template>
