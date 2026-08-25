import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import {parse} from '@vue/compiler-sfc';

const settingsSource = readFileSync(new URL('../resources/js/Pages/Settings.vue', import.meta.url), 'utf8');
const source = readFileSync(new URL('../resources/js/Pages/Settings/ConnectedAssistants.vue', import.meta.url), 'utf8');
const {descriptor} = parse(source);
const script = descriptor.scriptSetup?.content ?? '';
const template = descriptor.template?.content ?? '';

test('settings links signed-in users to connected assistants', () => {
    assert.match(settingsSource, /href="\/settings\/connected-assistants"/);
});

test('connected assistants are revoked only by a deliberate active-connection action', () => {
    assert.match(script, /const authorizedConnections = computed\(\(\) => props\.connections\.filter\(\(connection\) => !connection\.revokedAt\)\)/);
    assert.match(template, /v-for="connection in authorizedConnections"/);
    assert.match(template, /@click="revoke\(connection\)"/);
    assert.match(script, /revocationForm\.delete\(`\/settings\/connected-assistants\/\$\{connection\.id}`/);
    assert.doesNotMatch(script, /onMounted|watchEffect|watch\(/);
});

test('MCP setup provides copyable instructions for the main AI providers', () => {
    assert.match(template, /Copy MCP address/);
    assert.match(template, /@click="copyMcpEndpoint"/);
    assert.match(template, /readonly/);
    assert.match(template, /aria-label="Buff MCP server address"/);
    assert.match(template, />\s*Codex\s*</);
    assert.match(template, /codex mcp add buff --url \{\{ mcpEndpoint \}\}/);
    assert.match(template, /codex mcp login buff/);
    assert.match(template, /Restart Codex/);
    assert.match(template, /codex mcp get buff/);
    assert.match(template, />\s*ChatGPT\s*</);
    assert.match(template, />\s*Claude\s*</);
    assert.match(template, />\s*Gemini\s*</);
    assert.match(script, /await navigator\.clipboard\.writeText\(props\.mcpEndpoint\)/);
    assert.match(script, /new CustomEvent\('buff:toast', \{detail: 'MCP address copied\.'}\)/);
    assert.doesNotMatch(script, /copyStatus\.value = 'MCP address copied\.'/);
    assert.doesNotMatch(script, /Browser\.open|window\.location|chatgpt\.com/);
    assert.doesNotMatch(script, /onMounted|watchEffect|watch\(/);
});

test('connection status distinguishes authorization from actual use', () => {
    assert.match(script, /function connectionStatus\(connection: Connection\)/);
    assert.match(script, /return connection\.lastUsedAt \? 'Active' : 'Authorized'/);
    assert.match(template, /\{\{ connectionStatus\(connection\) \}\}/);
    assert.match(template, /Authorized \{\{ formatTimestamp\(connection\.linkedAt\) \}\}/);
    assert.match(template, /does not remove Buff from the assistant's local configuration/);
});

test('current access is prioritized while setup and revoked connections stay compact', () => {
    assert.ok(template.indexOf('id="authorized-assistants-heading"') < template.indexOf('Add an assistant'));
    assert.match(script, /const revokedConnections = computed\(\(\) => props\.connections\.filter\(\(connection\) => connection\.revokedAt\)\)/);
    assert.match(template, /v-for="connection in revokedConnections"/);
    assert.match(template, /Connection history/);
    assert.doesNotMatch(template, /<details open/);
});
