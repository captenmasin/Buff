<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Barcode, Home, Plus, Scale, Utensils, X, Target } from '@lucide/vue';

const page = usePage();
const addDrawerOpen = ref(false);
const drawerHistoryActive = ref(false);

const navItems = [
    { href: '/', label: 'Today', icon: Home, match: '/' },
    { href: '/goals', label: 'Goals', icon: Target, match: '/goals' },
    { href: '/progress', label: 'Progress', icon: Scale, match: '/progress' },
];

const path = computed(() => new URL(page.url, window.location.origin).pathname);
const isAddActive = computed(() => path.value === '/add');

function openAddDrawer() {
    if (addDrawerOpen.value) {
        return;
    }

    window.history.pushState({ ...(window.history.state || {}), buffAddDrawer: true }, '');
    drawerHistoryActive.value = true;
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

function openAddMode(mode) {
    closeDrawerImmediately();

    const params = new URLSearchParams({ mode });
    const selectedDate = page.props.summary?.date;

    if (selectedDate) {
        params.set('date', selectedDate);
    }

    if (mode === 'barcode') {
        params.set('scan', '1');
    }

    router.visit(`/add?${params.toString()}`);
}

onMounted(() => {
    window.addEventListener('popstate', handlePopState);
    window.__buffHandleAndroidBack = handleNativeAndroidBack;
});

onUnmounted(() => {
    window.removeEventListener('popstate', handlePopState);

    if (window.__buffHandleAndroidBack === handleNativeAndroidBack) {
        delete window.__buffHandleAndroidBack;
    }
});
</script>

<template>
    <div class="app-shell mx-auto flex max-w-md flex-col bg-[#f6f7f4]">
        <main class="app-main flex-1 px-4">
            <div
                v-if="$page.props.flash?.message"
                class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800"
            >
                {{ $page.props.flash.message }}
            </div>

            <slot />
        </main>

        <div
            v-if="addDrawerOpen"
            class="fixed inset-0 z-30 bg-black/30"
            @click="closeAddDrawer"
        />

        <section
            class="bottom-drawer fixed inset-x-0 bottom-0 z-40 mx-auto max-w-md transform rounded-t-lg bg-white px-4 pt-3 shadow-[0_-18px_50px_rgba(23,33,27,0.22)] transition-transform duration-200"
            :class="addDrawerOpen ? 'translate-y-0' : 'translate-y-full'"
            :aria-hidden="!addDrawerOpen"
            :inert="!addDrawerOpen"
            aria-label="Add meal"
        >
            <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-stone-300" />
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold">Add meal</h2>
                <button class="rounded-md p-2 text-stone-500 active:bg-stone-100" aria-label="Close add meal drawer" @click="closeAddDrawer">
                    <X :size="20" />
                </button>
            </div>

            <div class="grid gap-3">
                <button class="flex items-center gap-3 rounded-md border border-stone-200 bg-stone-50 p-4 text-left active:bg-stone-100" @click="openAddMode('barcode')">
                    <span class="grid h-11 w-11 place-items-center rounded-md bg-[#253d2c] text-white">
                        <Barcode :size="22" />
                    </span>
                    <span>
                        <span class="block font-bold">Barcode</span>
                        <span class="block text-sm font-medium text-stone-500">Open scanner</span>
                    </span>
                </button>

                <button class="flex items-center gap-3 rounded-md border border-stone-200 bg-stone-50 p-4 text-left active:bg-stone-100" @click="openAddMode('custom')">
                    <span class="grid h-11 w-11 place-items-center rounded-md bg-[#d28a45] text-white">
                        <Utensils :size="22" />
                    </span>
                    <span>
                        <span class="block font-bold">Custom meal</span>
                        <span class="block text-sm font-medium text-stone-500">Enter macros</span>
                    </span>
                </button>
            </div>
        </section>

        <nav class="bottom-nav fixed inset-x-0 bottom-0 z-20 border-t border-stone-200 bg-white/95 shadow-[0_-10px_28px_rgba(23,33,27,0.08)] backdrop-blur">
            <div class="mx-auto grid max-w-md grid-cols-4 gap-1 px-3 pt-2">
                <Link
                    :href="navItems[0].href"
                    class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-md text-[11px] font-semibold transition"
                    :class="path === navItems[0].match ? 'bg-[#dce8d4] text-[#17211b]' : 'text-stone-500 active:bg-stone-100'"
                >
                    <component :is="navItems[0].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[0].label }}</span>
                </Link>

                <Link
                    :href="navItems[1].href"
                    class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-md text-[11px] font-semibold transition"
                    :class="path === navItems[1].match ? 'bg-[#dce8d4] text-[#17211b]' : 'text-stone-500 active:bg-stone-100'"
                >
                    <component :is="navItems[1].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[1].label }}</span>
                </Link>

                <Link
                    :href="navItems[2].href"
                    class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-md text-[11px] font-semibold transition"
                    :class="path === navItems[2].match ? 'bg-[#dce8d4] text-[#17211b]' : 'text-stone-500 active:bg-stone-100'"
                >
                    <component :is="navItems[2].icon" :size="20" stroke-width="2.2" />
                    <span>{{ navItems[2].label }}</span>
                </Link>

                <button
                    class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-md text-[11px] font-semibold transition active:bg-stone-100"
                    :class="isAddActive ? 'bg-[#dce8d4] text-[#17211b]' : 'text-stone-500'"
                    aria-label="Add meal"
                    @click="openAddDrawer"
                >
                    <span class="grid h-7 w-7 place-items-center rounded-full bg-[#253d2c] text-white">
                        <Plus :size="20" stroke-width="2.5" />
                    </span>
                    <span>Add</span>
                </button>
            </div>
        </nav>
    </div>
</template>
