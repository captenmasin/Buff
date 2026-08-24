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
    assert.match(template, /v-for="connection in connections"/);
    assert.match(template, /v-if="!connection\.revokedAt"/);
    assert.match(template, /@click="revoke\(connection\)"/);
    assert.match(script, /revocationForm\.delete\(`\/settings\/connected-assistants\/\$\{connection\.id}`/);
    assert.doesNotMatch(script, /onMounted|watchEffect|watch\(/);
});

test('MCP setup provides copyable instructions for the main AI providers', () => {
    assert.match(template, /Copy MCP address/);
    assert.match(template, /@click="copyMcpEndpoint"/);
    assert.match(template, /readonly/);
    assert.match(template, /aria-label="Buff MCP server address"/);
    assert.match(template, />ChatGPT</);
    assert.match(template, />Claude</);
    assert.match(template, />Gemini</);
    assert.match(script, /await navigator\.clipboard\.writeText\(props\.mcpEndpoint\)/);
    assert.match(script, /new CustomEvent\('buff:toast', \{detail: 'MCP address copied\.'}\)/);
    assert.doesNotMatch(script, /copyStatus\.value = 'MCP address copied\.'/);
    assert.doesNotMatch(script, /Browser\.open|window\.location|chatgpt\.com/);
    assert.doesNotMatch(script, /onMounted|watchEffect|watch\(/);
});
