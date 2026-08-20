<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Camera, Dumbbell, Home, Pencil, Plus, Scale, ScanBarcode, Search, Settings, Target, X } from '@lucide/vue';
import { hapticImpact } from '../haptics';
import AppSheet from '../Components/AppSheet.vue';
import Button from '../Components/ui/button/Button.vue';

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
const isAddActive = computed(() => path.value === '/add' || addDrawerOpen.value);

function isActive(match: string) {
    return path.value === match;
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
    if (addDrawerOpen.value) {
        closeAddDrawer();

        return true;
    }

    if (window.history.length > 1) {
        window.history.back();

        return true;
    }

    return false;
}

function openAddMode(mode: string, extra: Record<string, string> = {}) {
    hapticImpact();
    closeDrawerImmediately();

    const params = new URLSearchParams({ mode, ...extra });
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
    <div class="app-shell flex bg-background sm:pl-64">
        <aside class="app-sidebar fixed inset-y-0 left-0 z-20 hidden w-64 border-r border-border/70 bg-card/75 px-4 py-5 backdrop-blur-xl sm:flex sm:flex-col">
            <div class="mb-8 px-2">
                <p class="page-title text-[1.65rem]">Buff</p>
            </div>

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
                    :variant="isAddActive ? 'default' : 'surface'"
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
                class="fixed inset-x-4 top-[calc(env(safe-area-inset-top,0px)+1rem)] z-50 mx-auto max-w-sm rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground shadow-card"
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
            <div class="mb-4 flex items-center justify-between">
                <h2 id="add-drawer-title" class="text-lg font-semibold">Add</h2>
                <Button variant="ghost" size="icon" class="rounded-full" aria-label="Close add drawer" @click="closeAddDrawer">
                    <X :size="20" />
                </Button>
            </div>

            <div class="grid gap-3">
                <div class="grid grid-cols-3 gap-2">
                    <Button variant="outline" class="h-auto w-full flex-col gap-2 rounded-2xl px-2 py-3" @click="openAddMode('food')">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-primary text-primary-foreground">
                            <Search :size="22" />
                        </span>
                        <span class="text-sm font-semibold">Search</span>
                    </Button>

                    <Button variant="outline" class="h-auto w-full flex-col gap-2 rounded-2xl px-2 py-3" @click="openAddMode('food', { scan: '1' })">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-primary text-primary-foreground">
                            <ScanBarcode :size="22" />
                        </span>
                        <span class="text-sm font-semibold">Scan</span>
                    </Button>

                    <Button variant="outline" class="h-auto w-full flex-col gap-2 rounded-2xl px-2 py-3" @click="openAddMode('custom')">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-food text-primary-foreground">
                            <Pencil :size="22" />
                        </span>
                        <span class="text-sm font-semibold">Custom</span>
                    </Button>
                </div>

                <Button variant="outline" class="h-auto justify-start rounded-2xl p-4 text-left" @click="openAddMode('workout')">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-workout text-primary-foreground">
                        <Dumbbell :size="22" />
                    </span>
                    <span>
                        <span class="block font-semibold">Workout</span>
                        <span class="block text-sm font-medium text-muted-foreground">Log calories burned</span>
                    </span>
                </Button>

                <Button variant="outline" class="h-auto justify-start rounded-2xl p-4 text-left" @click="openAddMode('photo')">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-food text-primary-foreground">
                        <Camera :size="22" />
                    </span>
                    <span>
                        <span class="block font-semibold">Photo meal</span>
                        <span class="block text-sm font-medium text-muted-foreground">Estimate editable macros</span>
                    </span>
                </Button>
            </div>
        </AppSheet>

        <nav class="bottom-nav fixed inset-x-3 bottom-3 z-20 rounded-2xl border border-border/70 bg-card/80 shadow-card backdrop-blur-xl sm:hidden">
            <div class="mx-auto grid max-w-md grid-cols-5 items-end gap-1 px-2 pt-1.5">
                <Button
                    :as="Link"
                    :href="navItems[0].href"
                    size="nav"
                    :variant="isActive(navItems[0].match) ? 'secondary' : 'ghost'"
                    class="flex rounded-xl"
                >
                    <component :is="navItems[0].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[0].label }}</span>
                </Button>

                <Button
                    :as="Link"
                    :href="navItems[1].href"
                    size="nav"
                    :variant="isActive(navItems[1].match) ? 'secondary' : 'ghost'"
                    class="flex rounded-xl"
                >
                    <component :is="navItems[1].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[1].label }}</span>
                </Button>

                <Button
                    size="icon"
                    variant="default"
                    class="relative z-10 mx-auto -mt-6 h-[3.6rem] w-[3.6rem] rounded-full border-[5px] border-card shadow-card active:scale-95"
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
                    class="flex rounded-xl"
                >
                    <component :is="navItems[2].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[2].label }}</span>
                </Button>

                <Button
                    :as="Link"
                    :href="navItems[3].href"
                    size="nav"
                    :variant="isActive(navItems[3].match) ? 'secondary' : 'ghost'"
                    class="flex rounded-xl"
                >
                    <component :is="navItems[3].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[3].label }}</span>
                </Button>
            </div>
        </nav>
    </div>
</template>
