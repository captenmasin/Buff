<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import {Bot, Clock3} from '@lucide/vue';
import {ref} from 'vue';
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
    <Head title="Connected assistants"/>

    <section class="space-y-5">
        <SettingsPageHeader>Connected assistants</SettingsPageHeader>

        <p class="text-sm leading-6 text-muted-foreground">
            Assistants you have allowed to read or update your Buff data. Revoking access signs that assistant out immediately.
        </p>

        <p v-if="error" class="rounded-xl bg-danger-soft p-4 text-sm text-danger-soft-foreground" role="alert">
            {{ error }}
        </p>

        <p v-if="revocationForm.errors.connection" class="rounded-xl bg-danger-soft p-4 text-sm text-danger-soft-foreground" role="alert">
            {{ revocationForm.errors.connection }}
        </p>

        <Card v-if="mcpEndpoint">
            <div class="flex items-start gap-3">
                <div class="grid size-10 flex-none place-items-center rounded-xl bg-primary text-primary-foreground" aria-hidden="true">
                    <Bot :size="19"/>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="card-title">Connect Buff to an AI assistant</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Copy this address, then follow the instructions for your provider.
                    </p>
                    <Input
                        :model-value="mcpEndpoint"
                        readonly
                        aria-label="Buff MCP server address"
                        class="mt-3 h-10 font-mono text-xs text-muted-foreground"
                    />
                </div>
            </div>

            <Button
                type="button"
                variant="outline"
                class="mt-3 w-full"
                @click="copyMcpEndpoint"
            >
                Copy MCP address
            </Button>

            <p v-if="copyStatus" class="mt-3 text-sm text-muted-foreground" role="status">
                {{ copyStatus }}
            </p>

            <div class="mt-5 grid gap-3">
                <details open class="rounded-xl border border-border bg-muted/30 p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-foreground">ChatGPT</summary>
                    <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-muted-foreground">
                        <li>Use ChatGPT on the web and turn on Developer mode in Settings &rarr; Apps &rarr; Advanced settings.</li>
                        <li>In Settings &rarr; Apps, choose Create. Name the app Buff and paste the MCP address above as its endpoint.</li>
                        <li>Choose OAuth if asked, scan the tools, complete the Buff sign-in, then create the app.</li>
                    </ol>
                    <p class="mt-3 text-xs leading-5 text-muted-foreground">
                        Full MCP access requires an eligible ChatGPT plan and workspace permissions.
                    </p>
                </details>

                <details class="rounded-xl border border-border bg-muted/30 p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-foreground">Claude</summary>
                    <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-muted-foreground">
                        <li>In Claude or Claude Desktop, open Customize &rarr; Connectors.</li>
                        <li>Choose + &rarr; Add custom connector, then name it Buff and paste the MCP address above.</li>
                        <li>Select Add, then Connect and approve access when Buff opens.</li>
                    </ol>
                    <p class="mt-3 text-xs leading-5 text-muted-foreground">
                        Team and Enterprise owners add it first in Organization settings &rarr; Connectors.
                    </p>
                </details>

                <details class="rounded-xl border border-border bg-muted/30 p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-foreground">Gemini</summary>
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
        </Card>

        <Card v-if="connections.length === 0 && !error">
            <div class="flex items-start gap-3">
                <div class="grid size-10 flex-none place-items-center rounded-xl bg-muted text-muted-foreground" aria-hidden="true">
                    <Bot :size="19"/>
                </div>
                <div>
                    <h2 class="card-title">No assistants connected</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Assistants you approve will appear here.</p>
                </div>
            </div>
        </Card>

        <div v-else class="grid gap-3">
            <Card v-for="connection in connections" :key="connection.id">
                <div class="flex items-start gap-3">
                    <div class="grid size-10 flex-none place-items-center rounded-xl bg-primary text-primary-foreground" aria-hidden="true">
                        <Bot :size="19"/>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="card-title truncate">{{ connection.clientName }}</h2>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="connection.revokedAt ? 'bg-muted text-muted-foreground' : 'bg-success-soft text-success-soft-foreground'"
                            >
                                {{ connection.revokedAt ? 'Revoked' : 'Connected' }}
                            </span>
                        </div>
                        <dl class="mt-3 grid gap-1.5 text-sm text-muted-foreground">
                            <div class="flex items-center gap-2">
                                <Clock3 :size="15" aria-hidden="true"/>
                                <dt class="sr-only">Connected</dt>
                                <dd>Connected {{ formatTimestamp(connection.linkedAt) }}</dd>
                            </div>
                            <div>
                                <dt class="inline font-medium text-foreground">Last used:</dt>
                                <dd class="inline"> {{ formatTimestamp(connection.lastUsedAt) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <Button
                    v-if="!connection.revokedAt"
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
</template>
