<script setup lang="ts">
import {Head, Link, useForm, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import {avatarColorClass, avatarInitials} from '../../avatar';
import Card from '../../Components/Card.vue';
import SettingsPageHeader from '../../Components/SettingsPageHeader.vue';
import Button from '../../Components/ui/button/Button.vue';
import Input from '../../Components/ui/input/Input.vue';
import Select from '../../Components/ui/select/Select.vue';
import SelectContent from '../../Components/ui/select/SelectContent.vue';
import SelectItem from '../../Components/ui/select/SelectItem.vue';
import SelectTrigger from '../../Components/ui/select/SelectTrigger.vue';
import SelectValue from '../../Components/ui/select/SelectValue.vue';

const props = defineProps<{
    timezones: string[];
}>();

interface BuffAccount {
    id: string;
    name: string;
    email: string;
    timezone: string;
    email_verified: boolean;
}

const page = usePage<{
    buff: {
        account: BuffAccount | null;
        has_local_account: boolean;
    };
}>();

const accountForm = useForm({
    name: page.props.buff.account?.name ?? '',
    email: page.props.buff.account?.email ?? '',
    timezone: page.props.buff.account?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC',
});
const logoutForm = useForm({});
const accountAvatarName = computed(() => page.props.buff.account?.name || page.props.buff.account?.email || 'Account');
const accountAvatarInitials = computed(() => avatarInitials(accountAvatarName.value));
const accountAvatarColor = computed(() => avatarColorClass(accountAvatarName.value));
const timezoneOptions = computed(() => {
    const timezones = [...props.timezones];

    if (accountForm.timezone && !timezones.includes(accountForm.timezone)) {
        timezones.unshift(accountForm.timezone);
    }

    return timezones;
});

function saveAccount() {
    accountForm.patch('/account', {preserveScroll: true});
}
</script>

<template>
    <Head title="Edit profile"/>

    <section class="space-y-5">
        <SettingsPageHeader>Edit profile</SettingsPageHeader>

        <Card>
            <template v-if="page.props.buff.account">
                <form class="space-y-3" @submit.prevent="saveAccount">
                    <h2 class="card-title">My Account</h2>
                    <div class="flex items-center gap-3">
                        <div
                            class="grid size-12 flex-none place-items-center rounded-full text-lg font-semibold text-white"
                            :class="accountAvatarColor"
                            aria-hidden="true"
                        >
                            {{ accountAvatarInitials }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ page.props.buff.account.name }}</p>
                            <p class="truncate text-sm text-muted-foreground">{{ page.props.buff.account.email }}</p>
                        </div>
                    </div>
                    <label class="block">
                        <span class="field-label">Name</span>
                        <Input v-model="accountForm.name" autocomplete="name" required class="mt-1" />
                        <span v-if="accountForm.errors.name" class="mt-1 block text-sm text-destructive">{{ accountForm.errors.name }}</span>
                    </label>
                    <label class="block">
                        <span class="field-label">Email</span>
                        <Input v-model="accountForm.email" type="email" autocomplete="email" required class="mt-1" />
                        <span v-if="accountForm.errors.email" class="mt-1 block text-sm text-destructive">{{ accountForm.errors.email }}</span>
                    </label>
                    <label class="block">
                        <span class="field-label">Timezone</span>
                        <Select v-model="accountForm.timezone" class="mt-1">
                            <SelectTrigger>
                                <SelectValue placeholder="Select timezone" />
                            </SelectTrigger>
                            <SelectContent class="max-h-72">
                                <SelectItem v-for="timezone in timezoneOptions" :key="timezone" :value="timezone">
                                    {{ timezone.replaceAll('_', ' ') }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <span v-if="accountForm.errors.timezone" class="mt-1 block text-sm text-destructive">{{ accountForm.errors.timezone }}</span>
                    </label>
                    <div class="grid gap-2">
                        <Button :disabled="accountForm.processing">Save account</Button>
                    </div>
                </form>
            </template>

            <div v-else class="space-y-3">
                <h2 class="card-title">Account sync paused</h2>
                <p class="text-sm text-muted-foreground">Your offline data remains on this device. Sign in before the next sync.</p>
                <Button :as="Link" href="/account/login" class="w-full">Sign in</Button>
                <form v-if="page.props.buff.has_local_account" @submit.prevent="logoutForm.post('/account/logout')">
                    <Button variant="surface" class="w-full" :disabled="logoutForm.processing">Remove local account data</Button>
                </form>
            </div>
        </Card>
    </section>
</template>
