<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Browser } from '#nativephp';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Card from '../Components/Card.vue';
import ConfirmSheet from '../Components/ConfirmSheet.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import { accountReplacementDecision, type SocialProvider } from '../accountReplacement';

defineOptions({ layout: null });

const props = withDefaults(defineProps<{
    screen: 'login' | 'register' | 'forgot' | 'reset' | 'verify';
    email?: string | null;
    token?: string;
    appleLoginAvailable: boolean;
    socialLoginUrl: string;
}>(), {
    email: '',
    token: '',
});

const page = usePage<{
    flash?: { message?: string };
    buff?: {
        account?: { email?: string } | null;
        has_local_account?: boolean;
    };
}>();
const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
const localAccountEmail = computed(() => {
    const email = page.props.buff?.account?.email;

    return typeof email === 'string' && email !== '' ? email : null;
});
const hasLocalAccount = computed(() => page.props.buff?.has_local_account === true);
const hasDeviceData = computed(() => hasLocalAccount.value || localAccountEmail.value !== null);
const loginForm = useForm({ email: localAccountEmail.value ?? '', password: '', timezone });
const switchConfirmOpen = ref(false);
const clearDataConfirmOpen = ref(false);
const pendingSocialProvider = ref<SocialProvider | null>(null);
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
const clearDataForm = useForm({});
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

function isSwitchingAccounts(): boolean {
    return localAccountEmail.value !== null
        && loginForm.email.trim().toLowerCase() !== localAccountEmail.value.toLowerCase();
}

function submitLogin() {
    if (isSwitchingAccounts()) {
        pendingSocialProvider.value = null;
        switchConfirmOpen.value = true;

        return;
    }

    loginForm.post('/account/login');
}

function cancelSwitch() {
    pendingSocialProvider.value = null;
    switchConfirmOpen.value = false;
}

function confirmSwitch() {
    const provider = pendingSocialProvider.value;
    pendingSocialProvider.value = null;
    switchConfirmOpen.value = false;

    if (provider !== null) {
        void launchSocialSignIn(provider);

        return;
    }

    loginForm.post('/account/login');
}

function confirmClearData() {
    clearDataConfirmOpen.value = false;
    clearDataForm.delete('/account/local-data');
}

async function launchSocialSignIn(provider: SocialProvider) {
    const query = new URLSearchParams({ device_name: 'Buff mobile', timezone });

    await Browser.auth(`${props.socialLoginUrl}/${provider}/redirect?${query}`);
}

async function signInWith(provider: SocialProvider) {
    const decision = accountReplacementDecision(hasLocalAccount.value, provider);

    if (decision.type === 'confirm') {
        pendingSocialProvider.value = decision.provider;
        switchConfirmOpen.value = true;

        return;
    }

    await launchSocialSignIn(decision.provider);
}
</script>

