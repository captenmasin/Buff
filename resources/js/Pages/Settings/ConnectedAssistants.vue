<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import {Bot, ChevronDown, Clock3} from '@lucide/vue';
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
const authorizedConnections = computed(() => props.connections.filter((connection) => !connection.revokedAt));
const revokedConnections = computed(() => props.connections.filter((connection) => connection.revokedAt));

async function copyMcpEndpoint(): Promise<void> {
    if (!props.mcpEndpoint || !navigator.clipboard) {
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

        <section v-if="!error" class="space-y-3" aria-labelledby="authorized-assistants-heading">
<!--            <div>-->
<!--                <h2 id="authorized-assistants-heading" class="text-base font-semibold text-foreground">Authorized assistants</h2>-->
<!--                <p class="mt-1 text-sm leading-6 text-muted-foreground">-->
<!--                    Revoking access blocks Buff immediately, but does not remove Buff from the assistant's local configuration.-->
<!--                </p>-->
<!--            </div>-->

            <Card v-if="authorizedConnections.length === 0" class="border border-dashed border-border bg-transparent shadow-none">
                <div class="flex items-start gap-3">
                    <div class="grid size-10 flex-none place-items-center rounded-xl bg-muted text-muted-foreground" aria-hidden="true">
                        <Bot :size="19"/>
                    </div>
                    <div>
                        <h3 class="card-title">No assistants have access</h3>
                        <p class="mt-1 text-sm text-muted-foreground">Add one below when you're ready.</p>
                    </div>
                </div>
            </Card>

            <div v-else class="grid gap-3 md:grid-cols-2">
                <Card v-for="connection in authorizedConnections" :key="connection.id">
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
            </div>
        </section>

        <Card v-if="mcpEndpoint">
            <div class="flex items-start gap-3">
                <div class="grid size-10 flex-none place-items-center rounded-xl bg-primary-container text-primary-container-foreground" aria-hidden="true">
                    <Bot :size="19"/>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="card-title">Add an assistant</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Use Buff's MCP address with your preferred assistant.
                    </p>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                <Input
                    :model-value="mcpEndpoint"
                    readonly
                    aria-label="Buff MCP server address"
                    class="h-10 min-w-0 font-mono text-xs text-muted-foreground"
                />
                <Button
                    type="button"
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="copyMcpEndpoint"
                >
                    Copy MCP address
                </Button>
            </div>

            <p v-if="copyStatus" class="mt-3 text-sm text-muted-foreground" role="status">
                {{ copyStatus }}
            </p>

            <div class="mt-5 border-t border-border pt-5">
                <h3 class="text-sm font-semibold text-foreground">Setup instructions</h3>
                <p class="mt-1 text-sm text-muted-foreground">Choose your assistant to see the steps.</p>

                <div class="mt-3 grid gap-3">
                    <details class="group rounded-xl border border-border bg-muted/30 p-4">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-foreground">
                            Codex
                            <ChevronDown class="transition-transform group-open:rotate-180" :size="18" aria-hidden="true"/>
                        </summary>
                        <ol class="mt-3 list-decimal space-y-3 pl-5 text-sm leading-6 text-muted-foreground">
                            <li>
                                Add Buff from a terminal:
                                <code class="mt-2 block break-all rounded-lg bg-background px-3 py-2 font-mono text-xs text-foreground">codex mcp add buff --url {{ mcpEndpoint }}</code>
                            </li>
                            <li>
                                Start authorization, then sign in to Buff and approve access in the browser:
                                <code class="mt-2 block rounded-lg bg-background px-3 py-2 font-mono text-xs text-foreground">codex mcp login buff</code>
                            </li>
                            <li>
                                Restart Codex, confirm the saved configuration below, then use <code class="font-mono text-xs text-foreground">/mcp</code> in Codex to confirm Buff is active:
                                <code class="mt-2 block rounded-lg bg-background px-3 py-2 font-mono text-xs text-foreground">codex mcp get buff</code>
                            </li>
                        </ol>
                    </details>

                    <details class="group rounded-xl border border-border bg-muted/30 p-4">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-foreground">
                            ChatGPT
                            <ChevronDown class="transition-transform group-open:rotate-180" :size="18" aria-hidden="true"/>
                        </summary>
                        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-muted-foreground">
                            <li>Use ChatGPT on the web and turn on Developer mode in Settings &rarr; Apps &rarr; Advanced settings.</li>
                            <li>In Settings &rarr; Apps, choose Create. Name the app Buff and paste the MCP address above as its endpoint.</li>
                            <li>Choose OAuth if asked, scan the tools, complete the Buff sign-in, then create the app.</li>
                        </ol>
                        <p class="mt-3 text-xs leading-5 text-muted-foreground">
                            Full MCP access requires an eligible ChatGPT plan and workspace permissions.
                        </p>
                    </details>

                    <details class="group rounded-xl border border-border bg-muted/30 p-4">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-foreground">
                            Claude
                            <ChevronDown class="transition-transform group-open:rotate-180" :size="18" aria-hidden="true"/>
                        </summary>
                        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-muted-foreground">
                            <li>In Claude or Claude Desktop, open Customize &rarr; Connectors.</li>
                            <li>Choose + &rarr; Add custom connector, then name it Buff and paste the MCP address above.</li>
                            <li>Select Add, then Connect and approve access when Buff opens.</li>
                        </ol>
                        <p class="mt-3 text-xs leading-5 text-muted-foreground">
                            Team and Enterprise owners add it first in Organization settings &rarr; Connectors.
                        </p>
                    </details>

                    <details class="group rounded-xl border border-border bg-muted/30 p-4">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-foreground">
                            Gemini
                            <ChevronDown class="transition-transform group-open:rotate-180" :size="18" aria-hidden="true"/>
                        </summary>
                        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-muted-foreground">
                            <li>On Gemini web, open Settings &amp; help &rarr; Connected Apps.</li>
                            <li>Under Custom apps for Spark, choose Add a custom app and paste the MCP address above.</li>
                            <li>Select Next, complete the Buff approval, then use @Buff in a Spark task.</li>
                        </ol>
                        <p class="mt-3 text-xs leading-5 text-muted-foreground">
                            Google currently limits custom MCP apps to eligible Gemini Spark accounts, with setup on the web.
                        </p>
                    </details>
                </div>
            </div>
        </Card>

        <Card v-if="revokedConnections.length">
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
    </section>
</template>
