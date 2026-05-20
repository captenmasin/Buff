<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Dumbbell, Home, Plus, Scale, Utensils, X, Target } from '@lucide/vue';
import { hapticImpact } from '../haptics';

const page = usePage();
const addDrawerOpen = ref(false);
const drawerHistoryActive = ref(false);
const fallbackToast = ref('');
const toastTimer = ref(null);
let removeFlashToastListener = null;
let foodGoalReminderTimer = null;

const foodGoalReminderStorageKey = 'buff.foodGoalReminder';

const navItems = [
    { href: '/', label: 'Home', icon: Home, match: '/' },
    { href: '/goals', label: 'Goals', icon: Target, match: '/goals' },
    { href: '/progress', label: 'Progress', icon: Scale, match: '/progress' },
];

const path = computed(() => new URL(page.url, window.location.origin).pathname);
const isAddActive = computed(() => path.value === '/add');

function openAddDrawer() {
    if (addDrawerOpen.value) {
        return;
    }

    hapticImpact();
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
    hapticImpact();
    closeDrawerImmediately();

    const params = new URLSearchParams({ mode });
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

function showFallbackToast(message) {
    clearFallbackToast();

    fallbackToast.value = message;
    toastTimer.value = window.setTimeout(() => {
        fallbackToast.value = '';
        toastTimer.value = null;
    }, 4000);
}

async function showFlashToast(message) {
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

function foodGoalReminderSettings() {
    try {
        return JSON.parse(window.localStorage.getItem(foodGoalReminderStorageKey) || '{}');
    } catch {
        return {};
    }
}

function nextReminderDelay(time) {
    const [hours, minutes] = String(time || '20:00').split(':').map(Number);
    const next = new Date();

    next.setHours(Number.isFinite(hours) ? hours : 20, Number.isFinite(minutes) ? minutes : 0, 0, 0);

    if (next <= new Date()) {
        next.setDate(next.getDate() + 1);
    }

    return next.getTime() - Date.now();
}

function sendFoodGoalReminder() {
    const title = 'Complete your food goals';
    const body = 'Open Buff and finish today\'s food log.';

    if ('Notification' in window && Notification.permission === 'granted') {
        const notification = new Notification(title, {
            body,
            tag: 'buff-food-goals',
        });

        notification.onclick = () => {
            window.focus();
            router.visit('/');
            notification.close();
        };
    } else {
        showFallbackToast(body);
    }
}

function scheduleFoodGoalReminder() {
    if (foodGoalReminderTimer) {
        window.clearTimeout(foodGoalReminderTimer);
        foodGoalReminderTimer = null;
    }

    const settings = foodGoalReminderSettings();

    if (!settings.enabled) {
        return;
    }

    foodGoalReminderTimer = window.setTimeout(() => {
        sendFoodGoalReminder();
        scheduleFoodGoalReminder();
    }, nextReminderDelay(settings.time));
}

onMounted(() => {
    window.addEventListener('popstate', handlePopState);
    window.addEventListener('buff-food-goal-reminder-updated', scheduleFoodGoalReminder);
    window.addEventListener('storage', scheduleFoodGoalReminder);
    window.__buffHandleAndroidBack = handleNativeAndroidBack;
    scheduleFoodGoalReminder();

    showFlashToast(page.props.flash?.message);

    removeFlashToastListener = router.on('success', (event) => {
        showFlashToast(event.detail.page.props.flash?.message);
    });
});

onUnmounted(() => {
    window.removeEventListener('popstate', handlePopState);
    window.removeEventListener('buff-food-goal-reminder-updated', scheduleFoodGoalReminder);
    window.removeEventListener('storage', scheduleFoodGoalReminder);
    clearFallbackToast();

    if (foodGoalReminderTimer) {
        window.clearTimeout(foodGoalReminderTimer);
        foodGoalReminderTimer = null;
    }

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
    <div class="app-shell mx-auto flex max-w-md flex-col bg-[#f6f7f4]">
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
                class="fixed inset-x-4 top-[calc(env(safe-area-inset-top,0px)+1rem)] z-50 mx-auto max-w-sm rounded-md bg-[#253d2c] px-4 py-3 text-sm font-semibold text-white shadow-[0_16px_40px_rgba(23,33,27,0.24)]"
                role="status"
                aria-live="polite"
            >
                {{ fallbackToast }}
            </div>
        </Transition>

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
            aria-label="Add"
        >
            <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-stone-300" />
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold">Add</h2>
                <button class="rounded-md p-2 text-stone-500 active:bg-stone-100" aria-label="Close add drawer" @click="closeAddDrawer">
                    <X :size="20" />
                </button>
            </div>

            <div class="grid gap-3">
                <button class="flex items-center gap-3 rounded-md border border-stone-200 bg-stone-50 p-4 text-left active:bg-stone-100" @click="openAddMode('food')">
                    <span class="grid h-11 w-11 place-items-center rounded-md bg-[#253d2c] text-white">
                        <Utensils :size="22" />
                    </span>
                    <span>
                        <span class="block font-bold">Food</span>
                        <span class="block text-sm font-medium text-stone-500">Search or scan</span>
                    </span>
                </button>

                <button class="flex items-center gap-3 rounded-md border border-stone-200 bg-stone-50 p-4 text-left active:bg-stone-100" @click="openAddMode('workout')">
                    <span class="grid h-11 w-11 place-items-center rounded-md bg-[#6f9b58] text-white">
                        <Dumbbell :size="22" />
                    </span>
                    <span>
                        <span class="block font-bold">Workout</span>
                        <span class="block text-sm font-medium text-stone-500">Log calories burned</span>
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
                    class="flex min-h-14 flex-col items-center justify-center gap-1 rounded-md text-[11px] font-semibold transition"
                    :class="isAddActive ? 'bg-[#dce8d4] text-[#17211b]' : 'text-stone-500'"
                    aria-label="Add"
                    @click="openAddDrawer"
                >
                    <Plus :size="20" stroke-width="2.2" />
                    <span class="text-[11px] font-semibold">Add</span>
                </button>
            </div>
        </nav>
    </div>
</template>
