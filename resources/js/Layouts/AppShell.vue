<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Dumbbell, Home, Pencil, Plus, Scale, Search, Settings, ScanBarcode, X, Target } from '@lucide/vue';
import { hapticImpact } from '../haptics';
import Button from '../Components/ui/button/Button.vue';

const page = usePage<{
    summary?: { date: string };
    flash?: { message?: string };
}>();
const addDrawerOpen = ref(false);
const drawerHistoryActive = ref(false);
const fallbackToast = ref('');
const toastTimer = ref<number | null>(null);
let removeFlashToastListener: (() => void) | null = null;

const navItems = [
    { href: '/', label: 'Home', icon: Home, match: '/' },
    { href: '/goals', label: 'Goals', icon: Target, match: '/goals' },
    { href: '/progress', label: 'Progress', icon: Scale, match: '/progress' },
    { href: '/settings', label: 'Settings', icon: Settings, match: '/settings' },
];

const path = computed(() => new URL(page.url, window.location.origin).pathname);
const isAddActive = computed(() => path.value === '/add');

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

function handleShortcutDrawerFlag() {
    const url = new URL(page.url, window.location.origin);

    if (url.searchParams.get('add') !== '1') {
        return;
    }

    openAddDrawer(false);
    url.searchParams.delete('add');
    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);
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
    handleShortcutDrawerFlag();

    showFlashToast(page.props.flash?.message);

    removeFlashToastListener = router.on('success', (event) => {
        const flash = event.detail.page.props.flash as { message?: string } | undefined;

        showFlashToast(flash?.message);
        handleShortcutDrawerFlag();
    });
});

onUnmounted(() => {
    window.removeEventListener('popstate', handlePopState);
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
    <div class="app-shell mx-auto flex max-w-md flex-col bg-background">
        <main class="app-main flex-1 px-4">
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

        <div
            v-if="addDrawerOpen"
            class="fixed inset-0 z-30 bg-foreground/30"
            @click="closeAddDrawer"
        />

        <section
            class="bottom-drawer fixed inset-x-0 bottom-0 z-40 mx-auto max-w-md transform rounded-t-lg bg-card px-4 pt-3 shadow-2xl transition-transform duration-200"
            :class="addDrawerOpen ? 'translate-y-0' : 'translate-y-full'"
            :aria-hidden="!addDrawerOpen"
            :inert="!addDrawerOpen"
            aria-label="Add"
        >
            <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-muted-foreground/35" />
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">Add</h2>
                <Button variant="ghost" size="icon" aria-label="Close add drawer" @click="closeAddDrawer">
                    <X :size="20" />
                </Button>
            </div>

            <div class="grid gap-3">
                <div class="grid grid-cols-2 gap-2">
                <Button variant="surface" class="h-auto justify-start p-4 text-left" @click="openAddMode('food')">
                    <span class="grid h-11 w-11 place-items-center rounded-md bg-primary text-primary-foreground">
                        <Search :size="22" />
                    </span>
                    <span>
                        <span class="block font-semibold">Search</span>
                    </span>
                </Button>

                <Button variant="surface" class="h-auto justify-start p-4 text-left" @click="openAddMode('food', { scan: '1' })">
                    <span class="grid h-11 w-11 place-items-center rounded-md bg-primary text-primary-foreground">
                        <ScanBarcode :size="22" />
                    </span>
                    <span>
                        <span class="block font-semibold">Scan</span>
                    </span>
                </Button>
                </div>

                <Button variant="surface" class="h-auto justify-start p-4 text-left" @click="openAddMode('custom')">
                    <span class="grid h-11 w-11 place-items-center rounded-md bg-food text-primary-foreground">
                        <Pencil :size="22" />
                    </span>
                    <span>
                        <span class="block font-semibold">Custom food</span>
                        <span class="block text-sm font-medium text-muted-foreground">Log your own macros</span>
                    </span>
                </Button>

                <Button variant="surface" class="h-auto justify-start p-4 text-left" @click="openAddMode('workout')">
                    <span class="grid h-11 w-11 place-items-center rounded-md bg-workout text-primary-foreground">
                        <Dumbbell :size="22" />
                    </span>
                    <span>
                        <span class="block font-semibold">Workout</span>
                        <span class="block text-sm font-medium text-muted-foreground">Log calories burned</span>
                    </span>
                </Button>
            </div>
        </section>

        <nav class="bottom-nav fixed inset-x-0 bottom-0 z-20 border-t border-border bg-card/95 shadow-lg backdrop-blur">
            <div class="mx-auto grid max-w-md grid-cols-5 gap-1 px-3 pt-2">
                <Button
                    :as="Link"
                    :href="navItems[0].href"
                    size="nav"
                    :variant="path === navItems[0].match ? 'secondary' : 'ghost'"
                    class="flex"
                >
                    <component :is="navItems[0].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[0].label }}</span>
                </Button>

                <Button
                    :as="Link"
                    :href="navItems[1].href"
                    size="nav"
                    :variant="path === navItems[1].match ? 'secondary' : 'ghost'"
                    class="flex"
                >
                    <component :is="navItems[1].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[1].label }}</span>
                </Button>

                <Button
                    size="icon"
                    :variant="isAddActive ? 'default' : 'default'"
                    class="relative z-10 mx-auto -mt-5 h-16 w-16 rounded-full border-4 border-card shadow-none active:translate-y-0.5 active:bg-primary"
                    aria-label="Add"
                    @click="openAddDrawer"
                >
                    <Plus :size="28" stroke-width="2.4" />
                </Button>

                <Button
                    :as="Link"
                    :href="navItems[2].href"
                    size="nav"
                    :variant="path === navItems[2].match ? 'secondary' : 'ghost'"
                    class="flex"
                >
                    <component :is="navItems[2].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[2].label }}</span>
                </Button>

                <Button
                    :as="Link"
                    :href="navItems[3].href"
                    size="nav"
                    :variant="path === navItems[3].match ? 'secondary' : 'ghost'"
                    class="flex"
                >
                    <component :is="navItems[3].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[3].label }}</span>
                </Button>
            </div>
        </nav>
    </div>
</template>
