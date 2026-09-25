<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import {Bot, ChevronDown, Clock3, Copy, Share2} from '@lucide/vue';
import {computed, ref} from 'vue';
import Card from '../../Components/Card.vue';
import SettingsPageHeader from '../../Components/SettingsPageHeader.vue';
import Button from '../../Components/ui/button/Button.vue';
import Input from '../../Components/ui/input/Input.vue';

interface Connection {
    id: string;
    clientName: string;
    linkedAt: string | null;
    lastUsedAt: string | null;
    revokedAt: string | null;
}

const props = defineProps<{
    connections: Connection[];
    error: string | null;
    mcpEndpoint: string | null;
}>();

const revocationForm = useForm({connection: ''});
const revokingId = ref<string | null>(null);
const copyStatus = ref<string | null>(null);
const shareStatus = ref<string | null>(null);
const authorizedConnections = computed(() => props.connections.filter((connection) => !connection.revokedAt));
const revokedConnections = computed(() => props.connections.filter((connection) => connection.revokedAt));
const canNativeShare = computed(() => typeof navigator !== 'undefined' && typeof navigator.share === 'function');

async function copyMcpEndpoint(): Promise<void> {
    if (!props.mcpEndpoint) {
        return;
    }

    if (!navigator.clipboard) {
        copyStatus.value = 'Select the address above and copy it manually.';

        return;
    }

    try {
        await navigator.clipboard.writeText(props.mcpEndpoint);
        copyStatus.value = null;
        window.dispatchEvent(new CustomEvent('buff:toast', {detail: 'MCP address copied.'}));
    } catch {
        copyStatus.value = 'Select the address above and copy it manually.';
    }
}

async function shareMcpEndpoint(): Promise<void> {
    if (!props.mcpEndpoint) {
        return;
    }

    shareStatus.value = null;

    if (canNativeShare.value) {
        try {
            await navigator.share({
                title: 'Buff MCP',
                text: 'Add Buff to your AI assistant with this MCP address.',
                url: props.mcpEndpoint,
            });
            window.dispatchEvent(new CustomEvent('buff:toast', {detail: 'MCP address shared.'}));

            return;
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }
        }
    }

    await copyMcpEndpoint();
    shareStatus.value = 'Shared by copying the address — paste it into your assistant.';
}

async function openAssistantSetup(url: string): Promise<void> {
    try {
        const {Browser} = await import('#nativephp');

        if (await Browser.open(url) || await Browser.inApp(url)) {
            return;
        }
    } catch {
        // Fall through to the system browser.
    }

    window.open(url, '_blank', 'noopener,noreferrer');
}

function formatTimestamp(timestamp: string | null): string {
    if (!timestamp) {
        return 'Never';
    }

    const date = new Date(timestamp);

    return Number.isNaN(date.getTime())
        ? 'Unknown'
        : date.toLocaleString([], {dateStyle: 'medium', timeStyle: 'short'});
}

function connectionStatus(connection: Connection): 'Active' | 'Authorized' {
    return connection.lastUsedAt ? 'Active' : 'Authorized';
}

function connectionStatusClass(connection: Connection): string {
    return connection.lastUsedAt
        ? 'bg-success-soft text-success-soft-foreground'
        : 'bg-warning-soft text-warning-soft-foreground';
}

function revoke(connection: Connection) {
    if (connection.revokedAt || revocationForm.processing) {
        return;
    }

    revokingId.value = connection.id;
    revocationForm.delete(`/settings/connected-assistants/${connection.id}`, {
        preserveScroll: true,
        onFinish: () => {
            revokingId.value = null;
        },
    });
}
</script>