<template>
    <main class="grid min-h-dvh place-items-center bg-background px-4 py-10 text-foreground">
        <Head :title="title" />

        <div class="w-full max-w-sm space-y-6">
            <header class="flex flex-col items-center gap-4 text-center">
                <img :src="'/icon.png'" alt="Buff" class="size-20 rounded-2xl dark:hidden" />
                <img :src="'/icon-dark.png'" alt="Buff" class="hidden size-20 rounded-2xl dark:block" />
                <h1 class="page-title">{{ title }}</h1>
            </header>

            <p
                v-if="page.props.flash?.message"
                class="rounded-xl bg-secondary px-4 py-3 text-sm"
                role="status"
            >
                {{ page.props.flash.message }}
            </p>

            <template v-if="screen === 'login'">
                <Card>
                    <form class="space-y-4" @submit.prevent="submitLogin">
                        <p v-if="hasLocalAccount && localAccountEmail" class="text-sm text-muted-foreground">
                            This device has data for <strong class="text-foreground">{{ localAccountEmail }}</strong>. Sign in with that account to keep it.
                        </p>
                        <p v-else-if="hasLocalAccount" class="text-sm text-muted-foreground">
                            This device still has data from a previous account. Sign in with that account to keep it, or a different one to replace it.
                        </p>
                        <label class="block">
                            <span class="field-label">Email</span>
                            <Input v-model="loginForm.email" type="email" autocomplete="email" required class="mt-1" />
                            <span v-if="loginForm.errors.email" class="mt-1 block text-sm text-destructive">{{ loginForm.errors.email }}</span>
                        </label>
                        <label class="block">
                            <span class="field-label">Password</span>
                            <Input v-model="loginForm.password" type="password" autocomplete="current-password" required class="mt-1" />
                            <span v-if="loginForm.errors.password" class="mt-1 block text-sm text-destructive">{{ loginForm.errors.password }}</span>
                        </label>
                        <Button class="w-full" :disabled="loginForm.processing">Sign in</Button>
                        <div class="space-y-2 border-t pt-4">
                            <Button type="button" variant="surface" class="w-full" @click="signInWith('google')">Continue with Google</Button>
                            <Button v-if="appleLoginAvailable" type="button" variant="surface" class="w-full" @click="signInWith('apple')">Continue with Apple</Button>
                        </div>
                        <div class="flex justify-between text-sm">
                            <Link href="/account/forgot-password" class="text-primary">Forgot password?</Link>
                            <Link href="/account/register" class="text-primary">Create account</Link>
                        </div>
                    </form>
                </Card>
                <Button
                    v-if="hasDeviceData"
                    type="button"
                    variant="destructive"
                    class="w-full"
                    :disabled="clearDataForm.processing"
                    @click="clearDataConfirmOpen = true"
                >
                    Clear device data
                </Button>
            </template>

            <Card v-else-if="screen === 'register'">
                <form class="space-y-4" @submit.prevent="registerForm.post('/account/register')">
                    <label class="block"><span class="field-label">Name</span><Input v-model="registerForm.name" autocomplete="name" required class="mt-1" /><span v-if="registerForm.errors.name" class="mt-1 block text-sm text-destructive">{{ registerForm.errors.name }}</span></label>
                    <label class="block"><span class="field-label">Email</span><Input v-model="registerForm.email" type="email" autocomplete="email" required class="mt-1" /><span v-if="registerForm.errors.email" class="mt-1 block text-sm text-destructive">{{ registerForm.errors.email }}</span></label>
                    <label class="block"><span class="field-label">Password</span><Input v-model="registerForm.password" type="password" autocomplete="new-password" minlength="8" required class="mt-1" /><span v-if="registerForm.errors.password" class="mt-1 block text-sm text-destructive">{{ registerForm.errors.password }}</span></label>
                    <label class="block"><span class="field-label">Confirm password</span><Input v-model="registerForm.password_confirmation" type="password" autocomplete="new-password" minlength="8" required class="mt-1" /></label>
                    <Button class="w-full" :disabled="registerForm.processing">Create account</Button>
                    <div class="space-y-2 border-t pt-4">
                        <Button type="button" variant="surface" class="w-full" @click="signInWith('google')">Continue with Google</Button>
                        <Button v-if="appleLoginAvailable" type="button" variant="surface" class="w-full" @click="signInWith('apple')">Continue with Apple</Button>
                    </div>
                    <p class="text-center text-sm"><Link href="/account/login" class="text-primary">Back to sign in</Link></p>
                </form>
            </Card>

            <Card v-else-if="screen === 'forgot'">
                <form class="space-y-4" @submit.prevent="forgotForm.post('/account/forgot-password')">
                    <p class="text-sm text-muted-foreground">We’ll email a link if the account exists.</p>
                    <label class="block">
                        <span class="field-label">Email</span>
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
                        <span class="field-label">Email</span>
                        <Input v-model="resetForm.email" type="email" autocomplete="email" required class="mt-1" />
                        <span v-if="resetForm.errors.email" class="mt-1 block text-sm text-destructive">{{ resetForm.errors.email }}</span>
                    </label>
                    <label class="block">
                        <span class="field-label">New password</span>
                        <Input v-model="resetForm.password" type="password" autocomplete="new-password" minlength="8" required class="mt-1" />
                        <span v-if="resetForm.errors.password" class="mt-1 block text-sm text-destructive">{{ resetForm.errors.password }}</span>
                    </label>
                    <label class="block">
                        <span class="field-label">Confirm password</span>
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

        <ConfirmSheet
            :open="switchConfirmOpen"
            title="Switch accounts?"
            message="The data on this device will be removed. Anything already synced stays in the other account."
            confirm-label="Switch"
            @cancel="cancelSwitch"
            @confirm="confirmSwitch"
        />
        <ConfirmSheet
            :open="clearDataConfirmOpen"
            title="Clear device data?"
            message="This permanently removes local health data from this device. Anything already synced stays in your account."
            confirm-label="Clear data"
            @cancel="clearDataConfirmOpen = false"
            @confirm="confirmClearData"
        />
    </main>
</template>
