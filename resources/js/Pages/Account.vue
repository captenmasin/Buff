<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import Card from '../Components/Card.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';

defineOptions({ layout: null });

const props = withDefaults(defineProps<{
    screen: 'login' | 'register' | 'forgot' | 'reset' | 'verify';
    email?: string | null;
    token?: string;
}>(), {
    email: '',
    token: '',
});

const page = usePage<{ flash?: { message?: string } }>();
const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
const loginForm = useForm({ email: '', password: '', timezone });
const registerForm = useForm({ name: '', email: '', password: '', password_confirmation: '', timezone });
const forgotForm = useForm({ email: props.email || '' });
const resetForm = useForm({
    email: props.email || '',
    token: props.token,
    password: '',
    password_confirmation: '',
});
const resendForm = useForm({});
const logoutForm = useForm({});
let verificationTimer: number | null = null;

const title = computed(() => ({
    login: 'Sign in',
    register: 'Create account',
    forgot: 'Reset password',
    reset: 'Choose a new password',
    verify: 'Check your email',
}[props.screen]));

async function checkVerification() {
    if (props.screen !== 'verify' || document.visibilityState !== 'visible') {
        return;
    }

    try {
        const { data } = await axios.get('/account/verification-status');

        if (data.verified) {
            router.visit('/');
        }
    } catch {
        // The next visible poll retries; account errors remain on the PHP side.
    }
}

function handleVisibilityChange() {
    if (document.visibilityState === 'visible') {
        checkVerification();
    }
}

onMounted(() => {
    if (props.screen === 'verify') {
        checkVerification();
        verificationTimer = window.setInterval(checkVerification, 5000);
        document.addEventListener('visibilitychange', handleVisibilityChange);
    }
});

onBeforeUnmount(() => {
    if (verificationTimer !== null) {
        window.clearInterval(verificationTimer);
    }

    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <main class="grid min-h-dvh place-items-center bg-background px-4 py-10 text-foreground">
        <Head :title="title" />

        <div class="w-full max-w-sm space-y-5">
            <header class="text-center">
                <p class="text-sm font-semibold text-primary">Buff</p>
                <h1 class="mt-1 text-3xl font-semibold">{{ title }}</h1>
            </header>

            <p
                v-if="page.props.flash?.message"
                class="rounded-md bg-secondary px-4 py-3 text-sm"
                role="status"
            >
                {{ page.props.flash.message }}
            </p>

            <Card v-if="screen === 'login'">
                <form class="space-y-4" @submit.prevent="loginForm.post('/account/login')">
                    <label class="block">
                        <span class="text-sm font-semibold">Email</span>
                        <Input v-model="loginForm.email" type="email" autocomplete="email" required class="mt-1" />
                        <span v-if="loginForm.errors.email" class="mt-1 block text-sm text-destructive">{{ loginForm.errors.email }}</span>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold">Password</span>
                        <Input v-model="loginForm.password" type="password" autocomplete="current-password" required class="mt-1" />
                        <span v-if="loginForm.errors.password" class="mt-1 block text-sm text-destructive">{{ loginForm.errors.password }}</span>
                    </label>
                    <Button class="w-full" :disabled="loginForm.processing">Sign in</Button>
                    <div class="flex justify-between text-sm">
                        <Link href="/account/forgot-password" class="text-primary">Forgot password?</Link>
                        <Link href="/account/register" class="text-primary">Create account</Link>
                    </div>
                </form>
            </Card>

            <Card v-else-if="screen === 'register'">
                <form class="space-y-4" @submit.prevent="registerForm.post('/account/register')">
                    <label class="block"><span class="text-sm font-semibold">Name</span><Input v-model="registerForm.name" autocomplete="name" required class="mt-1" /><span v-if="registerForm.errors.name" class="mt-1 block text-sm text-destructive">{{ registerForm.errors.name }}</span></label>
                    <label class="block"><span class="text-sm font-semibold">Email</span><Input v-model="registerForm.email" type="email" autocomplete="email" required class="mt-1" /><span v-if="registerForm.errors.email" class="mt-1 block text-sm text-destructive">{{ registerForm.errors.email }}</span></label>
                    <label class="block"><span class="text-sm font-semibold">Password</span><Input v-model="registerForm.password" type="password" autocomplete="new-password" minlength="8" required class="mt-1" /><span v-if="registerForm.errors.password" class="mt-1 block text-sm text-destructive">{{ registerForm.errors.password }}</span></label>
                    <label class="block"><span class="text-sm font-semibold">Confirm password</span><Input v-model="registerForm.password_confirmation" type="password" autocomplete="new-password" minlength="8" required class="mt-1" /></label>
                    <Button class="w-full" :disabled="registerForm.processing">Create account</Button>
                    <p class="text-center text-sm"><Link href="/account/login" class="text-primary">Back to sign in</Link></p>
                </form>
            </Card>

            <Card v-else-if="screen === 'forgot'">
                <form class="space-y-4" @submit.prevent="forgotForm.post('/account/forgot-password')">
                    <p class="text-sm text-muted-foreground">We’ll email a link if the account exists.</p>
                    <label class="block">
                        <span class="text-sm font-semibold">Email</span>
                        <Input v-model="forgotForm.email" type="email" autocomplete="email" required class="mt-1" />
                        <span v-if="forgotForm.errors.email" class="mt-1 block text-sm text-destructive">{{ forgotForm.errors.email }}</span>
                    </label>
                    <Button class="w-full" :disabled="forgotForm.processing">Send reset link</Button>
                    <p class="text-center text-sm"><Link href="/account/login" class="text-primary">Back to sign in</Link></p>
                </form>
            </Card>

            <Card v-else-if="screen === 'reset'">
                <form class="space-y-4" @submit.prevent="resetForm.post('/reset-password')">
                    <label class="block">
                        <span class="text-sm font-semibold">Email</span>
                        <Input v-model="resetForm.email" type="email" autocomplete="email" required class="mt-1" />
                        <span v-if="resetForm.errors.email" class="mt-1 block text-sm text-destructive">{{ resetForm.errors.email }}</span>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold">New password</span>
                        <Input v-model="resetForm.password" type="password" autocomplete="new-password" minlength="8" required class="mt-1" />
                        <span v-if="resetForm.errors.password" class="mt-1 block text-sm text-destructive">{{ resetForm.errors.password }}</span>
                    </label>
                    <label class="block">
                        <span class="text-sm font-semibold">Confirm password</span>
                        <Input v-model="resetForm.password_confirmation" type="password" autocomplete="new-password" minlength="8" required class="mt-1" />
                    </label>
                    <Button class="w-full" :disabled="resetForm.processing">Reset password</Button>
                </form>
            </Card>

            <Card v-else>
                <div class="space-y-4 text-center">
                    <p class="text-sm text-muted-foreground">We sent a verification link to <strong class="text-foreground">{{ email }}</strong>.</p>
                    <p class="text-sm text-muted-foreground">This screen checks automatically while it is open.</p>
                    <form @submit.prevent="resendForm.post('/account/verification/resend')">
                        <Button variant="surface" class="w-full" :disabled="resendForm.processing">Resend email</Button>
                    </form>
                    <Button :as="Link" href="/" class="w-full">Continue to Buff</Button>
                    <form @submit.prevent="logoutForm.post('/account/logout')">
                        <Button type="submit" variant="ghost" class="w-full">Use another account</Button>
                    </form>
                </div>
            </Card>
        </div>
    </main>
</template>