<template>
    <Head title="Connected AI assistants"/>

    <section class="space-y-8">
        <SettingsPageHeader>Connected AI assistants</SettingsPageHeader>

        <p v-if="error" class="rounded-xl bg-danger-soft p-4 text-sm text-danger-soft-foreground" role="alert">
            {{ error }}
        </p>

        <p v-if="revocationForm.errors.connection" class="rounded-xl bg-danger-soft p-4 text-sm text-danger-soft-foreground" role="alert">
            {{ revocationForm.errors.connection }}
        </p>

        <section v-if="!error" class="space-y-3" aria-label="Authorized assistants">
            <TransitionGroup
                tag="div"
                class="grid gap-3 md:grid-cols-2"
                enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:duration-150 motion-reduce:transition-opacity"
                enter-from-class="scale-[0.97] opacity-0 motion-reduce:scale-100"
                enter-to-class="scale-100 opacity-100"
                leave-active-class="transition-[opacity,transform] duration-150 ease-in-out motion-reduce:transition-opacity"
                leave-from-class="scale-100 opacity-100"
                leave-to-class="scale-[0.97] opacity-0 motion-reduce:scale-100"
            >
                <Card v-if="authorizedConnections.length === 0" key="empty" data-motion-transform class="border border-dashed border-border bg-transparent shadow-none md:col-span-2">
                    <div class="flex items-start gap-3">
                        <div class="grid size-10 flex-none place-items-center rounded-xl bg-muted text-muted-foreground" aria-hidden="true">
                            <Bot :size="19"/>
                        </div>
                        <div>
                            <h3 class="card-title">No assistants have access</h3>
                            <p class="mt-1 text-sm text-muted-foreground">Connect one below — you can finish the whole flow on your phone.</p>
                        </div>
                    </div>
                </Card>

                <Card v-for="connection in authorizedConnections" :key="connection.id" data-motion-transform>
                    <div class="flex items-start gap-3">
                        <div class="grid size-10 flex-none place-items-center rounded-xl bg-primary-container text-primary-container-foreground" aria-hidden="true">
                            <Bot :size="19"/>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="card-title truncate">{{ connection.clientName }}</h3>
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="connectionStatusClass(connection)"
                                >
                                    {{ connectionStatus(connection) }}
                                </span>
                            </div>
                            <dl class="mt-3 grid gap-1.5 text-sm text-muted-foreground">
                                <div class="flex items-center gap-2">
                                    <Clock3 :size="15" aria-hidden="true"/>
                                    <dt class="sr-only">Authorized</dt>
                                    <dd>Authorized {{ formatTimestamp(connection.linkedAt) }}</dd>
                                </div>
                                <div>
                                    <dt class="inline font-medium text-foreground">Last used:</dt>
                                    <dd class="inline"> {{ formatTimestamp(connection.lastUsedAt) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="destructive"
                        class="mt-4 w-full"
                        :aria-label="`Revoke ${connection.clientName}`"
                        :disabled="revocationForm.processing"
                        @click="revoke(connection)"
                    >
                        {{ revokingId === connection.id ? 'Revoking…' : 'Revoke access' }}
                    </Button>
                </Card>
            </TransitionGroup>
        </section>

        <Card v-if="mcpEndpoint">
            <div class="flex items-start gap-3">
                <div class="grid size-10 flex-none place-items-center rounded-xl bg-primary-container text-primary-container-foreground" aria-hidden="true">
                    <Bot :size="19"/>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="card-title">Connect from your phone</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Share Buff’s MCP address into an assistant, then approve the request when Buff opens.
                    </p>
                </div>
            </div>

            <ol class="mt-5 space-y-3 rounded-xl bg-muted/40 p-4 text-sm leading-6 text-muted-foreground">
                <li class="flex gap-3">
                    <span class="grid size-6 flex-none place-items-center rounded-full bg-primary-container text-xs font-bold text-primary-container-foreground">1</span>
                    <span><span class="font-medium text-foreground">Share or copy</span> the MCP address below.</span>
                </li>
                <li class="flex gap-3">
                    <span class="grid size-6 flex-none place-items-center rounded-full bg-primary-container text-xs font-bold text-primary-container-foreground">2</span>
                    <span><span class="font-medium text-foreground">Paste it</span> into Claude, ChatGPT, or Gemini as a custom connector / app.</span>
                </li>
                <li class="flex gap-3">
                    <span class="grid size-6 flex-none place-items-center rounded-full bg-primary-container text-xs font-bold text-primary-container-foreground">3</span>
                    <span><span class="font-medium text-foreground">Approve in Buff</span> when the assistant opens this app — no desktop required.</span>
                </li>
            </ol>

            <div class="mt-4 flex flex-col gap-2">
                <Input
                    :model-value="mcpEndpoint"
                    readonly
                    aria-label="Buff MCP server address"
                    class="h-10 min-w-0 font-mono text-xs text-muted-foreground"
                />
                <div class="grid grid-cols-2 gap-2">
                    <Button
                        type="button"
                        class="w-full"
                        @click="shareMcpEndpoint"
                    >
                        <Share2 :size="16"/>
                        {{ canNativeShare ? 'Share' : 'Share' }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="w-full"
                        @click="copyMcpEndpoint"
                    >
                        <Copy :size="16"/>
                        Copy
                    </Button>
                </div>
            </div>

            <p v-if="copyStatus || shareStatus" class="mt-3 text-sm text-muted-foreground" role="status">
                {{ shareStatus || copyStatus }}
            </p>

            <div class="mt-5 grid gap-2 border-t border-border pt-5">
                <h3 class="text-sm font-semibold text-foreground">Open an assistant</h3>
                <p class="text-sm text-muted-foreground">Jump straight into phone-friendly setup pages.</p>
                <div class="grid gap-2 sm:grid-cols-3">
                    <Button type="button" variant="surface" class="w-full" @click="openAssistantSetup('https://claude.ai/settings/connectors')">
                        Claude
                    </Button>
                    <Button type="button" variant="surface" class="w-full" @click="openAssistantSetup('https://chatgpt.com')">
                        ChatGPT
                    </Button>
                    <Button type="button" variant="surface" class="w-full" @click="openAssistantSetup('https://gemini.google.com/app')">
                        Gemini
                    </Button>
                </div>
            </div>

            <div class="mt-5 border-t border-border pt-5">
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-foreground">
                        Per-assistant tips
                        <ChevronDown class="transition-transform group-open:rotate-180" :size="18" aria-hidden="true"/>
                    </summary>

                    <div class="mt-3 grid gap-3">
                        <div class="rounded-xl border border-border bg-muted/30 p-4">
                            <h4 class="text-sm font-semibold text-foreground">Claude</h4>
                            <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm leading-6 text-muted-foreground">
                                <li>Open Claude → Customize → Connectors.</li>
                                <li>Add a custom connector named Buff and paste the MCP address.</li>
                                <li>Tap Connect, then Approve in Buff when this app opens.</li>
                            </ol>
                        </div>

                        <div class="rounded-xl border border-border bg-muted/30 p-4">
                            <h4 class="text-sm font-semibold text-foreground">ChatGPT</h4>
                            <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm leading-6 text-muted-foreground">
                                <li>In ChatGPT Settings → Apps, turn on Developer mode if needed.</li>
                                <li>Create an app named Buff and paste the MCP address as the endpoint.</li>
                                <li>Choose OAuth, then approve in Buff when prompted.</li>
                            </ol>
                            <p class="mt-3 text-xs leading-5 text-muted-foreground">
                                Needs an eligible ChatGPT plan. If the mobile app can’t create apps yet, open ChatGPT in your phone browser.
                            </p>
                        </div>

                        <div class="rounded-xl border border-border bg-muted/30 p-4">
                            <h4 class="text-sm font-semibold text-foreground">Gemini</h4>
                            <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm leading-6 text-muted-foreground">
                                <li>Open Gemini → Settings &amp; help → Connected Apps.</li>
                                <li>Add a custom Spark app and paste the MCP address.</li>
                                <li>Finish the Buff approval when this app opens.</li>
                            </ol>
                        </div>
                    </div>
                </details>
            </div>
        </Card>

        <Transition
            enter-active-class="transition-[opacity,transform] duration-200 ease-out motion-reduce:duration-150 motion-reduce:transition-opacity"
            enter-from-class="scale-[0.97] opacity-0 motion-reduce:scale-100"
            enter-to-class="scale-100 opacity-100"
            leave-active-class="transition-[opacity,transform] duration-150 ease-in-out motion-reduce:transition-opacity"
            leave-from-class="scale-100 opacity-100"
            leave-to-class="scale-[0.97] opacity-0 motion-reduce:scale-100"
        >
            <Card v-if="revokedConnections.length" data-motion-transform>
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                        <span>
                            <span class="block text-sm font-semibold text-foreground">Connection history</span>
                            <span class="mt-1 block text-sm text-muted-foreground">{{ revokedConnections.length }} revoked</span>
                        </span>
                        <ChevronDown class="transition-transform group-open:rotate-180" :size="18" aria-hidden="true"/>
                    </summary>

                    <div class="mt-4 divide-y divide-border border-t border-border">
                        <div
                            v-for="connection in revokedConnections"
                            :key="connection.id"
                            class="flex items-center justify-between gap-4 py-3 text-sm"
                        >
                            <span class="min-w-0 truncate font-medium text-foreground">{{ connection.clientName }}</span>
                            <span class="flex-none text-right text-muted-foreground">Revoked {{ formatTimestamp(connection.revokedAt) }}</span>
                        </div>
                    </div>
                </details>
            </Card>
        </Transition>
    </section>
</template>
