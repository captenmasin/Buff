<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Browser } from '#nativephp';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Eye, EyeOff } from '@lucide/vue';
import Card from '../Components/Card.vue';
import ConfirmSheet from '../Components/ConfirmSheet.vue';
import OfflineBanner from '../Components/OfflineBanner.vue';
import SetupFlow from '../Components/SetupFlow.vue';
import SocialLoginButtons from '../Components/SocialLoginButtons.vue';
import Button from '../Components/ui/button/Button.vue';
import Input from '../Components/ui/input/Input.vue';
import { avatarColorClass, avatarInitials } from '../avatar';
import { publicAssetUrl } from '../publicAssetUrl';

type SocialProvider = 'google' | 'apple';
type RegisterStep = 'name' | 'method' | 'email' | 'password';
const registrationNameKey = 'buff:registration-name';

function rememberedRegistrationName(): string {
    return typeof window === 'undefined' ? '' : (window.localStorage.getItem(registrationNameKey) ?? '');
}

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
        account?: { name?: string; email?: string } | null;
        can_resume?: boolean;
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
const localAccountName = computed(() => page.props.buff?.account?.name || localAccountEmail.value || 'your account');
const localAccountInitials = computed(() => avatarInitials(localAccountName.value));
const localAccountColor = computed(() => avatarColorClass(localAccountName.value));
const canResume = computed(() => page.props.buff?.can_resume === true);
const loginForm = useForm({ email: localAccountEmail.value ?? '', password: '', timezone });
const resumeForm = useForm({ account: '', email: '' });
const showLoginOptions = ref(localAccountEmail.value === null);
const switchConfirmOpen = ref(false);
const clearDataConfirmOpen = ref(false);
const pendingSocialProvider = ref<SocialProvider | null>(null);
const registerForm = useForm({ name: rememberedRegistrationName(), email: '', password: '', password_confirmation: '', timezone });
const registerStep = ref<RegisterStep>('name');
const passwordVisible = ref(false);
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
const registerSteps: RegisterStep[] = ['name', 'method', 'email', 'password'];
const registrationProgress = computed(() => ((registerSteps.indexOf(registerStep.value) + 1) / 14) * 100);
const registrationNextLabel = computed(() => registerStep.value === 'password' ? 'Create account' : 'Next');
const registrationNextDisabled = computed(() => {
    if (registerStep.value === 'name') {
        return registerForm.name.trim() === '';
    }

    if (registerStep.value === 'email') {
        return !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(registerForm.email.trim());
    }

    return registerStep.value === 'password' && registerForm.password.length < 8;
});

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
    if (!hasDeviceData.value) {
        for (let index = window.localStorage.length - 1; index >= 0; index -= 1) {
            const key = window.localStorage.key(index);

            if (key?.startsWith('buff:onboarding-draft:')) {
                window.localStorage.removeItem(key);
            }
        }
    }

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

function continueWithLocalAccount() {
    if (canResume.value) {
        resumeForm.post('/account/resume');

        return;
    }

    showLoginOptions.value = true;
}

