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

const form = useForm({ token: props.token ?? '' });

function approve() {
    if (props.approval?.status !== 'pending' || !props.token) {
        return;
    }

    form.post('/mcp-approve');
}
</script>

<template>
    <main class="grid min-h-dvh place-items-center bg-background px-4 py-[calc(env(safe-area-inset-top,0px)+2rem)] text-foreground sm:px-6">
        <Head title="Connect AI assistant" />

        <section class="w-full max-w-md" aria-labelledby="approval-title">
            <Card class="gap-5 p-6 sm:p-7">
                <template v-if="approved || approval?.status === 'approved'">
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
                </template>

                <template v-else-if="approval">
                    <div class="flex items-center gap-3">
                        <div class="grid size-12 flex-none place-items-center rounded-full bg-primary text-primary-foreground" aria-hidden="true">
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

                    <dl class="grid gap-2 rounded-xl bg-muted p-4 text-sm">
                        <dt class="font-medium">OAuth redirect origin</dt>
                        <dd class="break-all font-mono text-xs text-muted-foreground">{{ approval.redirectOrigin }}</dd>
                    </dl>

                    <form class="grid gap-3" @submit.prevent="approve">
                        <p v-if="form.errors.token" class="text-sm text-destructive" role="alert">
                            {{ form.errors.token }}
                        </p>
                        <Button type="submit" class="w-full" :disabled="form.processing">
                            {{ form.processing ? 'Approving…' : `Approve ${approval.clientName}` }}
                        </Button>
                        <Button :as="Link" href="/" type="button" variant="surface" class="w-full">
                            Cancel
                        </Button>
                    </form>
                </template>

                <template v-else>
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
                </template>
            </Card>
        </section>
    </main>
</template>
