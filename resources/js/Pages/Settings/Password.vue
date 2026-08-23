<script setup lang="ts">
import {Head, Link, useForm, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import Card from '../../Components/Card.vue';
import SettingsPageHeader from '../../Components/SettingsPageHeader.vue';
import Button from '../../Components/ui/button/Button.vue';
import Input from '../../Components/ui/input/Input.vue';

interface BuffAccount {
    email: string;
}

const page = usePage<{
    buff: {
        account: BuffAccount | null;
    };
}>();

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const passwordResetUrl = computed(() => {
    const email = page.props.buff.account?.email;

    return email
        ? `/account/forgot-password?${new URLSearchParams({email}).toString()}`
        : '/account/forgot-password';
});

function updatePassword() {
    passwordForm.put('/account/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <Head title="Change password"/>

    <section class="space-y-5">
        <SettingsPageHeader>Change password</SettingsPageHeader>

        <Card>
            <form class="space-y-3" @submit.prevent="updatePassword">
                <h2 class="card-title">Change password</h2>
                <p class="text-sm text-muted-foreground">
                    Signed in with Google or Apple, or don't know your current password?
                    <Link :href="passwordResetUrl" class="text-primary">Reset it by email.</Link>
                </p>
                <label class="block">
                    <span class="field-label">Current password</span>
                    <Input
                        v-model="passwordForm.current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="mt-1"
                    />
                    <span v-if="passwordForm.errors.current_password" class="mt-1 block text-sm text-destructive">{{ passwordForm.errors.current_password }}</span>
                </label>
                <label class="block">
                    <span class="field-label">New password</span>
                    <Input
                        v-model="passwordForm.password"
                        type="password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                        class="mt-1"
                    />
                    <span v-if="passwordForm.errors.password" class="mt-1 block text-sm text-destructive">{{ passwordForm.errors.password }}</span>
                </label>
                <label class="block">
                    <span class="field-label">Confirm new password</span>
                    <Input
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                        class="mt-1"
                    />
                </label>
                <div class="grid gap-2">
                    <Button :disabled="passwordForm.processing">Update password</Button>
                </div>
            </form>
        </Card>
    </section>
</template>
