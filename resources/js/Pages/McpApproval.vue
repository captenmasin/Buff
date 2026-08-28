<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, ShieldCheck } from '@lucide/vue';
import Card from '../Components/Card.vue';
import Button from '../Components/ui/button/Button.vue';

defineOptions({ layout: null });

const props = defineProps<{
    token: string | null;
    approval: {
        status: 'pending' | 'approved';
        clientName: string;
        redirectOrigin: string;
        expiresAt: string;
    } | null;
    approved: boolean;
    error: string | null;
}>();

const approvalForm = useForm({ token: props.token ?? '' });
const denialForm = useForm({ token: props.token ?? '', decision: 'denied' as const });

function formatExpiry(timestamp: string): string {
    const date = new Date(timestamp);

    return Number.isNaN(date.getTime())
        ? 'Unknown'
        : date.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
}

function approve() {
    if (props.approval?.status !== 'pending' || !props.token || approvalForm.processing || denialForm.processing) {
        return;
    }

    approvalForm.post('/mcp-approve');
}

function deny() {
    if (props.approval?.status !== 'pending' || !props.token || approvalForm.processing || denialForm.processing) {
        return;
    }

    denialForm.post('/mcp-approve');
}
</script>

<template>
    <main class="grid min-h-dvh place-items-center bg-background px-4 py-[calc(env(safe-area-inset-top,0px)+2rem)] text-foreground sm:px-6">
        <Head title="Connect AI assistant" />

        <section class="w-full max-w-md" aria-labelledby="approval-title">
            <Card class="p-6 sm:p-7">
                <Transition
                    mode="out-in"
                    enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:duration-150 motion-reduce:transition-opacity"
                    enter-from-class="scale-[0.97] opacity-0 motion-reduce:scale-100"
                    enter-to-class="scale-100 opacity-100"
                    leave-active-class="transition-[opacity,transform] duration-150 ease-in-out motion-reduce:transition-opacity"
                    leave-from-class="scale-100 opacity-100"
                    leave-to-class="scale-[0.97] opacity-0 motion-reduce:scale-100"
                >
                    <div v-if="approved || approval?.status === 'approved'" key="approved" data-motion-transform class="grid gap-5">
                        <div class="grid size-12 place-items-center rounded-full bg-success-soft text-success-soft-foreground" aria-hidden="true">
                            <CheckCircle2 :size="26" />
                        </div>
                        <div class="grid gap-2">
                            <h1 id="approval-title" class="text-2xl font-bold tracking-tight">Connection approved</h1>
                            <p class="text-sm leading-6 text-muted-foreground">
                                Return to your browser. It will continue connecting your AI assistant to Buff.
                            </p>
                        </div>
                        <Button :as="Link" href="/" variant="surface" class="w-full">
                            Back to Buff
                        </Button>
                    </div>

                    <div v-else-if="approval" key="pending" data-motion-transform class="grid gap-5">
                        <div class="flex items-center gap-3">
                            <div class="grid size-12 flex-none place-items-center rounded-full bg-primary-container text-primary-container-foreground" aria-hidden="true">
                                <ShieldCheck :size="26" />
                            </div>
                            <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Connection request</p>
                        </div>

                        <div class="grid gap-2">
                            <h1 id="approval-title" class="text-2xl font-bold tracking-tight">Connect {{ approval.clientName }}?</h1>
                            <p class="text-sm leading-6 text-muted-foreground">
                                This assistant will be able to read and update your Buff nutrition, workouts, goals, body profile, and progress data.
                            </p>
                        </div>

                        <dl class="grid gap-3 rounded-xl bg-muted p-4 text-sm">
                            <div class="grid gap-1">
                                <dt class="font-medium">OAuth redirect origin</dt>
                                <dd class="break-all font-mono text-xs text-muted-foreground">{{ approval.redirectOrigin }}</dd>
                            </div>
                            <div class="grid gap-1">
                                <dt class="font-medium">Request expires</dt>
                                <dd class="text-muted-foreground">{{ formatExpiry(approval.expiresAt) }}</dd>
                            </div>
                        </dl>

                        <div class="grid gap-3">
                            <p v-if="approvalForm.errors.token || denialForm.errors.token || denialForm.errors.decision" class="text-sm text-destructive" role="alert">
                                {{ approvalForm.errors.token || denialForm.errors.token || denialForm.errors.decision }}
                            </p>
                            <form @submit.prevent="approve">
                                <Button type="submit" class="w-full" :disabled="approvalForm.processing || denialForm.processing">
                                    {{ approvalForm.processing ? 'Approving…' : `Approve ${approval.clientName}` }}
                                </Button>
                            </form>
                            <form @submit.prevent="deny">
                                <Button type="submit" variant="surface" class="w-full" :disabled="approvalForm.processing || denialForm.processing">
                                    {{ denialForm.processing ? 'Denying…' : `Don't connect ${approval.clientName}` }}
                                </Button>
                            </form>
                        </div>
                    </div>

                    <div v-else key="unavailable" data-motion-transform class="grid gap-5">
                        <div class="grid size-12 place-items-center rounded-full bg-danger-soft text-danger-soft-foreground" aria-hidden="true">
                            <ShieldCheck :size="26" />
                        </div>
                        <div class="grid gap-2">
                            <h1 id="approval-title" class="text-2xl font-bold tracking-tight">Connection unavailable</h1>
                            <p class="text-sm leading-6 text-muted-foreground" role="alert">{{ error }}</p>
                        </div>
                        <Button :as="Link" href="/" variant="surface" class="w-full">
                            <ArrowLeft :size="18" />
                            Back to Buff
                        </Button>
                    </div>
                </Transition>
            </Card>
        </section>
    </main>
</template>
