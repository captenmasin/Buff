import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { parse } from '@vue/compiler-sfc';

const source = readFileSync(new URL('../resources/js/Pages/McpApproval.vue', import.meta.url), 'utf8');
const { descriptor } = parse(source);
const script = descriptor.scriptSetup?.content ?? '';
const template = descriptor.template?.content ?? '';

test('MCP approval is submitted only by the deliberate consent form', () => {
    assert.match(template, /@submit\.prevent="approve"/);
    assert.match(template, /type="submit"/);
    assert.match(script, /function approve\(\)/);
    assert.match(script, /form\.post\('\/mcp-approve'\)/);
    assert.doesNotMatch(script, /onMounted|watchEffect|watch\(/);
});

test('MCP consent visibly identifies the registered client and redirect origin', () => {
    assert.match(template, /approval\.clientName/);
    assert.match(template, /approval\.redirectOrigin/);
});
