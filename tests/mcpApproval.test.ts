import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';

const source = readFileSync(new URL('../resources/js/Pages/McpApproval.vue', import.meta.url), 'utf8');
const { descriptor } = parse(source);
const script = descriptor.scriptSetup?.content ?? '';
const template = descriptor.template?.content ?? '';

test('MCP approval and denial are submitted only by deliberate consent forms', () => {
    assert.match(template, /@submit\.prevent="approve"/);
    assert.match(template, /@submit\.prevent="deny"/);
    assert.match(template, /type="submit"/);
    assert.match(script, /function approve\(\)/);
    assert.match(script, /function deny\(\)/);
    assert.match(script, /approvalForm\.post\('\/mcp-approve'\)/);
    assert.match(script, /denialForm\.post\('\/mcp-approve'\)/);
    assert.match(script, /decision: 'denied'/);
    assert.match(template, /Don't connect \$\{approval\.clientName\}/);
    assert.doesNotMatch(script, /onMounted|watchEffect|watch\(/);
});

test('MCP consent visibly identifies the registered client and redirect origin', () => {
    assert.match(template, /approval\.clientName/);
    assert.match(template, /approval\.redirectOrigin/);
    assert.match(template, /Request expires/);
    assert.match(template, /formatExpiry\(approval\.expiresAt\)/);
    assert.match(script, /date\.toLocaleString\(\[\], \{ dateStyle: 'medium', timeStyle: 'short' \}\)/);
});