function useDifferentAccount() {
    loginForm.email = '';
    loginForm.password = '';
    loginForm.clearErrors();
    showLoginOptions.value = true;
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

function previousRegisterStep() {
    if (registerStep.value === 'name') {
        router.visit('/account/login');

        return;
    }

    const currentIndex = registerSteps.indexOf(registerStep.value);
    registerStep.value = registerSteps[currentIndex - 1];
}

function submitRegistration() {
    registerForm
        .transform((data) => ({ ...data, password_confirmation: data.password }))
        .post('/account/register', {
            onError: (errors) => {
                if (errors.name) {
                    registerStep.value = 'name';
                } else if (errors.email) {
                    registerStep.value = 'email';
                } else {
                    registerStep.value = 'password';
                }
            },
        });
}

function nextRegisterStep() {
    registerForm.clearErrors();

    if (registerStep.value === 'name') {
        registerForm.name = registerForm.name.trim();
        registerStep.value = 'method';
    } else if (registerStep.value === 'email') {
        registerForm.email = registerForm.email.trim();
        registerStep.value = 'password';
    } else if (registerStep.value === 'password') {
        submitRegistration();
    }
}

async function launchSocialSignIn(provider: SocialProvider) {
    const query = new URLSearchParams({ device_name: 'Buff mobile', timezone });

    if (props.screen === 'register') {
        window.localStorage.setItem(registrationNameKey, registerForm.name.trim());
        query.set('flow', 'register');
        query.set('preferred_name', registerForm.name.trim());
    }

    const url = `${props.socialLoginUrl}/${provider}/redirect?${query}`;

    try {
        if (await Browser.auth(url)) {
            return;
        }
    } catch {
        // Desktop browsers do not have a NativePHP bridge.
    }

    window.location.assign(url);
}

async function signInWith(provider: SocialProvider) {
    if (hasLocalAccount.value) {
        pendingSocialProvider.value = provider;
        switchConfirmOpen.value = true;

        return;
    }

    await launchSocialSignIn(provider);
}
</script>

<template>
    <div>
        <Head :title="title" />
        <OfflineBanner />

        <SetupFlow
            v-if="screen === 'register'"
            phase="Welcome"
            :progress="registrationProgress"
            :next-label="registrationNextLabel"
            :next-disabled="registrationNextDisabled"
            :processing="registerForm.processing"
            :show-next="registerStep !== 'method'"
            @back="previousRegisterStep"
            @next="nextRegisterStep"
        >
            <Transition
                mode="out-in"
                enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                enter-from-class="translate-x-3 opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="-translate-x-3 opacity-0"
            >
                <div :key="registerStep" class="space-y-8">
                    <template v-if="registerStep === 'name'">
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">First, what can we call you?</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">We’d like to get to know you</p>
                        </header>
                        <label class="block">
                            <span class="field-label">Preferred first name</span>
                            <Input
                                v-model="registerForm.name"
                                autocomplete="given-name"
                                maxlength="120"
                                autofocus
                                class="mt-2 h-16 rounded-xl px-4 text-lg"
                                :aria-invalid="Boolean(registerForm.errors.name)"
                                @keyup.enter="nextRegisterStep"
                            />
                            <span v-if="registerForm.errors.name" class="mt-2 block text-sm text-destructive">{{ registerForm.errors.name }}</span>
                        </label>
                    </template>

                    <template v-else-if="registerStep === 'method'">
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">How would you like to sign up?</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">Choose the account you’ll use with Buff.</p>
                        </header>
                        <div class="space-y-3">
                            <Button type="button" size="lg" class="h-14 w-full rounded-full text-base" @click="registerStep = 'email'">
                                Continue with email
                            </Button>
                            <SocialLoginButtons
                                :apple-login-available="appleLoginAvailable"
                                :divided="false"
                                @sign-in="signInWith"
                            />
                        </div>
                    </template>

                    <template v-else-if="registerStep === 'email'">
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">What’s your email?</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">You’ll use this to sign in and recover your account.</p>
                        </header>
                        <label class="block">
                            <span class="field-label">Email address</span>
                            <Input
                                v-model="registerForm.email"
                                type="email"
                                inputmode="email"
                                autocomplete="email"
                                autofocus
                                class="mt-2 h-16 rounded-xl px-4 text-lg"
                                :aria-invalid="Boolean(registerForm.errors.email)"
                                @keyup.enter="nextRegisterStep"
                            />
                            <span v-if="registerForm.errors.email" class="mt-2 block text-sm text-destructive">{{ registerForm.errors.email }}</span>
                        </label>
                    </template>

                    <template v-else>
                        <header>
                            <h1 class="text-3xl font-bold tracking-tight">Create a password</h1>
                            <p class="mt-2 text-lg font-medium text-muted-foreground">Use at least 8 characters. You can reveal it to check for typos.</p>
                        </header>
                        <label class="block">
                            <span class="field-label">Password</span>
                            <span class="relative mt-2 block">
                                <Input
                                    v-model="registerForm.password"
                                    :type="passwordVisible ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    minlength="8"
                                    autofocus
                                    class="h-16 rounded-xl px-4 pr-14 text-lg"
                                    :aria-invalid="Boolean(registerForm.errors.password)"
                                    @keyup.enter="nextRegisterStep"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 grid w-14 place-items-center rounded-r-xl text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
                                    :aria-label="passwordVisible ? 'Hide password' : 'Show password'"
                                    @click="passwordVisible = !passwordVisible"
                                >
                                    <EyeOff v-if="passwordVisible" :size="22" />
                                    <Eye v-else :size="22" />
                                </button>
                            </span>
                            <span v-if="registerForm.errors.password" class="mt-2 block text-sm text-destructive">{{ registerForm.errors.password }}</span>
                        </label>
                    </template>

                    <p v-if="page.props.flash?.message" class="rounded-xl bg-secondary px-4 py-3 text-sm" role="status">
                        {{ page.props.flash.message }}
                    </p>
                </div>
            </Transition>
        </SetupFlow>

        <main v-else class="grid min-h-dvh place-items-center bg-background px-4 py-10 text-foreground">
            <div class="w-full max-w-sm space-y-6">
                <header class="flex flex-col items-center gap-4 text-center">
                    <img :src="publicAssetUrl('/icon.png')" alt="Buff" class="size-20 rounded-2xl dark:hidden" />
                    <img :src="publicAssetUrl('/icon-dark.png')" alt="Buff" class="hidden size-20 rounded-2xl dark:block" />
                    <h1 class="page-title">{{ title }}</h1>
                </header>

                <p v-if="page.props.flash?.message" class="rounded-xl bg-secondary px-4 py-3 text-sm" role="status">
                    {{ page.props.flash.message }}
                </p>

                <template v-if="screen === 'login'">
                    <Card v-if="localAccountEmail && !showLoginOptions">
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="grid size-11 flex-none place-items-center rounded-full text-lg font-semibold text-white" :class="localAccountColor">
                                    {{ localAccountInitials }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold">{{ localAccountName }}</p>
                                    <p class="truncate text-sm text-muted-foreground">{{ localAccountEmail }}</p>
                                </div>
                            </div>
                            <form @submit.prevent="continueWithLocalAccount">
                                <Button class="w-full" :disabled="resumeForm.processing">Continue as {{ localAccountName }}</Button>
                            </form>
                            <p v-if="resumeForm.errors.account || resumeForm.errors.email" class="text-sm text-destructive">
                                {{ resumeForm.errors.account || resumeForm.errors.email }}
                            </p>
                            <Button type="button" variant="surface" class="w-full" @click="useDifferentAccount">Use a different account</Button>
                        </div>
                    </Card>
                    <Card v-else>
                        <form class="space-y-4" @submit.prevent="submitLogin">
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
                            <SocialLoginButtons :apple-login-available="appleLoginAvailable" @sign-in="signInWith" />
                            <div class="flex justify-between text-sm">
                                <Link href="/account/forgot-password" class="text-link">Forgot password?</Link>
                                <Link href="/account/register" class="text-link">Create account</Link>
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

                <Card v-else-if="screen === 'forgot'">
                    <form class="space-y-4" @submit.prevent="forgotForm.post('/account/forgot-password')">
                        <p class="text-sm text-muted-foreground">We’ll email a link if the account exists.</p>
                        <label class="block">
                            <span class="field-label">Email</span>
                            <Input v-model="forgotForm.email" type="email" autocomplete="email" required class="mt-1" />
                            <span v-if="forgotForm.errors.email" class="mt-1 block text-sm text-destructive">{{ forgotForm.errors.email }}</span>
                        </label>
                        <Button class="w-full" :disabled="forgotForm.processing">Send reset link</Button>
                        <p class="text-center text-sm"><Link href="/account/login" class="text-link">Back to sign in</Link></p>
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
        </main>

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
    </div>
</template>
